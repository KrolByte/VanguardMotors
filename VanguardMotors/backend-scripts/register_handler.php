<?php
// Incluye la función de conexión a la BD
require 'db.php'; 

// 1. Verifica que los datos del formulario hayan sido enviados por POST
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // 2. Recibe los datos del formulario y los limpia
    $fullName = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_SPECIAL_CHARS);
    $idNumber = filter_input(INPUT_POST, 'idNumber', FILTER_SANITIZE_SPECIAL_CHARS);
    $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_SPECIAL_CHARS);
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $password = filter_input(INPUT_POST, 'password', FILTER_DEFAULT);
    
    // Si la validación de email falla o falta la contraseña, detenemos el proceso
    if (!$email || !$password) {
        die("Error: Datos incompletos o email inválido.");
    }
    
    // 3. Hashea la contraseña para seguridad (¡CRUCIAL!)
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // 4. Conexión a la base de datos
    try {
        $pdo = getDbConnection();

        // **NOTA IMPORTANTE:** Reemplaza 'users' por el nombre real de tu tabla
        // Revisa que las columnas coincidan: full_name, id_number, phone, email, password_hash
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
        header("Location: ../LogIn.html?success=true");
        exit();

    } catch (Exception $e) {
        // Muestra un error si la conexión o la inserción falla
        die("Registration Error: " . $e->getMessage());
    }
} else {
    // Si alguien intenta acceder al archivo directamente sin POST
    header("Location: ../register.html");
    exit();
}
?>