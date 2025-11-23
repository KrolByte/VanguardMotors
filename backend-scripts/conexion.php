<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ** NOTA: Usar esta sintaxis que está dentro de la función **

function getDbConnection() {
    // Variables de conexión a Supabase
    $host = 'db.alvfdfubiefrgsgwkylr.supabase.co';
    $port = '5432';
    $dbname = 'postgres';
    $user = 'postgres';
    $password = 'pg584sql';

    try {
        // CORRECCIÓN 1: Usar la coma (,) para separar host y port (RESUELVE el error 08006)
        $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
        
        $conexion = new PDO($dsn, $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        
        // OPCIONAL (Para debugging): echo "¡CONEXIÓN EXITOSA!";
        
        // CORRECCIÓN 2: DEVOLVER el objeto de conexión (RESUELVE el error de la Línea 30)
        return $conexion; 
    }catch (PDOException $e) {
   throw new Exception("Error de conexión a la base de datos");
    }
} 

?>