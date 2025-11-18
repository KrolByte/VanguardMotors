<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$host = "localhost";
$port = "5433";
$dbname = "vanguardmotors";
$user = "postgres";
$password = "123456";

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;";
    $conexion = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    echo "❌ Error de conexión: " . $e->getMessage();
    exit;
}
?>
