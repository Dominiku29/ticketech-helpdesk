<?php
date_default_timezone_set('Asia/Manila');

// Get credentials securely from Render's Environment Variables
$host = getenv('DB_HOST');
$port = getenv('DB_PORT');
$dbname = getenv('DB_NAME');
$username = getenv('DB_USER');
$password = getenv('DB_PASSWORD');

// Keep your certificate path for Render
$sslrootcert = __DIR__ . "/root.crt";

$dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=verify-full;sslrootcert=$sslrootcert;options=--cluster=ticketech-26735";

try {
    $conn = new PDO($dsn, $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->exec("SET TIME ZONE 'Asia/Manila'");
} catch (PDOException $e) {
    die("❌ Connection failed: " . $e->getMessage());
}
?>
