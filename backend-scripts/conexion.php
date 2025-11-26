<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
function getDbConnection() {
$host = "db.alwfdfubiefrgsgwkylr.supabase.co";
$port = "5432";
$dbname = "postgres";
$user = "postgres";
$password = "pg584sql";


try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;";
    $conexion = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
return $conexion; 
    } catch (PDOException $e) {
        error_log("Fallo de conexión en getDbConnection(): " . $e->getMessage());
        http_response_code(500);
        
        // Devolver un JSON de error para que el frontend lo capture
        echo json_encode(['success' => false, 'message' => 'Error de conexión a la BD: ' . $e->getMessage()]);
        exit;
    }
  }
?>

