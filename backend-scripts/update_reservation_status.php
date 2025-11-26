<?php
header('Content-Type: application/json');
require_once 'conexion.php';

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
    
    // VALIDAR que el employed_id es un advisor real
    $sql_validate_advisor = "SELECT u.role FROM employed e 
                             INNER JOIN users u ON e.user_id = u.user_id 
                             WHERE e.employed_id = ?";
    $stmt_validate = $conn->prepare($sql_validate_advisor);
    $stmt_validate->execute([$advisor_id]);
    $advisor_data = $stmt_validate->fetch(PDO::FETCH_ASSOC);
    
    if (!$advisor_data || $advisor_data['role'] !== 'advisor') {
        throw new Exception("Access denied: Only advisors can update reservations");
    }
    
    // Estados válidos que el asesor puede asignar
    // El asesor puede: rechazar reservas aprobadas o marcarlas como completadas
    $valid_statuses = ['rejected', 'completed'];
    
    if (!in_array($new_status, $valid_statuses)) {
        throw new Exception("Invalid status. Allowed: rejected, completed");
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
        'pending' => [],       // Pending: solo gerente puede aprobar/rechazar
        'approved' => ['rejected', 'completed'], // Approved: asesor puede rechazar o completar
        'rejected' => [],      // Rejected: estado final, sin cambios
        'completed' => []      // Completed: estado final, sin cambios
    ];
    
    if (!isset($allowed_transitions[$current_status]) || 
        !in_array($new_status, $allowed_transitions[$current_status])) {
        throw new Exception("Cannot change from '$current_status' to '$new_status'. Only 'approved' reservations can be updated by advisors.");
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