<?php
header('Content-Type: application/json');
require_once 'db.php';

try {
    $conn = getDbConnection();
    
    // Obtener advisor_id del parámetro
    $advisor_id = isset($_GET['advisor_id']) ? (int)$_GET['advisor_id'] : 0;
    
    if ($advisor_id <= 0) {
        echo json_encode(["success" => false, "message" => "Invalid advisor ID"]);
        exit;
    }
    
    // Filtro opcional por estado
    $status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
    
    // Query para obtener reservas del asesor
    // IMPORTANTE: Usar TO_CHAR para evitar problemas de zona horaria con las fechas
    $sql = "SELECT 
                t.transaction_id,
                t.type_transaction,
                t.status,
                TO_CHAR(t.creation_date, 'YYYY-MM-DD') as creation_date,
                TO_CHAR(t.appointment_date, 'YYYY-MM-DD') as appointment_date,
                t.appointment_time::text as appointment_time,
                t.reservation_price,
                t.vehicle_id,
                t.person_id,
                t.advisor_id,
                -- Datos del cliente
                p.full_name AS customer_name,
                p.phone_number AS customer_phone,
                p.identification_number AS customer_id_number,
                -- Datos del vehículo
                v.brand,
                v.model,
                v.year,
                v.color,
                v.price AS vehicle_price,
                -- Imagen principal del vehículo
                (SELECT vi.image_url 
                 FROM vehicle_image vi 
                 WHERE vi.vehicle_id = v.vehicle_id AND vi.is_main = true 
                 LIMIT 1) AS vehicle_image
            FROM transaction t
            INNER JOIN person p ON t.person_id = p.person_id
            INNER JOIN vehicle v ON t.vehicle_id = v.vehicle_id
            WHERE t.advisor_id = ?
            AND t.type_transaction = 'consulting'";
    
    $params = [$advisor_id];
    
    // Agregar filtro de estado si existe
    if (!empty($status_filter) && $status_filter !== 'all') {
        $sql .= " AND t.status = ?";
        $params[] = $status_filter;
    }
    
    $sql .= " ORDER BY t.appointment_date DESC, t.appointment_time DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formatear datos para el frontend
    $formatted = array_map(function($r) {
        return [
            'transaction_id' => $r['transaction_id'],
            'type' => $r['type_transaction'],
            'status' => $r['status'],
            'status_label' => ucfirst($r['status']),
            'creation_date' => $r['creation_date'],
            'appointment_date' => $r['appointment_date'],
            'appointment_time' => $r['appointment_time'],
            'appointment_datetime' => $r['appointment_date'] . ' ' . $r['appointment_time'],
            'reservation_price' => $r['reservation_price'],
            // Cliente
            'customer' => [
                'person_id' => $r['person_id'],
                'name' => $r['customer_name'],
                'phone' => $r['customer_phone'],
                'id_number' => $r['customer_id_number']
            ],
            // Vehículo
            'vehicle' => [
                'vehicle_id' => $r['vehicle_id'],
                'brand' => $r['brand'],
                'model' => $r['model'],
                'year' => $r['year'],
                'color' => $r['color'],
                'price' => $r['vehicle_price'],
                'image' => $r['vehicle_image'] ?? 'img/no-image.jpg'
            ]
        ];
    }, $reservations);
    
    // Contar por estado para estadísticas
    $sql_stats = "SELECT status, COUNT(*) as count 
                  FROM transaction 
                  WHERE advisor_id = ? AND type_transaction = 'consulting'
                  GROUP BY status";
    $stmt_stats = $conn->prepare($sql_stats);
    $stmt_stats->execute([$advisor_id]);
    $stats_raw = $stmt_stats->fetchAll(PDO::FETCH_ASSOC);
    
    $stats = [
        'pending' => 0,
        'approved' => 0,
        'rejected' => 0,
        'completed' => 0,
        'total' => count($reservations)
    ];
    
    foreach ($stats_raw as $s) {
        if (isset($stats[$s['status']])) {
            $stats[$s['status']] = (int)$s['count'];
        }
    }
    
    echo json_encode([
        "success" => true,
        "data" => $formatted,
        "stats" => $stats,
        "count" => count($formatted)
    ]);

} catch (PDOException $e) {
    error_log("DB Error: " . $e->getMessage());
    echo json_encode([
        "success" => false,
        "message" => "Database error: " . $e->getMessage()
    ]);
}
?>