<?php
require_once 'db.php';
require_once '../includes/functions.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Enhanced Open House Sync Script - Active Properties Only</h1>";

// Get access token
$stmt = $conn->prepare("SELECT access_token, expires_at FROM token_store_yu WHERE token_type = 'trestle' LIMIT 1");
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

// Get today's date and future dates for open houses
$today_date = date('Y-m-d');
$future_date = date('Y-m-d', strtotime('+30 days')); // Get open houses for next 30 days

echo "<p>📅 Fetching open houses from $today_date to $future_date</p>";

// First, get total count
$count_url = "https://api-trestle.corelogic.com/trestle/odata/OpenHouse?\$filter=OpenHouseStatus+eq+'Active'+and+(OpenHouseDate+ge+$today_date+and+OpenHouseDate+le+$future_date)&\$count=true&\$top=1";

$curl = curl_init();
curl_setopt_array($curl, array(
    CURLOPT_URL => $count_url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'GET',
    CURLOPT_HTTPHEADER => array(
        'Authorization: Bearer ' . $access_token,
        'Content-Type: application/json'
    )
));

$count_response = curl_exec($curl);
$http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
curl_close($curl);

if ($http_code !== 200) {
    echo "<p style='color: red;'>❌ Error getting count: HTTP $http_code</p>";
    echo "<pre>" . htmlspecialchars($count_response) . "</pre>";
    exit;
}

$count_data = json_decode($count_response, true);
$total_count = $count_data['@odata.count'] ?? 0;

echo "<p>📊 Total open houses found: $total_count</p>";

if ($total_count === 0) {
    echo "<p>✅ No open houses found for the specified date range.</p>";
    exit;
}

// Process in batches of 200
$batch_size = 200;
$total_batches = ceil($total_count / $batch_size);
$total_inserted = 0;
$total_updated = 0;
$total_errors = 0;
$total_skipped_inactive = 0;

echo "<p>🔄 Processing $total_batches batches...</p>";

for ($batch = 0; $batch < $total_batches; $batch++) {
    $skip = $batch * $batch_size;
    
    echo "<h3>Processing batch " . ($batch + 1) . " of $total_batches (skip: $skip)</h3>";
    
    $data_url = "https://api-trestle.corelogic.com/trestle/odata/OpenHouse?\$filter=OpenHouseStatus+eq+'Active'+and+(OpenHouseDate+ge+$today_date+and+OpenHouseDate+le+$future_date)&\$orderby=OpenHouseDate+asc&\$skip=$skip&\$top=$batch_size";
    
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => $data_url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_HTTPHEADER => array(
            'Authorization: Bearer ' . $access_token,
            'Content-Type: application/json'
        )
    ));
    
    $response = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    
    if ($http_code !== 200) {
        echo "<p style='color: red;'>❌ Error fetching batch " . ($batch + 1) . ": HTTP $http_code</p>";
        $total_errors++;
        continue;
    }
    
    $data = json_decode($response, true);
    
    if (!isset($data['value']) || !is_array($data['value'])) {
        echo "<p style='color: red;'>❌ Invalid response format for batch " . ($batch + 1) . "</p>";
        $total_errors++;
        continue;
    }
    
    $batch_inserted = 0;
    $batch_updated = 0;
    $batch_skipped = 0;
    
    foreach ($data['value'] as $openhouse) {
        try {
            // ENHANCED: Check if the property is actually active in our rets_property table
            $property_check_sql = "SELECT L_Status FROM rets_property WHERE L_ListingID = ? AND L_Status = 'Active'";
            $property_check_stmt = $conn->prepare($property_check_sql);
            $property_check_stmt->bind_param("s", $openhouse['ListingKey']);
            $property_check_stmt->execute();
            $property_result = $property_check_stmt->get_result();
            
            if ($property_result->num_rows === 0) {
                echo "<p style='color: orange;'>⚠️ Skipping open house for {$openhouse['ListingKey']} - Property not active in our database</p>";
                $batch_skipped++;
                $property_check_stmt->close();
                continue;
            }
            $property_check_stmt->close();
            
            // Parse dates
            $openhouse_date = null;
            $start_time = null;
            $end_time = null;
            
            if (!empty($openhouse['OpenHouseDate'])) {
                $openhouse_date = new DateTime($openhouse['OpenHouseDate']);
                $openhouse_date = $openhouse_date->format('Y-m-d H:i:s');
            }
            
            if (!empty($openhouse['OpenHouseStartTime'])) {
                $start_time = new DateTime($openhouse['OpenHouseStartTime']);
                $start_time = $start_time->format('Y-m-d H:i:s');
            }
            
            if (!empty($openhouse['OpenHouseEndTime'])) {
                $end_time = new DateTime($openhouse['OpenHouseEndTime']);
                $end_time = $end_time->format('Y-m-d H:i:s');
            }
            
            // Check if record exists
            $check_sql = "SELECT id FROM rets_openhouse WHERE L_ListingID = ? AND OpenHouseDate = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("ss", $openhouse['ListingKey'], $openhouse_date);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            $all_data = json_encode($openhouse);
            
            // Prepare variables for bind_param
            $listing_key = $openhouse['ListingKey'];
            
            // Parse ModificationTimestamp properly
            $modification_timestamp = null;
            if (!empty($openhouse['ModificationTimestamp'])) {
                try {
                    $modification_dt = new DateTime($openhouse['ModificationTimestamp']);
                    $modification_timestamp = $modification_dt->format('Y-m-d H:i:s');
                } catch (Exception $e) {
                    // If parsing fails, set to current time
                    $modification_timestamp = date('Y-m-d H:i:s');
                }
            }
            
            if ($check_result->num_rows > 0) {
                // Update existing record
                $update_sql = "UPDATE rets_openhouse SET 
                    L_DisplayId = ?,
                    OH_StartTime = ?,
                    OH_EndTime = ?,
                    OH_StartDate = ?,
                    OH_EndDate = ?,
                    updated_date = ?,
                    API_OH_StartDate = ?,
                    API_OH_EndDate = ?,
                    all_data = ?,
                    updated_at = NOW()
                    WHERE L_ListingID = ? AND OpenHouseDate = ?";
                
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("sssssssssss", 
                    $listing_key,
                    $start_time,
                    $end_time,
                    $start_time,
                    $end_time,
                    $modification_timestamp,
                    $start_time,
                    $end_time,
                    $all_data,
                    $listing_key,
                    $openhouse_date
                );
                
                if ($update_stmt->execute()) {
                    $batch_updated++;
                    echo "<p style='color: blue;'>🔄 Updated open house for {$openhouse['ListingKey']}</p>";
                } else {
                    echo "<p style='color: red;'>❌ Error updating open house: " . $update_stmt->error . "</p>";
                    $total_errors++;
                }
                $update_stmt->close();
            } else {
                // Insert new record
                $insert_sql = "INSERT INTO rets_openhouse (
                    L_ListingID, L_DisplayId, OpenHouseDate, OH_StartTime, OH_EndTime, 
                    OH_StartDate, OH_EndDate, updated_date, API_OH_StartDate, API_OH_EndDate, all_data
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
                $insert_stmt = $conn->prepare($insert_sql);
                $insert_stmt->bind_param("sssssssssss", 
                    $listing_key,
                    $listing_key,
                    $openhouse_date,
                    $start_time,
                    $end_time,
                    $start_time,
                    $end_time,
                    $modification_timestamp,
                    $start_time,
                    $end_time,
                    $all_data
                );
                
                if ($insert_stmt->execute()) {
                    $batch_inserted++;
                    echo "<p style='color: green;'>✅ Inserted open house for {$openhouse['ListingKey']}</p>";
                } else {
                    echo "<p style='color: red;'>❌ Error inserting open house: " . $insert_stmt->error . "</p>";
                    $total_errors++;
                }
                $insert_stmt->close();
            }
            
            $check_stmt->close();
            
        } catch (Exception $e) {
            echo "<p style='color: red;'>❌ Error processing open house: " . $e->getMessage() . "</p>";
            $total_errors++;
        }
    }
    
    $total_inserted += $batch_inserted;
    $total_updated += $batch_updated;
    $total_skipped_inactive += $batch_skipped;
    
    echo "<p>✅ Batch " . ($batch + 1) . " complete: $batch_inserted inserted, $batch_updated updated, $batch_skipped skipped (inactive properties)</p>";
    
    // Small delay to be respectful to the API
    usleep(100000); // 0.1 second
}

// ENHANCED: Clean up open houses for properties that are no longer active
echo "<h3>🧹 Cleaning up open houses for inactive properties...</h3>";
$cleanup_sql = "
    DELETE oh FROM rets_openhouse oh
    LEFT JOIN rets_property rp ON oh.L_ListingID = rp.L_ListingID
    WHERE rp.L_ListingID IS NULL 
       OR rp.L_Status != 'Active'
       OR oh.OpenHouseDate < CURDATE()
";
$conn->query($cleanup_sql);
$cleanup_count = $conn->affected_rows;
echo "<p>🧹 Cleaned up $cleanup_count open houses for inactive/past properties</p>";

echo "<h2>🎉 Enhanced Sync Complete!</h2>";
echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
echo "<p><strong>Summary:</strong></p>";
echo "<ul>";
echo "<li>✅ Total inserted: $total_inserted</li>";
echo "<li>🔄 Total updated: $total_updated</li>";
echo "<li>⚠️ Total skipped (inactive properties): $total_skipped_inactive</li>";
echo "<li>🧹 Cleaned up past/inactive: $cleanup_count</li>";
echo "<li>❌ Total errors: $total_errors</li>";
echo "</ul>";
echo "</div>";

// Enhanced: Show recent open houses with property status verification
$recent_sql = "
    SELECT oh.L_ListingID, oh.OpenHouseDate, oh.OH_StartTime, oh.OH_EndTime, 
           oh.created_at, rp.L_Status, rp.L_Address
    FROM rets_openhouse oh
    JOIN rets_property rp ON oh.L_ListingID = rp.L_ListingID
    WHERE rp.L_Status = 'Active'
    ORDER BY oh.created_at DESC 
    LIMIT 10
";
$recent_result = $conn->query($recent_sql);

if ($recent_result && $recent_result->num_rows > 0) {
    echo "<h3>📅 Recent Open Houses (Active Properties Only):</h3>";
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Listing ID</th><th>Address</th><th>Date</th><th>Start Time</th><th>End Time</th><th>Property Status</th><th>Added</th></tr>";
    
    while ($row = $recent_result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['L_ListingID']) . "</td>";
        echo "<td>" . htmlspecialchars(substr($row['L_Address'], 0, 40)) . "...</td>";
        echo "<td>" . htmlspecialchars($row['OpenHouseDate']) . "</td>";
        echo "<td>" . htmlspecialchars($row['OH_StartTime']) . "</td>";
        echo "<td>" . htmlspecialchars($row['OH_EndTime']) . "</td>";
        echo "<td><span style='color: green; font-weight: bold;'>" . htmlspecialchars($row['L_Status']) . "</span></td>";
        echo "<td>" . htmlspecialchars($row['created_at']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Enhanced: Show statistics
$stats_sql = "
    SELECT 
        COUNT(*) as total_openhouses,
        COUNT(DISTINCT oh.L_ListingID) as unique_properties,
        MIN(oh.OpenHouseDate) as earliest_date,
        MAX(oh.OpenHouseDate) as latest_date
    FROM rets_openhouse oh
    JOIN rets_property rp ON oh.L_ListingID = rp.L_ListingID
    WHERE rp.L_Status = 'Active'
      AND oh.OpenHouseDate >= CURDATE()
";
$stats_result = $conn->query($stats_sql);

if ($stats_result && $stats_result->num_rows > 0) {
    $stats = $stats_result->fetch_assoc();
    echo "<h3>📊 Open House Statistics:</h3>";
    echo "<ul>";
    echo "<li><strong>Total upcoming open houses:</strong> " . $stats['total_openhouses'] . "</li>";
    echo "<li><strong>Unique active properties with open houses:</strong> " . $stats['unique_properties'] . "</li>";
    echo "<li><strong>Date range:</strong> " . $stats['earliest_date'] . " to " . $stats['latest_date'] . "</li>";
    echo "</ul>";
}

$conn->close();

echo "<p style='margin-top: 20px;'><strong>✅ rets_openhouse table now contains only open houses for Active properties!</strong></p>";
?>