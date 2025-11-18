<?php
// 1. Configuración y Conexión a la Base de Datos
require_once 'conexion.php'; // ASEGÚRATE que 'conexion.php' esté en la misma carpeta

// Inicializar variables para mensajes de alerta
$alert_message = '';
$alert_class = '';

// Manejo de mensajes de éxito o error enviados desde process_reservation.php
if (isset($_GET['success'])) {
    $alert_message = htmlspecialchars($_GET['success']);
    $alert_class = 'alert-success';
} elseif (isset($_GET['error'])) {
    $alert_message = htmlspecialchars($_GET['error']);
    $alert_class = 'alert-danger';
}

try {
    // 2. Consulta para obtener las cotizaciones/reservas pendientes
    // Filtramos SOLO las que tienen status = 'Pendiente'
    // JOIN con person para obtener el nombre del cliente.
    // JOIN con vehicle para obtener el modelo del vehículo.
 $sql = "
        SELECT 
            c.quote_id, 
            c.quote_date, 
            c.total_estimated,
            c.status,
            p.full_name AS client_name,
            v.brand,
            v.model
        FROM 
            public.cotization c
        JOIN 
            public.person p ON c.person_id = p.person_id
        JOIN 
            public.vehicle v ON c.vehicle_id = v.vehicle_id
        WHERE 
            c.status = 'Pendiente'
        ORDER BY 
            c.quote_date ASC;
    ";
    
    // **CAMBIO CLAVE:** Usamos prepare y execute en lugar de query()
   $stmt = $conexion->prepare($sql);
    $stmt->execute();
    $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // Manejo de error de conexión o consulta
    $alert_message = "Error de base de datos: " . $e->getMessage();
    $alert_class = 'alert-danger';
    $reservations = []; 
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Aprobar Reservas - Vanguard Motors</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { padding-top: 20px; }
    </style>
</head>
<body>

    <div class="container">
        <h2 class="mb-4">Panel de Gerente - Reservas Pendientes</h2>

        <?php if ($alert_message): ?>
            <div class="alert <?php echo $alert_class; ?> alert-dismissible fade show" role="alert">
                <?php echo $alert_message; ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        <?php endif; ?>

        <?php if (count($reservations) > 0): ?>
            <table class="table table-striped table-hover">
                <thead class="thead-dark">
                    <tr>
                        <th>ID Cita</th>
                        <th>Fecha Cita</th>
                        <th>Cliente</th>
                        <th>Vehículo</th>
                        <th>Monto Estimado</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reservations as $res): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($res['quote_id']); ?></td>
                            <td><?php echo htmlspecialchars($res['quote_date']); ?></td>
                            <td><?php echo htmlspecialchars($res['client_name']); ?></td>
                            <td><?php echo htmlspecialchars($res['brand'] . ' ' . $res['model']); ?></td>
                            <td>$<?php echo number_format($res['total_estimated'], 2, ',', '.'); ?></td>
                            <td><span class="badge badge-warning"><?php echo htmlspecialchars($res['status']); ?></span></td>
                            <td>
                                <form method="POST" action="process_reservation.php" style="display:inline;">
                                    <input type="hidden" name="quote_id" value="<?php echo htmlspecialchars($res['quote_id']); ?>">
                                    <input type="hidden" name="action" value="approve">
                                    <button type="submit" class="btn btn-success btn-sm" 
                                            onclick="return confirm('¿Está seguro de aprobar la cotización ID <?php echo htmlspecialchars($res['quote_id']); ?>?');">
                                        Aprobar
                                    </button>
                                </form>
                                
                                <form method="POST" action="process_reservation.php" style="display:inline; margin-left: 5px;">
                                    <input type="hidden" name="quote_id" value="<?php echo htmlspecialchars($res['quote_id']); ?>">
                                    <input type="hidden" name="action" value="reject">
                                    <button type="submit" class="btn btn-danger btn-sm" 
                                            onclick="return confirm('¿Está seguro de rechazar la cotización ID <?php echo htmlspecialchars($res['quote_id']); ?>? Esto no se puede revertir.');">
                                        Rechazar
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="alert alert-info" role="alert">
                🎉 ¡Felicidades! No hay reservas pendientes de aprobación en este momento.
            </div>
        <?php endif; ?>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>