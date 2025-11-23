<?php
/**
 * Archivo: backend-scripts/update_vehicle.php
 * Propósito: Actualizar datos de vehículos existentes
 * Método: POST
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once 'db.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
    exit;
}

try {
    $pdo = getDbConnection();
    
    // ============================================
    // 1. VALIDAR DATOS DEL FORMULARIO
    // ============================================
    $required_fields = ['vehicle_id', 'brand', 'model', 'year', 'color', 'price', 'availability', 'advisor_id'];
    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || trim($_POST[$field]) === '') {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => "Field '$field' is required"
            ]);
            exit;
        }
    }
    
    // ============================================
    // 2. SANITIZAR Y VALIDAR DATOS
    // ============================================
    $vehicle_id = intval($_POST['vehicle_id']);
    $brand = trim($_POST['brand']);
    $model = trim($_POST['model']);
    $year = intval($_POST['year']);
    $color = trim($_POST['color']);
    $price = floatval($_POST['price']);
    $availability = strtolower(trim($_POST['availability']));
    $description = isset($_POST['description']) ? trim($_POST['description']) : null;
    $advisor_id = intval($_POST['advisor_id']);
    
    // Validar que el vehículo existe
    $check_vehicle = "SELECT vehicle_id FROM vehicle WHERE vehicle_id = :vehicle_id";
    $stmt_check_vehicle = $pdo->prepare($check_vehicle);
    $stmt_check_vehicle->execute([':vehicle_id' => $vehicle_id]);
    
    if (!$stmt_check_vehicle->fetch()) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Vehicle not found'
        ]);
        exit;
    }
    
    // Validar año
    if ($year < 1900 || $year > 2030) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid year. Must be between 1900 and 2030.'
        ]);
        exit;
    }
    
    // Validar precio
    if ($price < 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Price must be a positive number.'
        ]);
        exit;
    }
    
    // Validar disponibilidad
    $valid_availability = ['available', 'reserved', 'sold', 'unavailable'];
    if (!in_array($availability, $valid_availability)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid availability status.'
        ]);
        exit;
    }
    
    // ============================================
    // 3. VALIDAR QUE EL ADVISOR EXISTE Y TIENE PERMISOS
    // ============================================
    $check_advisor = "
        SELECT e.employed_id, e.full_name, u.role 
        FROM employed e
        JOIN users u ON e.user_id = u.user_id
        WHERE e.employed_id = :advisor_id
    ";
    $stmt_check = $pdo->prepare($check_advisor);
    $stmt_check->execute([':advisor_id' => $advisor_id]);
    $advisor = $stmt_check->fetch(PDO::FETCH_ASSOC);
    
    if (!$advisor) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Advisor not found.'
        ]);
        exit;
    }
    
    if (!in_array(strtolower($advisor['role']), ['advisor', 'manager', 'owner'])) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Insufficient permissions.'
        ]);
        exit;
    }
    
    // ============================================
    // 4. ACTUALIZAR VEHÍCULO EN LA BASE DE DATOS
    // ============================================
    $sql_update = "
        UPDATE vehicle 
        SET brand = :brand,
            model = :model,
            year = :year,
            color = :color,
            price = :price,
            availability = :availability,
            description = :description
        WHERE vehicle_id = :vehicle_id
    ";
    
    $stmt_update = $pdo->prepare($sql_update);
    $result = $stmt_update->execute([
        ':brand' => $brand,
        ':model' => $model,
        ':year' => $year,
        ':color' => $color,
        ':price' => $price,
        ':availability' => $availability,
        ':description' => $description,
        ':vehicle_id' => $vehicle_id
    ]);
    
    if (!$result) {
        throw new Exception('Failed to update vehicle');
    }
    
    // ============================================
    // 5. RESPUESTA EXITOSA
    // ============================================
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Vehicle updated successfully',
        'data' => [
            'vehicle_id' => $vehicle_id,
            'brand' => $brand,
            'model' => $model,
            'year' => $year,
            'availability' => $availability
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Error in update_vehicle.php: " . $e->getMessage());
    http_response_code(500);
    
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error: ' . $e->getMessage()
    ]);
}
?>