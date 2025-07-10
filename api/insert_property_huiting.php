<?php

$host   = 'localhost';
$dbname = 'boxgra6_sd3';
$dbuser = 'boxgra6_sd3';
$dbpass = 'Real_estate123$';

$table_property   = 'rets_property';     
$table_openhouse  = 'rets_openhouse';    

$mysqli = new mysqli($host, $dbuser, $dbpass, $dbname);
if ($mysqli->connect_error) die("DB connection failed: " . $mysqli->connect_error);

$tokenSql = "SELECT access_token, expires_at FROM token_store WHERE token_type = 'trestle' LIMIT 1";
$tk  = $mysqli->query($tokenSql)->fetch_assoc();
if (!$tk)   die("No token found. Run generate_token.php first.");
if (time() > (int)$tk['expires_at']) die("Access token expired. Please refresh the token.");
$access_token = $tk['access_token'];

function trestleRequest(string $url, string $token): ?object {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ["Authorization: Bearer {$token}"]
    ]);
    $raw = curl_exec($ch);
    curl_close($ch);
    return json_decode($raw ?? '');
}

$baseProp   = "https://api-trestle.corelogic.com/trestle/odata/Property";
$commonQS   = "$orderby=ListingContractDate desc,ListingKey desc&$filter=" .
              "PropertyType eq 'Residential' and MlsStatus eq 'Active'";

// get count first --------------------------------------------------------- //
$meta = trestleRequest("{$baseProp}?{$commonQS}&$top=1&$count=true", $access_token);
if (empty($meta->{'@odata.count'})) die("Property endpoint returned no data.");

$total   = (int)$meta->{'@odata.count'};
$batch   = 200;                          
$pages   = ceil($total / $batch);
$insProp = $skipProp = 0;

$insertPropStmt = $mysqli->prepare("INSERT INTO {$table_property} (
        L_ListingID, L_DisplayId, L_Address, L_Zip, LM_char10_70, L_AddressStreet,
        L_City, L_State, L_Class, L_Type, L_Keyword2, LM_Dec_3, L_Keyword1, L_Keyword5,
        L_Keyword7, L_SystemPrice, LM_Int2_3, L_ListingDate, ListingContractDate,
        LMD_MP_Latitude, LMD_MP_Longitude, LA1_UserFirstName, LA1_UserLastName,
        L_Status, LO1_OrganizationName, L_Remarks, L_Photos, PhotoTime, PhotoCount, L_alldata
    ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
    ON DUPLICATE KEY UPDATE
        L_SystemPrice      = VALUES(L_SystemPrice),
        LM_Int2_3          = VALUES(LM_Int2_3),
        L_Status           = VALUES(L_Status),
        L_Remarks          = VALUES(L_Remarks),
        L_alldata          = VALUES(L_alldata),
        up_date            = NOW()  /* custom column */");

for ($p = 0; $p < $pages; $p++) {
    $skip = $p * $batch;
    $url  = "{$baseProp}?{$commonQS}&$skip={$skip}&$top={$batch}&$expand=Media($orderby=Order)";
    $data = trestleRequest($url, $access_token);
    if (empty($data->value)) continue;

    foreach ($data->value as $row) {

        $pics = [];
        if (!empty($row->Media)) {
            foreach ($row->Media as $m) $pics[] = $m->MediaURL;
        }
        $images = json_encode($pics);
        $levels = $row->Levels ?? '';
        $alldata = json_encode($row);

        $insertPropStmt->bind_param(
            'ssssssssssdddddsssssddsssssssi',
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
            $levels,
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
            $images,
            date('Y-m-d H:i:s', strtotime($row->PhotosChangeTimestamp)),
            $row->PhotosCount,
            $alldata
        );
        if ($insertPropStmt->execute()) {
            $insProp += $insertPropStmt->affected_rows; // 1 insert or 2 update? Only new rows count 1
        } else {
            echo "\n[PROPERTY] Error {$row->ListingKey}: {$insertPropStmt->error}";
            $skipProp++;
        }
    }
}
$insertPropStmt->close();

//ingest openhouse events                                            
$baseOH   = "https://api-trestle.corelogic.com/trestle/odata/OpenHouse";
$todayISO = date('Y-m-d');
$ohQS     = "$filter=EventDate ge {$todayISO}";   // only today onwards

$metaOH = trestleRequest("{$baseOH}?{$ohQS}&$top=1&$count=true", $access_token);
$totalOH = empty($metaOH->{'@odata.count'}) ? 0 : (int)$metaOH->{'@odata.count'};
$pagesOH = ceil($totalOH / $batch);
$insOH = $skipOH = 0;

$insertOHStmt = $mysqli->prepare("INSERT INTO {$table_openhouse} (
        L_ListingID, L_DisplayId, OpenHouseDate, OH_StartTime, OH_EndTime,
        OH_StartDate, OH_EndDate, all_others
    ) VALUES (?,?,?,?,?,?,?,?)
    ON DUPLICATE KEY UPDATE
        OH_StartTime = VALUES(OH_StartTime),
        OH_EndTime   = VALUES(OH_EndTime),
        all_others   = VALUES(all_others)");

for ($p = 0; $p < $pagesOH; $p++) {
    $skip = $p * $batch;
    $url  = "{$baseOH}?{$ohQS}&$skip={$skip}&$top={$batch}";
    $data = trestleRequest($url, $access_token);
    if (empty($data->value)) continue;

    foreach ($data->value as $oh) {
        $ohJson = json_encode($oh);
        $insertOHStmt->bind_param(
            'ssssssss',
            $oh->ListingKey,
            $oh->ListingId,
            $oh->EventDate,
            $oh->StartTime,
            $oh->EndTime,
            $oh->StartDate,
            $oh->EndDate,
            $ohJson
        );
        if ($insertOHStmt->execute()) {
            $insOH += $insertOHStmt->affected_rows;
        } else {
            echo "\n[OH] Error {$oh->ListingKey}|{$oh->EventDate}: {$insertOHStmt->error}";
            $skipOH++;
        }
    }
}
$insertOHStmt->close();

echo "\n== SUMMARY ==\n";
echo "Property ➜ Inserted/Updated: {$insProp}   Errors: {$skipProp}\n";
echo "OpenHouse ➜ Inserted/Updated: {$insOH}   Errors: {$skipOH}\n";

$mysqli->close();
?>
