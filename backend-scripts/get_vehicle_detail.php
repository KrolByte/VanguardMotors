<?php
header('Content-Type: application/json');

require_once __DIR__ . '/db.php';

try {
    $vehicle_id = isset($_GET['vehicle_id']) ? intval($_GET['vehicle_id']) : 0;

    if ($vehicle_id <= 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Vehicle ID is required'
        ]);
        exit;
    }

    $conn = getDbConnection();

    // 1. Consulta Principal del Vehículo (OPTIMIZADA)
    $query = "
        SELECT 
            v.vehicle_id, v.brand, v.model, v.year, v.color, v.price, v.description, v.availability,
            STRING_AGG(vi.image_url, ',' ORDER BY vi.is_main DESC) as images
        FROM vehicle v
        LEFT JOIN vehicle_image vi ON v.vehicle_id = vi.vehicle_id
        WHERE v.vehicle_id = :vehicle_id
        GROUP BY v.vehicle_id, v.brand, v.model, v.year, v.color, v.price, v.description, v.availability
    ";

    $stmt = $conn->prepare($query);
    $stmt->execute([':vehicle_id' => $vehicle_id]);
    $vehicle = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$vehicle) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Vehicle not found'
        ]);
        exit;
    }

    // 2. Normalizar datos del vehículo principal
    $images_raw = $vehicle['images'] ? explode(',', $vehicle['images']) : [];
    $images = array_map(function($img) {
        $img = trim($img);
        if (filter_var($img, FILTER_VALIDATE_URL)) {
            return $img;
        }
        // Asegurar la ruta local 'img/'
        $img = ltrim($img, '/');
        if (strpos($img, 'img/') !== 0) {
            return 'img/' . $img;
        }
        return $img;
    }, $images_raw);

    $primary_image = count($images) > 0 ? $images[0] : 'img/car-rent-6.png';
    $availability_text = (isset($vehicle['availability']) && strtolower($vehicle['availability']) === 'available') ? 'Available' : ucfirst($vehicle['availability'] ?? 'Not Available');
    
    // Preparar los parámetros para vehículos relacionados
    $basePrice = floatval($vehicle['price'] ?? 0);
    $priceRange = 20000000;
    $yearRange = 2;
    $yearValue = intval($vehicle['year'] ?? 0);


    // 3. Consulta de vehículos relacionados (sin cambios significativos en la lógica, solo formato)
    $related_query = "
        SELECT 
            v.vehicle_id, v.brand, v.model, v.year, v.color, v.price, v.availability,
            STRING_AGG(vi.image_url, ',' ORDER BY vi.is_main DESC) as images,
            (CASE WHEN v.brand = :brand THEN 1 ELSE 0 END) AS brand_match,
            (CASE WHEN v.color = :color THEN 1 ELSE 0 END) AS color_match,
            ABS(v.price - :price) AS price_diff,
            ABS(v.year - :year) AS year_diff
        FROM vehicle v
        LEFT JOIN vehicle_image vi ON v.vehicle_id = vi.vehicle_id
        WHERE v.vehicle_id != :vehicle_id
          AND (
                v.brand = :brand
                OR ABS(v.price - :price) <= :price_range
                OR v.color = :color
                OR ABS(v.year - :year) <= :year_range
              )
        GROUP BY v.vehicle_id, v.brand, v.model, v.year, v.color, v.price, v.availability
        ORDER BY brand_match DESC, color_match DESC, price_diff ASC, year_diff ASC
        LIMIT 6
    ";

    $related_stmt = $conn->prepare($related_query);
    $related_stmt->execute([
        ':brand' => $vehicle['brand'],
        ':color' => $vehicle['color'],
        ':vehicle_id' => $vehicle_id,
        ':price' => $basePrice,
        ':price_range' => $priceRange,
        ':year' => $yearValue,
        ':year_range' => $yearRange
    ]);

    $related_vehicles = $related_stmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. Relleno de vehículos relacionados si es necesario
    if (count($related_vehicles) < 2) {
        // Obtener IDs ya seleccionados para excluirlos
        $existingIds = array_map(function($r) { return (int)$r['vehicle_id']; }, $related_vehicles);
        $existingIds[] = (int)$vehicle_id; // excluir el actual

        $needed = 2 - count($related_vehicles);
        
        // Uso de marcadores de posición con el operador IN
        $placeholders = str_repeat('?,', count($existingIds) - 1) . '?';

        $fallback_query = "
            SELECT 
                v.vehicle_id, v.brand, v.model, v.year, v.color, v.price, v.availability,
                STRING_AGG(vi.image_url, ',' ORDER BY vi.is_main DESC) as images
            FROM vehicle v
            LEFT JOIN vehicle_image vi ON v.vehicle_id = vi.vehicle_id
            WHERE v.vehicle_id NOT IN ($placeholders)
            GROUP BY v.vehicle_id, v.brand, v.model, v.year, v.color, v.price, v.availability
            ORDER BY ABS(v.price - ?) ASC
            LIMIT ?
        ";

        $fb_stmt = $conn->prepare($fallback_query);

        // Bind Values
        $i = 1;
        foreach ($existingIds as $id) {
            $fb_stmt->bindValue($i++, $id, PDO::PARAM_INT);
        }
        $fb_stmt->bindValue($i++, $basePrice);
        $fb_stmt->bindValue($i++, $needed, PDO::PARAM_INT);

        $fb_stmt->execute();
        $fill = $fb_stmt->fetchAll(PDO::FETCH_ASSOC);

        // Unir resultados
        $related_vehicles = array_merge($related_vehicles, $fill);
    }

    // 5. Normalizar imágenes de vehículos relacionados y devolver la respuesta
    $related_data = array_map(function($v) {
        $images_raw = $v['images'] ? explode(',', $v['images']) : [];
        $images = array_map(function($img) {
            $img = trim($img);
            if (filter_var($img, FILTER_VALIDATE_URL)) {
                return $img;
            }
            $img = ltrim($img, '/');
            if (strpos($img, 'img/') !== 0) {
                return 'img/' . $img;
            }
            return $img;
        }, $images_raw);

        $v['primary_image'] = count($images) > 0 ? $images[0] : 'img/car-rent-6.png';
        $v['availability'] = (isset($v['availability']) && strtolower($v['availability']) === 'available') ? 'Available' : ucfirst($v['availability'] ?? 'Not Available');
        // Quitar la lista completa de imágenes para evitar cargar mucho el JSON de relacionados
        unset($v['images']); 
        return $v;
    }, $related_vehicles);

    echo json_encode([
        'success' => true,
        'data' => [
            'vehicle_id' => $vehicle['vehicle_id'],
            'brand' => $vehicle['brand'],
            'model' => $vehicle['model'],
            'year' => $vehicle['year'],
            'color' => $vehicle['color'],
            'price' => floatval($vehicle['price']),
            'description' => $vehicle['description'],
            'availability' => $availability_text,
            'primary_image' => $primary_image,
            'images' => $images // Lista completa y ordenada para el carrusel JS
        ],
        'related_vehicles' => $related_data
    ]);

} catch (Exception $e) {
    http_response_code(500);
    error_log("DB Error: " . $e->getMessage()); // Loguear el error en el servidor
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error. Please try again later.'
    ]);
}
?>