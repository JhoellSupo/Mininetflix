<?php
/**
 * MiniNetflix - Redirección a Creación de Usuario en Login
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    header("Location: " . BASE_URL . "/index.php");
    exit;
}

header("Location: " . BASE_URL . "/login.php?tab=register");
exit;
