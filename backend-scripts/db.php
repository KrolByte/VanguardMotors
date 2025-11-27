<?php
// Deshabilitar salida de errores
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require __DIR__ . '/../env_loader.php'; 

function getDbConnection() {
    $host = getenv('DB_HOST');
    $port = getenv('DB_PORT');
    $dbname = getenv('DB_NAME'); 
    $user = getenv('DB_USER'); 
    $password = getenv('DB_PASSWORD'); 

    if (empty($host) || empty($port) || empty($dbname) || empty($user)) {
        error_log("Database configuration error: Missing credentials");
        throw new Exception("Database configuration error");
    }

    try {
        $dsn = "pgsql:host={$host};port={$port};dbname={$dbname}";
        $pdo = new PDO($dsn, $user, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo; 
        
    } catch (PDOException $e) {
        error_log("Database connection failed: " . $e->getMessage());
        throw new Exception("Database connection failed");
    }
}
?>