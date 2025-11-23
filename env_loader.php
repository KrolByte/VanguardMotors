<?php
// Archivo: env_loader.php
// Propósito: Cargar variables de entorno desde el archivo .env ubicado en la raíz.
/**
 * Función para cargar variables de entorno desde un archivo .env
 * @param string $path Ruta absoluta de la carpeta donde reside el .env
 */
function loadEnv(string $path): void {
    $env_file = $path . '/.env';
    // Si el archivo no existe, no hacemos nada
    if (!file_exists($env_file)) {
        return; 
    }
    // Leemos el archivo, ignorando líneas vacías y saltos de línea
    $lines = file($env_file, FILE_SKIP_EMPTY_LINES | FILE_IGNORE_NEW_LINES);
    foreach ($lines as $line) {
        $line = trim($line); 
        // Ignorar comentarios que comienzan con #
        if (strpos($line, '#') === 0 || empty($line)) {
            continue;
        }
        // Dividir la línea en clave y valor (hasta el primer '=')
        // Si no hay '=', ignoramos la línea.
        if (strpos($line, '=') === false) {
             continue; 
        }

        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        // Quitar comillas dobles o simples del valor
        if (preg_match('/^["\'](.*)["\']$/', $value, $matches)) {
            $value = $matches[1];
        }
        // 1. Establecer en la tabla de entorno (para getenv())
        putenv(sprintf('%s=%s', $name, $value));
        // 2. Establecer en variables superglobales (para consistencia)
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value; 
    }
}

// Carga el archivo .env desde la carpeta donde reside este script (la raíz del proyecto)
loadEnv(__DIR__);
?>