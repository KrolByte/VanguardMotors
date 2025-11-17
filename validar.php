<?php
require 'conexion.php';

$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';

if (empty($email) || empty($password)) {
   header("Location: Login.php?error=empty");
   exit;
}

$sql = "SELECT * FROM users WHERE email = :email LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->execute([':email' => $email]);
$user = $stmt->fetch();

// Comparación directa porque NO tienes hashes reales
if ($user && $password === $user['password_hash']) {

    session_start();
    $_SESSION['email'] = $user['email'];
    $_SESSION['name']  = $user['email'];  // pon aquí otro campo si quieres

    header("Location: index_users.php");
    exit;

} else {
    header("Location: Login.php?error=invalid");
    exit;
}
?>
