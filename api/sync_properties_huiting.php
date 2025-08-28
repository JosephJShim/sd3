<?php
/********************************************************************
 * sync_properties_huiting.php — FINAL FIX + AUTO-CLEAN (STRICT/NULL-safe, ZWSP-clean)
 * - Skip empty ListingKey
 * - Prepared existence checks (with free_result)
 * - STRICT-safe: DATETIME/NUMERIC via NULLIF(?, '')
 * - Bind params ONLY variables (no expressions)
 * - utf8mb4 + clean/clamp long text/JSON
 * - Clean zero-width chars (U+200B/200C/200D/2060/FEFF) and NBSP, strip control chars
 * - Auto-delete blank L_ListingID; ensure UNIQUE(L_ListingID)
 ********************************************************************/

require_once 'db.php';
date_default_timezone_set('America/Chicago');

error_reporting(E_ALL);
ini_set('display_errors', 1);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function clean_text($s){
    $s = (string)($s ?? '');
    //    U+200B ZERO WIDTH SPACE, U+200C ZWNJ, U+200D ZWJ, U+2060 WORD JOINER, U+FEFF BOM
    $s = preg_replace('/[\x{200B}\x{200C}\x{200D}\x{2060}\x{FEFF}]/u', '', $s);
    $s = str_replace("\xC2\xA0", ' ', $s);
    $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $s);
    $s = @iconv('UTF-8', 'UTF-8//IGNORE', $s);
    return $s;
}
function clamp($s, $max=65500){
    $s = (string)$s;
    if (strlen($s) > $max) return substr($s, 0, $max-3).'...';
    return $s;
}
//echo "<h2>🏠 Trestle API Property Sync - STRICT/NULL-safe</h2><hr>";

// 1) Token
$stmt = $conn->prepare("
    SELECT access_token, expires_at
      FROM token_store_yu
     WHERE token_type = 'trestle'
     LIMIT 1
");
$stmt->execute();
$stmt->store_result();
$stmt->bind_result($access_token, $expires_at);
$stmt->fetch();
$stmt->close();

if (!$access_token) die("❌ No access token found.");
if (time() > (int)$expires_at) die("❌ Access token expired. Run <a href='generate_token.php'>generate_token.php</a>.");
//echo "✅ Valid access token found<br>";

// 2) Count
//echo "📊 Fetching total listings count...<br>";
$headers = ['Authorization: Bearer '.$access_token, 'Accept: application/json'];

$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL => "https://api-trestle.corelogic.com/trestle/odata/Property?\$orderby=ModificationTimestamp+desc&\$filter=PropertyType+eq+'Residential'&\$top=1&\$count=true",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_TIMEOUT => 30
]);
$json_data = curl_exec($curl);
$http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
curl_close($curl);

$use_ordering = true;
if ($http_code !== 200) {
    echo "❌ API Error: HTTP $http_code<br><pre>".h(substr($json_data??'',0,1000))."</pre>";
    echo "🔄 Fallback without ordering...<br>";
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => "https://api-trestle.corelogic.com/trestle/odata/Property?\$filter=PropertyType+eq+'Residential'&\$top=1&\$count=true",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 30
    ]);
    $json_data = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    if ($http_code !== 200) die("❌ API still failing: HTTP $http_code<br><pre>".h($json_data)."</pre>");
    $use_ordering = false;
}

$response = json_decode($json_data);
$total = (int)($response->{'@odata.count'} ?? 0);
if ($total <= 0) die("❌ No records found or invalid API response.");

//echo "📈 Total residential listings: <strong>".number_format($total)."</strong><br>";
$batch_size=200; $total_batches=(int)ceil($total/$batch_size);
$total_inserted=0; $total_skipped=0;
//echo "🔄 Will process $total_batches x $batch_size<br>";
if (!$use_ordering) echo "⚠️ No ordering due to API limits<br>";
//echo "<hr>";

$existStmt = $conn->prepare("SELECT 1 FROM rets_property WHERE L_ListingID = ? LIMIT 1");

// 3) Process
for ($batch=0; $batch<$total_batches; $batch++){
    $skip=$batch*$batch_size;
    //echo "<div style='background:#f8f9fa;padding:15px;border-radius:5px;margin:10px 0;'>";
    //echo "<h3>Batch ".($batch+1)." / $total_batches (offset: $skip)</h3>";

    $api_url = "https://api-trestle.corelogic.com/trestle/odata/Property?".
               ($use_ordering?"\$orderby=ModificationTimestamp+desc&":"").
               "\$filter=PropertyType+eq+'Residential'&".
               "\$skip=$skip&\$top=$batch_size&".
               "\$expand=Media(\$orderby=Order)";

    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $api_url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 60
    ]);
    $json_data = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($http_code !== 200){
        echo "❌ API Error HTTP $http_code<br><pre>".h(substr($json_data??'',0,800))."</pre></div>";
        continue;
    }

    $response = json_decode($json_data);
    if (empty($response->value)){ echo "❌ No listings in this batch</div>"; continue; }

    $batch_inserted=0; $batch_skipped=0;

    foreach ($response->value as $row){
        // 3A) Skip bad key
        if (empty($row->ListingKey) || trim((string)$row->ListingKey)===''){
            echo "🚫 Skip: missing ListingKey<br>"; continue;
        }

        // Exists?
        $lk=(string)$row->ListingKey;
        $existStmt->bind_param('s',$lk);
        $existStmt->execute();
        $existStmt->store_result();
        if ($existStmt->num_rows>0){
            $batch_skipped++;
            if ($batch_skipped<=5) echo "⭐ Skipped (exists): ".h(substr($row->UnparsedAddress ?? 'No Address',0,50))."...<br>";
            $existStmt->free_result();
            continue;
        }
        $existStmt->free_result();

        // --- Build all variables (NO expressions in bind_param) ---
        // Texts
        $v1  = (string)$row->ListingKey;               // L_ListingID
        $v2  = (string)$row->ListingKey;               // L_DisplayId
        $v3  = clean_text($row->UnparsedAddress ?? '');
        $v4  = clean_text($row->PostalCode ?? '');
        $v5  = clean_text($row->SubdivisionName ?? ''); // LM_char10_70
        $v6  = clean_text($row->StreetName ?? '');
        $v7  = clean_text($row->City ?? '');
        $v8  = clean_text($row->StateOrProvince ?? '');
        $v9  = clean_text($row->PropertyType ?? '');
        $v10 = clean_text($row->PropertySubType ?? '');

        // Helpers: return '' when missing; SQL will NULLIF(?, '')
        $to_dt = function($v){ 
            if (empty($v)) return '';
            $ts=strtotime((string)$v); return $ts? date('Y-m-d H:i:s',$ts):'';
        };
        $to_num = function($v){ return (isset($v) && $v!=='') ? (string)$v : ''; };

        // Numeric-ish mapped legacy columns
        $v11 = $to_num($row->BedroomsTotal ?? null);           // L_Keyword2
        $v12 = $to_num($row->BathroomsTotalInteger ?? null);   // LM_Dec_3
        $v13 = $to_num($row->LotSizeArea ?? null);             // L_Keyword1
        $v14 = $to_num($row->GarageSpaces ?? null);            // L_Keyword5

        // More text
        $levels  = clean_text($row->Levels ?? '');             // L_Keyword7

        // Pricing / areas / datetimes / coords
        $v16 = $to_num($row->ListPrice ?? null);               // L_SystemPrice
        $v17 = $to_num($row->LivingArea ?? null);              // LM_Int2_3
        $v18 = $to_dt($row->ModificationTimestamp ?? null);    // L_ListingDate
        $v19 = $to_dt($row->ListingContractDate ?? null);      // ListingContractDate
        $v20 = $to_num($row->Latitude ?? null);                // LMD_MP_Latitude
        $v21 = $to_num($row->Longitude ?? null);               // LMD_MP_Longitude

        // Agent / office / status / remarks
        $v22 = clean_text($row->ListAgentFirstName ?? '');
        $v23 = clean_text($row->ListAgentLastName ?? '');
        $v24 = clean_text($row->MlsStatus ?? '');
        $v25 = clean_text($row->ListOfficeName ?? '');
        $remarks = clamp(clean_text($row->PublicRemarks ?? ''), 64000);

        // Media JSON
        $media=[];
        if (!empty($row->Media) && is_array($row->Media)){
            foreach ($row->Media as $m){ if (!empty($m->MediaURL)) $media[]=(string)$m->MediaURL; }
        }
        $images  = clamp(clean_text(json_encode($media, JSON_UNESCAPED_UNICODE)));

        $v28     = $to_dt($row->PhotosChangeTimestamp ?? null);  // PhotoTime
        $v29     = $to_num($row->PhotosCount ?? null);           // PhotoCount
        $alldata = clamp(clean_text(json_encode($row, JSON_UNESCAPED_UNICODE)), 64000);

        // 3B) INSERT (30 placeholders) + NOW()
        $sql = "
            INSERT INTO rets_property (
                L_ListingID, L_DisplayId, L_Address, L_Zip, LM_char10_70, L_AddressStreet,
                L_City, L_State, L_Class, L_Type_, 
                L_Keyword2, LM_Dec_3, L_Keyword1, L_Keyword5, L_Keyword7,
                L_SystemPrice, LM_Int2_3, L_ListingDate, ListingContractDate,
                LMD_MP_Latitude, LMD_MP_Longitude, LA1_UserFirstName, LA1_UserLastName,
                L_Status, LO1_OrganizationName, L_Remarks, L_Photos, PhotoTime, PhotoCount,
                L_alldata, updated_date
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                NULLIF(?, ''), NULLIF(?, ''), NULLIF(?, ''), NULLIF(?, ''), ?,
                NULLIF(?, ''), NULLIF(?, ''), NULLIF(?, ''), NULLIF(?, ''),
                NULLIF(?, ''), NULLIF(?, ''), ?, ?, ?, ?, ?, ?, NULLIF(?, ''), NULLIF(?, ''), ?, NOW()
            )
        ";
        $stmt2 = $conn->prepare($sql);
        if (!$stmt2){ echo "❌ Prepare failed: ".h($conn->error)."<br>"; continue; }

        // Bind variables
        $b1=$v1; $b2=$v2; $b3=$v3; $b4=$v4; $b5=$v5; $b6=$v6; $b7=$v7; $b8=$v8; $b9=$v9; $b10=$v10;
        $b11=$v11; $b12=$v12; $b13=$v13; $b14=$v14; $b15=$levels;
        $b16=$v16; $b17=$v17; $b18=$v18; $b19=$v19; $b20=$v20; $b21=$v21;
        $b22=$v22; $b23=$v23; $b24=$v24; $b25=$v25; $b26=$remarks; $b27=$images; $b28=$v28; $b29=$v29; $b30=$alldata;

        $ok = $stmt2->bind_param(
            str_repeat('s',30),
            $b1,$b2,$b3,$b4,$b5,$b6,$b7,$b8,$b9,$b10,
            $b11,$b12,$b13,$b14,$b15,
            $b16,$b17,$b18,$b19,$b20,$b21,
            $b22,$b23,$b24,$b25,$b26,$b27,$b28,$b29,$b30
        );
        if (!$ok){ echo "❌ bind_param failed: ".h($stmt2->error)."<br>"; $stmt2->close(); continue; }

        try{
            $stmt2->execute();
            $batch_inserted++;
            if ($batch_inserted<=5){
                echo "✅ Inserted: ".h(substr($v3 ?: 'No Address',0,50))."... (Status: ".h($v24 ?: 'Unknown').")<br>";
            }
        }catch(Throwable $e){
            echo "❌ Insert error L_ListingID=".h($v1).": ".h($e->getMessage())."<br>";
        }finally{
            $stmt2->close();
        }
    }

    $total_inserted += $batch_inserted;
    $total_skipped  += $batch_skipped;

    echo "<p><strong>Batch ".($batch+1)." Summary:</strong> $batch_inserted inserted, $batch_skipped skipped</p>";
    if ($batch_inserted>5 || $batch_skipped>5) echo "<p><em>Note: showing only first 5 per category</em></p>";
    //echo "</div>";

    if ($batch < $total_batches-1){ echo "<p>⏱️ Waiting 2 seconds...</p>"; sleep(2); }
}

// 4) Cleanup blanks
////echo "<hr><h3>🧹 Cleaning up blank entries…</h3>";
$del_sql = "DELETE FROM rets_property WHERE (L_ListingID IS NULL OR TRIM(L_ListingID)='')";
$conn->query($del_sql);
echo "🗑️ Deleted blank entries: <strong>".number_format($conn->affected_rows)."</strong><br>";

// 5) UNIQUE index
//echo "<h3>🔐 Ensuring UNIQUE index on L_ListingID…</h3>";
$idxRes = $conn->query("SHOW INDEX FROM rets_property WHERE Key_name='uniq_L_ListingID'");
if ($idxRes && $idxRes->num_rows==0){
    try{ $conn->query("ALTER TABLE rets_property MODIFY L_ListingID VARCHAR(255) NOT NULL"); echo "✅ Set L_ListingID NOT NULL<br>"; }
    catch(Throwable $e){ echo "⚠️ NOT NULL failed (continue): ".h($e->getMessage())."<br>"; }
    try{ $conn->query("ALTER TABLE rets_property ADD UNIQUE KEY uniq_L_ListingID (L_ListingID)"); echo "✅ UNIQUE index added<br>"; }
    catch(Throwable $e){ echo "⚠️ UNIQUE add failed: ".h($e->getMessage())."<br>"; }
} else {
    echo "✅ UNIQUE index already present.<br>";
}

// 6) Summary
//echo "<div style='background:#d4edda;padding:15px;border-radius:5px;margin:10px 0;'>";
//echo "<h2>🎉 Complete Summary</h2>";
//echo "<strong>✅ Total Inserted:</strong> ".number_format($total_inserted)."<br>";
//echo "<strong>⭐ Total Skipped:</strong> ".number_format($total_skipped)."<br>";
//echo "<strong>📈 Total Records Available:</strong> ".number_format($total)."<br>";
//echo "<strong>📊 Total Batches:</strong> $total_batches<br>";
//echo "<strong>🔧 Ordering Used:</strong> ".($use_ordering?'ModificationTimestamp desc':'No ordering')."<br>";
//echo "</div>";

$conn->close();
//echo "<hr><p><strong>🎉 Sync completed!</strong></p>";
//echo "<p><a href='verification_dashboard.php'>🔍 Run Verification</a> | <a href='sync_activelisting_huiting_enhanced.php'>📊 Update ActiveListings</a></p>";
