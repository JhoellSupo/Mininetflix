<?php
/**
 * MiniNetflix - Configuración de AWS S3 y Base de Datos (Esquema en Español)
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/s3.php';

requireAdmin();

$pdo = getDB();
$driver = Database::getActiveDriver();
$s3Client = getS3Client();

// Cargar ajustes actuales de la tabla configuraciones
$settings = [];
try {
    $stmt = $pdo->query("SELECT clave, valor FROM configuraciones");
    while ($row = $stmt->fetch()) {
        $settings[$row['clave']] = $row['valor'];
    }
} catch (Exception $e) {}

$accessKey = $settings['aws_access_key_id'] ?? AWS_ACCESS_KEY_ID;
$secretKey = $settings['aws_secret_access_key'] ?? AWS_SECRET_ACCESS_KEY;
$region    = $settings['aws_region'] ?? AWS_REGION;
$bucket    = $settings['aws_bucket'] ?? AWS_BUCKET;
$expiry    = $settings['s3_presigned_expiry'] ?? S3_PRESIGNED_EXPIRY;

// Procesar guardado de configuración
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accessKey = trim($_POST['aws_access_key_id'] ?? '');
    $secretKey = trim($_POST['aws_secret_access_key'] ?? '');
    $region    = trim($_POST['aws_region'] ?? 'us-east-1');
    $bucket    = trim($_POST['aws_bucket'] ?? '');
    $expiry    = (int)($_POST['s3_presigned_expiry'] ?? 3600);

    try {
        $saveKeys = [
            'aws_access_key_id' => $accessKey,
            'aws_secret_access_key' => $secretKey,
            'aws_region' => $region,
            'aws_bucket' => $bucket,
            's3_presigned_expiry' => (string)$expiry
        ];

        foreach ($saveKeys as $k => $v) {
            if ($driver === 'mysql') {
                $up = $pdo->prepare("INSERT INTO configuraciones (clave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)");
            } else {
                $up = $pdo->prepare("INSERT INTO configuraciones (clave, valor) VALUES (?, ?) ON CONFLICT(clave) DO UPDATE SET valor = excluded.valor");
            }
            $up->execute([$k, $v]);
        }

        setFlash('success', 'Configuración de AWS S3 guardada correctamente en la base de datos.');
        header("Location: " . BASE_URL . "/admin/settings.php");
        exit;
    } catch (Exception $e) {
        setFlash('error', 'Error guardando ajustes: ' . $e->getMessage());
    }
}

$pageTitle = 'Configuración AWS S3 y Base de Datos';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="admin-container">
    <div class="admin-header">
        <div>
            <h1 class="admin-title">Configuración de Amazon S3 & Base de Datos</h1>
            <p style="color: var(--text-secondary); margin-top: 6px;">
                Administre las credenciales de AWS IAM, parámetros de prefirma SigV4 y estado de la base de datos
            </p>
        </div>
        <a href="<?= BASE_URL ?>/admin/index.php" class="btn btn-outline">&larr; Volver al Panel</a>
    </div>

    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 28px;">
        <!-- Formulario de Configuración AWS S3 -->
        <div>
            <div class="admin-table-card" style="padding: 30px; margin-bottom: 24px;">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px;">
                    <h2 style="font-size: 1.3rem; font-weight: 700; color: white;">
                        &#9729; Credenciales de Amazon Simple Storage Service (S3)
                    </h2>
                    <span class="badge-s3" style="font-size: 0.8rem; padding: 4px 10px;">
                        AWS SigV4
                    </span>
                </div>

                <p style="color: var(--text-secondary); font-size: 0.88rem; margin-bottom: 24px;">
                    Estas credenciales son utilizadas por <code>AwsS3Client.php</code> para subir los trailers e imágenes de portada al bucket y para generar las <strong>URLs prefirmadas temporales</strong> con expiración controlada.
                </p>

                <form action="<?= BASE_URL ?>/admin/settings.php" method="POST">
                    <div class="form-group">
                        <label for="aws_bucket" class="form-label">Nombre del Bucket S3 *</label>
                        <input type="text" id="aws_bucket" name="aws_bucket" class="form-control" placeholder="ej. mi-mininetflix-bucket" required value="<?= e($bucket) ?>">
                        <small style="color: var(--text-muted); font-size: 0.78rem;">El nombre exacto de su bucket creado en AWS S3 Console.</small>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div class="form-group">
                            <label for="aws_region" class="form-label">Región AWS *</label>
                            <select id="aws_region" name="aws_region" class="form-control">
                                <option value="us-east-1" <?= $region === 'us-east-1' ? 'selected' : '' ?>>US East (N. Virginia) us-east-1</option>
                                <option value="us-east-2" <?= $region === 'us-east-2' ? 'selected' : '' ?>>US East (Ohio) us-east-2</option>
                                <option value="us-west-1" <?= $region === 'us-west-1' ? 'selected' : '' ?>>US West (N. California) us-west-1</option>
                                <option value="us-west-2" <?= $region === 'us-west-2' ? 'selected' : '' ?>>US West (Oregon) us-west-2</option>
                                <option value="sa-east-1" <?= $region === 'sa-east-1' ? 'selected' : '' ?>>South America (São Paulo) sa-east-1</option>
                                <option value="eu-west-1" <?= $region === 'eu-west-1' ? 'selected' : '' ?>>Europe (Ireland) eu-west-1</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="s3_presigned_expiry" class="form-label">Tiempo de Expiración de URLs (Segundos)</label>
                            <input type="number" id="s3_presigned_expiry" name="s3_presigned_expiry" class="form-control" placeholder="3600" min="60" max="604800" value="<?= e($expiry) ?>">
                            <small style="color: var(--text-muted); font-size: 0.78rem;">Ej. 3600 = 1 hora de validez temporal.</small>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="aws_access_key_id" class="form-label">AWS Access Key ID *</label>
                        <input type="text" id="aws_access_key_id" name="aws_access_key_id" class="form-control" placeholder="AKIA..." value="<?= e($accessKey) ?>">
                    </div>

                    <div class="form-group">
                        <label for="aws_secret_access_key" class="form-label">AWS Secret Access Key *</label>
                        <input type="password" id="aws_secret_access_key" name="aws_secret_access_key" class="form-control" placeholder="••••••••••••••••••••••••" value="<?= e($secretKey) ?>">
                    </div>

                    <div style="display: flex; gap: 14px; margin-top: 26px;">
                        <button type="submit" class="btn btn-primary">
                            <span>&#128190;</span> Guardar Configuración
                        </button>
                        <button type="button" class="btn btn-secondary" id="btnTestS3">
                            <span>&#9889;</span> Probar Conexión con AWS S3
                        </button>
                    </div>

                    <div id="testResultBox" style="margin-top: 18px; display: none;"></div>
                </form>
            </div>
        </div>

        <!-- Columna de Estado del Entorno y Base de Datos -->
        <div>
            <!-- Estado de Base de Datos -->
            <div class="admin-table-card" style="padding: 24px; margin-bottom: 24px;">
                <h3 style="font-size: 1.1rem; font-weight: 700; color: white; margin-bottom: 14px;">
                    &#128451; Estado de la Base de Datos
                </h3>

                <div style="display: flex; flex-direction: column; gap: 12px; font-size: 0.88rem;">
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: var(--text-muted);">Motor Activo:</span>
                        <strong style="color: <?= $driver === 'mysql' ? '#2ecc71' : '#FFB800' ?>; text-transform: uppercase;">
                            <?= $driver === 'mysql' ? 'MySQL (Nativo)' : 'SQLite (Fallback)' ?>
                        </strong>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: var(--text-muted);">Host MySQL:</span>
                        <span><?= DB_HOST ?>:<?= DB_PORT ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: var(--text-muted);">Base de Datos:</span>
                        <span><?= DB_NAME ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: var(--text-muted);">Usuario DB:</span>
                        <span><?= DB_USER ?></span>
                    </div>
                </div>

                <div style="margin-top: 20px; padding-top: 16px; border-top: 1px solid var(--border-subtle);">
                    <p style="font-size: 0.8rem; color: var(--text-secondary); line-height: 1.5;">
                        El script <code>database.sql</code> incluye todas las tablas en español con claves foráneas.
                    </p>
                </div>
            </div>

            <!-- Mecanismo de Seguridad AWS -->
            <div class="admin-table-card" style="padding: 24px;">
                <h3 style="font-size: 1.1rem; font-weight: 700; color: white; margin-bottom: 12px;">
                    &#128274; Seguridad en Amazon S3
                </h3>
                <ul style="color: var(--text-secondary); font-size: 0.82rem; line-height: 1.6; padding-left: 18px;">
                    <li>Archivos con permisos privados en el bucket.</li>
                    <li>Acceso concedido mediante URLs prefirmadas SigV4 con firma criptográfica HMAC-SHA256.</li>
                    <li>Expiración programable por objeto.</li>
                    <li>No se exponen credenciales de AWS al cliente ni en el HTML.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
    document.getElementById('btnTestS3').addEventListener('click', () => {
        const resultBox = document.getElementById('testResultBox');
        resultBox.style.display = 'block';
        resultBox.className = 'alert alert-info';
        resultBox.textContent = 'Enviando petición HEAD a AWS S3 para probar autenticación y bucket...';

        const payload = {
            aws_access_key_id: document.getElementById('aws_access_key_id').value,
            aws_secret_access_key: document.getElementById('aws_secret_access_key').value,
            aws_region: document.getElementById('aws_region').value,
            aws_bucket: document.getElementById('aws_bucket').value
        };

        fetch('<?= BASE_URL ?>/api/test_s3.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                resultBox.className = 'alert alert-success';
                resultBox.textContent = '✓ ' + data.message;
            } else {
                resultBox.className = 'alert alert-error';
                resultBox.textContent = '✗ ' + (data.message || 'Error en prueba de conexión S3.');
            }
        })
        .catch(err => {
            resultBox.className = 'alert alert-error';
            resultBox.textContent = 'Error al comunicarse con la API de prueba.';
        });
    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
