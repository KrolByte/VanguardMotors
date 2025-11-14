<?php
// Archivo: test_connection.php (en la raíz del proyecto)

// 1. INCLUIR EL LECTOR DE VARIABLES DE ENTORNO
// Esto carga el archivo .env y sus valores (incluida la contraseña)
require 'env_loader.php'; 

// 2. OBTENER LAS VARIABLES SEGURAS
$host = getenv('DB_HOST');
$port = getenv('DB_PORT');
$dbname = getenv('DB_NAME'); 
$user = getenv('DB_USER'); 
$password = getenv('DB_PASSWORD'); 

// 3. INTENTAR CONECTAR Y HACER UNA CONSULTA DE PRUEBA
try {
    $dsn = "pgsql:host=" . $host . ";port=" . $port . ";dbname=" . $dbname;
    $pdo = new PDO($dsn, $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "<h3>✅ Conexión a Supabase establecida.</h3>";
    
    // --- CONSULTA DE PRUEBA ---
    $stmt = $pdo->query("SELECT user_id, email, role FROM public.users LIMIT 5");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<h3>Datos de prueba de la tabla 'users':</h3>";
    echo "<table border='1'><tr><th>ID</th><th>Email</th><th>Rol</th></tr>";
    
    foreach ($users as $user) {
        echo "<tr><td>" . $user['user_id'] . "</td><td>" . $user['email'] . "</td><td>" . $user['role'] . "</td></tr>";
    }
    echo "</table>";

} catch (PDOException $e) {
    // Si falla, el problema es la contraseña, host o puerto.
    die("<h3>❌ Error de conexión/consulta:</h3>" . $e->getMessage());
}
?>