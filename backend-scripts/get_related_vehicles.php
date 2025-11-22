<?php
// Archivo: backend-scripts/get_related_vehicles.php
// Propósito: Obtener vehículos similares basados en marca, año y precio

require_once 'db.php';

header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] == "GET") {
    try {
        $vehicle_id = filter_input(INPUT_GET, 'vehicle_id', FILTER_VALIDATE_INT);

        if (!$vehicle_id) {
            http_response_code(400);
            die(json_encode([
                'success' => false,
                'message' => 'Vehicle ID is required'
            ]));
        }

        $pdo = getDbConnection();

        // ==========================================================
        // 1. OBTENER DATOS DEL VEHÍCULO BASE
        // ==========================================================
        $sql_base = "SELECT brand, model, year, price FROM vehicle WHERE vehicle_id = ?";
        $stmt_base = $pdo->prepare($sql_base);
        $stmt_base->execute([$vehicle_id]);
        $base_vehicle = $stmt_base->fetch(PDO::FETCH_ASSOC);

        if (!$base_vehicle) {
            http_response_code(404);
            die(json_encode([
                'success' => false,
                'message' => 'Vehicle not found'
            ]));
        }

        // Calcular rango de precios (±20 millones)
        $min_price = $base_vehicle['price'] - 20000000;
        $max_price = $base_vehicle['price'] + 20000000;

        // ==========================================================
        // 2. BUSCAR VEHÍCULOS SIMILARES
        // ==========================================================
        $sql_related = "
            SELECT DISTINCT v.vehicle_id, v.brand, v.model, v.year, v.color, v.price, v.availability,
                   (CASE 
                       WHEN v.brand = ? THEN 3
                       ELSE 0
                   END +
                   CASE 
                       WHEN v.year = ? THEN 2
                       ELSE 0
                   END +
                   CASE 
                       WHEN v.price BETWEEN ? AND ? THEN 1
                       ELSE 0
                   END) AS relevance_score
            FROM vehicle v
            WHERE v.vehicle_id != ?
              AND v.availability = 'available'
              AND (
                  v.brand = ?
                  OR v.year = ?
                  OR v.price BETWEEN ? AND ?
              )
            ORDER BY relevance_score DESC, v.price ASC
            LIMIT 6
        ";

        $stmt_related = $pdo->prepare($sql_related);
        $stmt_related->execute([
            $base_vehicle['brand'],
            $base_vehicle['year'],
            $min_price,
            $max_price,
            $vehicle_id,
            $base_vehicle['brand'],
            $base_vehicle['year'],
            $min_price,
            $max_price
        ]);

        $related_vehicles = $stmt_related->fetchAll(PDO::FETCH_ASSOC);

        // ==========================================================
        // 3. OBTENER IMÁGENES DE CADA VEHÍCULO
        // ==========================================================
        $vehicles_with_images = [];

        foreach ($related_vehicles as $vehicle) {
            // Obtener imagen principal
            $sql_image = "SELECT image_url FROM vehicle_image 
                         WHERE vehicle_id = ? AND is_main = TRUE 
                         LIMIT 1";
            $stmt_image = $pdo->prepare($sql_image);
            $stmt_image->execute([$vehicle['vehicle_id']]);
            $image_row = $stmt_image->fetch(PDO::FETCH_ASSOC);
            $main_image = $image_row ? $image_row['image_url'] : null;

            // Si no hay imagen principal, obtener cualquiera
            if (!$main_image) {
                $sql_any_image = "SELECT image_url FROM vehicle_image WHERE vehicle_id = ? ORDER BY image_id LIMIT 1";
                $stmt_any = $pdo->prepare($sql_any_image);
                $stmt_any->execute([$vehicle['vehicle_id']]);
                $any_image_row = $stmt_any->fetch(PDO::FETCH_ASSOC);
                $main_image = $any_image_row ? $any_image_row['image_url'] : null;
            }

            // ✅ LIMPIAR RUTA DE IMAGEN (quitar / inicial)
            $image_url = 'img/car-placeholder.png'; // Default
            if ($main_image) {
                // Quitar el / inicial si existe
                $cleaned_image = ltrim($main_image, '/');
                $image_url = $cleaned_image;
            }

            error_log("=== VEHICLE ID: {$vehicle['vehicle_id']} ===");
                error_log("Brand/Model: {$vehicle['brand']} {$vehicle['model']}");
                error_log("Raw image from DB: " . ($main_image ?? 'NULL'));
                
                $image_url = 'img/car-placeholder.png';
                if ($main_image) {
                    $cleaned_image = ltrim($main_image, '/');
                    $image_url = $cleaned_image;
                    error_log("Cleaned image: {$cleaned_image}");
                } else {
                    error_log("No image found, using placeholder");
                }
                error_log("Final image URL: {$image_url}");
                error_log("==============================");
    
            $vehicles_with_images[] = [
                'vehicle_id' => $vehicle['vehicle_id'],
                'brand' => $vehicle['brand'],
                'model' => $vehicle['model'],
                'year' => $vehicle['year'],
                'color' => $vehicle['color'],
                'price' => $vehicle['price'],
                'availability' => $vehicle['availability'],
                'image' => $image_url,
                'relevance_score' => $vehicle['relevance_score']
            ];
        }

        // ==========================================================
        // 4. RESPUESTA
        // ==========================================================
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'base_vehicle' => [
                'brand' => $base_vehicle['brand'],
                'model' => $base_vehicle['model'],
                'year' => $base_vehicle['year'],
                'price' => $base_vehicle['price']
            ],
            'related_vehicles' => $vehicles_with_images,
            'count' => count($vehicles_with_images)
        ]);

    } catch (PDOException $e) {
        error_log("DB Error in get_related_vehicles: " . $e->getMessage());
        http_response_code(500);
        
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ]);
        
    } catch (Exception $e) {
        error_log("Error in get_related_vehicles: " . $e->getMessage());
        http_response_code(500);
        
        echo json_encode([
            'success' => false,
            'message' => 'Error retrieving related vehicles'
        ]);
    }
} else {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
}
?>