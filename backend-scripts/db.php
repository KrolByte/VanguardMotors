<?php
// Archivo: backend-scripts/db.php

// Adaptar la ruta de require para que funcione desde backend-scripts
require __DIR__ . '/../env_loader.php'; 

function getDbConnection() {
    // ... (El resto del código para obtener $host, $port, $dbname, $user, $password)
    // ... (El bloque try/catch para crear y devolver el objeto $pdo)
    
    $host = getenv('DB_HOST');
    $port = getenv('DB_PORT');
    $dbname = getenv('DB_NAME'); 
    $user = getenv('DB_USER'); 
    $password = getenv('DB_PASSWORD'); 

    try {
        $dsn = "pgsql:host=" . $host . ",port=" . $port . ";dbname=" . $dbname;
        $pdo = new PDO($dsn, $user, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo; 
    } catch (PDOException $e) {
        throw new Exception("Database Connection Failed: " . $e->getMessage());
    }


}
?>