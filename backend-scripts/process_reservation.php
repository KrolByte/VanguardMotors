<?php

require_once 'conexion.php'; 

$quote_id = $_POST['quote_id'] ?? null;
$action = $_POST['action'] ?? null;
$error_message = null;


if (!$quote_id || !$action) {
    $error_message = 'Datos incompletos para procesar la reserva.';
}

$new_status = '';
$message = '';


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


if (!$error_message) {
    try {
        $sql = "UPDATE public.cotization SET status = :new_status WHERE quote_id = :quote_id";
        $stmt = $conexion->prepare($sql);
        
        $stmt->bindParam(':new_status', $new_status);
        $stmt->bindParam(':quote_id', $quote_id, PDO::PARAM_INT);
        
        $stmt->execute();
        
      
        header('Location: approve_reservations.php?success=' . urlencode($message));
        exit;
        
    } catch (PDOException $e) {
        $error_message = "Error al actualizar la BD: " . $e->getMessage();
    }
}


if ($error_message) {
    header('Location: approve_reservations.php?error=' . urlencode($error_message));
}

exit;
?>