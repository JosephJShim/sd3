<?php


// $host = 'localhost';
// $dbname = 'boxgra5_cali';
// $dbuser = 'boxgra5_sd3';
// $dbpass = 'Real_estate123$';
// $table1 = 'rets_property';

$host = 'localhost';
$dbname = 'boxgra6_sd3';
$dbuser = 'boxgra6_yu';
$dbpass = '79._.h*.*yh@_Jd';
$table1 = 'rets_property_yu';

try {
    // Create a PHP data object instance to connect to mysql
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $dbuser, $dbpass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Connected successfully to table: <strong>$table1</strong><br><br>";
    
    // Display the frist 10 rows from the table
    $sql = "SELECT * FROM $table1 LIMIT 10";
    $stmt = $pdo->query($sql);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<pre>";
    print_r($results);
    echo "</pre>";

} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>

<!-- // Local Database Configuration for WSL2 Development
$servername = 'localhost';
$username = 'sd3_user'; // WSL2 MySQL user
$password = 'sd3_password_123'; // Password we just created
$dbname = 'sd3'; // Using your existing sd3 database
$port = 3306;


// $conn = new mysqli($servername, $username, $password, $dbname, $port);

// if ($conn->connect_error) {
//     die("Connection failed: " . $conn->connect_error);
// }

// // Set charset to handle special characters
// $conn->set_charset("utf8mb4");

echo "<!-- WSL2 database connected successfully -->";
?>  -->
