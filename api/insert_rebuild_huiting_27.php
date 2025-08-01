<?php
/* insert_rebuild_huiting_26.php
   - pulls 200 residential active listings per run
   - remembers progress in insert_property_offset table
   - no WordPress dependency
*/

ini_set('display_errors', 1);
error_reporting(E_ALL);

/* ---- DB CONFIG ---- */
$host   = 'localhost';
$dbname = 'boxgra6_sd3';
$user   = 'boxgra6_sd3';
$pass   = 'Real_estate123$';

$tableListings = 'rets_property_huiting';          // target
$tableOffset   = 'insert_property_offset'; // cursor


//echo ">>> Connecting to MySQL at {$host}, DB: {$dbname}\n";
$mysqli = new mysqli($host, $user, $pass, $dbname);
if ($mysqli->connect_error) {
    die('DB connect error: '.$mysqli->connect_error."\n");
}
//echo ">>> Connected successfully\n\n";


/* ---- FETCH ACCESS TOKEN ---- */
//echo ">>> Fetching latest access token...\n";
$res = $mysqli->query("
    SELECT access_token, expires_at
      FROM token_store_huiting
     WHERE token_type='trestle'
  ORDER BY expires_at DESC LIMIT 1
");
$tok = $res->fetch_assoc();
if (!$tok)             die("No token found\n");
if (time() > $tok['expires_at']) die("Token expired\n");
$access = $tok['access_token'];
//echo ">>> Got token (expires at {$tok['expires_at']})\n\n";


/* ---- HELPER TO BUILD URL ---- */
function trestle_url($orderby, $filter, $extra = []) {
    $params = ['$orderby'=>$orderby, '$filter'=>$filter] + $extra;
    return 'https://api-trestle.corelogic.com/trestle/odata/Property?'
         . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
}

/* ---- READ CURRENT OFFSET ---- */
//echo ">>> Reading current offset from {$tableOffset}\n";
$offRow = $mysqli->query("SELECT offset FROM {$tableOffset} WHERE id=1")->fetch_assoc();
$currentSkip = $offRow ? (int)$offRow['offset'] : 0;
//echo ">>> Current skip = {$currentSkip}\n\n";

/* ---- PAGE REQUEST ---- */
$batchSize = 200;
$url = trestle_url(
    "ListingContractDate desc,ListingKey desc",
    "PropertyType eq 'Residential' and MlsStatus eq 'Active'",
    [
        '$skip'   => $currentSkip,
        '$top'    => $batchSize,
        '$count'  => 'true',
        '$expand' => 'Media($orderby=Order)'
    ]
);
//echo ">>> Request URL:\n{$url}\n\n";

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => ["Authorization: Bearer {$access}"]
]);
$json = curl_exec($ch);
$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

//echo ">>> HTTP status code: {$http}\n";
if ($http !== 200) die("API error HTTP {$http}\n");
//echo ">>> Raw JSON length: ".strlen($json)." bytes\n\n";

$data = json_decode($json);
if (empty($data->value)) die("No data returned\n");
//echo ">>> Records returned this batch: ".count($data->value)."\n\n";


/* ---- PREPARE INSERT ---- */
//echo ">>> Preparing INSERT statement\n";
$cols = "
  L_ListingID,L_DisplayId,L_Address,L_Zip,LM_char10_70,L_AddressStreet,
  L_City,L_State,L_Class,L_Type_,L_Keyword2,LM_Dec_3,L_Keyword1,L_Keyword5,
  L_Keyword7,L_SystemPrice,LM_Int2_3,L_ListingDate,ListingContractDate,
  LMD_MP_Latitude,LMD_MP_Longitude,LA1_UserFirstName,LA1_UserLastName,
  L_Status,LO1_OrganizationName,L_Remarks,L_Photos,PhotoTime,PhotoCount,L_alldata";
$qm = rtrim(str_repeat('?,', 30), ',');
$ins = $mysqli->prepare("INSERT IGNORE INTO {$tableListings} ($cols) VALUES ($qm)");
$ins->bind_param(str_repeat('s', 30), ...array_fill(0, 30, ''));


/* ---- LOOP & INSERT ---- */
//echo ">>> Inserting rows...\n";
$inserted = 0;
foreach ($data->value as $row) {
    $media = [];
    foreach (($row->Media ?? []) as $m) {
        $media[] = $m->MediaURL;
    }

    $bind = [
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
      $row->Levels ?? '',
      $row->ListPrice,
      $row->LivingArea,
      date('Y-m-d H:i:s', strtotime($row->ModificationTimestamp)),
      date('Y-m-d H:i:s', strtotime($row->ListingContractDate)),
      $row->Latitude,
      $row->Longitude,
      $row->ListAgentFirstName,
      $row->ListAgentLastName,
      $row->MlsStatus,
      $row->ListOfficeName,
      $row->PublicRemarks,
      json_encode($media),
      date('Y-m-d H:i:s', strtotime($row->PhotosChangeTimestamp)),
      $row->PhotosCount,
      json_encode($row)
    ];
    $ins->bind_param(str_repeat('s', 30), ...$bind);
    if ($ins->execute()) {
        $inserted++;
        //echo "    - Inserted ListingKey {$row->ListingKey}\n";
    }
}
$ins->close();
//echo "\n>>> Total inserted this run: {$inserted}\n\n";

/* ---- ADVANCE OFFSET ---- */
$newSkip = $currentSkip + $batchSize;
//echo ">>> Updating offset to {$newSkip}\n";
$upd = $mysqli->prepare("UPDATE {$tableOffset} SET offset=? WHERE id=1");
$upd->bind_param('i', $newSkip);
$upd->execute();
$upd->close();

//echo ">>> Done. Next skip = {$newSkip}\n";
$mysqli->close();
