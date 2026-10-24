<?php
/**
 * MiniNetflix - Eliminación de Película y Limpieza en Amazon S3 (Esquema en Español)
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

$peliculaId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($peliculaId <= 0) {
    header("Location: " . BASE_URL . "/admin/index.php");
    exit;
}

$pdo = getDB();
$s3Client = getS3Client();

$stmt = $pdo->prepare("SELECT * FROM peliculas WHERE id = ?");
$stmt->execute([$peliculaId]);
$pelicula = $stmt->fetch();

if ($pelicula) {
    // Si estaba en S3 y el cliente está configurado, eliminar objetos en S3
    if ($pelicula['tipo_almacenamiento'] === 's3' && $s3Client->isConfigured()) {
        if (!empty($pelicula['portada_key']) && !str_starts_with($pelicula['portada_key'], 'http')) {
            $s3Client->deleteObject($pelicula['portada_key']);
        }
        if (!empty($pelicula['trailer_key']) && !str_starts_with($pelicula['trailer_key'], 'http')) {
            $s3Client->deleteObject($pelicula['trailer_key']);
        }
        if (!empty($pelicula['fondo_key']) && !str_starts_with($pelicula['fondo_key'], 'http')) {
            $s3Client->deleteObject($pelicula['fondo_key']);
        }
    } else {
        // Almacenamiento local: eliminar archivos si existen
        $localCover = UPLOADS_DIR . '/' . $pelicula['portada_key'];
        if (file_exists($localCover) && is_file($localCover)) {
            @unlink($localCover);
        }
        $localTrailer = UPLOADS_DIR . '/' . $pelicula['trailer_key'];
        if (file_exists($localTrailer) && is_file($localTrailer)) {
            @unlink($localTrailer);
        }
    }

    // Eliminar de MySQL (claves foráneas en cascada)
    $del = $pdo->prepare("DELETE FROM peliculas WHERE id = ?");
    $del->execute([$peliculaId]);

    setFlash('success', "La película '{$pelicula['titulo']}' y sus archivos fueron eliminados correctamente.");
} else {
    setFlash('error', "La película no existe o ya fue eliminada.");
}

header("Location: " . BASE_URL . "/admin/index.php");
exit;
