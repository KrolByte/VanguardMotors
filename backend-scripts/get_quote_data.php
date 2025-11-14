<?php
// Archivo: backend-scripts/get_quote_data.php
// Propósito: Obtener datos del vehículo y usuario para pre-llenar el formulario de cotización

require_once 'db.php';
require_once '../env_loader.php';

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
        $sql_vehicle = "SELECT vehicle_id, brand, model, year, color, price, description, 
                               availability, image_path
                        FROM vehicle 
                        WHERE vehicle_id = :vehicle_id";
        
        $stmt_vehicle = $pdo->prepare($sql_vehicle);
        $stmt_vehicle->execute([':vehicle_id' => $vehicle_id]);
        $vehicle = $stmt_vehicle->fetch(PDO::FETCH_ASSOC);

        if (!$vehicle) {
            http_response_code(404);
            die(json_encode([
                'success' => false,
                'message' => 'Vehicle not found'
            ]));
        }

        // ==========================================================
        // 2. OBTENER DATOS DEL USUARIO AUTENTICADO (si aplica)
        // ==========================================================
        $person = null;
        
        // Si tienes un sistema de sesiones, descomenta esto:
        // session_start();
        // if (isset($_SESSION['user_id'])) {
        //     $sql_person = "SELECT person_id, identification_number, full_name, phone_number, email
        //                    FROM person 
        //                    WHERE person_id = :person_id";
        //     
        //     $stmt_person = $pdo->prepare($sql_person);
        //     $stmt_person->execute([':person_id' => $_SESSION['user_id']]);
        //     $person = $stmt_person->fetch(PDO::FETCH_ASSOC);
        // }

        // Por ahora, devolver estructura vacía para la persona
        // El usuario la llenará manualmente o desde sesión
        $person = [
            'identification_number' => '',
            'full_name' => '',
            'phone_number' => '',
            'email' => ''
        ];

        // ==========================================================
        // 3. PREPARAR RESPUESTA
        // ==========================================================
        $availabilityMap = [
            'available' => 'Available',
            'sold' => 'Sold',
            'reserved' => 'Reserved',
            'maintenance' => 'In Maintenance'
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
                'description' => $vehicle['description'],
                'availability' => $availabilityMap[$vehicle['availability']] ?? ucfirst($vehicle['availability']),
                'image' => $vehicle['image_path'] ? 'img/' . $vehicle['image_path'] : 'img/car-rent-6.png'
            ],
            'person' => $person,
            'taxPercentage' => 19 // Ajusta según tu configuración
        ]);

    } catch (Exception $e) {
        error_log("Error al obtener datos de cotización: " . $e->getMessage());
        http_response_code(500);
        
        echo json_encode([
            'success' => false,
            'message' => 'Error retrieving quote data: ' . $e->getMessage()
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
