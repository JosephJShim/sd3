<?php
// Trestle API Token Generation Script
// Run this once to generate and store your API token

// Include local database configuration
include('../includes/db_local.php');

// Trestle API credentials
$token_type = 'trestle';
$client_id = 'trestle_IDXExchangeCRMLSRECore20240122014147';
$client_secret = 'e579677f6297447aa794739558011d06';
$token_url = 'https://api-trestle.corelogic.com/trestle/oidc/connect/token';

echo "<h2>🔑 Trestle API Token Generation</h2>";
echo "<hr>";

// Check for cached token
$stmt = $conn->prepare("SELECT access_token, expires_at FROM token_store WHERE token_type = ?");
$stmt->bind_param("s", $token_type);
$stmt->execute();
$stmt->store_result();
$stmt->bind_result($cached_token, $expires_at);

if ($stmt->num_rows > 0) {
    $stmt->fetch();
    if (time() < $expires_at) {
        echo "<p style='color: green;'>✅ Valid token already exists and hasn't expired yet.</p>";
        echo "<p><strong>Token expires at:</strong> " . date('Y-m-d H:i:s', $expires_at) . "</p>";
        echo "<p><strong>Token preview:</strong> " . substr($cached_token, 0, 20) . "...</p>";
        $stmt->close();
        $conn->close();
        exit;
    } else {
        echo "<p style='color: orange;'>⚠️ Existing token has expired. Generating new token...</p>";
    }
}
$stmt->close();

// Get new token from API
echo "<p>🔄 Requesting new token from Trestle API...</p>";

$curl = curl_init();
curl_setopt_array($curl, array(
    CURLOPT_URL => $token_url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query([
        'grant_type' => 'client_credentials',
        'client_id' => $client_id,
        'client_secret' => $client_secret
    ]),
    CURLOPT_HTTPHEADER => array(
        'Content-Type: application/x-www-form-urlencoded'
    ),
));

$response = curl_exec($curl);
$http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
curl_close($curl);

if ($http_code == 200) {
    $data = json_decode($response, true);
    
    if (!empty($data['access_token']) && !empty($data['expires_in'])) {
        $access_token = $data['access_token'];
        $expires_at = time() + $data['expires_in'] - 60; // refresh 1 min early

        // Upsert token in database
        $stmt = $conn->prepare("
            INSERT INTO token_store (token_type, access_token, expires_at)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE access_token = VALUES(access_token), expires_at = VALUES(expires_at)
        ");
        $stmt->bind_param("ssi", $token_type, $access_token, $expires_at);
        
        if ($stmt->execute()) {
            echo "<p style='color: green;'>✅ Token generated and stored successfully!</p>";
            echo "<p><strong>Token expires at:</strong> " . date('Y-m-d H:i:s', $expires_at) . "</p>";
            echo "<p><strong>Token preview:</strong> " . substr($access_token, 0, 20) . "...</p>";
            echo "<p><strong>Expires in:</strong> " . number_format($data['expires_in'] / 3600, 1) . " hours</p>";
        } else {
            echo "<p style='color: red;'>❌ Failed to store token in database.</p>";
        }
        $stmt->close();
    } else {
        echo "<p style='color: red;'>❌ Failed to get access token from response</p>";
        echo "<pre>" . print_r($data, true) . "</pre>";
    }
} else {
    echo "<p style='color: red;'>❌ API request failed with HTTP code: " . $http_code . "</p>";
    echo "<pre>" . $response . "</pre>";
}

$conn->close();

echo "<hr>";
echo "<p><a href='index.php'>🏠 Go to Property Search</a> | <a href='sync_properties.php'>🔄 Sync Properties</a></p>";
?> 