<?php
// Archivo: backend-scripts/register_handler.php

// Incluye la función de conexión a la BD (db.php)
// Nota: La ruta es relativa al db.php, que está en el mismo directorio.
require_once 'conexion.php';

// 1. Verifica que los datos del formulario hayan sido enviados por POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
     header('Location: ../register.html?error=invalid_method');
    exit();
}
    // 2. Recibe los datos del formulario y los limpia
    $fullName = filter_input(INPUT_POST, 'fullName', FILTER_SANITIZE_SPECIAL_CHARS);
    $idNumber = filter_input(INPUT_POST, 'idNumber', FILTER_SANITIZE_SPECIAL_CHARS);
    $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_SPECIAL_CHARS);
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $password = filter_input(INPUT_POST, 'password', FILTER_DEFAULT);

    // Validar que el email sea válido
if ($email === false) {
    header("Location: ../register.html?error=invalid_email");
    exit();
}
    
    // Si la validación de email falla o falta la contraseña, detenemos el proceso
    if  (empty($email) || empty($password) || empty($idNumber))  {
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
                VALUES (:full_name, :identification_number, :phone_number, :email, :password_hash)";
        
        $stmt = $pdo->prepare($sql);
        
        // 5. Enlaza los parámetros (previene inyección SQL)
        $stmt->bindParam(':full_name', $fullName);
        $stmt->bindParam(':identification_number', $idNumber);
        $stmt->bindParam(':phone_number', $phone);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':password_hash', $hashedPassword);
        
        // 6. Ejecuta la consulta
        $stmt->execute();

        // 7. Registro exitoso - Redirige a la página de login
        header("Location: ../LogIn.html?success=registered");
        exit();

    } catch (Exception $e) {
        // En un entorno de producción, nunca muestres $e->getMessage() al usuario
        die("Registration Error: " . $e->getMessage()); 
    }
?>