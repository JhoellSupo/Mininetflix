<?php
/**
 * MiniNetflix - API para Agregar/Eliminar de Mi Lista (Favoritos)
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autorizado. Inicie sesión.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$peliculaId = isset($input['movie_id']) ? (int)$input['movie_id'] : 0;

if ($peliculaId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'ID de película inválido.']);
    exit;
}

$usuarioId = currentUser()['id'];
$pdo = getDB();

try {
    // Verificar si ya existe en favoritos
    $stmt = $pdo->prepare("SELECT id FROM favoritos WHERE usuario_id = ? AND pelicula_id = ?");
    $stmt->execute([$usuarioId, $peliculaId]);
    $fav = $stmt->fetch();

    if ($fav) {
        $del = $pdo->prepare("DELETE FROM favoritos WHERE id = ?");
        $del->execute([$fav['id']]);
        echo json_encode([
            'success' => true,
            'is_favorite' => false,
            'message' => 'Eliminado de Mi Lista.'
        ]);
    } else {
        $ins = $pdo->prepare("INSERT INTO favoritos (usuario_id, pelicula_id, creado_en) VALUES (?, ?, CURRENT_TIMESTAMP)");
        $ins->execute([$usuarioId, $peliculaId]);
        echo json_encode([
            'success' => true,
            'is_favorite' => true,
            'message' => 'Agregado a Mi Lista.'
        ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error al actualizar favoritos: ' . $e->getMessage()]);
}
