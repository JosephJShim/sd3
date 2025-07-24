<?php
// Property Sync Script for Trestle API
// This script fetches property listings from Trestle API and stores them in local database

// Include local database configuration
include('../includes/db_local.php');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>🏠 Trestle API Property Sync</h2>";
echo "<hr>";

// Fetch valid token
$stmt = $conn->prepare("SELECT access_token, expires_at FROM token_store WHERE token_type = 'trestle' LIMIT 1");
$stmt->execute();
$stmt->store_result();
$stmt->bind_result($access_token, $expires_at);
$stmt->fetch();
$stmt->close();

// Validate expiration
if (time() > $expires_at) {
    die("❌ Access token expired. Please refresh the token by running <a href='generate_token.php'>generate_token.php</a> first.");
}
echo "✅ Valid access token found<br>";

// Get total listings count
echo "📊 Fetching total listings count...<br>";
$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL => 'https://api-trestle.corelogic.com/trestle/odata/Property?$orderby=ListingContractDate+desc,ListingKey+desc&$filter=PropertyType+eq+\'Residential\'+and+MlsStatus+eq+\'Active\'&$top=1&$count=true',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $access_token]
]);
$json_data = curl_exec($curl);
curl_close($curl);
$response = json_decode($json_data);

if (!empty($response->{'@odata.count'})) {
    $total = $response->{'@odata.count'};
    echo "📈 Total active residential listings: <strong>" . number_format($total) . "</strong><br>";
    
    $offset = 200;
    $diff = ceil($total / $offset);
    $total_loop = 0; // You can store this in a file/db to persist pagination
    $updated_offset = $total_loop * $offset;

    echo "🔄 Processing batch " . ($total_loop + 1) . " of $diff (offset: $updated_offset)<br>";
    echo "<hr>";

    // Fetch actual listings
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => 'https://api-trestle.corelogic.com/trestle/odata/Property?$orderby=ListingContractDate+desc,ListingKey+desc&$filter=PropertyType+eq+\'Residential\'+and+MlsStatus+eq+\'Active\'&$skip=' . $updated_offset . '&$top=200&$count=true&$expand=Media($orderby=Order)',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $access_token]
    ]);
    $json_data = curl_exec($curl);
    curl_close($curl);
    $response = json_decode($json_data);

    if (!empty($response->value)) {
        $Inserted = 0;
        $noInserted = 0;

        echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
        echo "<h3>Processing " . count($response->value) . " listings...</h3>";

        foreach ($response->value as $index => $row) {
            $listid = $conn->real_escape_string($row->ListingKey);
            $check = $conn->query("SELECT L_ListingID FROM rets_property WHERE L_ListingID = '$listid'");
            
            if ($check->num_rows === 0) {
                $media = [];
                if (!empty($row->Media)) {
                    foreach ($row->Media as $m) {
                        $media[] = $m->MediaURL;
                    }
                }
                $images = json_encode($media);
                $levels = !empty($row->Levels) ? $row->Levels : '';
                $alldata = json_encode($row);

                $stmt = $conn->prepare("INSERT INTO rets_property (
                    L_ListingID, L_DisplayId, L_Address, L_Zip, LM_char10_70, L_AddressStreet,
                    L_City, L_State, L_Class, L_Type_, L_Keyword2, LM_Dec_3, L_Keyword1, L_Keyword5,
                    L_Keyword7, L_SystemPrice, LM_Int2_3, L_ListingDate, ListingContractDate,
                    LMD_MP_Latitude, LMD_MP_Longitude, LA1_UserFirstName, LA1_UserLastName,
                    L_Status, LO1_OrganizationName, L_Remarks, L_Photos, PhotoTime, PhotoCount, L_alldata
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

                $stmt->bind_param(
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

                if ($stmt->execute()) {
                    $Inserted++;
                    echo "✅ Inserted: " . substr($row->UnparsedAddress, 0, 50) . "...<br>";
                } else {
                    $noInserted++;
                    echo "❌ Failed to insert: " . $row->ListingKey . "<br>";
                }
                $stmt->close();
            } else {
                $noInserted++;
                echo "⏭️ Skipped (exists): " . substr($row->UnparsedAddress, 0, 50) . "...<br>";
            }
        }

        echo "</div>";

        echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
        echo "<h3>📊 Summary</h3>";
        echo "<strong>✅ Inserted:</strong> $Inserted<br>";
        echo "<strong>⏭️ Skipped (already exists):</strong> $noInserted<br>";
        echo "<strong>📍 Current Offset:</strong> $updated_offset<br>";
        echo "<strong>📈 Total Records Available:</strong> " . number_format($total) . "<br>";
        echo "</div>";

    } else {
        echo "❌ No listings found in batch.";
    }

} else {
    echo "❌ No records found.";
}

$conn->close();

echo "<hr>";
echo "<p><strong>🎉 Sync completed!</strong></p>";
echo "<p><a href='sync_properties.php'>🔄 Run Again</a> | <a href='index.php'>🏠 View Properties</a></p>";
?> 