<?php
$host = "localhost";
$port = "5432";
$dbname = "VanguardMotors";
$user = "postgres";
$password = "tu_contraseña_aquí";

try {
    $conexion = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $user, $password);
    $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ Conexión exitosa a la base de datos.";
} catch (PDOException $e) {
    echo "❌ Error al conectar: " . $e->getMessage();
}
?>
