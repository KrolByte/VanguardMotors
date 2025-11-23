<?php
header('Content-Type: application/json');
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["success" => false, "message" => "Invalid method"]);
    exit;
}

try {
    $conn = getDbConnection();
    
    // Obtener datos del POST
    $transaction_id = isset($_POST['transaction_id']) ? (int)$_POST['transaction_id'] : 0;
    $new_status = isset($_POST['status']) ? trim($_POST['status']) : '';
    $advisor_id = isset($_POST['advisor_id']) ? (int)$_POST['advisor_id'] : 0;
    
    // Validaciones
    if ($transaction_id <= 0) {
        throw new Exception("Invalid transaction ID");
    }
    
    if ($advisor_id <= 0) {
        throw new Exception("Invalid advisor ID");
    }
    
    // Estados válidos que el asesor puede asignar
    $valid_statuses = ['confirmed', 'completed', 'cancelled'];
    
    if (!in_array($new_status, $valid_statuses)) {
        throw new Exception("Invalid status. Allowed: " . implode(', ', $valid_statuses));
    }
    
    // Verificar que la transacción pertenece al asesor
    $sql_check = "SELECT transaction_id, status 
                  FROM transaction 
                  WHERE transaction_id = ? AND advisor_id = ?";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->execute([$transaction_id, $advisor_id]);
    $transaction = $stmt_check->fetch(PDO::FETCH_ASSOC);
    
    if (!$transaction) {
        throw new Exception("Transaction not found or does not belong to this advisor");
    }
    
    // Verificar transiciones de estado válidas
    $current_status = $transaction['status'];
    $allowed_transitions = [
        'pending' => ['confirmed', 'cancelled'],
        'approved' => ['confirmed', 'completed', 'cancelled'],
        'confirmed' => ['completed', 'cancelled'],
        'completed' => [], // Estado final
        'cancelled' => [], // Estado final
        'rejected' => []   // Estado final (solo gerente)
    ];
    
    if (!isset($allowed_transitions[$current_status]) || 
        !in_array($new_status, $allowed_transitions[$current_status])) {
        throw new Exception("Cannot change from '$current_status' to '$new_status'");
    }
    
    // Actualizar estado
    $sql_update = "UPDATE transaction SET status = ? WHERE transaction_id = ?";
    $stmt_update = $conn->prepare($sql_update);
    $stmt_update->execute([$new_status, $transaction_id]);
    
    echo json_encode([
        "success" => true,
        "message" => "Status updated successfully",
        "transaction_id" => $transaction_id,
        "old_status" => $current_status,
        "new_status" => $new_status
    ]);

} catch (PDOException $e) {
    error_log("DB Error: " . $e->getMessage());
    echo json_encode([
        "success" => false,
        "message" => "Database error: " . $e->getMessage()
    ]);
} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
?>