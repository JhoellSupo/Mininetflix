<?php
/**
 * MiniNetflix - API de Búsqueda y Filtro de Catálogo (Ultra Rápida con Caché)
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$q = trim($_GET['q'] ?? '');
$categoriaId = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$usuarioId = isLoggedIn() ? currentUser()['id'] : 0;

// Liberar bloqueo de sesión inmediatamente para no retrasar otras peticiones concurrentes
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

$cacheFile = STORAGE_DIR . '/catalog_search_cache.json';
$cacheTTL = 300; // 5 minutos de caché para evitar consultas remotas lentas a AWS RDS

$allMovies = null;
if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTTL) {
    $raw = @file_get_contents($cacheFile);
    if ($raw) {
        $allMovies = json_decode($raw, true);
    }
}

if ($allMovies === null) {
    try {
        $pdo = getDB();
        $sql = "
            SELECT p.*, c.nombre AS categoria_nombre, c.slug AS categoria_slug
            FROM peliculas p
            JOIN categorias c ON p.categoria_id = c.id
            ORDER BY p.destacada DESC, p.calificacion DESC, p.creado_en DESC
        ";
        $stmt = $pdo->query($sql);
        $allMovies = $stmt->fetchAll(PDO::FETCH_ASSOC);
        @file_put_contents($cacheFile, json_encode($allMovies, JSON_UNESCAPED_UNICODE));
    } catch (Exception $e) {
        $allMovies = [];
    }
}

// Mapa de favoritos del usuario en sesión
$userFavMap = [];
if ($usuarioId > 0) {
    if (isset($_SESSION['user_fav_map']) && is_array($_SESSION['user_fav_map'])) {
        $userFavMap = $_SESSION['user_fav_map'];
    } else {
        try {
            $pdo = getDB();
            $favStmt = $pdo->prepare("SELECT pelicula_id FROM favoritos WHERE usuario_id = ?");
            $favStmt->execute([$usuarioId]);
            $userFavMap = array_flip($favStmt->fetchAll(PDO::FETCH_COLUMN));
            $_SESSION['user_fav_map'] = $userFavMap;
        } catch (Exception $e) {}
    }
}

// Función para normalizar texto (sin tildes y en minúsculas)
function normText(string $str): string {
    $str = mb_strtolower($str, 'UTF-8');
    $translits = [
        'á'=>'a', 'é'=>'e', 'í'=>'i', 'ó'=>'o', 'ú'=>'u', 'ü'=>'u', 'ñ'=>'n',
        'Á'=>'a', 'É'=>'e', 'Í'=>'i', 'Ó'=>'o', 'Ú'=>'u', 'Ü'=>'u', 'Ñ'=>'n'
    ];
    return strtr($str, $translits);
}

$qNorm = !empty($q) ? normText($q) : '';

$results = [];
foreach ($allMovies as $pelicula) {
    if ($categoriaId > 0 && (int)$pelicula['categoria_id'] !== $categoriaId) {
        continue;
    }

    if (!empty($qNorm)) {
        $titleNorm = normText($pelicula['titulo'] ?? '');
        $descNorm  = normText($pelicula['descripcion'] ?? '');
        $catNorm   = normText($pelicula['categoria_nombre'] ?? '');

        if (!str_contains($titleNorm, $qNorm) && !str_contains($descNorm, $qNorm) && !str_contains($catNorm, $qNorm)) {
            continue;
        }
    }

    $coverUrl = getMediaUrl($pelicula['portada_key'], $pelicula['tipo_almacenamiento']);
    $trailerUrl = getMediaUrl($pelicula['trailer_key'], $pelicula['tipo_almacenamiento']);
    $isFav = isset($userFavMap[(int)$pelicula['id']]);

    $results[] = [
        'id' => (int)$pelicula['id'],
        'title' => $pelicula['titulo'],
        'slug' => $pelicula['slug'],
        'category_id' => (int)$pelicula['categoria_id'],
        'category_name' => $pelicula['categoria_nombre'],
        'description' => $pelicula['descripcion'],
        'duration' => (int)$pelicula['duracion'],
        'duration_formatted' => formatDuration((int)$pelicula['duracion']),
        'release_year' => (int)$pelicula['anio'],
        'rating' => (float)$pelicula['calificacion'],
        'age_rating' => $pelicula['clasificacion_edad'],
        'cover_url' => $coverUrl,
        'trailer_url' => $trailerUrl,
        'storage_type' => $pelicula['tipo_almacenamiento'],
        'is_favorite' => $isFav
    ];
}

echo json_encode([
    'success' => true,
    'count' => count($results),
    'results' => $results
]);
