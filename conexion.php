<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$host = "db.alwfdfubiefrgsgwkylr.supabase.co";
$port = "5432";
$dbname = "VM";
$user = "postgres";
$password = "pg584sql";
try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;";
    $conn = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    echo "❌ Error de conexión: " . $e->getMessage();
    exit;
}
?>
