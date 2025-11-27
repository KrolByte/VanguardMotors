<?php
// Mostrar TODOS los errores
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h2>Testing Manager Panel Setup</h2>";
echo "<hr>";

// Test 1: Cargar env_loader
echo "<p><strong>Test 1:</strong> Loading env_loader.php...</p>";
try {
    require_once __DIR__ . '/../env_loader.php';
    echo "<p style='color:green'>✓ env_loader.php loaded successfully</p>";
} catch (Exception $e) {
    echo "<p style='color:red'>✗ Error loading env_loader.php: " . $e->getMessage() . "</p>";
    die();
}

// Test 2: Cargar db.php
echo "<p><strong>Test 2:</strong> Loading db.php...</p>";
try {
    require_once __DIR__ . '/db.php';
    echo "<p style='color:green'>✓ db.php loaded successfully</p>";
} catch (Exception $e) {
    echo "<p style='color:red'>✗ Error loading db.php: " . $e->getMessage() . "</p>";
    die();
}

// Test 3: Conectar a la base de datos
echo "<p><strong>Test 3:</strong> Connecting to database...</p>";
try {
    $conn = getDbConnection();
    echo "<p style='color:green'>✓ Database connection successful</p>";
} catch (Exception $e) {
    echo "<p style='color:red'>✗ Database connection failed: " . $e->getMessage() . "</p>";
    die();
}

// Test 4: Query de cotizaciones
echo "<p><strong>Test 4:</strong> Testing cotization query...</p>";
try {
    $sql = "SELECT COUNT(*) as total FROM cotization WHERE status = 'Pendiente'";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<p style='color:green'>✓ Cotization query successful. Pending quotes: " . $result['total'] . "</p>";
} catch (PDOException $e) {
    echo "<p style='color:red'>✗ Cotization query failed: " . $e->getMessage() . "</p>";
}

// Test 5: Query de consultas
echo "<p><strong>Test 5:</strong> Testing transaction query...</p>";
try {
    $sql = "SELECT COUNT(*) as total FROM transaction WHERE type_transaction = 'consulting' AND status = 'pending'";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<p style='color:green'>✓ Transaction query successful. Pending consultations: " . $result['total'] . "</p>";
} catch (PDOException $e) {
    echo "<p style='color:red'>✗ Transaction query failed: " . $e->getMessage() . "</p>";
}

// Test 6: Query completa de cotizaciones
echo "<p><strong>Test 6:</strong> Testing full quotes query...</p>";
try {
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
        LIMIT 5
    ";
    $stmt = $conn->prepare($sql_quotes);
    $stmt->execute();
    $quotes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<p style='color:green'>✓ Full quotes query successful. Found " . count($quotes) . " quotes</p>";
    
    if (count($quotes) > 0) {
        echo "<pre>";
        print_r($quotes[0]); // Mostrar el primer resultado
        echo "</pre>";
    }
} catch (PDOException $e) {
    echo "<p style='color:red'>✗ Full quotes query failed: " . $e->getMessage() . "</p>";
    echo "<p><small>SQL Error Code: " . $e->getCode() . "</small></p>";
}

// Test 7: Query completa de consultas
echo "<p><strong>Test 7:</strong> Testing full consultations query...</p>";
try {
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
        LIMIT 5
    ";
    $stmt = $conn->prepare($sql_consulting);
    $stmt->execute();
    $consultations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<p style='color:green'>✓ Full consultations query successful. Found " . count($consultations) . " consultations</p>";
    
    if (count($consultations) > 0) {
        echo "<pre>";
        print_r($consultations[0]); // Mostrar el primer resultado
        echo "</pre>";
    }
} catch (PDOException $e) {
    echo "<p style='color:red'>✗ Full consultations query failed: " . $e->getMessage() . "</p>";
    echo "<p><small>SQL Error Code: " . $e->getCode() . "</small></p>";
}

echo "<hr>";
echo "<h3>All tests completed!</h3>";
echo "<p>If all tests passed, your approve_reservations.php should work.</p>";
?>