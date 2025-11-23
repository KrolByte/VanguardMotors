<?php
// Archivo: backend-scripts/db.php
// Propósito: Contiene la función para establecer la conexión a PostgreSQL (Supabase)
// Cargar las variables de entorno desde la raíz (../)
require __DIR__ . '/../env_loader.php'; 

function getDbConnection() {
    // Obtener variables de entorno (getenv() funciona después de env_loader.php)
    $host = getenv('DB_HOST');
    $port = getenv('DB_PORT');
    $dbname = getenv('DB_NAME'); 
    $user = getenv('DB_USER'); 
    $password = getenv('DB_PASSWORD'); 

    // Validación (Opcional, pero bueno para depuración)
    if (empty($host) || empty($port) || empty($dbname) || empty($user)) {
        throw new Exception("Error de configuración: Faltan credenciales de base de datos. Verifique el archivo .env");
    }

    try {
        $dsn = "pgsql:host=" . $host . ",port=" . $port . ";dbname=" . $dbname;
        $pdo = new PDO($dsn, $user, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo; 
        
    } catch (PDOException $e) {
        // Lanzamos una excepción genérica para no exponer detalles de credenciales
        throw new Exception("Fallo de conexión a la Base de Datos: " . $e->getMessage());
    }


}
?>