<?php
// MOSTRAR errores para depuración (cambiar a 0 en producción)
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');

// Inicializar variables
$conn = null;

try {
    require_once __DIR__ . '/../env_loader.php';
} catch (Exception $e) {
    die("Error loading env_loader.php: " . $e->getMessage() . "<br>Path: " . __DIR__ . '/../env_loader.php');
}

try {
    require_once __DIR__ . '/db.php';
} catch (Exception $e) {
    die("Error loading db.php: " . $e->getMessage());
}

try {
    $conn = getDbConnection();
} catch (Exception $e) {
    die("Error connecting to database: " . $e->getMessage());
}

$alert_message = '';
$alert_type = '';

// Mensajes de éxito o error
if (isset($_GET['success'])) {
    $alert_message = htmlspecialchars($_GET['success']);
    $alert_type = 'success';
} elseif (isset($_GET['error'])) {
    $alert_message = htmlspecialchars($_GET['error']);
    $alert_type = 'error';
}

$pending_quotes = [];
$pending_consulting = [];
$total_pending = 0;

try {
    // ==========================================
    // COTIZACIONES PENDIENTES
    // ==========================================
    $sql_quotes = "
        SELECT 
            c.quote_id, 
            TO_CHAR(c.quote_date, 'YYYY-MM-DD') as quote_date,
            c.total_estimated,
            TO_CHAR(c.valid_until, 'YYYY-MM-DD') as valid_until,
            c.status,
            p.full_name AS client_name,
            p.phone_number as client_phone,
            v.brand,
            v.model,
            v.year
        FROM cotization c
        JOIN person p ON c.person_id = p.person_id
        JOIN vehicle v ON c.vehicle_id = v.vehicle_id
        WHERE c.status = 'Pendiente'
        ORDER BY c.quote_date ASC
    ";
    $stmt_quotes = $conn->prepare($sql_quotes);
    $stmt_quotes->execute();
    $pending_quotes = $stmt_quotes->fetchAll(PDO::FETCH_ASSOC);

    // ==========================================
    // RESERVAS DE ASESORÍA PENDIENTES
    // ==========================================
    $sql_consulting = "
        SELECT 
            t.transaction_id,
            TO_CHAR(t.creation_date, 'YYYY-MM-DD') as creation_date,
            TO_CHAR(t.appointment_date, 'YYYY-MM-DD') as appointment_date,
            t.appointment_time::text as appointment_time,
            t.reservation_price,
            t.status,
            p.full_name AS client_name,
            p.phone_number as client_phone,
            v.brand || ' ' || v.model || ' ' || v.year::text as vehicle_info,
            e.full_name as advisor_name
        FROM transaction t
        JOIN person p ON t.person_id = p.person_id
        JOIN vehicle v ON t.vehicle_id = v.vehicle_id
        LEFT JOIN employed e ON t.advisor_id = e.employed_id
        WHERE t.type_transaction = 'consulting' 
        AND t.status = 'pending'
        ORDER BY t.appointment_date ASC, t.appointment_time ASC
    ";
    $stmt_consulting = $conn->prepare($sql_consulting);
    $stmt_consulting->execute();
    $pending_consulting = $stmt_consulting->fetchAll(PDO::FETCH_ASSOC);

    $total_pending = count($pending_quotes) + count($pending_consulting);

} catch (PDOException $e) {
    error_log("Database error in approve_reservations: " . $e->getMessage());
    $alert_message = "Error loading data. Please try again later.";
    $alert_type = 'error';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Manager Dashboard - Vanguard Motors</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <link href="../img/favicon.ico" rel="icon">
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;500;600;700&family=Rubik&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.0/css/all.min.css" rel="stylesheet">
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <link href="../css/style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        .manager-header {
            background: linear-gradient(135deg, #e65100 0%, #ff6f00 100%);
            padding: 25px 0;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }
        .metric-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            transition: all 0.3s;
            height: 100%;
            border-left: 4px solid;
        }
        .metric-card:hover { 
            transform: translateY(-5px); 
            box-shadow: 0 5px 20px rgba(0,0,0,0.15); 
        }
        .metric-card.orange { border-left-color: #FF9800; }
        .metric-card.blue { border-left-color: #2196F3; }
        .metric-card.purple { border-left-color: #9C27B0; }
        .metric-icon {
            font-size: 3rem;
            opacity: 0.2;
            position: absolute;
            right: 20px;
            top: 20px;
        }
        .metric-value {
            font-size: 2.5rem;
            font-weight: 700;
            margin: 10px 0;
        }
        .metric-label {
            color: #666;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .section-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 25px;
        }
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e0e0e0;
        }
        .section-title {
            font-size: 1.4rem;
            font-weight: 600;
            color: #333;
            margin: 0;
        }
        .reservation-card {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s;
            background: #fafafa;
        }
        .reservation-card:hover {
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            background: white;
        }
        .badge-pending { 
            background: #fff3cd; 
            color: #856404; 
            padding: 6px 14px; 
            border-radius: 20px; 
            font-weight: 500;
            font-size: 0.85rem;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #f0f0f0;
        }
        .info-row:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        .info-label {
            color: #666;
            font-size: 0.9rem;
            font-weight: 500;
        }
        .info-value {
            color: #333;
            font-weight: 600;
        }
        .amount-highlight {
            color: #4CAF50;
            font-size: 1.2rem;
            font-weight: 700;
        }
        .action-buttons {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #e0e0e0;
            text-align: right;
        }
        .btn-approve {
            background: linear-gradient(135deg, #4CAF50, #66BB6A);
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 20px;
            font-weight: 500;
            transition: all 0.3s;
        }
        .btn-approve:hover {
            background: linear-gradient(135deg, #388E3C, #4CAF50);
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(76, 175, 80, 0.3);
        }
        .btn-reject {
            background: linear-gradient(135deg, #f44336, #e57373);
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 20px;
            font-weight: 500;
            transition: all 0.3s;
        }
        .btn-reject:hover {
            background: linear-gradient(135deg, #d32f2f, #f44336);
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(244, 67, 54, 0.3);
        }
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #999;
        }
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.3;
        }
    </style>
</head>

<body>
    <!-- Manager Header -->
    <div class="manager-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h2 class="text-white mb-0">
                        <i class="fas fa-user-tie mr-2"></i>Manager Dashboard
                    </h2>
                    <small class="text-white-50">Approval Panel - Vanguard Motors</small>
                </div>
                <div class="col-md-6 text-right">
                    <div class="d-inline-flex align-items-center bg-dark px-4 py-2 rounded">
                        <i class="fas fa-user-shield text-warning mr-2"></i>
                        <span class="text-white">Manager</span>
                        <span class="badge badge-warning ml-3">Manager</span>
                    </div>
                    <button class="btn btn-outline-danger ml-3" onclick="logout()">
                        <i class="fas fa-sign-out-alt mr-1"></i> Logout
                    </button>
                    <a href="../index.html" class="btn btn-outline-light ml-2">
                        <i class="fas fa-home mr-1"></i> Site
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Metrics -->
    <div class="container my-4">
        <div class="row">
            <div class="col-lg-4 col-md-6 mb-3">
                <div class="metric-card orange">
                    <i class="fas fa-file-invoice-dollar metric-icon"></i>
                    <div class="metric-label">Pending Quotes</div>
                    <div class="metric-value"><?php echo count($pending_quotes); ?></div>
                    <small class="text-muted">Awaiting approval</small>
                </div>
            </div>
            <div class="col-lg-4 col-md-6 mb-3">
                <div class="metric-card blue">
                    <i class="fas fa-calendar-check metric-icon"></i>
                    <div class="metric-label">Pending Consultations</div>
                    <div class="metric-value"><?php echo count($pending_consulting); ?></div>
                    <small class="text-muted">Awaiting approval</small>
                </div>
            </div>
            <div class="col-lg-4 col-md-6 mb-3">
                <div class="metric-card purple">
                    <i class="fas fa-tasks metric-icon"></i>
                    <div class="metric-label">Total Pending</div>
                    <div class="metric-value"><?php echo $total_pending; ?></div>
                    <small class="text-muted">Requires action</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Content -->
    <div class="container mb-5">
        <!-- QUOTES SECTION -->
        <div class="section-card">
            <div class="section-header">
                <h5 class="section-title">
                    <i class="fas fa-file-invoice-dollar mr-2"></i>Pending Quotes
                </h5>
                <button class="btn btn-sm btn-outline-primary" onclick="location.reload()">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
            </div>
            
            <?php if (count($pending_quotes) > 0): ?>
                <?php foreach ($pending_quotes as $q): ?>
                    <div class="reservation-card">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <strong style="font-size: 1.1rem; color: #FF9800;">
                                <i class="fas fa-file-alt mr-2"></i>Quote #<?php echo $q['quote_id']; ?>
                            </strong>
                            <span class="badge-pending">
                                <i class="fas fa-clock mr-1"></i>Pending
                            </span>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="info-row">
                                    <span class="info-label">
                                        <i class="fas fa-user mr-2"></i>Customer
                                    </span>
                                    <span class="info-value"><?php echo htmlspecialchars($q['client_name']); ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">
                                        <i class="fas fa-phone mr-2"></i>Phone
                                    </span>
                                    <span class="info-value"><?php echo htmlspecialchars($q['client_phone'] ?? 'N/A'); ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">
                                        <i class="fas fa-car mr-2"></i>Vehicle
                                    </span>
                                    <span class="info-value"><?php echo htmlspecialchars($q['brand'] . ' ' . $q['model'] . ' ' . $q['year']); ?></span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-row">
                                    <span class="info-label">
                                        <i class="fas fa-calendar mr-2"></i>Quote Date
                                    </span>
                                    <span class="info-value"><?php echo $q['quote_date']; ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">
                                        <i class="fas fa-calendar-times mr-2"></i>Valid Until
                                    </span>
                                    <span class="info-value"><?php echo $q['valid_until']; ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">
                                        <i class="fas fa-dollar-sign mr-2"></i>Amount
                                    </span>
                                    <span class="amount-highlight">$<?php echo number_format($q['total_estimated'], 0); ?></span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="action-buttons">
                            <form method="POST" action="process_reservation.php" style="display:inline;">
                                <input type="hidden" name="type" value="quote">
                                <input type="hidden" name="id" value="<?php echo $q['quote_id']; ?>">
                                <input type="hidden" name="action" value="approve">
                                <button type="submit" class="btn btn-approve" onclick="return confirmAction('approve', 'quote', <?php echo $q['quote_id']; ?>)">
                                    <i class="fas fa-check mr-1"></i> Approve
                                </button>
                            </form>
                            <form method="POST" action="process_reservation.php" style="display:inline;">
                                <input type="hidden" name="type" value="quote">
                                <input type="hidden" name="id" value="<?php echo $q['quote_id']; ?>">
                                <input type="hidden" name="action" value="reject">
                                <button type="submit" class="btn btn-reject" onclick="return confirmAction('reject', 'quote', <?php echo $q['quote_id']; ?>)">
                                    <i class="fas fa-times mr-1"></i> Reject
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h5>No Pending Quotes</h5>
                    <p class="text-muted">All quotes have been processed</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- CONSULTATIONS SECTION -->
        <div class="section-card">
            <div class="section-header">
                <h5 class="section-title">
                    <i class="fas fa-calendar-check mr-2"></i>Pending Consultations
                </h5>
                <button class="btn btn-sm btn-outline-primary" onclick="location.reload()">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
            </div>
            
            <?php if (count($pending_consulting) > 0): ?>
                <?php foreach ($pending_consulting as $c): ?>
                    <div class="reservation-card">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <strong style="font-size: 1.1rem; color: #2196F3;">
                                <i class="fas fa-handshake mr-2"></i>Consultation #<?php echo $c['transaction_id']; ?>
                            </strong>
                            <span class="badge-pending">
                                <i class="fas fa-clock mr-1"></i>Pending
                            </span>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="info-row">
                                    <span class="info-label">
                                        <i class="fas fa-user mr-2"></i>Customer
                                    </span>
                                    <span class="info-value"><?php echo htmlspecialchars($c['client_name']); ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">
                                        <i class="fas fa-phone mr-2"></i>Phone
                                    </span>
                                    <span class="info-value"><?php echo htmlspecialchars($c['client_phone'] ?? 'N/A'); ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">
                                        <i class="fas fa-car mr-2"></i>Vehicle
                                    </span>
                                    <span class="info-value"><?php echo htmlspecialchars($c['vehicle_info']); ?></span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-row">
                                    <span class="info-label">
                                        <i class="fas fa-calendar-plus mr-2"></i>Created
                                    </span>
                                    <span class="info-value"><?php echo $c['creation_date']; ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">
                                        <i class="fas fa-calendar-day mr-2"></i>Appointment
                                    </span>
                                    <span class="info-value"><?php echo $c['appointment_date'] . ' ' . substr($c['appointment_time'], 0, 5); ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">
                                        <i class="fas fa-user-tie mr-2"></i>Advisor
                                    </span>
                                    <span class="info-value"><?php echo htmlspecialchars($c['advisor_name'] ?? 'Not assigned'); ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">
                                        <i class="fas fa-dollar-sign mr-2"></i>Fee
                                    </span>
                                    <span class="amount-highlight">$<?php echo number_format($c['reservation_price'], 0); ?></span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="action-buttons">
                            <form method="POST" action="process_reservation.php" style="display:inline;">
                                <input type="hidden" name="type" value="consulting">
                                <input type="hidden" name="id" value="<?php echo $c['transaction_id']; ?>">
                                <input type="hidden" name="action" value="approve">
                                <button type="submit" class="btn btn-approve" onclick="return confirmAction('approve', 'consultation', <?php echo $c['transaction_id']; ?>)">
                                    <i class="fas fa-check mr-1"></i> Approve
                                </button>
                            </form>
                            <form method="POST" action="process_reservation.php" style="display:inline;">
                                <input type="hidden" name="type" value="consulting">
                                <input type="hidden" name="id" value="<?php echo $c['transaction_id']; ?>">
                                <input type="hidden" name="action" value="reject">
                                <button type="submit" class="btn btn-reject" onclick="return confirmAction('reject', 'consultation', <?php echo $c['transaction_id']; ?>)">
                                    <i class="fas fa-times mr-1"></i> Reject
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h5>No Pending Consultations</h5>
                    <p class="text-muted">All consultations have been processed</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Footer -->
    <div class="container-fluid bg-secondary py-4 px-sm-3 px-md-5 mt-5">
        <div class="row pt-4">
            <div class="col-12 text-center">
                <p class="mb-2 text-body">&copy; <a href="#">VanguardMotors</a>. All Rights Reserved.</p>
                <p class="m-0 text-body">Designed by K2ES SD</p>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.4.1.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.4.1/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Mostrar alerta si existe
        <?php if ($alert_message): ?>
            Swal.fire({
                icon: '<?php echo $alert_type; ?>',
                title: '<?php echo $alert_type === "success" ? "Success!" : "Error"; ?>',
                text: '<?php echo addslashes($alert_message); ?>',
                confirmButtonColor: '<?php echo $alert_type === "success" ? "#4CAF50" : "#f44336"; ?>',
                timer: 4000
            });
        <?php endif; ?>

        function confirmAction(action, type, id) {
            event.preventDefault();
            const form = event.target.closest('form');
            
            Swal.fire({
                title: `${action.charAt(0).toUpperCase() + action.slice(1)} ${type}?`,
                text: `Are you sure you want to ${action} ${type} #${id}?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: action === 'approve' ? '#4CAF50' : '#f44336',
                cancelButtonColor: '#999',
                confirmButtonText: `Yes, ${action}!`,
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
            
            return false;
        }

        function logout() {
            Swal.fire({
                title: 'Logout?',
                text: 'Are you sure you want to logout?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                confirmButtonText: 'Yes, logout',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '../index.html';
                }
            });
        }
    </script>
</body>
</html>