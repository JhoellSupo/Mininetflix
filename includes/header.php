<?php
/**
 * MiniNetflix - Header y Barra de Navegación
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

$user = currentUser();
$currentPage = basename($_SERVER['SCRIPT_NAME'] ?? '');
$dbDriver = Database::getActiveDriver();
$s3Client = getS3Client();
$isS3Ready = $s3Client->isConfigured();

// Obtener categorías para el menú desplegable
$pdo = getDB();
$navCategories = [];
try {
    $navCategories = $pdo->query("SELECT id, nombre, slug FROM categorias ORDER BY nombre ASC")->fetchAll();
} catch (Exception $e) {
    // Si no cargó aún
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? e($pageTitle) . ' - ' : '' ?><?= APP_NAME ?> | Plataforma Streaming</title>
    <meta name="description" content="MiniNetflix: Catálogo multimedia moderno con streaming en HTML5, MySQL y almacenamiento en Amazon S3.">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= file_exists(__DIR__ . '/../assets/css/style.css') ? filemtime(__DIR__ . '/../assets/css/style.css') : time() ?>">
    <script>
        window.BASE_URL = "<?= BASE_URL ?>";
    </script>
</head>
<body>

    <!-- Barra de Navegación Principal -->
    <header class="navbar">
        <div class="nav-left">
            <a href="<?= BASE_URL ?>/index.php" class="brand-logo" title="<?= APP_NAME ?>">
                <span class="brand-icon">&#9658;</span> <?= APP_NAME ?>
            </a>

            <?php if (isLoggedIn()): ?>
            <ul class="nav-links">
                <li><a href="<?= BASE_URL ?>/index.php" class="<?= $currentPage === 'index.php' && !isset($_GET['category']) ? 'active' : '' ?>">Inicio</a></li>
                <li><a href="<?= BASE_URL ?>/my_list.php" class="<?= $currentPage === 'my_list.php' ? 'active' : '' ?>">Mi Lista</a></li>
                <li><a href="<?= BASE_URL ?>/history.php" class="<?= $currentPage === 'history.php' ? 'active' : '' ?>">Historial</a></li>
                <?php if (isAdmin()): ?>
                <li><a href="<?= BASE_URL ?>/admin/index.php" class="<?= str_contains($currentPage, 'admin') ? 'active' : '' ?>" style="color: #FFB800;">Panel Admin</a></li>
                <?php endif; ?>
            </ul>
            <?php endif; ?>
        </div>

        <div class="nav-right">
            <?php if (isLoggedIn()): ?>
                <!-- Buscador en tiempo real -->
                <div class="search-wrapper">
                    <span class="search-icon">&#128269;</span>
                    <input type="text" class="search-input" placeholder="Títulos, géneros..." aria-label="Buscar películas">
                </div>

                <!-- Perfil y Menú de Usuario -->
                <div class="user-menu-wrapper">
                    <button class="user-btn" aria-label="Menú de usuario">
                        <div class="user-avatar">
                            <?= strtoupper(substr($user['name'] ?? 'U', 0, 1)) ?>
                        </div>
                    </button>

                    <div class="user-dropdown">
                        <div class="dropdown-header">
                            <div class="dropdown-user-name"><?= e($user['name'] ?? 'Usuario') ?></div>
                            <div class="dropdown-user-role"><?= e($user['role'] ?? 'user') ?></div>
                        </div>

                        <a href="<?= BASE_URL ?>/my_list.php" class="dropdown-item">
                            <span>&#9825;</span> Mi Lista Favorita
                        </a>
                        <a href="<?= BASE_URL ?>/history.php" class="dropdown-item">
                            <span>&#9200;</span> Historial de Vistos
                        </a>

                        <?php if (isAdmin()): ?>
                        <div class="dropdown-divider"></div>
                        <a href="<?= BASE_URL ?>/admin/index.php" class="dropdown-item" style="color: #FFB800;">
                            <span>&#9881;</span> Gestión de Películas S3
                        </a>
                        <a href="<?= BASE_URL ?>/admin/settings.php" class="dropdown-item">
                            <span>&#9729;</span> Configuración AWS S3 / BD
                        </a>
                        <?php endif; ?>

                        <div class="dropdown-divider"></div>
                        <a href="<?= BASE_URL ?>/logout.php" class="dropdown-item" style="color: #ff6b72;">
                            <span>&#10006;</span> Cerrar Sesión
                        </a>
                    </div>
                </div>

            <?php else: ?>
                <a href="<?= BASE_URL ?>/login.php" class="btn btn-primary btn-sm">Iniciar Sesión</a>
            <?php endif; ?>
        </div>
    </header>

    <!-- Indicador de Notificaciones Flash -->
    <?php 
    $flashSuccess = getFlash('success');
    $flashError = getFlash('error');
    if ($flashSuccess || $flashError): 
    ?>
    <div style="position: fixed; top: 80px; right: 24px; z-index: 2100; max-width: 400px;">
        <?php if ($flashSuccess): ?>
            <div class="alert alert-success">&#10004; <?= e($flashSuccess) ?></div>
        <?php endif; ?>
        <?php if ($flashError): ?>
            <div class="alert alert-error">&#9888; <?= e($flashError) ?></div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
