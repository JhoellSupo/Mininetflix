<?php
/**
 * MiniNetflix - Reproductor HTML5 a Pantalla Completa (Modo Cine)
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();

$peliculaId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($peliculaId <= 0) {
    header("Location: " . BASE_URL . "/index.php");
    exit;
}

$user = currentUser();
$pdo = getDB();

$stmt = $pdo->prepare("
    SELECT p.*, c.nombre AS categoria_nombre
    FROM peliculas p
    JOIN categorias c ON p.categoria_id = c.id
    WHERE p.id = ?
");
$stmt->execute([$peliculaId]);
$pelicula = $stmt->fetch();

if (!$pelicula) {
    header("Location: " . BASE_URL . "/index.php");
    exit;
}

// Obtener progreso previo
$progress = getWatchProgress($user['id'], $peliculaId);
$resumeTime = ($progress && !$progress['completado']) ? $progress['segundos_progreso'] : 0;

// Obtener URL de transmisión prefirmada
$trailerUrl = getMediaUrl($pelicula['trailer_key'], $pelicula['tipo_almacenamiento'], 7200); // 2 horas
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reproduciendo: <?= e($pelicula['titulo']) ?> - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= file_exists(__DIR__ . '/assets/css/style.css') ? filemtime(__DIR__ . '/assets/css/style.css') : time() ?>">
    <style>
        body {
            background: #000;
            overflow: hidden;
            margin: 0;
            padding: 0;
        }
        .theater-video {
            width: 100vw;
            height: 100vh;
            object-fit: contain;
            background: #000;
        }
        .resume-prompt {
            position: absolute;
            bottom: 120px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(24, 25, 31, 0.95);
            border: 1px solid var(--primary-red);
            padding: 14px 24px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 16px;
            z-index: 3020;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.8);
        }
    </style>
</head>
<body>

    <div class="watch-container" id="watchContainer">
        <!-- Barra de Controles Superior -->
        <div class="watch-header-overlay" id="watchHeader">
            <a href="<?= BASE_URL ?>/index.php" class="watch-back-btn">
                <span style="font-size: 1.5rem;">&#8592;</span> Volver al Catálogo
            </a>
            <div style="text-align: center;">
                <h1 style="font-size: 1.3rem; font-weight: 800; margin: 0;"><?= e($pelicula['titulo']) ?></h1>
                <div style="font-size: 0.82rem; color: var(--text-secondary); margin-top: 4px;">
                    <span><?= e($pelicula['categoria_nombre']) ?></span> &bull; 
                    <span><?= e($pelicula['anio']) ?></span> &bull; 
                    <span style="color: #6ee7b7;">Streaming HD</span>
                </div>
            </div>
            <div style="width: 120px; text-align: right;">
                <span class="badge-res">4K UHD</span>
            </div>
        </div>

        <!-- Mensaje para reanudar si existe progreso -->
        <?php if ($resumeTime > 10): ?>
        <div class="resume-prompt" id="resumePrompt">
            <span>¿Deseas reanudar desde el segundo <?= $resumeTime ?>s?</span>
            <button class="btn btn-primary btn-sm" id="btnResume">Reanudar</button>
            <button class="btn btn-outline btn-sm" id="btnRestart">Desde el inicio</button>
        </div>
        <?php endif; ?>

        <!-- Reproductor de Video HTML5 -->
        <video id="player" class="theater-video" controls autoplay playsinline preload="auto">
            <source src="<?= e($trailerUrl) ?>" type="video/mp4">
            Tu navegador no soporta la reproducción de video HTML5.
        </video>
    </div>

    <script>
        const player = document.getElementById('player');
        const container = document.getElementById('watchContainer');
        const header = document.getElementById('watchHeader');
        const resumePrompt = document.getElementById('resumePrompt');
        const movieId = <?= (int)$pelicula['id'] ?>;
        const resumeTime = <?= (int)$resumeTime ?>;

        let hideTimer;
        function showControls() {
            container.classList.remove('hide-controls');
            clearTimeout(hideTimer);
            hideTimer = setTimeout(() => {
                if (!player.paused) {
                    container.classList.add('hide-controls');
                }
            }, 3000);
        }

        window.addEventListener('mousemove', showControls);
        window.addEventListener('keydown', showControls);

        if (resumePrompt) {
            document.getElementById('btnResume').addEventListener('click', () => {
                player.currentTime = resumeTime;
                player.play();
                resumePrompt.remove();
            });
            document.getElementById('btnRestart').addEventListener('click', () => {
                player.currentTime = 0;
                player.play();
                resumePrompt.remove();
            });
            setTimeout(() => { if (resumePrompt) resumePrompt.remove(); }, 8000);
        }

        // Reporte periódico de progreso al historial
        let lastReport = 0;
        player.addEventListener('timeupdate', () => {
            const current = Math.floor(player.currentTime);
            if (current - lastReport >= 5) {
                lastReport = current;
                fetch('<?= BASE_URL ?>/api/history.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        movie_id: movieId,
                        progress_seconds: current,
                        total_seconds: Math.floor(player.duration || 0),
                        completed: 0
                    })
                });
            }
        });

        player.addEventListener('ended', () => {
            fetch('<?= BASE_URL ?>/api/history.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    movie_id: movieId,
                    progress_seconds: Math.floor(player.duration || 0),
                    total_seconds: Math.floor(player.duration || 0),
                    completed: 1
                })
            });
        });

        // Atajos de teclado (Espacio, Flechas, F)
        window.addEventListener('keydown', (e) => {
            if (e.code === 'Space') {
                e.preventDefault();
                player.paused ? player.play() : player.pause();
            } else if (e.code === 'ArrowRight') {
                player.currentTime += 10;
            } else if (e.code === 'ArrowLeft') {
                player.currentTime -= 10;
            } else if (e.code === 'KeyF') {
                if (!document.fullscreenElement) {
                    container.requestFullscreen().catch(() => {});
                } else {
                    document.exitFullscreen().catch(() => {});
                }
            }
        });
    </script>
</body>
</html>
