<?php
/* insert_property_huiting_fixed.php — Updated to get ALL properties, not just active ones */
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
echo "STEP 1: connecting database...\n";
$mysqli = new mysqli($host,$dbuser,$dbpass,$dbname);
if ($mysqli->connect_error) die("DB connect error: ".$mysqli->connect_error);
echo "STEP 1 OK: connected to $dbname\n";

/* ---------- ACCESS TOKEN ---------- */
echo "STEP 2: getting access token...\n";
$stmt = $mysqli->prepare(
    "SELECT access_token,expires_at
       FROM token_store_yu
      WHERE token_type='trestle'
      ORDER BY expires_at DESC LIMIT 1"
);
$stmt->execute();
$stmt->bind_result($access_token,$expires_at);
$stmt->fetch();
$stmt->close();
if (!$access_token)           die("STEP 2 ERROR: no token in DB\n");
if (time() > $expires_at)     die("STEP 2 ERROR: token expired\n");
echo "STEP 2 OK: token ends at ".date('Y-m-d H:i:s',$expires_at)."\n";

/* ---------- URL HELPER ---------- */
function trestle_url(string $orderby,string $filter,array $extra=[]): string {
    $params = ['$orderby'=>$orderby,'$filter'=>$filter] + $extra;
    return 'https://api-trestle.corelogic.com/trestle/odata/Property?' .
           http_build_query($params,'','&',PHP_QUERY_RFC3986);
}

/* ---------- TOTAL COUNT ---------- */
echo "STEP 3: requesting total count…\n";
$countUrl = trestle_url(
    "ListingKey desc",
    "PropertyType eq 'Residential'", // REMOVED: and MlsStatus eq 'Active' AND removed problematic date sorting
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
echo "STEP 3 OK: total=$total (ALL residential properties)\n";

/* ---------- PAGINATION LOOP ---------- */
$offset = 200;
$total_batches = ceil($total / $offset);
$total_processed = 0;

echo "STEP 4: Processing $total_batches batches...\n";

for ($batch = 0; $batch < $total_batches; $batch++) {
    $skip = $batch * $offset;
    echo "Processing batch " . ($batch + 1) . "/$total_batches (skip=$skip)...\n";

    /* ---------- PAGE REQUEST ---------- */
    $pageUrl = trestle_url(
        "ListingKey desc",
        "PropertyType eq 'Residential'", // REMOVED: and MlsStatus eq 'Active' AND removed problematic date sorting
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
        CURLOPT_HTTPHEADER     => ["Authorization: Bearer $access_token"],
        CURLOPT_TIMEOUT        => 60
    ]);
    $pageJson = curl_exec($curl);
    $http2    = curl_getinfo($curl,CURLINFO_HTTP_CODE);
    curl_close($curl);
    
    if ($http2!=200) {
        echo "STEP 4 ERROR: HTTP $http2 for batch " . ($batch + 1) . "\n";
        continue;
    }
    
    $page = json_decode($pageJson);
    if (empty($page->value)) {
        echo "STEP 4 WARNING: empty page for batch " . ($batch + 1) . "\n";
        continue;
    }
    
    echo "STEP 4 OK: retrieved ".count($page->value)." records in batch " . ($batch + 1) . "\n";

    /* ---------- PREPARE INSERT (no id) ---------- */
    if ($batch == 0) { // Only prepare once
        $cols = "
          L_ListingID,L_DisplayId,L_Address,L_Zip,LM_char10_70,L_AddressStreet,
          L_City,L_State,L_Class,L_Type_,L_Keyword2,LM_Dec_3,L_Keyword1,L_Keyword5,
          L_Keyword7,L_SystemPrice,LM_Int2_3,L_ListingDate,ListingContractDate,
          LMD_MP_Latitude,LMD_MP_Longitude,LA1_UserFirstName,LA1_UserLastName,
          L_Status,LO1_OrganizationName,L_Remarks,L_Photos,PhotoTime,PhotoCount,L_alldata";
        $qmarks = rtrim(str_repeat('?,',30),',');

        $ins = $mysqli->prepare("
          INSERT INTO $table1 ($cols) VALUES ($qmarks)
          ON DUPLICATE KEY UPDATE
            L_DisplayId           = VALUES(L_DisplayId),
            L_Address             = VALUES(L_Address),
            L_Zip                 = VALUES(L_Zip),
            LM_char10_70          = VALUES(LM_char10_70),
            L_AddressStreet       = VALUES(L_AddressStreet),
            L_City                = VALUES(L_City),
            L_State               = VALUES(L_State),
            L_Class               = VALUES(L_Class),
            L_Type_               = VALUES(L_Type_),
            L_Keyword2            = VALUES(L_Keyword2),
            LM_Dec_3              = VALUES(LM_Dec_3),
            L_Keyword1            = VALUES(L_Keyword1),
            L_Keyword5            = VALUES(L_Keyword5),
            L_Keyword7            = VALUES(L_Keyword7),
            L_SystemPrice         = VALUES(L_SystemPrice),
            LM_Int2_3             = VALUES(LM_Int2_3),
            L_ListingDate         = VALUES(L_ListingDate),
            ListingContractDate   = VALUES(ListingContractDate),
            LMD_MP_Latitude       = VALUES(LMD_MP_Latitude),
            LMD_MP_Longitude      = VALUES(LMD_MP_Longitude),
            LA1_UserFirstName     = VALUES(LA1_UserFirstName),
            LA1_UserLastName      = VALUES(LA1_UserLastName),
            L_Status              = VALUES(L_Status),
            LO1_OrganizationName  = VALUES(LO1_OrganizationName),
            L_Remarks             = VALUES(L_Remarks),
            L_Photos              = VALUES(L_Photos),
            PhotoTime             = VALUES(PhotoTime),
            PhotoCount            = VALUES(PhotoCount),
            L_alldata             = VALUES(L_alldata),
            updated_date          = NOW()
        ");
        if (!$ins) die("prepare failed: ".$mysqli->error."\n");
        $types  = str_repeat('s',30);   // binding all as string for simplicity
    }

    $insCnt = 0;
    echo "STEP 5: inserting batch " . ($batch + 1) . "…\n";

    foreach ($page->value as $row) {
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
            $row->MlsStatus, // Now includes ALL statuses (Active, Sold, Pending, etc.)
            $row->ListOfficeName,
            $row->PublicRemarks,
            json_encode($media),
            date('Y-m-d H:i:s',strtotime($row->PhotosChangeTimestamp)),
            $row->PhotosCount,
            json_encode($row)
        ];
        $ins->bind_param($types,...$data);
        if ($ins->execute()) {
            $insCnt++;
        } else {
            echo "Insert error {$row->ListingKey}: ".$ins->error."\n";
        }
    }
    
    $total_processed += $insCnt;
    echo "STEP 5 DONE: batch " . ($batch + 1) . " - inserted/updated=$insCnt\n";
    
    // Small delay between batches
    if ($batch < $total_batches - 1) {
        echo "Waiting 2 seconds before next batch...\n";
        sleep(2);
    }
}

if (isset($ins)) $ins->close();
echo "\n=== FINAL SUMMARY ===\n";
echo "Total batches processed: $total_batches\n";
echo "Total records processed: $total_processed\n";
echo "All residential properties (regardless of status) have been synced to rets_property table.\n";
$mysqli->close();
?>