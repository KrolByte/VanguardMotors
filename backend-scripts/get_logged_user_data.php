<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/db.php'; 

// ⚠️ SIMULACIÓN: ID de prueba para testing
$test_person_id = 1;

try {
    // 1. Verificar si hay sesión activa
    if (!isset($_SESSION['person_id'])) {
        // ⚠️ MODO PRUEBA: usar ID de test
        $person_id = $test_person_id;
    } else {
        $person_id = $_SESSION['person_id'];
    }

    $conn = getDbConnection();

    // 2. Obtener datos del cliente (según tu estructura REAL de BD)
    $query = "
        SELECT 
            p.person_id, 
            p.full_name, 
            p.identification_number AS identification,
            u.email, 
            p.phone_number AS phone
        FROM person p
        JOIN users u ON p.user_id = u.user_id
        WHERE p.person_id = :person_id
    ";

    $stmt = $conn->prepare($query);
    $stmt->execute([':person_id' => $person_id]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user_data) {
        echo json_encode([
            'success' => true,
            'is_logged_in' => true,
            'data' => [
                'person_id' => $user_data['person_id'],
                'full_name' => $user_data['full_name'],
                'identification' => $user_data['identification'],
                'email' => $user_data['email'],
                'phone' => $user_data['phone']
            ]
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'is_logged_in' => false, 
            'message' => 'Usuario no encontrado en la base de datos'
        ]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'is_logged_in' => false,
        'message' => 'Error del servidor: ' . $e->getMessage()
    ]);
}
?>