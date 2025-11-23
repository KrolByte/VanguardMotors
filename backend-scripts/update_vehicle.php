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
    $pdo->beginTransaction();
    try {
        // 4.1 Actualizar datos del vehículo
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
        // 5. GESTIONAR IMÁGENES
        // ============================================
        // 5.1 Eliminar imágenes marcadas
        $deleted_images = isset($_POST['deleted_images']) ? $_POST['deleted_images'] : '';
        if (!empty($deleted_images)) {
            $image_ids = explode(',', $deleted_images);
            foreach ($image_ids as $img_id) {
                $img_id = intval($img_id);
                // Obtener URL de la imagen para eliminar archivo
                $sql_get_url = "SELECT image_url FROM vehicle_image WHERE image_id = :image_id AND vehicle_id = :vehicle_id";
                $stmt_get = $pdo->prepare($sql_get_url);
                $stmt_get->execute([':image_id' => $img_id, ':vehicle_id' => $vehicle_id]);
                $img_data = $stmt_get->fetch(PDO::FETCH_ASSOC);
                if ($img_data) {
                    // Eliminar archivo físico
                    $file_path = '../' . $img_data['image_url'];
                    if (file_exists($file_path) && strpos($img_data['image_url'], 'img/vehicles/') !== false) {
                        unlink($file_path);
                    }
                    // Eliminar de BD
                    $sql_delete = "DELETE FROM vehicle_image WHERE image_id = :image_id AND vehicle_id = :vehicle_id";
                    $stmt_delete = $pdo->prepare($sql_delete);
                    $stmt_delete->execute([':image_id' => $img_id, ':vehicle_id' => $vehicle_id]);
                }
            }
        }
        // 5.2 Cambiar imagen principal
        $main_image_id = isset($_POST['main_image_id']) ? intval($_POST['main_image_id']) : 0;
        if ($main_image_id > 0) {
            // Quitar is_main de todas las imágenes
            $sql_unset_main = "UPDATE vehicle_image SET is_main = 0 WHERE vehicle_id = :vehicle_id";
            $stmt_unset = $pdo->prepare($sql_unset_main);
            $stmt_unset->execute([':vehicle_id' => $vehicle_id]);   
            // Establecer nueva imagen principal
            $sql_set_main = "UPDATE vehicle_image SET is_main = 1 WHERE image_id = :image_id AND vehicle_id = :vehicle_id";
            $stmt_set = $pdo->prepare($sql_set_main);
            $stmt_set->execute([':image_id' => $main_image_id, ':vehicle_id' => $vehicle_id]);
        }
        // 5.3 Agregar nuevas imágenes
        $uploaded_new_images = [];
        if (isset($_FILES['new_images']) && !empty($_FILES['new_images']['name'][0])) {
            $new_images = $_FILES['new_images'];
            $upload_dir = '../img/vehicles/';   
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
            $max_size = 5 * 1024 * 1024; // 5MB
            // Verificar total de imágenes
            $sql_count = "SELECT COUNT(*) FROM vehicle_image WHERE vehicle_id = :vehicle_id";
            $stmt_count = $pdo->prepare($sql_count);
            $stmt_count->execute([':vehicle_id' => $vehicle_id]);
            $current_count = $stmt_count->fetchColumn();
            if ($current_count + count($new_images['name']) > 5) {
                throw new Exception('Maximum 5 images per vehicle');
            }
            for ($i = 0; $i < count($new_images['name']); $i++) {
                if ($new_images['error'][$i] !== UPLOAD_ERR_OK) {
                    continue;
                }   
                // Validar tipo
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime_type = finfo_file($finfo, $new_images['tmp_name'][$i]);
                finfo_close($finfo);
                if (!in_array($mime_type, $allowed_types)) {
                    throw new Exception('Invalid file type');
                }
                // Validar tamaño
                if ($new_images['size'][$i] > $max_size) {
                    throw new Exception('Image too large');
                }
                // Subir archivo
                $extension = pathinfo($new_images['name'][$i], PATHINFO_EXTENSION);
                $filename = 'vehicle_' . $vehicle_id . '_' . uniqid() . '_' . time() . '.' . $extension;
                $filepath = $upload_dir . $filename;
                if (!move_uploaded_file($new_images['tmp_name'][$i], $filepath)) {
                    throw new Exception('Failed to save image');
                }
                $uploaded_new_images[] = 'img/vehicles/' . $filename;
            }
            // Insertar nuevas imágenes en BD
            if (!empty($uploaded_new_images)) {
                $sql_insert_img = "INSERT INTO vehicle_image (vehicle_id, image_url, is_main) VALUES (:vehicle_id, :image_url, 0)";
                $stmt_insert_img = $pdo->prepare($sql_insert_img);   
                foreach ($uploaded_new_images as $img_url) {
                    $stmt_insert_img->execute([
                        ':vehicle_id' => $vehicle_id,
                        ':image_url' => $img_url
                    ]);
                }
            }
        }
        $pdo->commit();
        // ============================================
        // 6. RESPUESTA EXITOSA
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
                'availability' => $availability,
                'images_added' => count($uploaded_new_images),
                'images_deleted' => !empty($deleted_images) ? count(explode(',', $deleted_images)) : 0
            ]
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        // Eliminar imágenes subidas si falla
        if (!empty($uploaded_new_images)) {
            foreach ($uploaded_new_images as $img_url) {
                $file_to_delete = '../' . $img_url;
                if (file_exists($file_to_delete)) {
                    unlink($file_to_delete);
                }
            }
        }
        throw $e;
    }
} catch (Exception $e) {
    error_log("Error in update_vehicle.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error: ' . $e->getMessage()
    ]);
}
?>