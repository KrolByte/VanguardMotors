<?php
// Deshabilitar salida de errores en producción
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');

try {
    require_once __DIR__ . '/../env_loader.php';
    require_once __DIR__ . '/db.php';
    $conn = getDbConnection();
} catch (Exception $e) {
    error_log("Connection error in process_reservation: " . $e->getMessage());
    header('Location: approve_reservations.php?error=' . urlencode('Database connection error'));
    exit;
}

// Verificar que sea una petición POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: approve_reservations.php?error=' . urlencode('Invalid request method'));
    exit;
}

// Obtener datos del formulario
$type = $_POST['type'] ?? null;      // 'quote' o 'consulting'
$id = $_POST['id'] ?? null;
$action = $_POST['action'] ?? null;  // 'approve' o 'reject'

$error_message = null;

// Validaciones básicas
if (!$type || !$id || !$action) {
    header('Location: approve_reservations.php?error=' . urlencode('Incomplete data to process the reservation'));
    exit;
}

if (!in_array($action, ['approve', 'reject'])) {
    header('Location: approve_reservations.php?error=' . urlencode('Invalid action'));
    exit;
}

if (!in_array($type, ['quote', 'consulting'])) {
    header('Location: approve_reservations.php?error=' . urlencode('Invalid type'));
    exit;
}

// Procesar según el tipo
try {
    $conn->beginTransaction();
    
    if ($type === 'quote') {
        // ==========================================
        // PROCESAR COTIZACIÓN Y SU TRANSACCIÓN ASOCIADA
        // ==========================================
        $new_status_cotization = ($action === 'approve') ? 'Aprobado' : 'Rechazado';
        $new_status_transaction = ($action === 'approve') ? 'approved' : 'rejected';
        $message = ($action === 'approve') 
            ? "Quote #$id approved successfully" 
            : "Quote #$id rejected";
        
        error_log("=== PROCESSING QUOTE #$id ===");
        error_log("Action: $action");
        error_log("New cotization status: $new_status_cotization");
        error_log("New transaction status: $new_status_transaction");
        
        // Verificar que la cotización existe y obtener su quote_id
        $sql_check = "SELECT quote_id, status FROM cotization WHERE quote_id = :quote_id";
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->bindParam(':quote_id', $id, PDO::PARAM_INT);
        $stmt_check->execute();
        $quote_data = $stmt_check->fetch(PDO::FETCH_ASSOC);
        
        if (!$quote_data) {
            error_log("ERROR: Quote #$id not found in database");
            throw new Exception("Quote #$id not found");
        }
        
        $current_status = trim($quote_data['status']);
        error_log("Current status (trimmed): '$current_status'");
        
        // Verificar que está pendiente
        if ($current_status !== 'Pendiente') {
            error_log("WARNING: Quote status is '$current_status', not 'Pendiente'");
            throw new Exception("Quote #$id has already been processed (current status: $current_status)");
        }
        
        // PASO 1: Actualizar la tabla COTIZATION
        $sql_cotization = "UPDATE cotization 
                SET status = :new_status 
                WHERE quote_id = :quote_id 
                AND TRIM(status) = 'Pendiente'";
        
        $stmt_cotization = $conn->prepare($sql_cotization);
        $stmt_cotization->bindParam(':new_status', $new_status_cotization, PDO::PARAM_STR);
        $stmt_cotization->bindParam(':quote_id', $id, PDO::PARAM_INT);
        $stmt_cotization->execute();
        $rows_cotization = $stmt_cotization->rowCount();
        
        error_log("Cotization table - Rows affected: $rows_cotization");
        
        if ($rows_cotization === 0) {
            error_log("ERROR: UPDATE cotization didn't affect any rows");
            throw new Exception("Failed to update quote #$id in cotization table");
        }
        
        // PASO 2: Actualizar la tabla TRANSACTION asociada
        // Buscar la transacción que referencia esta cotización
        $sql_transaction = "UPDATE transaction 
                SET status = :new_status 
                WHERE quote_id = :quote_id 
                AND TRIM(status) = 'pending'";
        
        $stmt_transaction = $conn->prepare($sql_transaction);
        $stmt_transaction->bindParam(':new_status', $new_status_transaction, PDO::PARAM_STR);
        $stmt_transaction->bindParam(':quote_id', $id, PDO::PARAM_INT);
        $stmt_transaction->execute();
        $rows_transaction = $stmt_transaction->rowCount();
        
        error_log("Transaction table - Rows affected: $rows_transaction");
        
        if ($rows_transaction === 0) {
            error_log("WARNING: No transaction found for quote_id #$id, or already processed");
            // No lanzamos error aquí porque puede que no todas las cotizaciones tengan transacción
        } else {
            error_log("SUCCESS: Transaction updated for quote #$id");
        }
        
        error_log("SUCCESS: Quote #$id updated to '$new_status_cotization'");
        error_log("=== END PROCESSING QUOTE #$id ===");
        
    } elseif ($type === 'consulting') {
        // ==========================================
        // PROCESAR RESERVA DE ASESORÍA
        // ==========================================
        $new_status = ($action === 'approve') ? 'approved' : 'rejected';
        $message = ($action === 'approve') 
            ? "Consultation reservation #$id approved successfully" 
            : "Consultation reservation #$id rejected";
        
        error_log("=== PROCESSING CONSULTING #$id ===");
        
        $sql = "UPDATE transaction 
                SET status = :new_status 
                WHERE transaction_id = :transaction_id 
                AND type_transaction = 'consulting'
                AND TRIM(status) = 'pending'";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':new_status', $new_status, PDO::PARAM_STR);
        $stmt->bindParam(':transaction_id', $id, PDO::PARAM_INT);
        $stmt->execute();
        
        if ($stmt->rowCount() === 0) {
            error_log("ERROR: Consultation not found or already processed");
            throw new Exception("Consultation reservation not found or already processed");
        }
        
        error_log("SUCCESS: Consulting #$id updated");
        error_log("=== END PROCESSING CONSULTING #$id ===");
    }
    
    // COMMIT LA TRANSACCIÓN
    error_log("Attempting to commit transaction...");
    $commit_result = $conn->commit();
    error_log("Commit result: " . ($commit_result ? 'SUCCESS' : 'FAILED'));
    
    if (!$commit_result) {
        throw new Exception("Failed to commit transaction to database");
    }
    
    error_log("Transaction committed successfully. Changes should be saved.");
    
    header('Location: approve_reservations.php?success=' . urlencode($message));
    exit;
    
} catch (PDOException $e) {
    $conn->rollBack();
    error_log("Database error in process_reservation: " . $e->getMessage());
    header('Location: approve_reservations.php?error=' . urlencode('Database error occurred'));
    exit;
} catch (Exception $e) {
    $conn->rollBack();
    error_log("Error in process_reservation: " . $e->getMessage());
    header('Location: approve_reservations.php?error=' . urlencode($e->getMessage()));
    exit;
}
?>