<?php
/**
 * MiniNetflix - API para Historial de Reproducciones y Progreso (Esquema en Español)
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autorizado.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$peliculaId = isset($input['movie_id']) ? (int)$input['movie_id'] : 0;
$progreso = isset($input['progress_seconds']) ? max(0, (int)$input['progress_seconds']) : 0;
$total = isset($input['total_seconds']) ? max(0, (int)$input['total_seconds']) : 0;
$completado = !empty($input['completed']) ? 1 : 0;

if ($peliculaId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'ID de película inválido.']);
    exit;
}

$usuarioId = currentUser()['id'];
$pdo = getDB();
$driver = Database::getActiveDriver();

try {
    if ($driver === 'mysql') {
        $stmt = $pdo->prepare("
            INSERT INTO historial_reproducciones (usuario_id, pelicula_id, segundos_progreso, segundos_totales, completado, ultima_reproduccion)
            VALUES (?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                segundos_progreso = VALUES(segundos_progreso),
                segundos_totales = VALUES(segundos_totales),
                completado = VALUES(completado),
                ultima_reproduccion = NOW()
        ");
        $stmt->execute([$usuarioId, $peliculaId, $progreso, $total, $completado]);
    } else {
        // SQLite upsert
        $stmt = $pdo->prepare("
            INSERT INTO historial_reproducciones (usuario_id, pelicula_id, segundos_progreso, segundos_totales, completado, ultima_reproduccion)
            VALUES (?, ?, ?, ?, ?, datetime('now'))
            ON CONFLICT(usuario_id, pelicula_id) DO UPDATE SET
                segundos_progreso = excluded.segundos_progreso,
                segundos_totales = excluded.segundos_totales,
                completado = excluded.completado,
                ultima_reproduccion = datetime('now')
        ");
        $stmt->execute([$usuarioId, $peliculaId, $progreso, $total, $completado]);
    }

    // Incrementar contador de vistas en la película
    $inc = $pdo->prepare("UPDATE peliculas SET vistas = vistas + 1 WHERE id = ?");
    $inc->execute([$peliculaId]);

    echo json_encode([
        'success' => true,
        'movie_id' => $peliculaId,
        'progress_seconds' => $progreso,
        'total_seconds' => $total,
        'completed' => (bool)$completado
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error al guardar progreso: ' . $e->getMessage()]);
}
