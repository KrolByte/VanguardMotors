<?php
/**
 * Archivo: backend-scripts/db_config.php
 * DEPRECADO - Usar db.php en su lugar
 * 
 * Este archivo se mantiene solo por compatibilidad hacia atrás.
 * Para nuevo código, incluye 'db.php' directamente y usa la función getDbConnection()
 */

require_once __DIR__ . '/db.php';

// Para compatibilidad con código antiguo que espera $pdo global
// Si necesitas la conexión inmediata, descomenta:
// $pdo = getDbConnection();
?>