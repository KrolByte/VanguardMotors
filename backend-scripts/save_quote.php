<?php
// Archivo: backend-scripts/save_quote.php
// Propósito: Guardar una cotización de vehículo en la base de datos
// Requiere: Datos de persona y cotización del formulario quote.html

require_once 'db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        // ==========================================================
        // 1. OBTENCIÓN Y VALIDACIÓN DE DATOS DE PERSONA
        // ==========================================================
        $full_name = filter_input(INPUT_POST, 'full_name', FILTER_SANITIZE_STRING);
        $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
        $phone_number = filter_input(INPUT_POST, 'phone_number', FILTER_SANITIZE_STRING);
        $identification_number = filter_input(INPUT_POST, 'identification_number', FILTER_SANITIZE_STRING);
        
        // ==========================================================
        // 2. OBTENCIÓN Y VALIDACIÓN DE DATOS DE COTIZACIÓN
        // ==========================================================
        $vehicle_id = filter_input(INPUT_POST, 'vehicle_id', FILTER_VALIDATE_INT);
        $taxes = filter_input(INPUT_POST, 'taxes', FILTER_VALIDATE_FLOAT);
        $total_estimated = filter_input(INPUT_POST, 'total_estimated', FILTER_VALIDATE_FLOAT);
        $valid_until = filter_input(INPUT_POST, 'valid_until', FILTER_SANITIZE_STRING);
        
        // Fecha actual de cotización
        $quote_date = date('Y-m-d');

        // ==========================================================
        // 3. VALIDACIÓN DE DATOS ESENCIALES
        // ==========================================================
        if (!$full_name || !$email || !$phone_number || !$identification_number || 
            !$vehicle_id || $taxes === false || $total_estimated === false) {
            http_response_code(400);
            die(json_encode([
                'success' => false,
                'message' => 'Error: Faltan datos esenciales o son inválidos.'
            ]));
        }

        // ==========================================================
        // 4. CONEXIÓN A LA BASE DE DATOS E INSERCIÓN
        // ==========================================================
        $pdo = getDbConnection();
        $pdo->beginTransaction();

        // --- Verificar que el vehículo existe y obtener su precio ---
        $sql_vehicle = "SELECT price FROM vehicle WHERE vehicle_id = :vehicle_id";
        $stmt_vehicle = $pdo->prepare($sql_vehicle);
        $stmt_vehicle->execute([':vehicle_id' => $vehicle_id]);
        $vehicle_price = $stmt_vehicle->fetchColumn();
        
        if ($vehicle_price === false) {
            http_response_code(400);
            die(json_encode([
                'success' => false,
                'message' => 'Error: El vehículo especificado no existe.'
            ]));
        }

        // --- A. INSERTAR O OBTENER LA PERSONA ---
        $sql_person = "INSERT INTO person (identification_number, full_name, phone_number, email) 
                       VALUES (:identification_number, :full_name, :phone_number, :email)
                       ON CONFLICT (identification_number) DO UPDATE 
                       SET full_name = :full_name, phone_number = :phone_number, email = :email
                       RETURNING person_id";
        
        $stmt_person = $pdo->prepare($sql_person);
        $stmt_person->execute([
            ':identification_number' => $identification_number,
            ':full_name' => $full_name,
            ':phone_number' => $phone_number,
            ':email' => $email
        ]);
        
        $person_id = $stmt_person->fetchColumn();

        // --- B. INSERTAR LA COTIZACIÓN ---
        $sql_quote = "INSERT INTO cotization (quote_date, taxes, total_estimated, valid_until, person_id, vehicle_id) 
                      VALUES (:quote_date, :taxes, :total_estimated, :valid_until, :person_id, :vehicle_id)
                      RETURNING cotization_id";
        
        $stmt_quote = $pdo->prepare($sql_quote);
        $stmt_quote->execute([
            ':quote_date' => $quote_date,
            ':taxes' => $taxes,
            ':total_estimated' => $total_estimated,
            ':valid_until' => $valid_until,
            ':person_id' => $person_id,
            ':vehicle_id' => $vehicle_id
        ]);
        
        $cotization_id = $stmt_quote->fetchColumn();
        $pdo->commit();

        // ==========================================================
        // 5. RESPUESTA DE ÉXITO
        // ==========================================================
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => "¡Cotización #{$cotization_id} para {$full_name} guardada exitosamente!",
            'cotization_id' => $cotization_id,
            'person_id' => $person_id
        ]);

    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        error_log("Error de cotización: " . $e->getMessage());
        http_response_code(500);
        
        echo json_encode([
            'success' => false,
            'message' => 'Error: Falló al guardar la cotización. Revise los datos e inténtelo de nuevo.'
        ]);
    }
} else {
    // Redirigir si acceden directamente (método GET)
    header("Location: ../quote.html");
    exit();
}
?>