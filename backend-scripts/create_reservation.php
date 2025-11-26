<?php
session_start();
header('Content-Type: application/json');
require_once 'conexion.php';

// Simulación de sesión (reemplazar con login real)
$TEST_PERSON_ID = 1;
$person_id = $_SESSION['person_id'] ?? $TEST_PERSON_ID;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["success" => false, "message" => "Invalid method"]);
    exit;
}

try {
    $conn = getDbConnection();
    $conn->beginTransaction();

    // =====================================================
    // 1. RECOLECTAR DATOS DEL FORMULARIO
    // =====================================================
    $vehicle_id       = (int)$_POST['vehicle_id'];
    $advisor_id       = (int)$_POST['advisor_id'];
    $appointment_date = $_POST['appointment_date'];
    $appointment_time = $_POST['appointment_time'];
    $customer_phone   = trim($_POST['customer_phone'] ?? '');
    $payment_method   = $_POST['payment_method'];
    
    // Validaciones básicas
    if ($vehicle_id <= 0 || $advisor_id <= 0) {
        throw new Exception("Invalid vehicle or advisor ID");
    }
    
    if (empty($appointment_date) || empty($appointment_time)) {
        throw new Exception("Appointment date and time are required");
    }
    
    // =====================================================
    // 2. VERIFICAR DISPONIBILIDAD (evitar dobles reservas)
    // =====================================================
    $sql_check = "SELECT COUNT(*) FROM transaction 
                  WHERE advisor_id = ? 
                  AND appointment_date = ? 
                  AND appointment_time = ?
                  AND status IN ('pending')";
    
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->execute([$advisor_id, $appointment_date, $appointment_time]);
    
    if ($stmt_check->fetchColumn() > 0) {
        throw new Exception("This time slot is already booked. Please select another time.");
    }
    
    // =====================================================
    // 3. INSERTAR TRANSACCIÓN (consulting = asesoría)
    // =====================================================
    $reservation_price = 20000.00;
    $status = 'pending';
    
    $sql_transaction = "INSERT INTO transaction 
                        (type_transaction, status, creation_date, appointment_date, 
                         appointment_time, reservation_price, vehicle_id, person_id, advisor_id)
                        VALUES ('consulting', ?, CURRENT_DATE, ?, ?, ?, ?, ?, ?)
                        RETURNING transaction_id";
    
    $stmt_transaction = $conn->prepare($sql_transaction);
    $stmt_transaction->execute([
        $status,
        $appointment_date,
        $appointment_time,
        $reservation_price,
        $vehicle_id,
        $person_id,
        $advisor_id
    ]);
    
    $transaction_id = $stmt_transaction->fetchColumn();
    
    // =====================================================
    // 4. INSERTAR PAGO
    // =====================================================
    $gateway_reference = null;
    $card_number = null;
    $issue_date = null;
    $card_code = null;
    $reference_number = null;
    
    if ($payment_method === 'card') {
        // Pago con tarjeta
        $gateway_reference = 'SIM_' . strtoupper(bin2hex(random_bytes(8)));
        $card_number = isset($_POST['card_number']) ? substr(str_replace(' ', '', $_POST['card_number']), -4) : null;
        $issue_date = $_POST['issue_date'] ?? null;
        $card_code = $_POST['card_code'] ?? null;
        
    } else if ($payment_method === 'transfer') {
        // Transferencia bancaria
        $reference_number = $_POST['reference_number'] ?? null;
    }
    
    $sql_payment = "INSERT INTO payment 
                    (amount_pay, payment_datetime, gateway_reference, payment_method, 
                     card_number, issue_date, card_code, reference_number, transaction_id)
                    VALUES (?, NOW(), ?, ?, ?, ?, ?, ?, ?)
                    RETURNING payment_id";
    
    $stmt_payment = $conn->prepare($sql_payment);
    $stmt_payment->execute([
        $reservation_price,
        $gateway_reference,
        $payment_method,
        $card_number,
        $issue_date,
        $card_code,
        $reference_number,
        $transaction_id
    ]);
    
    $payment_id = $stmt_payment->fetchColumn();
    
    // =====================================================
    // 5. ACTUALIZAR TELÉFONO DEL CLIENTE
    // =====================================================
    if (!empty($customer_phone)) {
        $sql_update_phone = "UPDATE person SET phone_number = ? WHERE person_id = ?";
        $stmt_phone = $conn->prepare($sql_update_phone);
        $stmt_phone->execute([$customer_phone, $person_id]);
    }
    
    // =====================================================
    // 6. COMMIT Y RESPUESTA
    // =====================================================
    $conn->commit();
    
    echo json_encode([
        "success" => true,
        "message" => "Reservation created successfully!",
        "transaction_id" => $transaction_id,
        "payment_id" => $payment_id,
        "status" => $status
    ]);

} catch (PDOException $e) {
    if (isset($conn)) $conn->rollBack();
    error_log("DB Error: " . $e->getMessage());
    
    echo json_encode([
        "success" => false,
        "message" => "Database error: " . $e->getMessage()
    ]);
    
} catch (Exception $e) {
    if (isset($conn)) $conn->rollBack();
    error_log("Error: " . $e->getMessage());
    
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
?>