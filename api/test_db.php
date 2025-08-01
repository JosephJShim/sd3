<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>🔍 Remote Database Connection Test</h2>";
echo "<hr>";

// Remote database configuration
$host = '199.250.207.194';
$db = 'boxgra6_sd3';
$user = 'boxgra6_sd3';
$pass = 'Real_estate123$';
$port = 3306;

echo "<p><strong>Testing connection to:</strong></p>";
echo "<ul>";
echo "<li>Host: $host</li>";
echo "<li>User: $user</li>";
echo "<li>Database: $db</li>";
echo "<li>Port: $port</li>";
echo "</ul>";

try {
    $conn = new mysqli($host, $user, $pass, $db, $port);
    
    if ($conn->connect_error) {
        echo "<p style='color: red;'>❌ Connection failed: " . $conn->connect_error . "</p>";
    } else {
        echo "<p style='color: green;'>✅ Connected successfully!</p>";
        echo "<p><strong>Server info:</strong> " . $conn->server_info . "</p>";
        echo "<p><strong>Host info:</strong> " . $conn->host_info . "</p>";
        
        // Test a simple query
        $result = $conn->query("SHOW TABLES");
        if ($result) {
            echo "<p><strong>Tables found:</strong> " . $result->num_rows . "</p>";
            
            // Show all table names
            echo "<p><strong>Table names:</strong></p><ul>";
            while ($row = $result->fetch_array()) {
                echo "<li>" . $row[0] . "</li>";
            }
            echo "</ul>";
        }
        
        $conn->close();
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Exception: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><a href='generate_token.php'>🔑 Back to Token Generation</a></p>";
?>