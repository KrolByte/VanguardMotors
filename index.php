<?php
include 'conexion.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Catálogo de Vehículos</title>
  <link rel="stylesheet" href="estilos.css">
</head>
<body>
  <h1>🚗 Catálogo de Vehículos</h1>

  <table>
    <thead>
      <tr>
        <th>ID</th>
        <th>Marca</th>
        <th>Modelo</th>
        <th>Año</th>
        <th>Precio</th>
      </tr>
    </thead>
    <tbody>
      <?php
      try {
          // Ajusta el nombre de la tabla según la que tengas (vehicle, vehiculo, etc.)
          $stmt = $conexion->query("SELECT vehicle_id, brand, model, year, price FROM vehicle ORDER BY vehicle_id");
          while ($row = $stmt->fetch()) {
              echo "<tr>";
              echo "<td>{$row['vehicle_id']}</td>";
              echo "<td>{$row['brand']}</td>";
              echo "<td>{$row['model']}</td>";
              echo "<td>{$row['year']}</td>";
              echo "<td>{$row['price']}</td>";
              echo "</tr>";
          }
      } catch (Exception $e) {
          echo "<tr><td colspan='5'>Error: " . htmlspecialchars($e->getMessage()) . "</td></tr>";
      }
      ?>
    </tbody>
  </table>
</body>
</html>
