<?php
// 1. Tell PHP to use Philippine Time
date_default_timezone_set('Asia/Manila');

$host = "ticketech-26735.j77.aws-ap-southeast-1.cockroachlabs.cloud";
$port = "26257";
$dbname = "defaultdb";
$username = "danilo";
$password = "JfX-IK5qThfKxlAWqqspLw";

// 2. Update this path to point to your new helpdesk folder!
$sslrootcert = __DIR__ . "/root.crt";

$dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=verify-full;sslrootcert=$sslrootcert;options=--cluster=ticketech-26735";

try {
    $conn = new PDO($dsn, $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 3. Tell CockroachDB to use Philippine Time
    $conn->exec("SET TIME ZONE 'Asia/Manila'");
    
} catch (PDOException $e) {
    die("❌ Connection failed: " . $e->getMessage());
}
?>