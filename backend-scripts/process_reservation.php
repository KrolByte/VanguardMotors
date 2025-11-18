<?php
// process_reservation.php

// 1. Incluir la conexión a la base de datos (Están en la misma carpeta)
require_once 'conexion.php'; 

$quote_id = $_POST['quote_id'] ?? null;
$action = $_POST['action'] ?? null;
$error_message = null;

// 2. Verificar que se recibieron los datos necesarios (quote_id y action)
if (!$quote_id || !$action) {
    $error_message = 'Datos incompletos para procesar la reserva.';
}

$new_status = '';
$message = '';

// 3. Determinar el nuevo estado
if (!$error_message) {
    if ($action === 'approve') {
        $new_status = 'Aprobado';
        $message = 'Reserva aprobada con éxito.';
    } elseif ($action === 'reject') {
        $new_status = 'Rechazado';
        $message = 'Reserva rechazada y cancelada.';
    } else {
        $error_message = 'Acción no válida.';
    }
}

// 4. Actualizar el estado en la base de datos
if (!$error_message) {
    try {
        $sql = "UPDATE public.cotization SET status = :new_status WHERE quote_id = :quote_id";
        $stmt = $conexion->prepare($sql);
        
        $stmt->bindParam(':new_status', $new_status);
        $stmt->bindParam(':quote_id', $quote_id, PDO::PARAM_INT);
        
        $stmt->execute();
        
        // 5. Redirigir de vuelta a la página del gerente con un mensaje de éxito
        header('Location: approve_reservations.php?success=' . urlencode($message));
        exit;
        
    } catch (PDOException $e) {
        $error_message = "Error al actualizar la BD: " . $e->getMessage();
    }
}

// Si hay un error, redirigir con el mensaje de error
if ($error_message) {
    header('Location: approve_reservations.php?error=' . urlencode($error_message));
}

exit;
?>