<?php
/**
 * Archivo: backend-scripts/get_vehicle_images.php
 * Propósito: Obtener todas las imágenes de un vehículo específico
 * Método: GET
 * Parámetro: vehicle_id
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once 'conexion.php';

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
    if (!isset($_GET['vehicle_id']) || empty($_GET['vehicle_id'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'vehicle_id is required'
        ]);
        exit;
    }
    
    $vehicle_id = intval($_GET['vehicle_id']);
    
    // Verificar que el vehículo existe
    $check_vehicle = "SELECT vehicle_id FROM vehicle WHERE vehicle_id = :vehicle_id";
    $stmt_check = $pdo->prepare($check_vehicle);
    $stmt_check->execute([':vehicle_id' => $vehicle_id]);
    
    if (!$stmt_check->fetch()) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Vehicle not found'
        ]);
        exit;
    }
    
    // Obtener imágenes del vehículo
    $sql = "SELECT image_id, vehicle_id, image_url, is_main
            FROM vehicle_image
            WHERE vehicle_id = :vehicle_id
            ORDER BY is_main DESC, image_id ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':vehicle_id' => $vehicle_id]);
    $images = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formatear respuesta
    $formatted_images = array_map(function($img) {
        return [
            'image_id' => (int)$img['image_id'],
            'vehicle_id' => (int)$img['vehicle_id'],
            'image_url' => $img['image_url'],
            'is_main' => (int)$img['is_main']
        ];
    }, $images);
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => $formatted_images,
        'count' => count($formatted_images)
    ]);
    
} catch (Exception $e) {
    error_log("Error in get_vehicle_images.php: " . $e->getMessage());
    http_response_code(500);
    
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error'
    ]);
}
?>