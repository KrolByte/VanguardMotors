<?php
// Archivo: env_loader.php (en la raíz del proyecto)

/**
 * Función manual para cargar variables de entorno desde un archivo .env
 * @param string $path El directorio donde se encuentra el .env
 */
function loadEnv($path) {
    if (!file_exists($path . '/.env')) {
        die("Error: El archivo .env no se encontró en la ruta: " . $path);
    }

    $lines = file($path . '/.env', FILE_IGNORE_EMPTY_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Ignora comentarios y líneas sin asignación
        if (strpos(trim($line), '#') === 0 || strpos($line, '=') === false) {
            continue;
        }

        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);

        // Limpia comillas si existen (opcional)
        if (preg_match('/^"(.*)"$/', $value, $matches)) {
            $value = $matches[1];
        }

        // Establece la variable de entorno global
        putenv(sprintf('%s=%s', $name, $value));
        $_ENV[$name] = $value;
    }
}

// Carga el archivo .env desde la carpeta actual (la raíz de tu proyecto)
loadEnv(__DIR__);

?>