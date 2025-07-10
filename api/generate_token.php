<?php
// Trestle API Token Generation Script for Huiting
// Run this once to generate and store your API token into the token_store_huiting table

error_reporting(E_ALL);
ini_set('display_errors', 1);

// 1. 引入数据库连接（保持不变）
include('../api/db.php');

// 2. 确保 token_store_huiting 表存在，结构同 token_store
$create_sql = "
    CREATE TABLE IF NOT EXISTS `token_store_huiting` 
    LIKE `token_store`;
";
if (! $conn->query($create_sql)) {
    die("❌ Failed to create token_store_huiting table: " . $conn->error);
}

// 3. Trestle API credentials
$token_type    = 'trestle';
$client_id     = 'trestle_IDXExchangeCRMLSRECore20240122014147';
$client_secret = 'e579677f6297447aa794739558011d06';
$token_url     = 'https://api-trestle.corelogic.com/trestle/oidc/connect/token';

echo "<h2>🔑 Trestle API Token Generation (Huiting)</h2><hr>";

// 4. 先查缓存，看有没有未过期的
$stmt = $conn->prepare("
    SELECT access_token, expires_at 
      FROM token_store_huiting 
     WHERE token_type = ?
");
$stmt->bind_param("s", $token_type);
$stmt->execute();
$stmt->store_result();
$stmt->bind_result($cached_token, $expires_at);

if ($stmt->num_rows > 0) {
    $stmt->fetch();
    if (time() < $expires_at) {
        echo "<p style='color: green;'>✅ Valid token already exists.</p>";
        echo "<p><strong>Expires at:</strong> " 
             . date('Y-m-d H:i:s', $expires_at) . "</p>";
        echo "<p><strong>Preview:</strong> " 
             . substr($cached_token, 0, 20) . "...</p>";
        $stmt->close();
        $conn->close();
        exit;
    } else {
        echo "<p style='color: orange;'>⚠️ Existing token expired. Generating new one…</p>";
    }
}
$stmt->close();

// 5. 请求新的 token
echo "<p>🔄 Requesting new token from Trestle API…</p>";
$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL            => $token_url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query([
        'grant_type'    => 'client_credentials',
        'client_id'     => $client_id,
        'client_secret' => $client_secret
    ]),
    CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
]);
$response  = curl_exec($curl);
$http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
curl_close($curl);

if ($http_code === 200) {
    $data = json_decode($response, true);
    if (!empty($data['access_token']) && !empty($data['expires_in'])) {
        $access_token = $data['access_token'];
        // 提前 60 秒刷新
        $expires_at   = time() + $data['expires_in'] - 60;

        // 6. 写入 token_store_huiting
        $upsert = $conn->prepare("
            INSERT INTO token_store_huiting 
                (token_type, access_token, expires_at)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE
                access_token = VALUES(access_token),
                expires_at   = VALUES(expires_at)
        ");
        $upsert->bind_param("ssi", $token_type, $access_token, $expires_at);

        if ($upsert->execute()) {
            echo "<p style='color: green;'>✅ Token stored successfully!</p>";
            echo "<p><strong>Expires at:</strong> " 
                 . date('Y-m-d H:i:s', $expires_at) . "</p>";
            echo "<p><strong>Preview:</strong> " 
                 . substr($access_token, 0, 20) . "...</p>";
            echo "<p><strong>Lifetime:</strong> " 
                 . number_format($data['expires_in']/3600, 1) . " hours</p>";
        } else {
            echo "<p style='color: red;'>❌ Failed to store token:</p>";
            echo "<pre>" . htmlspecialchars($upsert->error) . "</pre>";
        }
        $upsert->close();
    } else {
        echo "<p style='color: red;'>❌ Invalid response from API:</p>";
        echo "<pre>" . htmlspecialchars($response) . "</pre>";
    }
} else {
    echo "<p style='color: red;'>❌ API request failed (HTTP {$http_code}):</p>";
    echo "<pre>" . htmlspecialchars($response) . "</pre>";
}

$conn->close();
