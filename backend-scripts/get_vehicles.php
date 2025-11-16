<?php
// Archivo: backend_scripts/get_vehicles.php

require_once 'db.php'; // Usa el db.php que ya funciona

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: * ');

if ($_SERVER["REQUEST_METHOD"] == "GET" ){
    try {
        $pdo = getDbConnection();

        // Consulta SQL usando STRING_AGG para PostgreSQL
        $sql_vehicles = "SELECT 
                            v.vehicle_id,
                            v.brand,
                            v.model,
                            v.year,
                            v.color,
                            v.price,
                            v.availability,
                            -- Usamos STRING_AGG para obtener todas las URLs de imagen
                            STRING_AGG(vi.image_url, ',') as images 
                         FROM vehicle v
                         LEFT JOIN vehicle_image vi ON v.vehicle_id = vi.vehicle_id
                         -- Aseguramos que solo se muestren los vehículos disponibles o de interés
                         WHERE v.availability != 'sold' AND v.availability != 'maintenance'
                         GROUP BY v.vehicle_id 
                         ORDER BY v.vehicle_id ASC";

        $stmt = $pdo->prepare($sql_vehicles);
        $stmt->execute();
        $vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $formatted_vehicles = [];
        
        foreach ($vehicles as $vehicle) {
            
            // Procesar imágenes y obtener la imagen principal (la primera en la cadena)
            $images = [];
            if (!empty($vehicle['images'])) {
                $images = array_map('trim', explode(',', $vehicle['images']));
                // Filtramos cualquier URL vacía que pueda quedar
                $images = array_filter($images);
            }
            
            // Mapear disponibilidad para texto en el Front-End
            $availability_map = [
                'available' => 'Available',
                'sold' => 'Sold',
                'reserved' => 'Reserved',
                'maintenance' => 'In Maintenance'
            ];

            $formatted_vehicles[] = [
                'vehicle_id' => (int)$vehicle['vehicle_id'],
                'brand' => $vehicle['brand'],
                'model' => $vehicle['model'],
                'year' => (int)$vehicle['year'],
                'color' => $vehicle['color'],
                'price' => (float)$vehicle['price'],
                // Texto legible para el usuario
                'availability' => $availability_map[$vehicle['availability']] ?? ucfirst($vehicle['availability']),
                // Estado para las clases CSS (success, danger, etc.)
                'availability_status' => $vehicle['availability'], 
                // Usamos la primera imagen como principal (debe ser la que tiene is_main=TRUE)
                'primary_image' => $images[0] ?? 'img/default_car.png' 
            ];
        }

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'data' => $formatted_vehicles,
            'count' => count($formatted_vehicles)
        ]);

    } catch (Exception $e) {
        // Aseguramos que el error de DB se capture y se envíe al Front-End
        error_log("Error en get_vehicles: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error retrieving data from server. Details: ' . $e->getMessage()
        ]);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}

data.data.forEach(vehicle => {
    
    let imageUrl = vehicle.primary_image;
    
    // Si la URL es local y comienza con /, quitamos el / y añadimos el prefijo de la carpeta
    if (imageUrl.startsWith('/img/')) {
        // Asume que VMPage es la carpeta raíz del proyecto
        imageUrl = 'img/' + imageUrl.substring(5); // Remueve "/img/"
    }

    // Usar la ruta corregida en el HTML:
    htmlContent += `
        <img class="img-fluid mb-4" src="${imageUrl}" alt="${vehicle.brand} ${vehicle.model}">
    `;
});
?>