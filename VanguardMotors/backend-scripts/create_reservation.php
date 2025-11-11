<?php
// Incluir la función de conexión a la BD
require_once 'db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // 1. Recolección y Limpieza de Datos del Formulario de Reserva
    
    // Datos de PERSON (Asumimos que vienen del formulario, aunque estén readonly/hidden)
    $full_name          = trim($_POST['full_name']);
    $identification_num = trim($_POST['identification_num']); 
    $customer_email     = trim($_POST['customer_email']);
    $customer_phone     = trim($_POST['customer_phone']);

    // Datos de TRANSACTION (Cita y Asesor)
    $vehicle_id         = trim($_POST['vehicle_id']);
    $advisor_id         = trim($_POST['advisor_id']);
    $appointment_date   = trim($_POST['appointment_date']);
    $appointment_time   = trim($_POST['appointment_time']);
    
    // Datos de PAYMENT (Depósito y Método)
    $reservation_price  = floatval(trim($_POST['reservation_price'])); // Debe ser 500.00
    $payment_method     = trim($_POST['payment_method']);
    
    // Datos específicos del método de pago (para la simulación)
    $proof_reference    = trim($_POST['proof_reference'] ?? ''); // Solo para transferencia
    $card_number        = trim($_POST['card_number'] ?? '');    // Solo para tarjeta
    
    // Definir STATUS de la transacción basado en el método de pago
    // Tarjeta (Simulada): Confirmada | Transferencia: Pendiente de verificación
    $transaction_status = ($payment_method === 'card') ? 'CONFIRMED' : 'PENDING';

    try {
        // Obtener conexión a la base de datos
        $pdo = getDbConnection();
        
        // Iniciar una transacción de DB para asegurar la atomicidad (ACID)
        $pdo->beginTransaction();

        // ==========================================================
        // A. INSERCIÓN O BÚSQUEDA EN LA TABLA PERSON 
        // (Asumimos que si la identificación existe, no se crea, pero aquí la creamos para simplificar)
        // ==========================================================
        $sql_person = "INSERT INTO PERSON (identification_num, full_name, phone_number, email) 
                       VALUES (:identification_num, :full_name, :phone_number, :email) 
                       ON CONFLICT (identification_num) DO NOTHING 
                       RETURNING person_id";
        
        $stmt_person = $pdo->prepare($sql_person);
        $stmt_person->execute([
            ':identification_num' => $identification_num,
            ':full_name' => $full_name,
            ':phone_number' => $customer_phone,
            ':email' => $customer_email
        ]);
        
        // Si no se insertó (conflicto), buscamos el person_id existente
        $person_id = $pdo->query("SELECT person_id FROM PERSON WHERE identification_num = '{$identification_num}'")->fetchColumn();
        
        
        // ==========================================================
        // B. INSERCIÓN EN LA TABLA TRANSACTION
        // ==========================================================
        $sql_transaction = "INSERT INTO TRANSACTION (person_id, vehicle_id, type_transaction, status, consulting_buys, pending_approved)
                            VALUES (:person_id, :vehicle_id, 'reservation', :status, :appointment_time, :appointment_date)
                            RETURNING transaction_id";
                            
        $stmt_transaction = $pdo->prepare($sql_transaction);
        $stmt_transaction->execute([
            ':person_id' => $person_id,
            ':vehicle_id' => $vehicle_id,
            ':status' => $transaction_status,
            ':appointment_time' => $appointment_time,
            ':appointment_date' => $appointment_date 
        ]);

        $transaction_id = $stmt_transaction->fetchColumn();

        // ==========================================================
        // C. INSERCIÓN EN LA TABLA PAYMENT (Solo si hay un depósito)
        // ==========================================================
        if ($reservation_price > 0) {
            
            // Determinar la referencia a guardar
            $gateway_ref = ($payment_method === 'card') 
                           ? uniqid('GATE_') // Simulación de ID de pasarela
                           : $proof_reference; // Referencia dada por el usuario en transferencia

            $sql_payment = "INSERT INTO PAYMENT (transaction_id, payment_reason, reservation_direct, amount_pay, payment_datetime)
                            VALUES (:transaction_id, 'reservation', :method, :amount, NOW())";
            
            $stmt_payment = $pdo->prepare($sql_payment);
            $stmt_payment->execute([
                ':transaction_id' => $transaction_id,
                ':method' => $payment_method, // Usamos el método como 'reservation_direct'
                ':amount' => $reservation_price
                // NOTA: La tabla PAYMENT no tiene campo para 'gateway_reference' visible en tu diagrama,
                // por lo que se asume que 'reservation_direct' almacena el método o la referencia.
            ]);
        }
        
        // Confirmar la transacción si todas las inserciones fueron exitosas
        $pdo->commit();
        
        // Redirigir a una página de confirmación con el ID de la transacción
        header("Location: ../confirmation.html?trans_id={$transaction_id}");
        exit();

    } catch (Exception $e) {
        $pdo->rollBack();
        error_log($e->getMessage()); // Registrar el error en el log
        
        // Redirigir a una página de error
        header("Location: ../error.html?message=" . urlencode("Error al procesar la reserva."));
        exit();
    }
} else {
    header("Location: ../index.html");
    exit();
}
?>