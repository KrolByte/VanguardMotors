<?php

require_once 'conexion.php';


if ($_SERVER["REQUEST_METHOD"] !== "POST") {
   
   header('Location: ../register.html?error=invalid_method');
    exit();
}
    
$fullName = filter_input(INPUT_POST, 'fullName', FILTER_SANITIZE_SPECIAL_CHARS);
$idNumber = filter_input(INPUT_POST, 'idNumber', FILTER_SANITIZE_SPECIAL_CHARS);
$phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_SPECIAL_CHARS);
$email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL); 
$password = filter_input(INPUT_POST, 'password', FILTER_DEFAULT);

if (empty($email) || empty($password) || empty($idNumber) || empty($fullName)) {
    header("Location: ../register.html?error=incomplete");
    exit();
}
    
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);
$defaultRole = 'person';

try {
    global $conexion; 
    // CÓDIGO DENTRO DE register_handler.php

$conexion = getDbConnection();

// --- ¡ESTA ES LA LÍNEA DE SEGURIDAD! ---
if (!$conexion instanceof PDO) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error de conexión interno. El servidor de la base de datos no respondió.']);
    exit;
}
// ----------------------------------------

// Línea 29: Ahora sabemos que $conexion es un objeto PDO válido

// ... el resto del código ...

    $conexion->beginTransaction();

    $sql_users = "INSERT INTO users (email, password_hash, role) 
                  VALUES (:email, :password_hash, :role) 
                  RETURNING user_id"; 
    
    $stmt_users = $conexion->prepare($sql_users);
    $stmt_users->bindParam(':email', $email);
    $stmt_users->bindParam(':password_hash', $hashedPassword);
    $stmt_users->bindParam(':role', $defaultRole);
    $stmt_users->execute();

    
    $new_user_id = $stmt_users->fetchColumn(); 

    $sql_person = "INSERT INTO person (full_name, identification_number, phone_number, user_id) 
                   VALUES (:full_name, :identification_number, :phone_number, :user_id)";
    
    $stmt_person = $conexion->prepare($sql_person);
    
    
    $stmt_person->bindParam(':full_name', $fullName); 
    $stmt_person->bindParam(':identification_number', $idNumber);
    $stmt_person->bindParam(':phone_number', $phone);
    $stmt_person->bindParam(':user_id', $new_user_id, PDO::PARAM_INT); // Enlazamos el ID

    $stmt_person->execute();

    $conexion->commit();

   
    header("Location: LogIn.php?success=registered");
    exit();

} catch (\PDOException $e) {
    
    if (isset($conexion) && $conexion->inTransaction()) {
        $conexion->rollBack();
    }
  
    die("Registration Error: " . $e->getMessage()); 
}
?>