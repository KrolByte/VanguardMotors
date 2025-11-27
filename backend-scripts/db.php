<?php
// Archivo: backend-scripts/db.php
// Propósito: Contiene la función para establecer la conexión a PostgreSQL (Supabase)

// Deshabilitar salida de errores
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/db_errors.log');

// Cargar las variables de entorno desde la raíz
require_once __DIR__ . '/../env_loader.php'; 

function getDbConnection() {
    // Obtener variables de entorno
    $host = getenv('DB_HOST');
    $port = getenv('DB_PORT');
    $dbname = getenv('DB_NAME'); 
    $user = getenv('DB_USER'); 
    $password = getenv('DB_PASSWORD'); 

    // Validación
    if (empty($host) || empty($port) || empty($dbname) || empty($user)) {
        error_log("Database configuration error: Missing credentials");
        error_log("Host: " . ($host ?: 'EMPTY'));
        error_log("Port: " . ($port ?: 'EMPTY'));
        error_log("DB Name: " . ($dbname ?: 'EMPTY'));
        error_log("User: " . ($user ?: 'EMPTY'));
        throw new Exception("Database configuration error: Missing credentials");
    }

    try {
        // CORRECCIÓN: El formato correcto es sin coma entre host y port
        $dsn = "pgsql:host={$host};port={$port};dbname={$dbname}";
        
        // Opciones de conexión
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_AUTOCOMMIT => true, // Asegurar que autocommit esté activado
            PDO::ATTR_TIMEOUT => 5 // Timeout de 5 segundos
        ];
        
        $pdo = new PDO($dsn, $user, $password, $options);
        
        return $pdo; 
        
    } catch (PDOException $e) {
        // Log del error real
        error_log("Database connection failed: " . $e->getMessage());
        error_log("DSN used: pgsql:host={$host};port={$port};dbname={$dbname}");
        
        // Lanzar excepción genérica para no exponer credenciales
        throw new Exception("Database connection failed. Check server logs for details.");
    }
}
?>