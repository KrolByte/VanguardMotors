<?php
header('Content-Type: application/json');
require_once __DIR__ . '/db.php'; 

try {
    $conn = getDbConnection();

    // ✅ Query con más información para debug
    $query = "
        SELECT 
            e.employed_id AS advisor_id, 
            e.full_name,
            e.user_id,
            u.email,
            u.role
        FROM employed e
        JOIN users u ON e.user_id = u.user_id
        WHERE u.role = 'advisor'
        ORDER BY e.full_name
    ";

    $stmt = $conn->prepare($query);
    $stmt->execute();
    $advisors = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ✅ LOG detallado
    error_log("=== GET_ADVISORS DEBUG ===");
    error_log("Total asesores encontrados: " . count($advisors));
    error_log("Datos: " . json_encode($advisors));

    if (count($advisors) === 0) {
        // Hacer query adicional para debug
        $debug_query = "
            SELECT COUNT(*) as total_employed FROM employed;
            SELECT COUNT(*) as total_advisors FROM users WHERE role = 'advisor';
        ";
        
        echo json_encode([
            'success' => false,
            'data' => [],
            'message' => 'No hay empleados vinculados a usuarios con rol "advisor"',
            'debug' => [
                'query_executed' => $query,
                'found_rows' => 0
            ]
        ]);
        exit;
    }

    // Formatear respuesta
    $formatted_advisors = array_map(function($a) {
        return [
            'advisor_id' => (int)$a['advisor_id'],
            'full_name' => $a['full_name'],
            // Incluir email solo para debug
            'email' => $a['email']
        ];
    }, $advisors);

    echo json_encode([
        'success' => true,
        'data' => $formatted_advisors,
        'total' => count($formatted_advisors)
    ]);

} catch (PDOException $e) {
    error_log("Error DB en get_advisors: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error de base de datos',
        'error' => $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("Error general en get_advisors: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error del servidor',
        'error' => $e->getMessage()
    ]);
}
?>