<?php
header('Content-Type: application/json');
require_once 'db.php';

try {
    $conn = getDbConnection();
    
    $user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
    
    if ($user_id <= 0) {
        echo json_encode(["success" => false, "message" => "Invalid user ID"]);
        exit;
    }
    
    // Obtener datos del owner desde person y users
    $sql = "SELECT 
                p.person_id,
                p.full_name,
                p.identification_number,
                p.phone_number,
                u.email,
                u.role
            FROM users u
            LEFT JOIN person p ON u.user_id = p.user_id
            WHERE u.user_id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$user_id]);
    $owner = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($owner) {
        echo json_encode([
            "success" => true,
            "data" => $owner
        ]);
    } else {
        echo json_encode([
            "success" => false,
            "message" => "Owner not found"
        ]);
    }

} catch (PDOException $e) {
    error_log("DB Error: " . $e->getMessage());
    echo json_encode([
        "success" => false,
        "message" => "Database error: " . $e->getMessage()
    ]);
}
?>