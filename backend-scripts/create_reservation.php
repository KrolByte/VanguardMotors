<?php
// backend-scripts/create_reservation.php
session_start();
header('Content-Type: application/json');

require_once '../db.php';

$TEST_PERSON_ID = 1;
$person_id = $_SESSION['person_id'] ?? $TEST_PERSON_ID;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["success" => false, "message" => "Método no permitido"]);
    exit;
}

try {
    $conn = getDbConnection();
    $conn->beginTransaction();

    // Datos del form
    $vehicle_id       = (int)$_POST['vehicle_id'];
    $advisor_id       = (int)$_POST['advisor_id'];
    $appointment_date = $_POST['appointment_date'];
    $appointment_time = $_POST['appointment_time'];
    $phone            = trim($_POST['phone'] ?? '');
    $payment_method   = $_POST['payment_method'];
    $proof_reference  = $_POST['proof_reference'] ?? null;

    $gateway_reference = ($payment_method === 'card')
        ? 'SIM_' . strtoupper(bin2hex(random_bytes(6)))
        : null;

    $status = ($payment_method === 'transfer') ? 'pending_verification' : 'pending_approval';

    // INSERT EN payment – CON ? (nunca falla)
    $sql = "INSERT INTO payment (amount_py, payment_status, gateway_reference, payment_method, reference_number) 
            VALUES (20000, 'completed', ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$gateway_reference, $payment_method, $proof_reference]);
    $payment_id = $conn->lastInsertId();

    // INSERT EN transaction – CON ?
    $sql2 = "INSERT INTO \"transaction\" 
             (type_transaction, status, creation_date, appointment_date, appointment_time, 
              reservation_price, vehicle_id, person_id, advisor_id, payment_id)
             VALUES ('reservation', ?, NOW(), ?, ?, 20000, ?, ?, ?, ?)";
    $stmt2 = $conn->prepare($sql2);
    $stmt2->execute([
        $status,
        $appointment_date,
        $appointment_time,
        $vehicle_id,
        $person_id,
        $advisor_id,
        $payment_id
    ]);

    // Actualizar teléfono si viene
    if (!empty($phone)) {
        $conn->prepare("UPDATE person SET phone_number = ? WHERE person_id = ?")
             ->execute([$phone, $person_id]);
    }

    $conn->commit();

    echo json_encode([
        "success" => true,
        "message" => "¡Reserva creada con éxito!"
    ]);

} catch (Exception $e) {
    if (isset($conn)) $conn->rollBack();
    
    // ESTE ES EL TRUCO: vemos el error real aunque PHP esté mal configurado
    echo json_encode([
        "success" => false,
        "message" => "ERROR EXACTO: " . $e->getMessage(),
        "line" => $e->getLine(),
        "file" => basename($e->getFile())
    ]);
}
?>