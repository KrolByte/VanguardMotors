<?php
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';

try {
    // Obtener el ID del asesor desde la URL
    $advisor_id = isset($_GET['advisor_id']) ? intval($_GET['advisor_id']) : 0;

    // ✅ Si no hay advisor_id, devolver array vacío (no error)
    if ($advisor_id <= 0) {
        echo json_encode([
            'success' => true,
            'data' => [] // Sin citas reservadas
        ]);
        exit;
    }

    $conn = getDbConnection();

    // Obtener todas las citas confirmadas o pendientes para este asesor
    $query = "
        SELECT 
            appointment_date,
            appointment_time
        FROM transaction
        WHERE advisor_id = :advisor_id
          AND type_transaction = 'consulting'
          AND status IN ('pending', 'approved')
          AND appointment_date >= CURRENT_DATE
        ORDER BY appointment_date, appointment_time
    ";

    $stmt = $conn->prepare($query);
    $stmt->execute([':advisor_id' => $advisor_id]);
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Formatear los datos como { 'YYYY-MM-DD': ['HH:MM', 'HH:MM'], ... }
    $booked_dates = [];
    foreach ($appointments as $apt) {
        $date = $apt['appointment_date'];
        $time = substr($apt['appointment_time'], 0, 5); // 'HH:MM'
        
        if (!isset($booked_dates[$date])) {
            $booked_dates[$date] = [];
        }
        $booked_dates[$date][] = $time;
    }

    echo json_encode([
        'success' => true,
        'data' => $booked_dates
    ]);

} catch (Exception $e) {
    error_log("Error en get_advisor_availability: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al obtener disponibilidad: ' . $e->getMessage()
    ]);
}
?>