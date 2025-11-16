<?php
header('Content-Type: application/json');

// db.php is in the same folder as this script
require_once __DIR__ . '/db.php';

try {
    // Obtener el ID del vehículo desde los parámetros GET
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

    // Consulta principal del vehículo
    $query = "
        SELECT 
            v.vehicle_id,
            v.brand,
            v.model,
            v.year,
            v.color,
            v.price,
            v.description,
            v.availability,
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

    // Normalizar imágenes
    $images_raw = $vehicle['images'] ? explode(',', $vehicle['images']) : [];
    $images = array_map(function($img) {
        $img = trim($img);
        if (filter_var($img, FILTER_VALIDATE_URL)) {
            return $img;
        }
        // Si no es URL absoluta, asegurar que comience con img/
        $img = ltrim($img, '/');
        if (strpos($img, 'img/') !== 0) {
            $img = 'img/' . $img;
        }
        return $img;
    }, $images_raw);

    // Establecer imagen principal
    $primary_image = count($images) > 0 ? $images[0] : 'img/car-rent-6.png';

    // Mapear disponibilidad
    $availability_text = (isset($vehicle['availability']) && $vehicle['availability'] === 'available') ? 'Available' : ucfirst($vehicle['availability'] ?? 'Not Available');

    // Consulta de vehículos relacionados: ampliada para asegurar siempre resultados
    // Calculamos un rango de precio fijo: +/- 20.000.000 alrededor del precio del vehículo
    $basePrice = isset($vehicle['price']) ? floatval($vehicle['price']) : 0;
    $priceRange = 20000000; // +/- 20,000,000
    $yearRange = 2; // años de diferencia aceptable

    // Primer intento: buscar por marca, precio cercano, color o año cercano
    // Limitar a máximo 6 resultados
    $related_query = "
        SELECT 
            v.vehicle_id,
            v.brand,
            v.model,
            v.year,
            v.color,
            v.price,
            v.availability,
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
        ':year' => intval($vehicle['year']),
        ':year_range' => $yearRange
    ]);

    $related_vehicles = $related_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Si el primer intento devuelve menos de 2, rellenar con vehículos por cercanía de precio
    if (count($related_vehicles) < 2) {
        // Obtener IDs ya seleccionados para excluirlos
        $existingIds = array_map(function($r) { return (int)$r['vehicle_id']; }, $related_vehicles);
        $existingIds[] = (int)$vehicle_id; // excluir el actual también

        // Preparar placeholders con nombres para excluir (evitar mezclar positional y named params)
        $excludePlaceholders = [];
        foreach ($existingIds as $k => $id) {
            $excludePlaceholders[] = ':ex' . $k;
        }
        $placeholders = implode(',', $excludePlaceholders);

        $needed = 2 - count($related_vehicles);

        $fallback_query = "
            SELECT 
                v.vehicle_id,
                v.brand,
                v.model,
                v.year,
                v.color,
                v.price,
                v.availability,
                STRING_AGG(vi.image_url, ',' ORDER BY vi.is_main DESC) as images
            FROM vehicle v
            LEFT JOIN vehicle_image vi ON v.vehicle_id = vi.vehicle_id
            WHERE v.vehicle_id NOT IN ($placeholders)
            GROUP BY v.vehicle_id, v.brand, v.model, v.year, v.color, v.price, v.availability
            ORDER BY ABS(v.price - :price) ASC
            LIMIT :limit
        ";

        $fb_stmt = $conn->prepare($fallback_query);

        // Bind excluded ids by name
        foreach ($existingIds as $k => $id) {
            $fb_stmt->bindValue(':ex' . $k, $id, PDO::PARAM_INT);
        }
        $fb_stmt->bindValue(':price', $basePrice);
        $fb_stmt->bindValue(':limit', $needed, PDO::PARAM_INT);

        $fb_stmt->execute();
        $fill = $fb_stmt->fetchAll(PDO::FETCH_ASSOC);

        // Unir resultados
        foreach ($fill as $f) {
            $related_vehicles[] = $f;
        }
    }

    // Normalizar imágenes de vehículos relacionados
    $related_data = array_map(function($v) {
        $images_raw = $v['images'] ? explode(',', $v['images']) : [];
        $images = array_map(function($img) {
            $img = trim($img);
            if (filter_var($img, FILTER_VALIDATE_URL)) {
                return $img;
            }
            $img = ltrim($img, '/');
            if (strpos($img, 'img/') !== 0) {
                $img = 'img/' . $img;
            }
            return $img;
        }, $images_raw);

        $v['primary_image'] = count($images) > 0 ? $images[0] : 'img/car-rent-6.png';
        $v['availability'] = (isset($v['availability']) && $v['availability'] === 'available') ? 'Available' : ucfirst($v['availability'] ?? 'Not Available');
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
            'images' => $images
        ],
        'related_vehicles' => $related_data
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error retrieving vehicle details: ' . $e->getMessage()
    ]);
}
?>
