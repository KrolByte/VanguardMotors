<?php
// ========================================
// IMPORTANTE: Deshabilitar salida de errores HTML
// ========================================
error_reporting(E_ALL);
ini_set('display_errors', 0); // No mostrar errores en pantalla
ini_set('log_errors', 1); // Sí registrar en log
ini_set('error_log', __DIR__ . '/php_errors.log'); // Log personalizado

// Limpiar cualquier salida previa
ob_start();

header('Content-Type: application/json');
require_once 'db.php';

try {
    $conn = getDbConnection();
    
    // Obtener user_id (en lugar de employed_id)
    $user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
    
    if ($user_id <= 0) {
        ob_end_clean(); // Limpiar buffer
        echo json_encode(["success" => false, "message" => "Invalid user ID"]);
        exit;
    }
    
    // Verificar que el usuario sea owner
    $sql_check = "SELECT role FROM users WHERE user_id = ?";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->execute([$user_id]);
    $user_role = $stmt_check->fetchColumn();
    
    if ($user_role !== 'owner') {
        ob_end_clean();
        echo json_encode(["success" => false, "message" => "Access denied: Only owner can view this dashboard"]);
        exit;
    }
    
    // =====================================================
    // 1. ESTADÍSTICAS GENERALES
    // =====================================================
    
    // Total empleados por rol
    $sql_employees = "
        SELECT 
            u.role,
            COUNT(*) as count
        FROM employed e
        INNER JOIN users u ON e.user_id = u.user_id
        GROUP BY u.role
    ";
    $stmt_emp = $conn->prepare($sql_employees);
    $stmt_emp->execute();
    $employees_stats = $stmt_emp->fetchAll(PDO::FETCH_ASSOC);
    
    $employee_summary = ['advisor' => 0, 'manager' => 0, 'owner' => 0, 'total' => 0];
    foreach ($employees_stats as $stat) {
        $employee_summary[$stat['role']] = (int)$stat['count'];
        $employee_summary['total'] += (int)$stat['count'];
    }
    
    // Total clientes (personas que no son empleados)
    $sql_customers = "
        SELECT COUNT(DISTINCT p.person_id) as total
        FROM person p
        LEFT JOIN employed e ON p.user_id = e.user_id
        WHERE e.employed_id IS NULL
    ";
    $stmt_cust = $conn->prepare($sql_customers);
    $stmt_cust->execute();
    $customers_total = (int)$stmt_cust->fetchColumn();
    
    // Estadísticas de vehículos
    $sql_vehicles = "
        SELECT 
            availability,
            COUNT(*) as count,
            SUM(price) as total_value
        FROM vehicle
        GROUP BY availability
    ";
    $stmt_veh = $conn->prepare($sql_vehicles);
    $stmt_veh->execute();
    $vehicles_stats = $stmt_veh->fetchAll(PDO::FETCH_ASSOC);
    
    $vehicle_summary = [
        'available' => ['count' => 0, 'value' => 0],
        'reserved' => ['count' => 0, 'value' => 0],
        'sold' => ['count' => 0, 'value' => 0],
        'unavailable' => ['count' => 0, 'value' => 0],
        'total_count' => 0,
        'total_inventory_value' => 0
    ];
    
    foreach ($vehicles_stats as $stat) {
        $status = $stat['availability'];
        if (isset($vehicle_summary[$status])) {
            $vehicle_summary[$status]['count'] = (int)$stat['count'];
            $vehicle_summary[$status]['value'] = (float)$stat['total_value'];
        }
        $vehicle_summary['total_count'] += (int)$stat['count'];
        if ($status !== 'sold') {
            $vehicle_summary['total_inventory_value'] += (float)$stat['total_value'];
        }
    }
    
    // Estadísticas de reservas
    $sql_reservations = "
        SELECT 
            status,
            COUNT(*) as count,
            SUM(reservation_price) as total_fees
        FROM transaction
        WHERE type_transaction = 'consulting'
        GROUP BY status
    ";
    $stmt_res = $conn->prepare($sql_reservations);
    $stmt_res->execute();
    $reservations_stats = $stmt_res->fetchAll(PDO::FETCH_ASSOC);
    
    $reservation_summary = [
        'pending' => 0,
        'approved' => 0,
        'completed' => 0,
        'rejected' => 0,
        'total' => 0,
        'total_fees' => 0
    ];
    
    foreach ($reservations_stats as $stat) {
        if (isset($reservation_summary[$stat['status']])) {
            $reservation_summary[$stat['status']] = (int)$stat['count'];
        }
        $reservation_summary['total'] += (int)$stat['count'];
        $reservation_summary['total_fees'] += (float)($stat['total_fees'] ?? 0);
    }
    
    // Ingresos totales por ventas
    $sql_sales_revenue = "
        SELECT 
            COUNT(*) as total_sales,
            SUM(v.price) as total_revenue
        FROM vehicle v
        WHERE v.availability = 'sold'
    ";
    $stmt_sales = $conn->prepare($sql_sales_revenue);
    $stmt_sales->execute();
    $sales_data = $stmt_sales->fetch(PDO::FETCH_ASSOC);
    
    // =====================================================
    // 2. LISTADO COMPLETO DE EMPLEADOS
    // =====================================================
    $sql_all_employees = "
        SELECT 
            e.employed_id,
            e.identification_number,
            e.full_name,
            e.phone_number,
            u.email,
            u.role,
            COUNT(DISTINCT t.transaction_id) as total_reservations,
            COUNT(CASE WHEN t.status = 'completed' THEN 1 END) as completed_reservations
        FROM employed e
        INNER JOIN users u ON e.user_id = u.user_id
        LEFT JOIN transaction t ON e.employed_id = t.advisor_id AND t.type_transaction = 'consulting'
        GROUP BY e.employed_id, e.identification_number, e.full_name, e.phone_number, u.email, u.role
        ORDER BY u.role, e.full_name
    ";
    $stmt_all_emp = $conn->prepare($sql_all_employees);
    $stmt_all_emp->execute();
    $all_employees = $stmt_all_emp->fetchAll(PDO::FETCH_ASSOC);
    
    // =====================================================
    // 3. LISTADO DE CLIENTES (TOP 50 MÁS RECIENTES)
    // =====================================================
    $sql_customers_list = "
        SELECT 
            p.person_id,
            p.identification_number,
            p.full_name,
            p.phone_number,
            u.email,
            COUNT(t.transaction_id) as total_reservations,
            MAX(t.creation_date) as last_reservation_date
        FROM person p
        INNER JOIN users u ON p.user_id = u.user_id
        LEFT JOIN employed e ON p.user_id = e.user_id
        LEFT JOIN transaction t ON p.person_id = t.person_id
        WHERE e.employed_id IS NULL
        GROUP BY p.person_id, p.identification_number, p.full_name, p.phone_number, u.email
        ORDER BY last_reservation_date DESC NULLS LAST
        LIMIT 50
    ";
    $stmt_cust_list = $conn->prepare($sql_customers_list);
    $stmt_cust_list->execute();
    $customers_list = $stmt_cust_list->fetchAll(PDO::FETCH_ASSOC);
    
    // =====================================================
    // 4. HISTORIAL DE RESERVAS (ÚLTIMAS 100)
    // =====================================================
    $sql_reservations_history = "
        SELECT 
            t.transaction_id,
            TO_CHAR(t.creation_date, 'YYYY-MM-DD') as creation_date,
            TO_CHAR(t.appointment_date, 'YYYY-MM-DD') as appointment_date,
            t.appointment_time::text as appointment_time,
            t.status,
            t.reservation_price,
            p.full_name as customer_name,
            p.identification_number as customer_id,
            v.brand || ' ' || v.model || ' ' || v.year::text as vehicle_info,
            v.price as vehicle_price,
            e.full_name as advisor_name
        FROM transaction t
        INNER JOIN person p ON t.person_id = p.person_id
        INNER JOIN vehicle v ON t.vehicle_id = v.vehicle_id
        LEFT JOIN employed e ON t.advisor_id = e.employed_id
        WHERE t.type_transaction = 'consulting'
        ORDER BY t.creation_date DESC, t.transaction_id DESC
        LIMIT 100
    ";
    $stmt_res_hist = $conn->prepare($sql_reservations_history);
    $stmt_res_hist->execute();
    $reservations_history = $stmt_res_hist->fetchAll(PDO::FETCH_ASSOC);
    
    // =====================================================
    // 5. VEHÍCULOS VENDIDOS (HISTORIAL DE VENTAS)
    // =====================================================
    $sql_sales_history = "
        SELECT 
            v.vehicle_id,
            v.brand,
            v.model,
            v.year,
            v.color,
            v.price,
            (SELECT vi.image_url FROM vehicle_image vi 
             WHERE vi.vehicle_id = v.vehicle_id AND vi.is_main = true LIMIT 1) as image,
            NULL as added_by
        FROM vehicle v
        WHERE v.availability = 'sold'
        ORDER BY v.vehicle_id DESC
    ";
    $stmt_sales_hist = $conn->prepare($sql_sales_history);
    $stmt_sales_hist->execute();
    $sales_history = $stmt_sales_hist->fetchAll(PDO::FETCH_ASSOC);
    
    // =====================================================
    // 6. MÉTRICAS DE RENDIMIENTO POR ASESOR
    // =====================================================
    $sql_advisor_performance = "
        SELECT 
            e.full_name as advisor_name,
            COUNT(t.transaction_id) as total_appointments,
            COUNT(CASE WHEN t.status = 'completed' THEN 1 END) as completed,
            COUNT(CASE WHEN t.status = 'approved' THEN 1 END) as approved,
            COUNT(CASE WHEN t.status = 'pending' THEN 1 END) as pending,
            COUNT(CASE WHEN t.status = 'rejected' THEN 1 END) as rejected,
            ROUND(
                CASE 
                    WHEN COUNT(t.transaction_id) > 0 
                    THEN (COUNT(CASE WHEN t.status = 'completed' THEN 1 END)::numeric / COUNT(t.transaction_id)::numeric) * 100
                    ELSE 0 
                END, 2
            ) as completion_rate
        FROM employed e
        INNER JOIN users u ON e.user_id = u.user_id
        LEFT JOIN transaction t ON e.employed_id = t.advisor_id AND t.type_transaction = 'consulting'
        WHERE u.role = 'advisor'
        GROUP BY e.employed_id, e.full_name
        ORDER BY completed DESC, total_appointments DESC
    ";
    $stmt_perf = $conn->prepare($sql_advisor_performance);
    $stmt_perf->execute();
    $advisor_performance = $stmt_perf->fetchAll(PDO::FETCH_ASSOC);
    
    // =====================================================
    // LIMPIAR BUFFER Y ENVIAR RESPUESTA
    // =====================================================
    ob_end_clean();
    
    echo json_encode([
        "success" => true,
        "data" => [
            "overview" => [
                "employees" => $employee_summary,
                "customers" => $customers_total,
                "vehicles" => $vehicle_summary,
                "reservations" => $reservation_summary,
                "sales" => [
                    "total_sales" => (int)($sales_data['total_sales'] ?? 0),
                    "total_revenue" => (float)($sales_data['total_revenue'] ?? 0)
                ]
            ],
            "employees_list" => $all_employees,
            "customers_list" => $customers_list,
            "reservations_history" => $reservations_history,
            "sales_history" => $sales_history,
            "advisor_performance" => $advisor_performance
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    ob_end_clean();
    error_log("DB Error in owner dashboard: " . $e->getMessage());
    echo json_encode([
        "success" => false,
        "message" => "Database error occurred. Check server logs.",
        "debug" => $e->getMessage() // Solo en desarrollo
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    ob_end_clean();
    error_log("General Error in owner dashboard: " . $e->getMessage());
    echo json_encode([
        "success" => false,
        "message" => "An error occurred: " . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>