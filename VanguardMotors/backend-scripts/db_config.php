<?php
require 'env_loader.php';
// Configuración de PostgreSQL
define('DB_HOST', 'localhost');
define('DB_NAME', 'VM'); // Reemplaza con tu nombre de DB
define('DB_USER', 'postgres');      // Reemplaza con tu usuario
define('DB_PASSWORD', 'pg584sql');        // Reemplaza con tu contraseña

// Intenta establecer la conexión
try {
    // Cadena de conexión (DSN) para PostgreSQL
    $dsn = "pgsql:host=" . DB_HOST . ";dbname=" . DB_NAME;
    
    // Crear una nueva instancia de PDO
    $pdo = new PDO($dsn, DB_USER, DB_PASSWORD);
    
    // Configurar atributos para manejar errores y codificación
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET NAMES 'utf8'");

    // Si la conexión es exitosa, la variable $pdo contiene el objeto de conexión
} catch (PDOException $e) {
    // Si hay un error, terminar la ejecución y mostrar el mensaje de error
    die("Error de conexión a PostgreSQL: " . $e->getMessage());
}
?>