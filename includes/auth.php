<?php
/**
 * MiniNetflix - Gestión de Autenticación y Control de Acceso
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function currentUser(): ?array {
    if (!isLoggedIn()) {
        return null;
    }
    return [
        'id' => $_SESSION['user_id'],
        'nombre' => $_SESSION['user_name'] ?? 'Usuario',
        'correo' => $_SESSION['user_email'] ?? '',
        'rol' => $_SESSION['user_role'] ?? 'usuario',
        'avatar' => $_SESSION['user_avatar'] ?? 'default_avatar.png',
        // Alias para compatibilidad
        'name' => $_SESSION['user_name'] ?? 'Usuario',
        'email' => $_SESSION['user_email'] ?? '',
        'role' => $_SESSION['user_role'] ?? 'usuario'
    ];
}

function isAdmin(): bool {
    if (!isLoggedIn()) return false;
    $rol = $_SESSION['user_role'] ?? '';
    return ($rol === 'admin');
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        $_SESSION['flash_error'] = 'Debe iniciar sesión para acceder al catálogo.';
        $redirectUrl = BASE_URL . '/login.php';
        header("Location: {$redirectUrl}");
        exit;
    }
}

function requireAdmin(): void {
    requireLogin();
    if (!isAdmin()) {
        $_SESSION['flash_error'] = 'Acceso denegado. Se requieren permisos de administrador.';
        $redirectUrl = BASE_URL . '/index.php';
        header("Location: {$redirectUrl}");
        exit;
    }
}

function loginUser(array $usuario): void {
    $_SESSION['user_id'] = (int)$usuario['id'];
    $_SESSION['user_name'] = $usuario['nombre'] ?? ($usuario['name'] ?? 'Usuario');
    $_SESSION['user_email'] = $usuario['correo'] ?? ($usuario['email'] ?? '');
    $_SESSION['user_role'] = $usuario['rol'] ?? ($usuario['role'] ?? 'usuario');
    $_SESSION['user_avatar'] = $usuario['avatar'] ?? 'default_avatar.png';
}

function logoutUser(): void {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }
    session_destroy();
}
