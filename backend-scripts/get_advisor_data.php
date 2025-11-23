<?php
/**
 * Archivo: backend-scripts/get_advisor_data.php
 * Propósito: Obtener datos del asesor para mostrar en el panel
 * Método: GET
 * Parámetro: advisor_id
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once 'db.php';

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
    exit;
}

try {
    $pdo = getDbConnection();
    
    // Validar parámetro
    if (!isset($_GET['advisor_id']) || empty($_GET['advisor_id'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'advisor_id is required'
        ]);
        exit;
    }
    
    $advisor_id = intval($_GET['advisor_id']);
    
    // Consultar datos del asesor
    $sql = "
        SELECT e.employed_id, e.full_name, u.email, u.role
        FROM employed e
        JOIN users u ON e.user_id = u.user_id
        WHERE e.employed_id = :advisor_id
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':advisor_id' => $advisor_id]);
    $advisor = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$advisor) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Advisor not found'
        ]);
        exit;
    }
    
    // Verificar que sea advisor, manager u owner
    if (!in_array(strtolower($advisor['role']), ['advisor', 'manager', 'owner'])) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'User is not an advisor'
        ]);
        exit;
    }
    
    // Respuesta exitosa
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => [
            'employed_id' => (int)$advisor['employed_id'],
            'full_name' => $advisor['full_name'],
            'email' => $advisor['email'],
            'role' => ucfirst($advisor['role'])
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Error in get_advisor_data.php: " . $e->getMessage());
    http_response_code(500);
    
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error'
    ]);
}
?>