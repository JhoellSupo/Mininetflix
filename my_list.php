<?php
/**
 * MiniNetflix - Mi Lista (Películas Favoritas)
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();

$user = currentUser();
$pdo = getDB();

$stmt = $pdo->prepare("
    SELECT p.*, c.nombre AS categoria_nombre, f.creado_en AS agregado_en
    FROM favoritos f
    JOIN peliculas p ON f.pelicula_id = p.id
    JOIN categorias c ON p.categoria_id = c.id
    WHERE f.usuario_id = ?
    ORDER BY f.creado_en DESC
");
$stmt->execute([$user['id']]);
$favoritos = $stmt->fetchAll();

$pageTitle = 'Mi Lista de Favoritos';
require_once __DIR__ . '/includes/header.php';
?>

<div class="admin-container" style="padding-top: 100px;">
    <div class="admin-header">
        <div>
            <h1 class="admin-title">Mi Lista</h1>
            <p style="color: var(--text-secondary); margin-top: 6px;">
                Tus películas y trailers guardados para ver en cualquier momento
            </p>
        </div>
        <span class="tech-badge" style="font-size: 0.9rem; padding: 6px 14px;">
            <?= count($favoritos) ?> títulos guardados
        </span>
    </div>

    <?php if (empty($favoritos)): ?>
    <div style="text-align: center; padding: 80px 20px; background: var(--bg-card); border-radius: var(--radius-lg); border: 1px solid var(--border-subtle);">
        <div style="font-size: 3rem; margin-bottom: 16px; color: var(--text-muted);">&#9825;</div>
        <h2 style="font-family: var(--font-heading); font-size: 1.6rem; margin-bottom: 10px;">Tu lista está vacía</h2>
        <p style="color: var(--text-secondary); max-width: 450px; margin: 0 auto 24px auto;">
            Explora el catálogo y pulsa el botón <strong>+ Mi Lista</strong> para guardar las películas y trailers que más te interesen.
        </p>
        <a href="<?= BASE_URL ?>/index.php" class="btn btn-primary">Explorar Catálogo</a>
    </div>
    <?php else: ?>
    <div class="movies-grid">
        <?php foreach ($favoritos as $pelicula): 
            $coverUrl = getMediaUrl($pelicula['portada_key'], $pelicula['tipo_almacenamiento']);
            $trailerUrl = getMediaUrl($pelicula['trailer_key'], $pelicula['tipo_almacenamiento']);
        ?>
        <div class="movie-card" data-modal-movie
             data-id="<?= $pelicula['id'] ?>"
             data-title="<?= e($pelicula['titulo']) ?>"
             data-description="<?= e($pelicula['descripcion']) ?>"
             data-category="<?= e($pelicula['categoria_nombre']) ?>"
             data-year="<?= $pelicula['anio'] ?>"
             data-duration="<?= formatDuration((int)$pelicula['duracion']) ?>"
             data-rating="<?= $pelicula['calificacion'] ?>"
             data-trailer-url="<?= e($trailerUrl) ?>"
             data-is-favorite="true">
            <div class="card-poster-wrapper">
                <img class="card-poster" src="<?= e($coverUrl) ?>" alt="<?= e($pelicula['titulo']) ?>" loading="lazy">
                <div class="card-overlay-actions">
                    <div class="action-buttons">
                        <span class="icon-btn btn-play-circle">&#9658;</span>
                        <button class="icon-btn btn-toggle-favorite is-active" data-movie-id="<?= $pelicula['id'] ?>" title="Eliminar de Mi Lista">
                            <span class="fav-icon">&#10004;</span>
                        </button>
                    </div>
                </div>
            </div>
            <div class="card-info">
                <h3 class="card-title"><?= e($pelicula['titulo']) ?></h3>
                <div class="card-meta-line">
                    <span class="card-rating">★ <?= e($pelicula['calificacion']) ?></span>
                    <span><?= e($pelicula['anio']) ?> &bull; <?= formatDuration((int)$pelicula['duracion']) ?></span>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
