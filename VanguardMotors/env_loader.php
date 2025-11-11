<?php
// Archivo: env_loader.php (ÚLTIMA VERSIÓN CORREGIDA)

/**
 * Función para cargar variables de entorno desde un archivo .env
 */
function loadEnv($path) {
    $env_file = $path . '/.env';
    
    if (!file_exists($env_file)) {
        return; 
    }

    $lines = file($env_file, FILE_SKIP_EMPTY_LINES);
    
    foreach ($lines as $line) {
        $line = trim($line); 
        
        // Ignorar comentarios
        if (strpos($line, '#') === 0 || empty($line)) {
            continue;
        }

        // ASEGURARSE DE QUE HAYA UN SIGNO DE IGUAL
        if (strpos($line, '=') === false) {
             // Si no hay signo de igual, la línea está mal. La ignoramos.
             continue; 
        }

        // Dividir la línea en clave y valor
        list($name, $value) = explode('=', $line, 2);
        
        $name = trim($name);
        $value = trim($value);

        // Quitar comillas del valor
        if (preg_match('/^"(.*)"$/', $value, $matches)) {
            $value = $matches[1];
        }

        // 3. Establecer las variables de entorno
        // putenv() puede fallar si $name está vacío o es inválido,
        // pero la verificación anterior debería evitar esto.
        putenv(sprintf('%s=%s', $name, $value));
        $_ENV[$name] = $value;
    }
}

// Carga el archivo .env desde la carpeta actual (la raíz de tu proyecto)
loadEnv(__DIR__);

?>