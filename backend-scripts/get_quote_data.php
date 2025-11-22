<?php
// Archivo: backend-scripts/get_quote_data.php
// Propósito: Obtener datos del vehículo y usuario para pre-llenar el formulario de cotización

session_start();
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
        // 1. OBTENER DATOS DEL VEHÍCULO
        // ==========================================================
        $sql_vehicle = "SELECT vehicle_id, brand, model, year, color, price, description, availability
                        FROM vehicle 
                        WHERE vehicle_id = ?";
        
        $stmt_vehicle = $pdo->prepare($sql_vehicle);
        $stmt_vehicle->execute([$vehicle_id]);
        $vehicle = $stmt_vehicle->fetch(PDO::FETCH_ASSOC);

        if (!$vehicle) {
            http_response_code(404);
            die(json_encode([
                'success' => false,
                'message' => 'Vehicle not found'
            ]));
        }

        // ==========================================================
        // 2. OBTENER IMAGEN PRINCIPAL DEL VEHÍCULO
        // ==========================================================
        $sql_image = "SELECT image_url 
                      FROM vehicle_image 
                      WHERE vehicle_id = ? AND is_main = TRUE 
                      LIMIT 1";
        
        $stmt_image = $pdo->prepare($sql_image);
        $stmt_image->execute([$vehicle_id]);
        $main_image_row = $stmt_image->fetch(PDO::FETCH_ASSOC);
        $main_image = $main_image_row ? $main_image_row['image_url'] : null;
        
        // Si no hay imagen principal, obtener la primera disponible
        if (!$main_image) {
            $sql_any_image = "SELECT image_url FROM vehicle_image WHERE vehicle_id = ? ORDER BY image_id LIMIT 1";
            $stmt_any = $pdo->prepare($sql_any_image);
            $stmt_any->execute([$vehicle_id]);
            $any_image_row = $stmt_any->fetch(PDO::FETCH_ASSOC);
            $main_image = $any_image_row ? $any_image_row['image_url'] : null;
        }

        // ✅ NORMALIZAR RUTA DE IMAGEN (maneja TODOS los formatos)
        $image_url = normalizeImageUrl($main_image);

        // ==========================================================
        // 3. OBTENER DATOS DEL USUARIO
        // ==========================================================
        $person = null;
        $test_person_id = 1;
        
        $person_id = $_SESSION['person_id'] ?? $test_person_id;
        
        if ($person_id) {
            $sql_person = "SELECT p.person_id, p.identification_number, p.full_name, 
                                  p.phone_number, u.email
                           FROM person p
                           JOIN users u ON p.user_id = u.user_id
                           WHERE p.person_id = ?";
            
            $stmt_person = $pdo->prepare($sql_person);
            $stmt_person->execute([$person_id]);
            $person = $stmt_person->fetch(PDO::FETCH_ASSOC);
        }
        
        if (!$person) {
            $person = [
                'identification_number' => '',
                'full_name' => '',
                'phone_number' => '',
                'email' => ''
            ];
        }

        // ==========================================================
        // 4. PREPARAR RESPUESTA
        // ==========================================================
        $availabilityMap = [
            'available' => 'Available',
            'sold' => 'Sold',
            'reserved' => 'Reserved',
            'unavailable' => 'Unavailable'
        ];

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'vehicle' => [
                'vehicle_id' => $vehicle['vehicle_id'],
                'brand' => $vehicle['brand'],
                'model' => $vehicle['model'],
                'year' => $vehicle['year'],
                'color' => $vehicle['color'],
                'price' => $vehicle['price'],
                'description' => $vehicle['description'] ?? 'High-performance vehicle with excellent features.',
                'availability' => $availabilityMap[$vehicle['availability']] ?? ucfirst($vehicle['availability']),
                'image' => $image_url
            ],
            'person' => $person,
            'taxPercentage' => 19
        ]);

    } catch (PDOException $e) {
        error_log("DB Error in get_quote_data: " . $e->getMessage());
        http_response_code(500);
        
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ]);
        
    } catch (Exception $e) {
        error_log("Error in get_quote_data: " . $e->getMessage());
        http_response_code(500);
        
        echo json_encode([
            'success' => false,
            'message' => 'Error retrieving quote data'
        ]);
    }
} else {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
}

// =====================================================
// FUNCIÓN PARA NORMALIZAR URLs DE IMÁGENES
// =====================================================
function normalizeImageUrl($image_url) {
    if (!$image_url) {
        return 'img/car-placeholder.png';
    }
    
    // Si es una URL completa (http/https), devolverla tal cual
    if (preg_match('/^https?:\/\//i', $image_url)) {
        return $image_url;
    }
    
    // Quitar el / inicial si existe
    $cleaned = ltrim($image_url, '/');
    
    // Si ya tiene 'img/' al inicio, devolverla tal cual
    if (strpos($cleaned, 'img/') === 0) {
        return $cleaned;
    }
    
    // Si no tiene 'img/' y no es una URL, agregarlo
    if (!empty($cleaned)) {
        return 'img/' . $cleaned;
    }
    
    return 'img/car-placeholder.png';
}
?>