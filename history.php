<?php
/**
 * MiniNetflix - Historial de Reproducciones (Esquema en Español)
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();

$user = currentUser();
$pdo = getDB();

// Acción para limpiar historial
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'clear') {
    $del = $pdo->prepare("DELETE FROM historial_reproducciones WHERE usuario_id = ?");
    $del->execute([$user['id']]);
    setFlash('success', 'El historial de reproducciones ha sido limpiado.');
    header("Location: " . BASE_URL . "/history.php");
    exit;
}

$stmt = $pdo->prepare("
    SELECT p.*, c.nombre AS categoria_nombre, h.segundos_progreso, h.segundos_totales, h.completado, h.ultima_reproduccion
    FROM historial_reproducciones h
    JOIN peliculas p ON h.pelicula_id = p.id
    JOIN categorias c ON p.categoria_id = c.id
    WHERE h.usuario_id = ?
    ORDER BY h.ultima_reproduccion DESC
");
$stmt->execute([$user['id']]);
$historial = $stmt->fetchAll();

$pageTitle = 'Historial de Reproducciones';
require_once __DIR__ . '/includes/header.php';
?>

<div class="admin-container" style="padding-top: 100px;">
    <div class="admin-header">
        <div>
            <h1 class="admin-title">Historial de Reproducciones</h1>
            <p style="color: var(--text-secondary); margin-top: 6px;">
                Películas y trailers que has visualizado recientemente
            </p>
        </div>
        <div style="display: flex; gap: 14px; align-items: center;">
            <span class="tech-badge" style="font-size: 0.9rem; padding: 6px 14px;">
                <?= count($historial) ?> reproducciones
            </span>
            <?php if (!empty($historial)): ?>
            <form action="<?= BASE_URL ?>/history.php" method="POST" onsubmit="return confirm('¿Seguro que deseas borrar todo tu historial?');">
                <input type="hidden" name="action" value="clear">
                <button type="submit" class="btn btn-outline btn-sm" style="color: #ff6b72; border-color: rgba(229, 9, 20, 0.4);">
                    <span>&#128465;</span> Borrar Historial
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <?php if (empty($historial)): ?>
    <div style="text-align: center; padding: 80px 20px; background: var(--bg-card); border-radius: var(--radius-lg); border: 1px solid var(--border-subtle);">
        <div style="font-size: 3rem; margin-bottom: 16px; color: var(--text-muted);">&#9200;</div>
        <h2 style="font-family: var(--font-heading); font-size: 1.6rem; margin-bottom: 10px;">Aún no tienes reproducciones</h2>
        <p style="color: var(--text-secondary); max-width: 450px; margin: 0 auto 24px auto;">
            Cuando reproduzcas trailers en el catálogo o en el reproductor a pantalla completa, tu progreso aparecerá aquí automáticamente.
        </p>
        <a href="<?= BASE_URL ?>/index.php" class="btn btn-primary">Comenzar a Ver</a>
    </div>
    <?php else: ?>
    <div class="movies-grid">
        <?php foreach ($historial as $item): 
            $coverUrl = getMediaUrl($item['portada_key'], $item['tipo_almacenamiento']);
            $trailerUrl = getMediaUrl($item['trailer_key'], $item['tipo_almacenamiento']);
            $isFav = isFavorite($user['id'], (int)$item['id']);
            $progressPct = ($item['segundos_totales'] > 0) ? min(100, round(($item['segundos_progreso'] / $item['segundos_totales']) * 100)) : 0;
        ?>
        <div class="movie-card" data-modal-movie
             data-id="<?= $item['id'] ?>"
             data-title="<?= e($item['titulo']) ?>"
             data-description="<?= e($item['descripcion']) ?>"
             data-category="<?= e($item['categoria_nombre']) ?>"
             data-year="<?= $item['anio'] ?>"
             data-duration="<?= formatDuration((int)$item['duracion']) ?>"
             data-rating="<?= $item['calificacion'] ?>"
             data-trailer-url="<?= e($trailerUrl) ?>"
             data-is-favorite="<?= $isFav ? 'true' : 'false' ?>">
            <div class="card-poster-wrapper">
                <img class="card-poster" src="<?= e($coverUrl) ?>" alt="<?= e($item['titulo']) ?>" loading="lazy">
                <div class="card-badges">
                    <span class="badge-s3">&#9729; S3</span>
                    <?php if ($item['completado']): ?>
                        <span class="badge-top10" style="background: #2ecc71;">COMPLETADO</span>
                    <?php endif; ?>
                </div>
                <div class="card-overlay-actions">
                    <div class="action-buttons">
                        <span class="icon-btn btn-play-circle">&#9658;</span>
                        <a href="<?= BASE_URL ?>/watch.php?id=<?= $item['id'] ?>" class="icon-btn" title="Reanudar en pantalla completa" onclick="event.stopPropagation();">
                            &#9200;
                        </a>
                    </div>
                </div>
                <div class="card-progress-bar">
                    <div class="card-progress-fill" style="width: <?= $progressPct ?>%;"></div>
                </div>
            </div>
            <div class="card-info">
                <h3 class="card-title"><?= e($item['titulo']) ?></h3>
                <div class="card-meta-line">
                    <span style="color: var(--accent-gold); font-weight: 700;">★ <?= e($item['calificacion']) ?></span>
                    <span style="font-size: 0.78rem;"><?= $item['completado'] ? '100% Visto' : $progressPct . '% visto' ?></span>
                </div>
                <div style="font-size: 0.72rem; color: var(--text-muted); margin-top: 4px;">
                    <?= date('d/m/Y H:i', strtotime($item['ultima_reproduccion'])) ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
