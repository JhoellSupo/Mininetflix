<?php
/**
 * MiniNetflix - Catálogo Principal de Películas y Streaming (Vista de Catálogo)
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();

$user = currentUser();
$pdo = getDB();
$selectedCategory = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$modoVista = isset($_GET['vista']) ? $_GET['vista'] : 'catalogo'; // 'catalogo' (grid) o 'carrusel'

// 1. Obtener todas las películas para la vista de catálogo general (cargadas una vez para filtrado instantáneo)
$todasLasPeliculas = [];
try {
    $sql = "
        SELECT p.*, c.nombre AS categoria_nombre 
        FROM peliculas p
        JOIN categorias c ON p.categoria_id = c.id
        ORDER BY p.destacada DESC, p.calificacion DESC, p.creado_en DESC
    ";
    $stmt = $pdo->query($sql);
    $todasLasPeliculas = $stmt->fetchAll();
} catch (Exception $e) {}

// Pre-cargar mapa de favoritos del usuario en O(1) para evitar N consultas en bucles
$userFavoriteIds = [];
try {
    $stmtFav = $pdo->prepare("SELECT pelicula_id FROM favoritos WHERE usuario_id = ?");
    $stmtFav->execute([$user['id']]);
    $userFavoriteIds = array_flip($stmtFav->fetchAll(PDO::FETCH_COLUMN));
} catch (Exception $e) {}

// 2. Obtener Película Destacada para el spotlight compacto
$peliculaDestacada = null;
try {
    $stmt = $pdo->prepare("
        SELECT p.*, c.nombre AS categoria_nombre 
        FROM peliculas p
        JOIN categorias c ON p.categoria_id = c.id
        WHERE p.destacada = 1 
        ORDER BY p.calificacion DESC, p.creado_en DESC 
        LIMIT 1
    ");
    $stmt->execute();
    $peliculaDestacada = $stmt->fetch();

    if (!$peliculaDestacada && !empty($todasLasPeliculas)) {
        $peliculaDestacada = $todasLasPeliculas[0];
    }
} catch (Exception $e) {}

// 3. Obtener "Mi Lista" (Favoritos) del usuario
$miLista = [];
try {
    $stmt = $pdo->prepare("
        SELECT p.*, c.nombre AS categoria_nombre
        FROM favoritos f
        JOIN peliculas p ON f.pelicula_id = p.id
        JOIN categorias c ON p.categoria_id = c.id
        WHERE f.usuario_id = ?
        ORDER BY f.creado_en DESC
        LIMIT 10
    ");
    $stmt->execute([$user['id']]);
    $miLista = $stmt->fetchAll();
} catch (Exception $e) {}

// 5. Categorías para filtros y carruseles
$categorias = [];
try {
    $categorias = $pdo->query("SELECT * FROM categorias ORDER BY id ASC")->fetchAll();
} catch (Exception $e) {}

// Películas agrupadas por categoría (si se activa modo carrusel)
$peliculasPorCategoria = [];
if ($modoVista === 'carrusel') {
    foreach ($categorias as $cat) {
        try {
            $stmt = $pdo->prepare("
                SELECT p.*, c.nombre AS categoria_nombre 
                FROM peliculas p
                JOIN categorias c ON p.categoria_id = c.id
                WHERE p.categoria_id = ?
                ORDER BY p.calificacion DESC, p.creado_en DESC
            ");
            $stmt->execute([$cat['id']]);
            $peliculasPorCategoria[$cat['id']] = $stmt->fetchAll();
        } catch (Exception $e) {}
    }
}

$pageTitle = 'Catálogo de Películas y Trailers';
require_once __DIR__ . '/includes/header.php';
?>

<!-- CONTENEDOR PRINCIPAL DEL CATÁLOGO DIRECTO -->
<div class="catalog-container" style="padding-top: 90px;">

    <!-- BARRA DE CONTROL DEL CATÁLOGO (Selector de Vista y Filtros) -->
    <div class="catalog-toolbar">
        <div>
            <h2 style="font-family: var(--font-heading); font-size: 1.8rem; font-weight: 800; color: white; display: flex; align-items: center; gap: 12px;">
                Catálogo de Películas
                <span class="tech-badge" style="font-size: 0.8rem; font-weight: 700;">
                    <?= count($todasLasPeliculas) ?> películas
                </span>
            </h2>
            <p style="color: var(--text-secondary); font-size: 0.88rem; margin-top: 4px;">
                Explora todos los títulos disponibles con trailers en streaming en alta definición
            </p>
        </div>

        <!-- Selector de Modo de Visualización (Cuadrícula o Carrusel) -->
        <div class="view-toggle-group">
            <a href="<?= BASE_URL ?>/index.php?vista=catalogo<?= $selectedCategory > 0 ? '&category=' . $selectedCategory : '' ?>" 
               class="view-toggle-btn <?= $modoVista === 'catalogo' ? 'active' : '' ?>" title="Ver todas las películas en cuadrícula">
                <span>&#8862;</span> Catálogo Completo
            </a>
            <a href="<?= BASE_URL ?>/index.php?vista=carrusel<?= $selectedCategory > 0 ? '&category=' . $selectedCategory : '' ?>" 
               class="view-toggle-btn <?= $modoVista === 'carrusel' ? 'active' : '' ?>" title="Ver agrupado por categorías">
                <span>&#9776;</span> Por Categorías
            </a>
        </div>
    </div>

    <!-- PÍLDORAS DE FILTRADO RÁPIDO POR CATEGORÍA -->
    <div class="category-filter-bar" style="display: flex; align-items: center; gap: 10px; margin-bottom: 28px; overflow-x: auto; padding-bottom: 8px;">
        <span style="font-weight: 700; color: var(--text-muted); font-size: 0.85rem; text-transform: uppercase;">Filtrar:</span>
        <a href="<?= BASE_URL ?>/index.php?vista=<?= e($modoVista) ?>" 
           data-filter-category="0"
           class="tech-badge category-filter-pill <?= $selectedCategory === 0 ? 'active' : '' ?>" 
           style="<?= $selectedCategory === 0 ? 'background: var(--primary-red); color: white; border-color: var(--primary-red);' : '' ?>">
            Todas las Películas
        </a>
        <?php foreach ($categorias as $nc): ?>
            <a href="<?= BASE_URL ?>/index.php?vista=<?= e($modoVista) ?>&category=<?= $nc['id'] ?>" 
               data-filter-category="<?= $nc['id'] ?>"
               class="tech-badge category-filter-pill <?= $selectedCategory === (int)$nc['id'] ? 'active' : '' ?>" 
               style="<?= $selectedCategory === (int)$nc['id'] ? 'background: var(--primary-red); color: white; border-color: var(--primary-red);' : '' ?>">
                <?= e($nc['nombre']) ?>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- SECCIÓN PERSONALIZADA: MI LISTA DE FAVORITOS (Si el usuario tiene títulos agregados) -->
    <?php if ($selectedCategory === 0 && !empty($miLista)): ?>
        <section style="margin-bottom: 35px; padding-bottom: 25px; border-bottom: 1px solid var(--border-subtle);">
            <div class="row-header" style="margin-top: 5px; margin-bottom: 16px; display: flex; justify-content: space-between; align-items: center;">
                <h3 class="row-title" style="font-size: 1.35rem; display: flex; align-items: center; gap: 8px;">
                    <span style="color: var(--primary-red);">&#9829;</span> Mi Lista Personal
                    <span style="font-size: 0.85rem; color: var(--text-muted); font-weight: normal;">(<?= count($miLista) ?> títulos guardados)</span>
                </h3>
                <a href="<?= BASE_URL ?>/my_list.php" style="color: var(--primary-red); font-size: 0.85rem; font-weight: 600; text-decoration: none;">
                    Ver lista completa &rarr;
                </a>
            </div>
            <div class="movies-grid" style="display: grid !important; grid-template-columns: repeat(auto-fill, minmax(180px, 205px)) !important; gap: 20px !important; justify-content: flex-start !important;">
                <?php foreach ($miLista as $pelicula): 
                    $coverUrl = getMediaUrl($pelicula['portada_key'], $pelicula['tipo_almacenamiento']);
                    $trailerUrl = getMediaUrl($pelicula['trailer_key'], $pelicula['tipo_almacenamiento']);
                ?>
                <div class="movie-card" 
                     style="width: 100% !important; max-width: 205px !important;" 
                     data-modal-movie
                     data-id="<?= $pelicula['id'] ?>"
                     data-category-id="<?= (int)$pelicula['categoria_id'] ?>"
                     data-title="<?= e($pelicula['titulo']) ?>"
                     data-description="<?= e($pelicula['descripcion']) ?>"
                     data-category="<?= e($pelicula['categoria_nombre']) ?>"
                     data-year="<?= $pelicula['anio'] ?>"
                     data-duration="<?= formatDuration((int)$pelicula['duracion']) ?>"
                     data-rating="<?= $pelicula['calificacion'] ?>"
                     data-trailer-url="<?= e($trailerUrl) ?>"
                     data-is-favorite="true">
                    <div class="card-poster-wrapper" style="width: 100% !important; height: 275px !important; max-height: 275px !important; overflow: hidden !important; position: relative !important; background: #1f2026;">
                        <img class="card-poster" style="width: 100% !important; height: 100% !important; object-fit: cover !important; display: block !important;" src="<?= e($coverUrl) ?>" alt="<?= e($pelicula['titulo']) ?>" loading="lazy">
                        <div class="card-overlay-actions">
                            <div class="action-buttons">
                                <span class="icon-btn btn-play-circle" title="Reproducir Trailer">&#9658;</span>
                                <button class="icon-btn btn-toggle-favorite is-active" data-movie-id="<?= $pelicula['id'] ?>" title="Eliminar de Mi Lista">
                                    <span class="fav-icon">&#10004;</span>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="card-info">
                        <h4 class="card-title"><?= e($pelicula['titulo']) ?></h4>
                        <div class="card-meta-line">
                            <span class="card-rating">★ <?= e($pelicula['calificacion']) ?></span>
                            <span><?= e($pelicula['anio']) ?> &bull; <?= formatDuration((int)$pelicula['duracion']) ?></span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <!-- MODO 1: VISTA DE CATÁLOGO COMPLETO (CUADRÍCULA / GRID) -> DIRECTA PARA VER TODAS LAS PELÍCULAS -->
    <?php if ($modoVista === 'catalogo'): ?>
        <div class="row-header" style="margin-top: 15px; margin-bottom: 18px;">
            <h3 class="row-title" style="font-size: 1.35rem; display: flex; align-items: center; gap: 10px;">
                Catálogo de Películas
                <span class="catalog-visible-count" style="font-size: 0.9rem; color: var(--text-muted); font-weight: normal;">(<?= count($todasLasPeliculas) ?> títulos disponibles)</span>
            </h3>
        </div>
        <?php if (empty($todasLasPeliculas)): ?>
            <div style="text-align: center; padding: 60px 20px; background: var(--bg-card); border-radius: var(--radius-lg); border: 1px solid var(--border-subtle);">
                <div style="font-size: 2.5rem; margin-bottom: 12px; color: var(--text-muted);">&#127916;</div>
                <h3 style="font-size: 1.4rem; margin-bottom: 8px;">No se encontraron películas en esta categoría</h3>
                <p style="color: var(--text-secondary); margin-bottom: 18px;">Intente seleccionando otra categoría o explore todo el catálogo.</p>
                <a href="<?= BASE_URL ?>/index.php" class="btn btn-primary btn-sm">Ver Todas las Películas</a>
            </div>
        <?php else: ?>
            <div class="movies-grid" style="display: grid !important; grid-template-columns: repeat(auto-fill, minmax(180px, 205px)) !important; gap: 20px !important; justify-content: flex-start !important; padding-top: 10px;">
                <?php foreach ($todasLasPeliculas as $pelicula): 
                    $coverUrl = getMediaUrl($pelicula['portada_key'], $pelicula['tipo_almacenamiento']);
                    $trailerUrl = getMediaUrl($pelicula['trailer_key'], $pelicula['tipo_almacenamiento']);
                    $isFav = isset($userFavoriteIds[(int)$pelicula['id']]);
                    $isHidden = ($selectedCategory > 0 && (int)$pelicula['categoria_id'] !== $selectedCategory);
                ?>
                <div class="movie-card <?= $isHidden ? 'is-filtered-out' : '' ?>" 
                     style="width: 100% !important; max-width: 205px !important; <?= $isHidden ? 'display: none !important;' : '' ?>" 
                     data-modal-movie
                     data-id="<?= $pelicula['id'] ?>"
                     data-category-id="<?= (int)$pelicula['categoria_id'] ?>"
                     data-title="<?= e($pelicula['titulo']) ?>"
                     data-description="<?= e($pelicula['descripcion']) ?>"
                     data-category="<?= e($pelicula['categoria_nombre']) ?>"
                     data-year="<?= $pelicula['anio'] ?>"
                     data-duration="<?= formatDuration((int)$pelicula['duracion']) ?>"
                     data-rating="<?= $pelicula['calificacion'] ?>"
                     data-trailer-url="<?= e($trailerUrl) ?>"
                     data-is-favorite="<?= $isFav ? 'true' : 'false' ?>">
                    <div class="card-poster-wrapper" style="width: 100% !important; height: 275px !important; max-height: 275px !important; overflow: hidden !important; position: relative !important; background: #1f2026;">
                        <img class="card-poster" style="width: 100% !important; height: 100% !important; object-fit: cover !important; display: block !important;" src="<?= e($coverUrl) ?>" alt="<?= e($pelicula['titulo']) ?>" loading="lazy">
                        <div class="card-badges">
                            <?php if ($pelicula['destacada']): ?>
                                <span class="badge-top10" style="font-size: 0.65rem; padding: 2px 6px;">TOP</span>
                            <?php endif; ?>
                        </div>
                        <div class="card-overlay-actions">
                            <div class="action-buttons">
                                <span class="icon-btn btn-play-circle" title="Reproducir Trailer">&#9658;</span>
                                <button class="icon-btn btn-toggle-favorite <?= $isFav ? 'is-active' : '' ?>" data-movie-id="<?= $pelicula['id'] ?>" title="Agregar a Mi Lista">
                                    <span class="fav-icon"><?= $isFav ? '&#10004;' : '+' ?></span>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="card-info">
                        <h4 class="card-title"><?= e($pelicula['titulo']) ?></h4>
                        <div class="card-meta-line">
                            <span class="card-rating">★ <?= e($pelicula['calificacion']) ?></span>
                            <span><?= e($pelicula['anio']) ?> &bull; <?= formatDuration((int)$pelicula['duracion']) ?></span>
                        </div>
                        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 2px;">
                            <?= e($pelicula['categoria_nombre']) ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    <!-- MODO 2: VISTA AGRUPADA POR CATEGORÍAS (CARRUSELES HORIZONTALES) -->
    <?php else: ?>
        <?php foreach ($categorias as $cat): 
            $catPeliculas = $peliculasPorCategoria[$cat['id']] ?? [];
            if (empty($catPeliculas)) continue;
        ?>
        <section class="category-row">
            <div class="row-header">
                <h3 class="row-title"><?= e($cat['nombre']) ?></h3>
                <span style="font-size: 0.85rem; color: var(--text-muted);"><?= count($catPeliculas) ?> títulos</span>
            </div>
            <div class="carousel-wrapper">
                <button class="carousel-btn prev" aria-label="Anterior">&#10094;</button>
                <div class="movie-row-grid">
                    <?php foreach ($catPeliculas as $pelicula): 
                        $coverUrl = getMediaUrl($pelicula['portada_key'], $pelicula['tipo_almacenamiento']);
                        $trailerUrl = getMediaUrl($pelicula['trailer_key'], $pelicula['tipo_almacenamiento']);
                        $isFav = isset($userFavoriteIds[(int)$pelicula['id']]);
                    ?>
                    <div class="movie-card" data-modal-movie
                         data-id="<?= $pelicula['id'] ?>"
                         data-category-id="<?= (int)$pelicula['categoria_id'] ?>"
                         data-title="<?= e($pelicula['titulo']) ?>"
                         data-description="<?= e($pelicula['descripcion']) ?>"
                         data-category="<?= e($cat['nombre']) ?>"
                         data-year="<?= $pelicula['anio'] ?>"
                         data-duration="<?= formatDuration((int)$pelicula['duracion']) ?>"
                         data-rating="<?= $pelicula['calificacion'] ?>"
                         data-trailer-url="<?= e($trailerUrl) ?>"
                         data-is-favorite="<?= $isFav ? 'true' : 'false' ?>">
                        <div class="card-poster-wrapper">
                            <img class="card-poster" src="<?= e($coverUrl) ?>" alt="<?= e($pelicula['titulo']) ?>" loading="lazy">
                            <div class="card-overlay-actions">
                                <div class="action-buttons">
                                    <span class="icon-btn btn-play-circle">&#9658;</span>
                                    <button class="icon-btn btn-toggle-favorite <?= $isFav ? 'is-active' : '' ?>" data-movie-id="<?= $pelicula['id'] ?>">
                                        <span class="fav-icon"><?= $isFav ? '&#10004;' : '+' ?></span>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="card-info">
                            <h4 class="card-title"><?= e($pelicula['titulo']) ?></h4>
                            <div class="card-meta-line">
                                <span class="card-rating">★ <?= e($pelicula['calificacion']) ?></span>
                                <span><?= e($pelicula['anio']) ?> &bull; <?= formatDuration((int)$pelicula['duracion']) ?></span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <button class="carousel-btn next" aria-label="Siguiente">&#10095;</button>
            </div>
        </section>
        <?php endforeach; ?>
    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
