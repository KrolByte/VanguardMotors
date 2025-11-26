<?php
require 'conexion.php';
$conexion = getDbConnection();

$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';

if (empty($email) || empty($password)) {
   header("Location: Login.php?error=empty");
   exit;
}

$sql = "SELECT * FROM users WHERE email = :email LIMIT 1";
$stmt = $conexion->prepare($sql);
$stmt->execute([':email' => $email]);
$user = $stmt->fetch();

// Comparación directa porque NO usas hashes reales
if ($user && $password === $user['password_hash']) {

    session_start();
    $_SESSION['email'] = $user['email'];
    $_SESSION['role']  = $user['role'];  // 👈 guardamos el rol

    // 🚀 REDIRECCIÓN SEGÚN ROL
    switch ($user['role']) {

        case 'advisor':
            header("Location: admin-dashboard.html");
            break;

        case 'manager':
            header("Location: approve_reservations.php");
            break;

        case 'person':
            header("Location: index_users.php");
            break;

        default:
            header("Location: Login.php?error=invalid");
            break;
    }

    exit;

} else {
    header("Location: Login.php?error=invalid");
    exit;
}
?>
