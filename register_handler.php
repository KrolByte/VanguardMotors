<?php
// Archivo: backend-scripts/register_handler.php

require_once './conexion.php';

// Habilitar errores para depuración (Quitar en producción)
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

// 1. Verifica que los datos del formulario hayan sido enviados por POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    // Si acceden directamente (GET), redirige a la página de registro.
   header('Location: /VanguardMotors/register.html?error=invalid_method');
    exit();
}
    
// 2. Recibe los datos del formulario y los limpia
// --------------------------------------------------------------------------------------------------
// **IMPORTANTE:** Aquí definimos las variables que el bloque 'try' necesita.
// Usamos FILTER_SANITIZE_STRING o FILTER_DEFAULT para evitar que el filtro falle y devuelva NULL.
// --------------------------------------------------------------------------------------------------

$fullName = filter_input(INPUT_POST, 'fullName', FILTER_SANITIZE_SPECIAL_CHARS);
$idNumber = filter_input(INPUT_POST, 'idNumber', FILTER_SANITIZE_SPECIAL_CHARS);
$phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_SPECIAL_CHARS);
// Usamos SANITIZE para limpiar el email, asegurando que el valor no sea NULL
$email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL); 
$password = filter_input(INPUT_POST, 'password', FILTER_DEFAULT);

// Si la validación falla o falta algún campo esencial, detenemos el proceso
if (empty($email) || empty($password) || empty($idNumber) || empty($fullName)) {
    header("Location: ../register.html?error=incomplete");
    exit();
}
    
// 3. Hashea la contraseña para seguridad (¡CRUCIAL!)
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);
$defaultRole = 'person'; // Rol por defecto

// 4. Inserción DOBLE en una Transacción
try {
    global $conn; // Declaramos la conexión como global

    // INICIAR TRANSACCIÓN
    $conn->beginTransaction();

    // =================================================================
    // PASO 1: INSERTAR EN LA TABLA DE AUTENTICACIÓN (USERS)
    // =================================================================
    // Asumimos que la clave primaria de 'users' es 'user_id'
    $sql_users = "INSERT INTO users (email, password_hash, role) 
                  VALUES (:email, :password_hash, :role) 
                  RETURNING user_id"; 
    
    $stmt_users = $conn->prepare($sql_users);
    $stmt_users->bindParam(':email', $email);
    $stmt_users->bindParam(':password_hash', $hashedPassword);
    $stmt_users->bindParam(':role', $defaultRole);
    $stmt_users->execute();

    // Obtener el ID del nuevo usuario insertado (user_id)
    $new_user_id = $stmt_users->fetchColumn(); 

    // =================================================================
    // PASO 2: INSERTAR EN LA TABLA DE DATOS PERSONALES (PERSON)
    // =================================================================
    // Usamos el $new_user_id para la columna 'user_id'
    $sql_person = "INSERT INTO person (full_name, identification_number, phone_number, user_id) 
                   VALUES (:full_name, :identification_number, :phone_number, :user_id)";
    
    $stmt_person = $conn->prepare($sql_person);
    
    // Enlazamos las variables que definimos arriba
    $stmt_person->bindParam(':full_name', $fullName); 
    $stmt_person->bindParam(':identification_number', $idNumber);
    $stmt_person->bindParam(':phone_number', $phone);
    $stmt_person->bindParam(':user_id', $new_user_id, PDO::PARAM_INT); // Enlazamos el ID

    $stmt_person->execute();

    // FINALIZAR TRANSACCIÓN
    $conn->commit();

    // 7. Registro exitoso - Redirige
    header("Location: /VanguardMotors/LogIn.php?success=registered");
    exit();

} catch (\PDOException $e) {
    // Si algo falla, deshace todos los cambios de la transacción
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    // Muestra el error para depurar
    die("Registration Error: " . $e->getMessage()); 
}
?>