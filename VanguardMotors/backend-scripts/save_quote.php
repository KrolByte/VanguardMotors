<?php
// Incluir el archivo de configuración de la conexión
require_once 'db_config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // 1. Recolección y Limpieza de Datos
    // Se asume que el formulario de la cotización incluye campos para PERSON y COTIZATION.
    $full_name          = trim($_POST['full_name']);
    $phone_number       = trim($_POST['phone_number']);
    $identification_num = trim($_POST['identification_num']); // Campo de la tabla PERSON
    
    // Datos de la Cotización
    $vehicle_id         = trim($_POST['vehicle_id']);
    $total_estimated    = trim($_POST['total_estimated']);
    $taxes              = trim($_POST['taxes']);
    
    try {
        // Iniciar una transacción para asegurar que ambas inserciones (PERSON y COTIZATION) se completen o fallen juntas
        $pdo->beginTransaction();

        // ==========================================================
        // A. INSERCIÓN EN LA TABLA PERSON
        // ==========================================================
        $sql_person = "INSERT INTO PERSON (identification_num, full_name, phone_number) 
                       VALUES (:identification_num, :full_name, :phone_number) 
                       RETURNING person_id"; // RETURNING devuelve el ID que acabamos de crear

        $stmt_person = $pdo->prepare($sql_person);
        $stmt_person->execute([
            ':identification_num' => $identification_num,
            ':full_name' => $full_name,
            ':phone_number' => $phone_number
        ]);

        // Obtener el ID de la persona recién insertada para usarlo como FK
        $person_id = $stmt_person->fetchColumn(); 

        // ==========================================================
        // B. INSERCIÓN EN LA TABLA COTIZATION
        // ==========================================================
        $sql_cotization = "INSERT INTO COTIZATION (person_id, vehicle_id, quote_date, taxes, total_estimated, valid_until) 
                           VALUES (:person_id, :vehicle_id, NOW(), :taxes, :total_estimated, NOW() + INTERVAL '30 days') 
                           RETURNING id_cotization";
        
        $stmt_cotization = $pdo->prepare($sql_cotization);
        $stmt_cotization->execute([
            ':person_id' => $person_id,
            ':vehicle_id' => $vehicle_id,
            ':taxes' => $taxes,
            ':total_estimated' => $total_estimated
        ]);

        $cotization_id = $stmt_cotization->fetchColumn();

        // Si todo va bien, confirmar la transacción
        $pdo->commit();
        
        // Redirigir al usuario a una página de éxito
        header("Location: ../success.html?quote_id={$cotization_id}");
        exit();

    } catch (Exception $e) {
        // Si algo falla, revertir todos los cambios de la transacción
        $pdo->rollBack();
        
        // Redirigir a una página de error (puedes crear una error.html)
        header("Location: ../error.html?message=" . urlencode("Error al guardar la cotización."));
        exit();
    }
} else {
    // Si alguien intenta acceder directamente a este script
    header("Location: ../index.html");
    exit();
}
?>