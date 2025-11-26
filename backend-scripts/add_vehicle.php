<?php
/**
 * Archivo: backend-scripts/add_vehicle.php
 * Propósito: Agregar nuevos vehículos al catálogo con imágenes
 * Método: POST
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once 'conexion.php';

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
    $required_fields = ['brand', 'model', 'year', 'color', 'price', 'availability', 'advisor_id'];
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
    $brand = trim($_POST['brand']);
    $model = trim($_POST['model']);
    $year = intval($_POST['year']);
    $color = trim($_POST['color']);
    $price = floatval($_POST['price']);
    $availability = strtolower(trim($_POST['availability']));
    $description = isset($_POST['description']) ? trim($_POST['description']) : null;
    $advisor_id = intval($_POST['advisor_id']);
    
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
    // 3. VALIDAR QUE EL ADVISOR EXISTE Y ES ADVISOR
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
            'message' => 'Insufficient permissions. Only advisors, managers, and owners can add vehicles.'
        ]);
        exit;
    }
    
    // ============================================
    // 4. VALIDAR IMÁGENES
    // ============================================
    if (!isset($_FILES['images']) || empty($_FILES['images']['name'][0])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'At least one image is required.'
        ]);
        exit;
    }
    
    $images = $_FILES['images'];
    $is_main_flags = isset($_POST['is_main']) ? $_POST['is_main'] : [];
    
    // Validar número de imágenes
    if (count($images['name']) > 5) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Maximum 5 images allowed.'
        ]);
        exit;
    }
    
    // ============================================
    // 5. PROCESAR Y SUBIR IMÁGENES
    // ============================================
    $upload_dir = '../img/vehicles/';
    
    // Crear directorio si no existe
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $uploaded_images = [];
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    for ($i = 0; $i < count($images['name']); $i++) {
        // Verificar errores de subida
        if ($images['error'][$i] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Error uploading image: ' . $images['name'][$i]
            ]);
            exit;
        }
        
        // Validar tipo de archivo
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $images['tmp_name'][$i]);
        finfo_close($finfo);
        
        if (!in_array($mime_type, $allowed_types)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid file type. Only JPEG, PNG, GIF, and WebP are allowed.'
            ]);
            exit;
        }
        
        // Validar tamaño
        if ($images['size'][$i] > $max_size) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Image too large. Maximum size is 5MB.'
            ]);
            exit;
        }
        
        // Generar nombre único
        $extension = pathinfo($images['name'][$i], PATHINFO_EXTENSION);
        $filename = 'vehicle_' . uniqid() . '_' . time() . '.' . $extension;
        $filepath = $upload_dir . $filename;
        
        // Mover archivo
        if (!move_uploaded_file($images['tmp_name'][$i], $filepath)) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Failed to save image: ' . $images['name'][$i]
            ]);
            exit;
        }
        
        // Guardar información de la imagen
        $uploaded_images[] = [
            'url' => 'img/vehicles/' . $filename,
            'is_main' => isset($is_main_flags[$i]) && $is_main_flags[$i] == '1'
        ];
    }
    
    // ============================================
    // 6. INSERTAR VEHÍCULO EN LA BASE DE DATOS
    // ============================================
    $pdo->beginTransaction();
    
    try {
        // Insertar vehículo
        $sql_insert = "INSERT INTO vehicle (brand, model, year, color, price, availability, description) 
                      VALUES (:brand, :model, :year, :color, :price, :availability, :description)
                      RETURNING vehicle_id";
        
        $stmt_insert = $pdo->prepare($sql_insert);
        $stmt_insert->execute([
            ':brand' => $brand,
            ':model' => $model,
            ':year' => $year,
            ':color' => $color,
            ':price' => $price,
            ':availability' => $availability,
            ':description' => $description
        ]);
        
        $vehicle_id = $stmt_insert->fetchColumn();
        
        if (!$vehicle_id) {
            throw new Exception('Failed to insert vehicle');
        }
        
        // Insertar imágenes
        $sql_image = "INSERT INTO vehicle_image (vehicle_id, image_url, is_main) 
                     VALUES (:vehicle_id, :image_url, :is_main)";
        $stmt_image = $pdo->prepare($sql_image);
        
        foreach ($uploaded_images as $img) {
            $stmt_image->execute([
                ':vehicle_id' => $vehicle_id,
                ':image_url' => $img['url'],
                ':is_main' => $img['is_main'] ? 1 : 0
            ]);
        }
        
        $pdo->commit();
        
        // ============================================
        // 7. RESPUESTA EXITOSA
        // ============================================
        http_response_code(201);
        echo json_encode([
            'success' => true,
            'message' => 'Vehicle added successfully',
            'data' => [
                'vehicle_id' => $vehicle_id,
                'brand' => $brand,
                'model' => $model,
                'images_count' => count($uploaded_images)
            ]
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        
        // Eliminar imágenes subidas si falla la inserción en BD
        foreach ($uploaded_images as $img) {
            $file_to_delete = '../' . $img['url'];
            if (file_exists($file_to_delete)) {
                unlink($file_to_delete);
            }
        }
        
        throw $e;
    }
    
} catch (Exception $e) {
    error_log("Error in add_vehicle.php: " . $e->getMessage());
    http_response_code(500);
    
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error: ' . $e->getMessage()
    ]);
}
?>