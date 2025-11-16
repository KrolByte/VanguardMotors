<?php
// Archivo: backend-scripts/register_handler.php

// Incluye la función de conexión a la BD (db.php)
// Nota: La ruta es relativa al db.php, que está en el mismo directorio.
require 'db.php'; 

// 1. Verifica que los datos del formulario hayan sido enviados por POST
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // 2. Recibe los datos del formulario y los limpia
    $fullName = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_SPECIAL_CHARS);
    $idNumber = filter_input(INPUT_POST, 'id_number', FILTER_SANITIZE_SPECIAL_CHARS);
    $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_SPECIAL_CHARS);
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $password = filter_input(INPUT_POST, 'password', FILTER_DEFAULT);
    
    // Si la validación de email falla o falta la contraseña, detenemos el proceso
    if (!$email || !$password) {
        // Podrías redirigir con un error en lugar de morir
        header("Location: ../register.html?error=incomplete");
        exit();
    }
    
    // 3. Hashea la contraseña para seguridad (¡CRUCIAL!)
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // 4. Conexión a la base de datos
    try {
        $pdo = getDbConnection();

        // **NOTA IMPORTANTE:** Confirma que el nombre de la tabla y las columnas sean exactos
        // Reemplaza 'users' si usaste otro nombre (ej. 'clientes')
        $sql = "INSERT INTO person (full_name, identification_number, phone_number, email, password_hash) 
                VALUES (:full_name, : identification_number, :phone_number, :email, :password_hash)";
        
        $stmt = $pdo->prepare($sql);
        
        // 5. Enlaza los parámetros (previene inyección SQL)
        $stmt->bindParam(':full_name', $fullName);
        $stmt->bindParam(':identification_number', $identification_number);
        $stmt->bindParam(':phone_number', $phone_number);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':password_hash', $hashedPassword);
        
        // 6. Ejecuta la consulta
        $stmt->execute();

        // 7. Registro exitoso - Redirige a la página de login
        header("Location: ../LogIn.html?success=true");
        exit();

    } catch (Exception $e) {
        // En un entorno de producción, nunca muestres $e->getMessage() al usuario
        die("Registration Error: " . $e->getMessage()); 
    }
} else {
    // Si alguien intenta acceder al archivo directamente sin POST
    header("Location: ../register.html");
    exit();
}
?>