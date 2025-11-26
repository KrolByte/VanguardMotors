<?php
// Archivo: test_connection.php (en la raíz del proyecto)

// 1. INCLUIR EL ARCHIVO DE CONEXIÓN
// Esto, a su vez, carga el env_loader.php
require 'backend-scripts/db.php'; 
putenv('TEST_VAR=SUCCESS');
$test = getenv('TEST_VAR');

if ($test !== 'SUCCESS') {
    die("❌ ERROR CRÍTICO: PHP no pudo leer la variable 'TEST_VAR'. putenv()/getenv() NO funcionan correctamente. La carga del .env fallará.");
}

echo "<h1>Test de Conexión a Supabase (PostgreSQL)</h1>";

// 2. INTENTAR CONECTAR Y HACER UNA CONSULTA DE PRUEBA
try {
    // Llama a la función de conexión desde db.php
    $pdo = getDbConnection();

    echo "<h3 style='color: green;'>✅ Conexión a Supabase establecida.</h3>";
    
    // --- CONSULTA DE PRUEBA (Ejemplo con tabla USERS de tu diagrama) ---
    // Nota: Si la tabla 'users' está en el esquema 'public' o 'auth', debes ajustarlo.
    $stmt = $pdo->query("SELECT user_id, email, role FROM public.users LIMIT 5");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($users) > 0) {
        echo "<h3>Datos de prueba de la tabla 'users':</h3>";
        echo "<table border='1'><tr><th>ID</th><th>Email</th><th>Rol</th></tr>";
        
        foreach ($users as $user) {
            echo "<tr><td>" . htmlspecialchars($user['user_id']) . "</td><td>" . htmlspecialchars($user['email']) . "</td><td>" . htmlspecialchars($user['role']) . "</td></tr>";
        }
        echo "</table>";
    } else {
        echo "<h3>Advertencia:</h3> La conexión es exitosa, pero la tabla 'public.users' está vacía o no existe.";
    }

} catch (Exception $e) {
    // Capturamos la excepción de db.php
    die("<h3 style='color: red;'>❌ Error de conexión/consulta:</h3><pre>" . htmlspecialchars($e->getMessage()) . "</pre>");
}
?>