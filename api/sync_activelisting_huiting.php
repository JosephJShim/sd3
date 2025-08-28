<?php
/********************************************************************
 * sync_activelisting_huiting_enhanced.php — Enhanced fast incremental sync
 * This creates and maintains the activelistings table with only Active properties
 ********************************************************************/
date_default_timezone_set('America/Chicago');
echo "===== ActiveListing ENHANCED SYNC " . date('Y-m-d H:i:s') . " =====\n";

require_once __DIR__.'/db.php';
if ($conn->connect_errno) exit("DB error: {$conn->connect_error}\n");
echo "✅ DB connected.\n";

/* 1. Ensure target table & indexes */
echo "🔧 Setting up activelistings table...\n";

// Create table if not exists (copy structure from rets_property)
$conn->query("CREATE TABLE IF NOT EXISTS activelistings LIKE rets_property");

// Add indexes for better performance
$conn->query("ALTER TABLE activelistings ADD UNIQUE KEY uk_listing (L_ListingID)");
$conn->query("ALTER TABLE activelistings ADD INDEX idx_status (L_Status)");
$conn->query("ALTER TABLE activelistings ADD INDEX idx_updated (updated_date)");

echo "✅ Table/indexes OK.\n";

/* 2. Get statistics before sync */
$before_result = $conn->query("SELECT COUNT(*) as count FROM activelistings");
$before_count = $before_result ? $before_result->fetch_assoc()['count'] : 0;
echo "📊 Active listings before sync: $before_count\n";

/* 3. Insert new active listings from recent updates */
echo "➕ Inserting new active listings...\n";
$sqlInsert = "
INSERT IGNORE INTO activelistings
SELECT *
  FROM rets_property
 WHERE L_Status = 'Active'
   AND (updated_date >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)
        OR L_ListingID NOT IN (SELECT L_ListingID FROM activelistings))
";
$conn->query($sqlInsert);
$inserted = $conn->affected_rows;
echo "➕ Inserted $inserted new active listings.\n";

/* 4. Update existing records that might have changed */
echo "🔄 Updating existing active listings...\n";
$sqlUpdate = "
UPDATE activelistings a
  JOIN rets_property p ON a.L_ListingID = p.L_ListingID
   SET a.L_DisplayId = p.L_DisplayId,
       a.L_Address = p.L_Address,
       a.L_Zip = p.L_Zip,
       a.LM_char10_70 = p.LM_char10_70,
       a.L_AddressStreet = p.L_AddressStreet,
       a.L_City = p.L_City,
       a.L_State = p.L_State,
       a.L_Class = p.L_Class,
       a.L_Type_ = p.L_Type_,
       a.L_Keyword2 = p.L_Keyword2,
       a.LM_Dec_3 = p.LM_Dec_3,
       a.L_Keyword1 = p.L_Keyword1,
       a.L_Keyword5 = p.L_Keyword5,
       a.L_Keyword7 = p.L_Keyword7,
       a.L_SystemPrice = p.L_SystemPrice,
       a.LM_Int2_3 = p.LM_Int2_3,
       a.L_ListingDate = p.L_ListingDate,
       a.ListingContractDate = p.ListingContractDate,
       a.LMD_MP_Latitude = p.LMD_MP_Latitude,
       a.LMD_MP_Longitude = p.LMD_MP_Longitude,
       a.LA1_UserFirstName = p.LA1_UserFirstName,
       a.LA1_UserLastName = p.LA1_UserLastName,
       a.L_Status = p.L_Status,
       a.LO1_OrganizationName = p.LO1_OrganizationName,
       a.L_Remarks = p.L_Remarks,
       a.L_Photos = p.L_Photos,
       a.PhotoTime = p.PhotoTime,
       a.PhotoCount = p.PhotoCount,
       a.L_alldata = p.L_alldata,
       a.updated_date = p.updated_date
 WHERE p.L_Status = 'Active'
   AND p.updated_date >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)
";
$conn->query($sqlUpdate);
$updated = $conn->affected_rows;
echo "🔄 Updated $updated existing active listings.\n";

/* 5. Delete/remove rows no longer Active */
echo "🗑️ Removing listings that are no longer active...\n";
$sqlDelete = "
DELETE a
  FROM activelistings a
  LEFT JOIN rets_property p
    ON a.L_ListingID = p.L_ListingID
   AND p.L_Status = 'Active'
 WHERE p.L_ListingID IS NULL
    OR p.L_Status != 'Active'
";
$conn->query($sqlDelete);
$deleted = $conn->affected_rows;
echo "🗑️ Removed $deleted listings that are no longer active.\n";

/* 6. Clean up any duplicates (safety measure) */
echo "🧹 Cleaning up any duplicates...\n";
$sqlCleanup = "
DELETE a1 FROM activelistings a1
INNER JOIN activelistings a2
WHERE a1.id > a2.id AND a1.L_ListingID = a2.L_ListingID
";
$conn->query($sqlCleanup);
$duplicates_removed = $conn->affected_rows;
if ($duplicates_removed > 0) {
    echo "🧹 Removed $duplicates_removed duplicate records.\n";
}

/* 7. Get final statistics */
$after_result = $conn->query("SELECT COUNT(*) as count FROM activelistings");
$after_count = $after_result ? $after_result->fetch_assoc()['count'] : 0;

// Get status breakdown from main table for comparison
$status_result = $conn->query("
    SELECT L_Status, COUNT(*) as count 
    FROM rets_property 
    WHERE updated_date >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    GROUP BY L_Status 
    ORDER BY count DESC
");

echo "\n📊 SYNC SUMMARY:\n";
echo "================\n";
echo "Before sync: $before_count active listings\n";
echo "After sync:  $after_count active listings\n";
echo "Net change:  " . ($after_count - $before_count) . "\n";
echo "Inserted:    $inserted\n";
echo "Updated:     $updated\n";
echo "Removed:     $deleted\n";

if ($status_result && $status_result->num_rows > 0) {
    echo "\n📈 Recent Status Breakdown (last 24h):\n";
    while ($row = $status_result->fetch_assoc()) {
        echo "  " . $row['L_Status'] . ": " . $row['count'] . "\n";
    }
}

/* 8. Verify data integrity */
echo "\n🔍 Data Integrity Check:\n";
$integrity_check = $conn->query("
    SELECT COUNT(*) as invalid_count
    FROM activelistings a
    LEFT JOIN rets_property p ON a.L_ListingID = p.L_ListingID
    WHERE p.L_ListingID IS NULL OR p.L_Status != 'Active'
");
$invalid_count = $integrity_check ? $integrity_check->fetch_assoc()['invalid_count'] : 0;

if ($invalid_count > 0) {
    echo "⚠️  Warning: $invalid_count records in activelistings don't match active properties in rets_property\n";
} else {
    echo "✅ All records in activelistings are valid and active\n";
}

$conn->close();

echo "\n===== ENHANCED SYNC END " . date('Y-m-d H:i:s') . " =====\n";
echo "✅ activelistings table now contains only Active properties from rets_property\n";
?>