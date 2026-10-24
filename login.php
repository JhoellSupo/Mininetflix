<?php
/**
 * MiniNetflix - Inicio de Sesión y Creación Rápida de Usuario
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (isLoggedIn()) {
    header("Location: " . BASE_URL . "/index.php");
    exit;
}

$error = '';
$activeTab = (isset($_GET['tab']) && $_GET['tab'] === 'register') ? 'register' : 'login';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'login';

    if ($action === 'register') {
        $activeTab = 'register';
        $nombre = trim($_POST['name'] ?? '');
        $correo = trim($_POST['email'] ?? '');
        $clave = $_POST['password'] ?? '';

        if (empty($nombre) || empty($correo) || empty($clave)) {
            $error = 'Por favor complete todos los campos (nombre, correo y contraseña).';
        } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            $error = 'El correo electrónico ingresado no tiene un formato válido.';
        } elseif (strlen($clave) < 4) {
            $error = 'La contraseña debe tener al menos 4 caracteres.';
        } else {
            try {
                $pdo = getDB();
                // Verificar si ya existe este correo
                $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE correo = ? LIMIT 1");
                $stmt->execute([$correo]);
                if ($stmt->fetch()) {
                    $error = 'Este correo electrónico ya está registrado. Por favor inicie sesión.';
                } else {
                    $claveHash = password_hash($clave, PASSWORD_BCRYPT);
                    // Rol estrictamente 'usuario' para cuentas nuevas (admin permanece intacto)
                    $ins = $pdo->prepare("
                        INSERT INTO usuarios (nombre, correo, clave, rol, avatar, creado_en) 
                        VALUES (?, ?, ?, 'usuario', 'default_avatar.png', CURRENT_TIMESTAMP)
                    ");
                    $ins->execute([$nombre, $correo, $claveHash]);
                    $nuevoId = (int)$pdo->lastInsertId();

                    // Iniciar sesión automáticamente
                    loginUser([
                        'id' => $nuevoId,
                        'nombre' => $nombre,
                        'correo' => $correo,
                        'rol' => 'usuario',
                        'avatar' => 'default_avatar.png'
                    ]);

                    setFlash('success', "¡Bienvenido a " . APP_NAME . ", {$nombre}! Tu cuenta ha sido creada con éxito.");
                    header("Location: " . BASE_URL . "/index.php");
                    exit;
                }
            } catch (Exception $e) {
                $error = 'Error al registrar el usuario: ' . $e->getMessage();
            }
        }
    } else {
        // Inicio de sesión normal
        $activeTab = 'login';
        $correo = trim($_POST['email'] ?? '');
        $clave = $_POST['password'] ?? '';

        if (empty($correo) || empty($clave)) {
            $error = 'Por favor ingrese su correo y contraseña.';
        } else {
            try {
                $pdo = getDB();
                $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE correo = ? LIMIT 1");
                $stmt->execute([$correo]);
                $usuario = $stmt->fetch();

                if ($usuario && password_verify($clave, $usuario['clave'])) {
                    loginUser($usuario);
                    setFlash('success', "¡Bienvenido de nuevo, {$usuario['nombre']}!");
                    header("Location: " . BASE_URL . "/index.php");
                    exit;
                } else {
                    $error = 'Correo electrónico o contraseña incorrectos.';
                }
            } catch (Exception $e) {
                $error = 'Error de conexión: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $activeTab === 'register' ? 'Crear Usuario' : 'Iniciar Sesión' ?> - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= file_exists(__DIR__ . '/assets/css/style.css') ? filemtime(__DIR__ . '/assets/css/style.css') : time() ?>">
</head>
<body class="auth-page">

    <div class="auth-card">
        <div class="auth-header">
            <a href="<?= BASE_URL ?>/index.php" class="brand-logo" style="justify-content: center;">
                <span class="brand-icon">&#9658;</span> <?= APP_NAME ?>
            </a>
            <h1 class="auth-title" id="auth-title">
                <?= $activeTab === 'register' ? 'Crear Usuario' : 'Inicia Sesión' ?>
            </h1>
            <p class="auth-subtitle" id="auth-subtitle">
                <?= $activeTab === 'register' ? 'Crea tu cuenta simple y guarda tus películas favoritas' : 'Accede a tus películas y trailers favoritos' ?>
            </p>
        </div>

        <!-- Pestañas para alternar entre Iniciar Sesión y Crear Usuario -->
        <div class="auth-tabs" role="tablist">
            <button type="button" 
                    id="tab-login" 
                    class="auth-tab <?= $activeTab === 'login' ? 'active' : '' ?>" 
                    onclick="switchAuthTab('login')">
                Iniciar Sesión
            </button>
            <button type="button" 
                    id="tab-register" 
                    class="auth-tab <?= $activeTab === 'register' ? 'active' : '' ?>" 
                    onclick="switchAuthTab('register')">
                Crear Usuario
            </button>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error">&#9888; <?= e($error) ?></div>
        <?php endif; ?>

        <?php 
        $flashMsg = getFlash('error');
        if ($flashMsg): 
        ?>
            <div class="alert alert-error">&#9888; <?= e($flashMsg) ?></div>
        <?php endif; ?>

        <!-- ================= PANEL 1: INICIAR SESIÓN ================= -->
        <div id="panel-login" style="<?= $activeTab === 'login' ? 'display: block;' : 'display: none;' ?>">
            <form action="<?= BASE_URL ?>/login.php" method="POST">
                <input type="hidden" name="action" value="login">

                <div class="form-group">
                    <label for="email" class="form-label">Correo Electrónico</label>
                    <input type="email" id="email" name="email" class="form-control" placeholder="ejemplo@correo.com" required <?= $activeTab === 'login' ? 'autofocus' : '' ?> value="<?= e($_POST['email'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">Contraseña</label>
                    <input type="password" id="password" name="password" class="form-control" placeholder="••••••••" required>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 10px;">
                    Iniciar Sesión
                </button>
            </form>

            <div class="auth-footer" style="margin-top: 24px;">
                ¿No tienes una cuenta? <a href="javascript:void(0)" onclick="switchAuthTab('register')">Crear usuario ahora</a>
            </div>
        </div>

        <!-- ================= PANEL 2: CREAR USUARIO SIMPLE ================= -->
        <div id="panel-register" style="<?= $activeTab === 'register' ? 'display: block;' : 'display: none;' ?>">
            <div class="auth-info-box">
                <span style="font-size: 1.1rem; color: var(--accent-gold);">&#9733;</span>
                <span>Registro simple: crea tu cuenta de <strong>Usuario</strong> con tu propia lista de favoritos independiente.</span>
            </div>

            <form action="<?= BASE_URL ?>/login.php" method="POST">
                <input type="hidden" name="action" value="register">

                <div class="form-group">
                    <label for="reg_name" class="form-label">Nombre Completo</label>
                    <input type="text" id="reg_name" name="name" class="form-control" placeholder="Ej. Juan Pérez" required <?= $activeTab === 'register' ? 'autofocus' : '' ?> value="<?= e($_POST['name'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label for="reg_email" class="form-label">Correo Electrónico</label>
                    <input type="email" id="reg_email" name="email" class="form-control" placeholder="usuario@correo.com" required value="<?= e($_POST['email'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label for="reg_password" class="form-label">Contraseña</label>
                    <input type="password" id="reg_password" name="password" class="form-control" placeholder="Mínimo 4 caracteres" required>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 10px;">
                    Crear Usuario y Entrar
                </button>
            </form>

            <div class="auth-footer">
                ¿Ya tienes cuenta? <a href="javascript:void(0)" onclick="switchAuthTab('login')">Inicia sesión</a>
            </div>
        </div>
    </div>

    <script>
        function switchAuthTab(tab) {
            const tabLogin = document.getElementById('tab-login');
            const tabRegister = document.getElementById('tab-register');
            const panelLogin = document.getElementById('panel-login');
            const panelRegister = document.getElementById('panel-register');
            const title = document.getElementById('auth-title');
            const subtitle = document.getElementById('auth-subtitle');

            if (tab === 'register') {
                tabLogin.classList.remove('active');
                tabRegister.classList.add('active');
                panelLogin.style.display = 'none';
                panelRegister.style.display = 'block';
                title.textContent = 'Crear Usuario';
                subtitle.textContent = 'Crea tu cuenta simple y guarda tus películas favoritas';
                document.getElementById('reg_name').focus();
            } else {
                tabRegister.classList.remove('active');
                tabLogin.classList.add('active');
                panelRegister.style.display = 'none';
                panelLogin.style.display = 'block';
                title.textContent = 'Inicia Sesión';
                subtitle.textContent = 'Accede a tus películas y trailers favoritos';
                document.getElementById('email').focus();
            }

            // Actualizar URL sin recargar para persistir el estado
            if (window.history && window.history.replaceState) {
                const newUrl = window.location.pathname + (tab === 'register' ? '?tab=register' : '');
                window.history.replaceState(null, '', newUrl);
            }
        }
    </script>
</body>
</html>
