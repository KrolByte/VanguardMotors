<?php
// Archivo: backend-scripts/save_quote.php
// Propósito: Guardar cotización y agendar cita automática

session_start();
require_once 'db.php';

header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        // ==========================================================
        // 1. OBTENER PERSON_ID DE LA SESIÓN
        // ==========================================================
        $test_person_id = 1;
        $person_id = $_SESSION['person_id'] ?? $test_person_id;
        
        if (!$person_id) {
            http_response_code(401);
            die(json_encode([
                'success' => false,
                'message' => 'You must be logged in to request a quote.'
            ]));
        }
        
        // ==========================================================
        // 2. VALIDACIÓN DE DATOS
        // ==========================================================
        $vehicle_id = filter_input(INPUT_POST, 'vehicle_id', FILTER_VALIDATE_INT);
        $taxes = filter_input(INPUT_POST, 'taxes', FILTER_VALIDATE_FLOAT);
        $total_estimated = filter_input(INPUT_POST, 'total_estimated', FILTER_VALIDATE_FLOAT);
        $valid_until = filter_input(INPUT_POST, 'valid_until', FILTER_SANITIZE_STRING);
        
        $quote_date = date('Y-m-d');

        if (!$vehicle_id || $taxes === false || $total_estimated === false || !$valid_until) {
            http_response_code(400);
            die(json_encode([
                'success' => false,
                'message' => 'Missing or invalid required fields.'
            ]));
        }

        // ==========================================================
        // 3. CONEXIÓN Y TRANSACCIÓN
        // ==========================================================
        $pdo = getDbConnection();
        $pdo->beginTransaction();

        // Verificar vehículo
        $sql_vehicle = "SELECT price, brand, model FROM vehicle WHERE vehicle_id = ?";
        $stmt_vehicle = $pdo->prepare($sql_vehicle);
        $stmt_vehicle->execute([$vehicle_id]);
        $vehicle = $stmt_vehicle->fetch(PDO::FETCH_ASSOC);
        
        if (!$vehicle) {
            $pdo->rollBack();
            http_response_code(404);
            die(json_encode(['success' => false, 'message' => 'Vehicle not found.']));
        }

        // Verificar persona y obtener email
        $sql_person = "SELECT p.person_id, p.full_name, u.email 
                       FROM person p 
                       JOIN users u ON p.user_id = u.user_id 
                       WHERE p.person_id = ?";
        $stmt_person = $pdo->prepare($sql_person);
        $stmt_person->execute([$person_id]);
        $person = $stmt_person->fetch(PDO::FETCH_ASSOC);
        
        if (!$person) {
            $pdo->rollBack();
            http_response_code(404);
            die(json_encode(['success' => false, 'message' => 'User not found.']));
        }

        // ==========================================================
        // 4. INSERTAR COTIZACIÓN
        // ==========================================================
        $sql_quote = "INSERT INTO cotization (quote_date, taxes, total_estimated, valid_until, person_id, vehicle_id) 
                      VALUES (?, ?, ?, ?, ?, ?)
                      RETURNING quote_id";
        
        $stmt_quote = $pdo->prepare($sql_quote);
        $stmt_quote->execute([$quote_date, $taxes, $total_estimated, $valid_until, $person_id, $vehicle_id]);
        $quote_id = $stmt_quote->fetchColumn();

        // ==========================================================
        // 5. BUSCAR ASESOR DISPONIBLE Y HORARIO
        // ==========================================================
        
        // Fecha sugerida: 7 días después de la cotización
        $suggested_date = date('Y-m-d', strtotime('+7 days'));
        
        // Horarios disponibles para búsqueda (9 AM - 5 PM)
        $available_hours = ['09:00', '09:30', '10:00', '10:30', '11:00', '11:30', 
                           '14:00', '14:30', '15:00', '15:30', '16:00', '16:30', '17:00'];
        
        // Obtener todos los asesores activos
        $sql_advisors = "SELECT e.employed_id 
                        FROM employed e 
                        JOIN users u ON e.user_id = u.user_id 
                        WHERE u.role = 'advisor' 
                        ORDER BY RANDOM()"; // Orden aleatorio
        
        $stmt_advisors = $pdo->prepare($sql_advisors);
        $stmt_advisors->execute();
        $advisors = $stmt_advisors->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($advisors)) {
            $pdo->rollBack();
            http_response_code(500);
            die(json_encode(['success' => false, 'message' => 'No advisors available.']));
        }
        
        // Buscar slot disponible
        $found_slot = false;
        $assigned_advisor = null;
        $assigned_date = null;
        $assigned_time = null;
        
        // Intentar en la fecha sugerida primero, luego días siguientes
        for ($day_offset = 0; $day_offset <= 14 && !$found_slot; $day_offset++) {
            $check_date = date('Y-m-d', strtotime("+{$day_offset} days", strtotime($suggested_date)));
            
            // Saltar fines de semana
            $day_of_week = date('w', strtotime($check_date));
            if ($day_of_week == 0 || $day_of_week == 6) continue;
            
            foreach ($available_hours as $hour) {
                foreach ($advisors as $advisor_id) {
                    // Verificar si el asesor está libre en ese horario
                    $sql_check = "SELECT COUNT(*) FROM transaction 
                                 WHERE advisor_id = ? 
                                 AND appointment_date = ? 
                                 AND appointment_time = ?
                                 AND status IN ('pending', 'approved')";
                    
                    $stmt_check = $pdo->prepare($sql_check);
                    $stmt_check->execute([$advisor_id, $check_date, $hour]);
                    
                    if ($stmt_check->fetchColumn() == 0) {
                        // ¡Slot encontrado!
                        $assigned_advisor = $advisor_id;
                        $assigned_date = $check_date;
                        $assigned_time = $hour;
                        $found_slot = true;
                        break 3; // Salir de los 3 loops
                    }
                }
            }
        }
        
        if (!$found_slot) {
            // Si no se encuentra slot, asignar al primer asesor con fecha +7 días
            $assigned_advisor = $advisors[0];
            $assigned_date = $suggested_date;
            $assigned_time = '10:00';
        }

        // ==========================================================
        // 6. CREAR TRANSACCIÓN DE CITA (buys = compra/revisión cotización)
        // ==========================================================
        $sql_transaction = "INSERT INTO transaction 
                           (type_transaction, status, creation_date, appointment_date, 
                            appointment_time, vehicle_id, person_id, advisor_id, quote_id)
                           VALUES ('buys', 'pending', CURRENT_DATE, ?, ?, ?, ?, ?, ?)
                           RETURNING transaction_id";
        
        $stmt_transaction = $pdo->prepare($sql_transaction);
        $stmt_transaction->execute([
            $assigned_date,
            $assigned_time,
            $vehicle_id,
            $person_id,
            $assigned_advisor,
            $quote_id
        ]);
        
        $transaction_id = $stmt_transaction->fetchColumn();

        // Obtener nombre del asesor
        $sql_advisor_name = "SELECT full_name FROM employed WHERE employed_id = ?";
        $stmt_advisor_name = $pdo->prepare($sql_advisor_name);
        $stmt_advisor_name->execute([$assigned_advisor]);
        $advisor_name = $stmt_advisor_name->fetchColumn();

        // ==========================================================
        // 7. COMMIT Y PREPARAR RESPUESTA
        // ==========================================================
        $pdo->commit();
        
        // Formatear fecha para mostrar
        $formatted_date = date('F j, Y', strtotime($assigned_date));
        $formatted_time = date('g:i A', strtotime($assigned_time));

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Quote saved and appointment scheduled successfully!',
            'quote_id' => $quote_id,
            'transaction_id' => $transaction_id,
            'appointment' => [
                'date' => $formatted_date,
                'time' => $formatted_time,
                'advisor' => $advisor_name,
                'vehicle' => $vehicle['brand'] . ' ' . $vehicle['model']
            ],
            'customer' => [
                'name' => $person['full_name'],
                'email' => $person['email']
            ]
        ]);

    } catch (PDOException $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        error_log("DB Error in save_quote: " . $e->getMessage());
        http_response_code(500);
        
        echo json_encode([
            'success' => false,
            'message' => 'Database error while saving quote.'
        ]);
        
    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        error_log("Error in save_quote: " . $e->getMessage());
        http_response_code(500);
        
        echo json_encode([
            'success' => false,
            'message' => 'Error saving quote. Please try again.'
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
