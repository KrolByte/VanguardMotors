<?php
/**
 * Archivo: backend-scripts/get_vehicles.php
 * Propósito: Obtener lista de vehículos con sus imágenes desde la BD
 * Devuelve: JSON con array de vehículos
 */

require_once 'db.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if ($_SERVER["REQUEST_METHOD"] == "GET") {
    try {
        $pdo = getDbConnection();

        // Consulta SQL usando STRING_AGG para PostgreSQL
        // NOTE: la columna de imágenes en la tabla es `image_url`
        $sql_vehicles = "SELECT 
                            v.vehicle_id,
                            v.brand,
                            v.model,
                            v.year,
                            v.color,
                            v.price,
                            v.availability,
                            v.description,
                            STRING_AGG(vi.image_url, ',') as images
                        FROM vehicle v
                        LEFT JOIN vehicle_image vi ON v.vehicle_id = vi.vehicle_id
                        GROUP BY v.vehicle_id
                        ORDER BY v.vehicle_id ASC";

        $stmt = $pdo->prepare($sql_vehicles);
        $stmt->execute();
        $vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$vehicles) {
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'data' => [],
                'message' => 'No vehicles found'
            ]);
            exit;
        }

        // Procesar imágenes y formatear respuesta
        $formatted_vehicles = [];
        
        foreach ($vehicles as $vehicle) {
            // Procesar imágenes (comma-separated)
            $images = [];
            if (!empty($vehicle['images'])) {
                $image_paths = explode(',', $vehicle['images']);
                foreach ($image_paths as $path) {
                    $p = trim($path);
                    if ($p === '') continue;

                    // Si es URL absoluta (http/https), dejarla tal cual
                    if (preg_match('#^https?://#i', $p)) {
                        $images[] = $p;
                        continue;
                    }

                    // Normalizar rutas locales: quitar slash inicial y anteponer 'img/' si no empieza por 'img/'
                    $p = ltrim($p, '/');
                    if (!preg_match('#^img/#i', $p)) {
                        $p = 'img/' . $p;
                    }
                    $images[] = $p;
                }
            }

            // Si no hay imágenes, usar imagen por defecto
            if (empty($images)) {
                $images[] = 'img/car-rent-6.png';
            }

            // Mapear disponibilidad
            $availability_map = [
                'available' => 'Available',
                'sold' => 'Sold',
                'reserved' => 'Reserved',
                'unavailable' => 'Unavailable',
                'maintenance' => 'maintenance' // Mapear maintenance antiguo a unavailable
            ];

            $formatted_vehicles[] = [
                'vehicle_id' => (int)$vehicle['vehicle_id'],
                'brand' => $vehicle['brand'],
                'model' => $vehicle['model'],
                'year' => (int)$vehicle['year'],
                'color' => $vehicle['color'],
                'price' => (float)$vehicle['price'],
                'availability' => $availability_map[$vehicle['availability']] ?? ucfirst($vehicle['availability']),
                'availability_status' => $vehicle['availability'],
                'images' => $images,
                'primary_image' => $images[0] ?? 'img/car-rent-6.png'
            ];
        }

        // Enviar respuesta
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'data' => $formatted_vehicles,
            'count' => count($formatted_vehicles)
        ]);

    } catch (Exception $e) {
        error_log("Error en get_vehicles: " . $e->getMessage());
        http_response_code(500);
        
        echo json_encode([
            'success' => false,
            'message' => 'Error retrieving vehicles: ' . $e->getMessage()
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