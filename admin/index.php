<?php
/**
 * MiniNetflix - Panel Administrativo Principal (Esquema en Español)
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

$pdo = getDB();
$s3Client = getS3Client();

// Métricas del sistema
$totalPeliculas = 0;
$totalCategorias = 0;
$totalVistas = 0;
$totalUsuarios = 0;

try {
    $totalPeliculas = (int)$pdo->query("SELECT COUNT(*) FROM peliculas")->fetchColumn();
    $totalCategorias = (int)$pdo->query("SELECT COUNT(*) FROM categorias")->fetchColumn();
    $totalVistas = (int)$pdo->query("SELECT COALESCE(SUM(vistas), 0) FROM peliculas")->fetchColumn();
    $totalUsuarios = (int)$pdo->query("SELECT COUNT(*) FROM usuarios")->fetchColumn();
} catch (Exception $e) {}

// Obtener todas las películas para el listado administrativo
$stmt = $pdo->query("
    SELECT p.*, c.nombre AS categoria_nombre
    FROM peliculas p
    JOIN categorias c ON p.categoria_id = c.id
    ORDER BY p.id DESC
");
$peliculas = $stmt->fetchAll();

$pageTitle = 'Administración de Catálogo';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="admin-container">
    <div class="admin-header">
        <div>
            <h1 class="admin-title">Panel de Control & Catálogo</h1>
            <p style="color: var(--text-secondary); margin-top: 6px;">
                Gestión de contenidos multimedia, trailers y portadas
            </p>
        </div>
        <div style="display: flex; gap: 12px; align-items: center;">
            <a href="<?= BASE_URL ?>/admin/settings.php" class="btn btn-secondary">
                <span>&#9881;</span> Configurar Ajustes & BD
            </a>
            <a href="<?= BASE_URL ?>/admin/movie_create.php" class="btn btn-primary">
                <span>+</span> Registrar Película
            </a>
        </div>
    </div>

    <!-- Tarjetas de Métricas -->
    <div class="metrics-grid">
        <div class="metric-card">
            <div class="metric-icon-box" style="background: rgba(229, 9, 20, 0.15); color: var(--primary-red);">
                &#127916;
            </div>
            <div>
                <div class="metric-val"><?= $totalPeliculas ?></div>
                <div class="metric-label">Películas en Catálogo</div>
            </div>
        </div>

        <div class="metric-card">
            <div class="metric-icon-box" style="background: rgba(255, 184, 0, 0.15); color: var(--accent-gold);">
                &#128193;
            </div>
            <div>
                <div class="metric-val"><?= $totalCategorias ?></div>
                <div class="metric-label">Categorías Activas</div>
            </div>
        </div>

        <div class="metric-card">
            <div class="metric-icon-box" style="background: rgba(0, 128, 255, 0.15); color: var(--accent-blue);">
                &#128065;
            </div>
            <div>
                <div class="metric-val"><?= number_format($totalVistas) ?></div>
                <div class="metric-label">Reproducciones Totales</div>
            </div>
        </div>

        <div class="metric-card">
            <div class="metric-icon-box" style="background: rgba(46, 204, 113, 0.15); color: #2ecc71;">
                &#9729;
            </div>
            <div>
                <div class="metric-val" style="font-size: 1.1rem; font-weight: 700;">
                    <?= $s3Client->isConfigured() ? 'S3 Activo' : 'Modo Seguro' ?>
                </div>
                <div class="metric-label">
                    <?= $s3Client->isConfigured() ? e($s3Client->getBucket()) : 'URLs con HMAC Sig' ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Alerta Informativa S3 -->
    <div class="alert alert-info" style="margin-bottom: 24px;">
        <div style="font-size: 1.3rem;">&#9432;</div>
        <div>
            <strong>Mecanismo de Seguridad AWS S3:</strong> 
            Los trailers e imágenes de portada se almacenan en un bucket privado de Amazon S3 y se sirven exclusivamente a través de 
            <strong>URLs prefirmadas temporales (Presigned URLs SigV4)</strong>, evitando la exposición pública permanente de los archivos.
        </div>
    </div>

    <!-- Tabla de Películas -->
    <div class="admin-table-card">
        <div style="padding: 18px 24px; border-bottom: 1px solid var(--border-subtle); display: flex; justify-content: space-between; align-items: center;">
            <h2 style="font-size: 1.25rem; font-weight: 700;">Catálogo de Películas Registradas</h2>
            <span style="font-size: 0.85rem; color: var(--text-muted);"><?= count($peliculas) ?> registros</span>
        </div>

        <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th style="width: 70px;">Portada</th>
                        <th>Título</th>
                        <th>Categoría</th>
                        <th>Año</th>
                        <th>Duración</th>
                        <th>Puntaje</th>
                        <th>Destacada</th>
                        <th>Vistas</th>
                        <th>Almacenamiento</th>
                        <th style="text-align: right;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($peliculas)): ?>
                    <tr>
                        <td colspan="10" style="text-align: center; padding: 40px; color: var(--text-secondary);">
                            No hay películas registradas. Pulse el botón "Registrar Película en S3" para comenzar.
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($peliculas as $p): 
                        $coverUrl = getMediaUrl($p['portada_key'], $p['tipo_almacenamiento']);
                    ?>
                    <tr>
                        <td>
                            <img src="<?= e($coverUrl) ?>" alt="<?= e($p['titulo']) ?>" class="table-thumb">
                        </td>
                        <td>
                            <strong style="color: white;"><?= e($p['titulo']) ?></strong>
                            <div style="font-size: 0.75rem; color: var(--text-muted);"><?= e($p['clasificacion_edad']) ?></div>
                        </td>
                        <td>
                            <span class="tech-badge"><?= e($p['categoria_nombre']) ?></span>
                        </td>
                        <td><?= e($p['anio']) ?></td>
                        <td><?= formatDuration((int)$p['duracion']) ?></td>
                        <td>
                            <span style="color: var(--accent-gold); font-weight: 700;">★ <?= e($p['calificacion']) ?></span>
                        </td>
                        <td>
                            <?php if ($p['destacada']): ?>
                                <span class="badge-top10" style="font-size: 0.65rem; padding: 2px 6px;">Hero</span>
                            <?php else: ?>
                                <span style="color: var(--text-muted); font-size: 0.8rem;">-</span>
                            <?php endif; ?>
                        </td>
                        <td><?= (int)$p['vistas'] ?></td>
                        <td>
                            <span class="badge-s3">
                                <?= strtoupper($p['tipo_almacenamiento']) ?>
                            </span>
                        </td>
                        <td style="text-align: right;">
                            <div style="display: inline-flex; gap: 8px;">
                                <a href="<?= BASE_URL ?>/watch.php?id=<?= $p['id'] ?>" class="btn btn-outline btn-sm" title="Probar reproductor" target="_blank">
                                    &#9658; Ver
                                </a>
                                <a href="<?= BASE_URL ?>/admin/movie_edit.php?id=<?= $p['id'] ?>" class="btn btn-secondary btn-sm" title="Editar">
                                    &#9998;
                                </a>
                                <a href="<?= BASE_URL ?>/admin/movie_delete.php?id=<?= $p['id'] ?>" class="btn btn-outline btn-sm" style="color: #ff6b72; border-color: rgba(229, 9, 20, 0.4);" onclick="return confirm('¿Seguro que desea eliminar esta película y sus archivos en S3?');" title="Eliminar">
                                    &#128465;
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
