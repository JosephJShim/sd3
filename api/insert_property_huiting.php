<?php
/* insert_property_huiting.php – Debug done, no progress echoes */
ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);

/* ---------- DB CONFIG ---------- */
$host   = 'localhost';
$dbname = 'boxgra6_sd3';
$dbuser = 'boxgra6_sd3';
$dbpass = 'Real_estate123$';
$table1 = 'rets_property';

/* ---------- DB CONNECT ---------- */
//echo "STEP 1: connecting database...\n";
$mysqli = new mysqli($host,$dbuser,$dbpass,$dbname);
if ($mysqli->connect_error) die("DB connect error: ".$mysqli->connect_error);
//echo "STEP 1 OK: connected to $dbname\n";

/* ---------- ACCESS TOKEN ---------- */
//echo "STEP 2: getting access token...\n";
$stmt = $mysqli->prepare(
    "SELECT access_token,expires_at
       FROM token_store_huiting
      WHERE token_type='trestle'
      ORDER BY expires_at DESC LIMIT 1"
);
$stmt->execute();
$stmt->bind_result($access_token,$expires_at);
$stmt->fetch();
$stmt->close();
if (!$access_token)           die("STEP 2 ERROR: no token in DB\n");
if (time() > $expires_at)     die("STEP 2 ERROR: token expired\n");
// "STEP 2 OK: token ends at ".date('Y-m-d H:i:s',$expires_at)."\n";

/* ---------- URL HELPER ---------- */
function trestle_url(string $orderby,string $filter,array $extra=[]): string {
    $params = ['$orderby'=>$orderby,'$filter'=>$filter] + $extra;
    return 'https://api-trestle.corelogic.com/trestle/odata/Property?' .
           http_build_query($params,'','&',PHP_QUERY_RFC3986);
}

/* ---------- TOTAL COUNT ---------- */
//echo "STEP 3: requesting total count …\n";
$countUrl = trestle_url(
    "ListingContractDate desc,ListingKey desc",
    "PropertyType eq 'Residential' and MlsStatus eq 'Active'",
    ['$top'=>1,'$count'=>'true']
);
$ch = curl_init();
curl_setopt_array($ch,[
    CURLOPT_URL            => $countUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_HTTPHEADER     => ["Authorization: Bearer $access_token"]
]);
$countJson = curl_exec($ch);
$httpCode  = curl_getinfo($ch,CURLINFO_HTTP_CODE);
curl_close($ch);
if ($httpCode!=200) die("STEP 3 ERROR: HTTP $httpCode\n");
$total = (int)(json_decode($countJson)->{'@odata.count'} ?? 0);
//echo "STEP 3 OK: total=$total\n";

/* ---------- PAGINATION ---------- */
$offset = 200;
$skip   = 0;
//echo "STEP 4: skip=$skip limit=$offset\n";

/* ---------- PAGE REQUEST ---------- */
$pageUrl = trestle_url(
    "ListingContractDate desc,ListingKey desc",
    "PropertyType eq 'Residential' and MlsStatus eq 'Active'",
    [
        '$skip'   => $skip,
        '$top'    => $offset,
        '$count'  => 'true',
        '$expand' => 'Media($orderby=Order)'
    ]
);
$curl = curl_init();
curl_setopt_array($curl,[
    CURLOPT_URL            => $pageUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => ["Authorization: Bearer $access_token"]
]);
$pageJson = curl_exec($curl);
$http2    = curl_getinfo($curl,CURLINFO_HTTP_CODE);
curl_close($curl);
if ($http2!=200) die("STEP 4 ERROR: HTTP $http2\n");
$page = json_decode($pageJson);
if (empty($page->value)) die("STEP 4 ERROR: empty page\n");
//echo "STEP 4 OK: retrieved ".count($page->value)." records\n";

/* ---------- PREPARE INSERT (no id) ---------- */
$cols = "
  L_ListingID,L_DisplayId,L_Address,L_Zip,LM_char10_70,L_AddressStreet,
  L_City,L_State,L_Class,L_Type_,L_Keyword2,LM_Dec_3,L_Keyword1,L_Keyword5,
  L_Keyword7,L_SystemPrice,LM_Int2_3,L_ListingDate,ListingContractDate,
  LMD_MP_Latitude,LMD_MP_Longitude,LA1_UserFirstName,LA1_UserLastName,
  L_Status,LO1_OrganizationName,L_Remarks,L_Photos,PhotoTime,PhotoCount,L_alldata";
$qmarks = rtrim(str_repeat('?,',30),',');
$ins    = $mysqli->prepare("INSERT INTO $table1 ($cols) VALUES ($qmarks)");
if (!$ins) die("prepare failed: ".$mysqli->error."\n");
$types  = str_repeat('s',30);   // binding all as string for simplicity

$insCnt = $skipCnt = 0;
//echo "STEP 5: inserting…\n";

foreach ($page->value as $row) {
    // duplicate check
    $safeKey = $mysqli->real_escape_string($row->ListingKey);
    if ($mysqli->query("SELECT 1 FROM $table1 WHERE L_ListingID='$safeKey' LIMIT 1")->num_rows){
        $skipCnt++; continue;
    }
    // build data
    $media=[]; if (!empty($row->Media)) foreach ($row->Media as $m) $media[]=$m->MediaURL;
    $data=[
        $row->ListingKey,
        $row->ListingKey,
        $row->UnparsedAddress,
        $row->PostalCode,
        $row->SubdivisionName,
        $row->StreetName,
        $row->City,
        $row->StateOrProvince,
        $row->PropertyType,
        $row->PropertySubType,
        $row->BedroomsTotal,
        $row->BathroomsTotalInteger,
        $row->LotSizeArea,
        $row->GarageSpaces,
        $row->Levels??'',
        $row->ListPrice,
        $row->LivingArea,
        date('Y-m-d H:i:s',strtotime($row->ModificationTimestamp)),
        date('Y-m-d H:i:s',strtotime($row->ListingContractDate)),
        $row->Latitude,
        $row->Longitude,
        $row->ListAgentFirstName,
        $row->ListAgentLastName,
        $row->MlsStatus,
        $row->ListOfficeName,
        $row->PublicRemarks,
        json_encode($media),
        date('Y-m-d H:i:s',strtotime($row->PhotosChangeTimestamp)),
        $row->PhotosCount,
        json_encode($row)
    ];
    $ins->bind_param($types,...$data);
    if ($ins->execute()) $insCnt++;
        //echo "Insert error {$row->ListingKey}: ".$ins->error."\n";
}
$ins->close();
//echo "STEP 5 DONE: inserted=$insCnt skipped=$skipCnt\n";
$mysqli->close();
?>
