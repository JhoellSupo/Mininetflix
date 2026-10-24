<?php
/**
 * MiniNetflix - Transmisor Seguro de Medios Locales
 * Valida la firma temporal HMAC (imitando la seguridad de S3 Presigned URLs)
 * y soporta cabeceras HTTP Range para reproducción y rebobinado fluido en HTML5.
 */

require_once __DIR__ . '/config/config.php';

// Liberar bloqueo de sesión inmediatamente para no congelar otras peticiones mientras se transmite video
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

$fileParam = $_GET['file'] ?? '';
$expires = (int)($_GET['expires'] ?? 0);
$token = $_GET['token'] ?? '';

if (empty($fileParam) || empty($expires) || empty($token)) {
    http_response_code(403);
    die('Acceso denegado: Parámetros de seguridad incompletos.');
}

// 1. Validar que la URL no haya expirado
if (time() > $expires) {
    http_response_code(403);
    die('Acceso denegado: El enlace prefirmado ha expirado.');
}

// 2. Validar la firma HMAC
$payload = "{$fileParam}:{$expires}";
$expectedToken = hash_hmac('sha256', $payload, APP_SECRET_KEY);

if (!hash_equals($expectedToken, $token)) {
    http_response_code(403);
    die('Acceso denegado: Firma inválida.');
}

// 3. Resolver la ruta física del archivo (en storage/uploads o en E:/DESCARGAS 2)
$safeFile = str_replace(['..', "\0"], '', $fileParam);
$realPath = null;

$candidatePaths = [
    UPLOADS_DIR . '/' . $safeFile,
    UPLOADS_DIR . '/covers/' . $safeFile,
    UPLOADS_DIR . '/trailers/' . $safeFile,
    'E:/DESCARGAS 2/' . $safeFile
];

foreach ($candidatePaths as $candidate) {
    if (file_exists($candidate)) {
        $realPath = realpath($candidate);
        break;
    }
}

// Búsqueda inteligente por ID único de video para tolerar pequeñas variaciones en el nombre
if (!$realPath && (str_ends_with(strtolower($safeFile), '.mp4') || str_ends_with(strtolower($safeFile), '.webm'))) {
    $searchDirs = [UPLOADS_DIR, UPLOADS_DIR . '/trailers', 'E:/DESCARGAS 2'];
    if (preg_match('/_([A-Za-z0-9_-]{10,12})_/', $safeFile, $matches)) {
        $ytId = $matches[1];
        foreach ($searchDirs as $dir) {
            if (is_dir($dir)) {
                $files = scandir($dir);
                foreach ($files as $f) {
                    if (str_contains($f, $ytId) && (str_ends_with(strtolower($f), '.mp4') || str_ends_with(strtolower($f), '.webm'))) {
                        $realPath = realpath($dir . '/' . $f);
                        break 2;
                    }
                }
            }
        }
    }
}

if (!$realPath || !file_exists($realPath)) {
    http_response_code(404);
    die('Archivo no encontrado.');
}

// 4. Determinar tipo MIME
$ext = strtolower(pathinfo($realPath, PATHINFO_EXTENSION));
$mimeTypes = [
    'mp4'  => 'video/mp4',
    'webm' => 'video/webm',
    'ogg'  => 'video/ogg',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'jfif' => 'image/jpeg',
    'png'  => 'image/png',
    'webp' => 'image/webp',
    'avif' => 'image/avif',
    'svg'  => 'image/svg+xml'
];
$mimeType = $mimeTypes[$ext] ?? 'application/octet-stream';

$fileSize = filesize($realPath);
$file = fopen($realPath, 'rb');

// 5. Soporte para HTTP Range (scrubbing y streaming en HTML5 Video)
$start = 0;
$end = $fileSize - 1;

header("Content-Type: {$mimeType}");
header("Accept-Ranges: bytes");
header("Cache-Control: private, max-age=" . max(0, $expires - time()));

if (isset($_SERVER['HTTP_RANGE'])) {
    $c_start = $start;
    $c_end = $end;

    [, $range] = explode('=', $_SERVER['HTTP_RANGE'], 2);
    if (strpos($range, ',') !== false) {
        header('HTTP/1.1 416 Requested Range Not Satisfiable');
        header("Content-Range: bytes $start-$end/$fileSize");
        exit;
    }

    if ($range === '-') {
        $c_start = $fileSize - substr($range, 1);
    } else {
        $range = explode('-', $range);
        $c_start = $range[0];
        $c_end = (isset($range[1]) && is_numeric($range[1])) ? $range[1] : $fileSize - 1;
    }

    $c_end = ($c_end > $end) ? $end : $c_end;
    if ($c_start > $c_end || $c_start > $fileSize - 1 || $c_end >= $fileSize) {
        header('HTTP/1.1 416 Requested Range Not Satisfiable');
        header("Content-Range: bytes $start-$end/$fileSize");
        exit;
    }

    $start = $c_start;
    $end = $c_end;
    $length = $end - $start + 1;

    header('HTTP/1.1 206 Partial Content');
    header("Content-Length: {$length}");
    header("Content-Range: bytes {$start}-{$end}/{$fileSize}");
} else {
    header("Content-Length: {$fileSize}");
}

fseek($file, $start);
$buffer = 1024 * 64; // 64KB
while (!feof($file) && ($p = ftell($file)) <= $end) {
    if ($p + $buffer > $end) {
        $buffer = $end - $p + 1;
    }
    set_time_limit(0);
    echo fread($file, $buffer);
    flush();
}
fclose($file);
exit;
