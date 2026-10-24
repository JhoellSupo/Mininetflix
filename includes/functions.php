<?php
/**
 * MiniNetflix - Funciones Globales y Utilidades (Esquema en Español)
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/s3.php';

/**
 * Escapar texto HTML
 */
function e(?string $str): string {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Genera un slug para URLs amigables
 */
function slugify(string $text): string {
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    $text = strtolower($text);
    return empty($text) ? 'n-a' : $text;
}

/**
 * Formatea duración en minutos a formato amigable (ej. 132 min -> 2h 12m)
 */
function formatDuration(int $minutes): string {
    if ($minutes <= 0) return '0m';
    $hours = floor($minutes / 60);
    $mins = $minutes % 60;
    if ($hours > 0 && $mins > 0) {
        return "{$hours}h {$mins}m";
    } elseif ($hours > 0) {
        return "{$hours}h";
    }
    return "{$mins}m";
}

/**
 * Obtiene una instancia configurada de AwsS3Client desde la tabla configuraciones
 */
function getS3Client(): AwsS3Client {
    static $client = null;
    if ($client !== null) {
        return $client;
    }

    $cacheFile = STORAGE_DIR . '/s3_config_cache.json';
    $settings = null;
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 600) {
        $settings = json_decode(@file_get_contents($cacheFile), true);
    }

    if ($settings === null) {
        $settings = [];
        try {
            $pdo = getDB();
            $stmt = $pdo->query("SELECT clave, valor FROM configuraciones");
            while ($row = $stmt->fetch()) {
                $settings[$row['clave']] = $row['valor'];
            }
            @file_put_contents($cacheFile, json_encode($settings));
        } catch (Exception $e) {}
    }

    $accessKey = !empty($settings['aws_access_key_id']) ? $settings['aws_access_key_id'] : AWS_ACCESS_KEY_ID;
    $secretKey = !empty($settings['aws_secret_access_key']) ? $settings['aws_secret_access_key'] : AWS_SECRET_ACCESS_KEY;
    $region    = !empty($settings['aws_region']) ? $settings['aws_region'] : AWS_REGION;
    $bucket    = !empty($settings['aws_bucket']) ? $settings['aws_bucket'] : AWS_BUCKET;
    $expiry    = !empty($settings['s3_presigned_expiry']) ? (int)$settings['s3_presigned_expiry'] : S3_PRESIGNED_EXPIRY;

    $client = new AwsS3Client($accessKey, $secretKey, $region, $bucket, $expiry);
    return $client;
}

/**
 * Obtiene la URL de acceso a un recurso multimedia (trailer o portada).
 * Si está en S3, genera una URL prefirmada (Presigned URL) de AWS S3 temporal.
 * Si es local, genera un token firmado HMAC temporal (media_stream.php).
 */
function getMediaUrl(string $key, string $storageType = 's3', ?int $expiresInSeconds = null): string {
    if (empty($key)) {
        return '';
    }

    if (str_starts_with($key, 'http://') || str_starts_with($key, 'https://')) {
        return $key;
    }

    $expiresInSeconds = $expiresInSeconds ?? S3_PRESIGNED_EXPIRY;
    $s3 = getS3Client();

    if ($storageType === 's3' && $s3->isConfigured()) {
        $presignedUrl = $s3->getPresignedUrl($key, $expiresInSeconds);
        if (!empty($presignedUrl)) {
            return $presignedUrl;
        }
    }

    $expires = time() + $expiresInSeconds;
    $payload = "{$key}:{$expires}";
    $token = hash_hmac('sha256', $payload, APP_SECRET_KEY);

    return BASE_URL . '/media_stream.php?file=' . urlencode($key) . '&expires=' . $expires . '&token=' . $token;
}

/**
 * Verifica si una película está en la lista de favoritos del usuario
 */
function isFavorite(int $usuarioId, int $peliculaId): bool {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT 1 FROM favoritos WHERE usuario_id = ? AND pelicula_id = ? LIMIT 1");
    $stmt->execute([$usuarioId, $peliculaId]);
    return (bool)$stmt->fetchColumn();
}

/**
 * Obtiene el progreso de reproducción de un usuario para una película
 */
function getWatchProgress(int $usuarioId, int $peliculaId): ?array {
    $pdo = getDB();
    $stmt = $pdo->prepare("
        SELECT segundos_progreso, segundos_totales, completado, ultima_reproduccion 
        FROM historial_reproducciones 
        WHERE usuario_id = ? AND pelicula_id = ? 
        LIMIT 1
    ");
    $stmt->execute([$usuarioId, $peliculaId]);
    $historial = $stmt->fetch();
    if (!$historial) {
        return null;
    }
    $porcentaje = 0;
    if ($historial['segundos_totales'] > 0) {
        $porcentaje = min(100, round(($historial['segundos_progreso'] / $historial['segundos_totales']) * 100));
    }
    return [
        'segundos_progreso' => (int)$historial['segundos_progreso'],
        'segundos_totales' => (int)$historial['segundos_totales'],
        'progress_seconds' => (int)$historial['segundos_progreso'],
        'total_seconds' => (int)$historial['segundos_totales'],
        'completado' => (bool)$historial['completado'],
        'completed' => (bool)$historial['completado'],
        'porcentaje' => $porcentaje,
        'percentage' => $porcentaje,
        'ultima_reproduccion' => $historial['ultima_reproduccion'],
        'last_watched_at' => $historial['ultima_reproduccion']
    ];
}

/**
 * Mensajes Flash para sesiones
 */
function setFlash(string $type, string $message): void {
    $_SESSION['flash_' . $type] = $message;
}

function getFlash(string $type): ?string {
    if (isset($_SESSION['flash_' . $type])) {
        $msg = $_SESSION['flash_' . $type];
        unset($_SESSION['flash_' . $type]);
        return $msg;
    }
    return null;
}
