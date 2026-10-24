<?php
/**
 * MiniNetflix - Configuración Global
 */

// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Rutas del sistema de archivos
define('ROOT_DIR', dirname(__DIR__));
define('STORAGE_DIR', ROOT_DIR . '/storage');
define('UPLOADS_DIR', STORAGE_DIR . '/uploads');

// Cargar variables de entorno desde .env si existe
$envFile = ROOT_DIR . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (strpos($line, '=') !== false) {
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value, " \t\n\r\0\x0B\"'");
            if (!isset($_ENV[$key])) {
                $_ENV[$key] = $value;
                putenv("{$key}={$value}");
            }
        }
    }
}

// Helper para obtener variable de entorno o valor por defecto
function env(string $key, $default = null) {
    return $_ENV[$key] ?? getenv($key) ?: $default;
}

// Configuración de Base de Datos (sqlite, mysql o auto)
define('DB_CONNECTION', env('DB_CONNECTION', 'auto'));
define('DB_HOST', env('DB_HOST', '127.0.0.1'));
define('DB_PORT', env('DB_PORT', '3306'));
define('DB_NAME', env('DB_NAME', 'mininetflix'));
define('DB_USER', env('DB_USER', 'root'));
define('DB_PASS', env('DB_PASS', ''));

// Configuración de Amazon S3
define('AWS_ACCESS_KEY_ID', env('AWS_ACCESS_KEY_ID', ''));
define('AWS_SECRET_ACCESS_KEY', env('AWS_SECRET_ACCESS_KEY', ''));
define('AWS_REGION', env('AWS_REGION', 'us-east-1'));
define('AWS_BUCKET', env('AWS_BUCKET', 'mininetflix-media-bucket'));
define('S3_PRESIGNED_EXPIRY', (int)env('S3_PRESIGNED_EXPIRY', 3600));

// Clave secreta para tokens firmados HMAC locales
define('APP_SECRET_KEY', env('APP_SECRET_KEY', 'mininetflix_super_secret_sigv4_token_2026'));

// Configuración de la Aplicación
define('APP_NAME', 'MiniNetflix');
define('APP_VERSION', '1.0.0');

// Detección dinámica de BASE_URL
if (php_sapi_name() === 'cli') {
    define('BASE_URL', 'http://127.0.0.1:8000');
} else {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'] ?? '127.0.0.1:8000';
    $scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
    $scriptDir = str_replace('\\', '/', $scriptDir);
    $cleanDir = preg_replace('#/(admin|api)$#', '', $scriptDir);
    if ($cleanDir === '/' || $cleanDir === '.') {
        $cleanDir = '';
    }
    define('BASE_URL', rtrim($protocol . $host . $cleanDir, '/'));
}
