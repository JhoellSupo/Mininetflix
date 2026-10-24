<?php
/**
 * MiniNetflix - Registro de Película y Carga de Archivos a Amazon S3 (Esquema en Español)
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

$pdo = getDB();
$s3Client = getS3Client();
$categorias = $pdo->query("SELECT * FROM categorias ORDER BY nombre ASC")->fetchAll();

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo = trim($_POST['title'] ?? '');
    $categoriaId = (int)($_POST['category_id'] ?? 0);
    $descripcion = trim($_POST['description'] ?? '');
    $duracion = (int)($_POST['duration'] ?? 0);
    $anio = (int)($_POST['release_year'] ?? date('Y'));
    $calificacion = (float)($_POST['rating'] ?? 8.0);
    $clasificacionEdad = trim($_POST['age_rating'] ?? '+16');
    $destacada = !empty($_POST['featured']) ? 1 : 0;

    // Validación básica
    if (empty($titulo)) {
        $errors[] = 'El título es obligatorio.';
    }
    if ($categoriaId <= 0) {
        $errors[] = 'Debe seleccionar una categoría válida.';
    }
    if ($duracion <= 0) {
        $errors[] = 'La duración debe ser mayor a 0 minutos.';
    }
    if (empty($descripcion)) {
        $errors[] = 'La descripción es obligatoria.';
    }

    $slug = slugify($titulo);
    $timestamp = time();

    // 1. Manejo del Archivo de Portada (Imagen)
    $portadaKey = '';
    $tipoAlmacenamiento = $s3Client->isConfigured() ? 's3' : 'local';

    if (isset($_FILES['cover_file']) && $_FILES['cover_file']['error'] === UPLOAD_ERR_OK) {
        $coverTmp = $_FILES['cover_file']['tmp_name'];
        $coverExt = strtolower(pathinfo($_FILES['cover_file']['name'], PATHINFO_EXTENSION));
        $coverMime = $_FILES['cover_file']['type'] ?: 'image/jpeg';
        $coverName = "covers/{$slug}-{$timestamp}.{$coverExt}";

        if ($s3Client->isConfigured()) {
            $s3Upload = $s3Client->putObject($coverName, $coverTmp, $coverMime);
            if ($s3Upload['success']) {
                $portadaKey = $coverName;
                $tipoAlmacenamiento = 's3';
            } else {
                $errors[] = "Error subiendo portada a S3: " . ($s3Upload['error'] ?? 'desconocido');
            }
        } else {
            $destPath = UPLOADS_DIR . '/' . $coverName;
            if (!is_dir(dirname($destPath))) {
                mkdir(dirname($destPath), 0777, true);
            }
            if (move_uploaded_file($coverTmp, $destPath)) {
                $portadaKey = $coverName;
                $tipoAlmacenamiento = 'local';
            } else {
                $errors[] = 'No se pudo guardar la imagen de portada localmente.';
            }
        }
    } elseif (!empty($_POST['cover_key_manual'])) {
        $portadaKey = trim($_POST['cover_key_manual']);
    } else {
        $errors[] = 'Debe cargar un archivo de portada o ingresar una ruta válida.';
    }

    // 2. Manejo del Archivo de Video / Trailer
    $trailerKey = '';
    if (isset($_FILES['trailer_file']) && $_FILES['trailer_file']['error'] === UPLOAD_ERR_OK) {
        $trailerTmp = $_FILES['trailer_file']['tmp_name'];
        $trailerExt = strtolower(pathinfo($_FILES['trailer_file']['name'], PATHINFO_EXTENSION));
        $trailerMime = $_FILES['trailer_file']['type'] ?: 'video/mp4';
        $trailerName = "trailers/{$slug}-{$timestamp}.{$trailerExt}";

        if ($s3Client->isConfigured()) {
            $s3Upload = $s3Client->putObject($trailerName, $trailerTmp, $trailerMime);
            if ($s3Upload['success']) {
                $trailerKey = $trailerName;
            } else {
                $errors[] = "Error subiendo trailer a S3: " . ($s3Upload['error'] ?? 'desconocido');
            }
        } else {
            $destPath = UPLOADS_DIR . '/' . $trailerName;
            if (!is_dir(dirname($destPath))) {
                mkdir(dirname($destPath), 0777, true);
            }
            if (move_uploaded_file($trailerTmp, $destPath)) {
                $trailerKey = $trailerName;
            } else {
                $errors[] = 'No se pudo guardar el archivo de video localmente.';
            }
        }
    } elseif (!empty($_POST['trailer_key_manual'])) {
        $trailerKey = trim($_POST['trailer_key_manual']);
    } else {
        $errors[] = 'Debe cargar un video trailer o ingresar una ruta/URL válida.';
    }

    // 3. Manejo opcional de Fondo / Backdrop (Panorámico)
    $fondoKey = null;
    if (isset($_FILES['backdrop_file']) && $_FILES['backdrop_file']['error'] === UPLOAD_ERR_OK) {
        $bdTmp = $_FILES['backdrop_file']['tmp_name'];
        $bdExt = strtolower(pathinfo($_FILES['backdrop_file']['name'], PATHINFO_EXTENSION));
        $bdMime = $_FILES['backdrop_file']['type'] ?: 'image/jpeg';
        $bdName = "covers/backdrop-{$slug}-{$timestamp}.{$bdExt}";

        if ($s3Client->isConfigured()) {
            $s3Bd = $s3Client->putObject($bdName, $bdTmp, $bdMime);
            if ($s3Bd['success']) {
                $fondoKey = $bdName;
            }
        } else {
            $destPath = UPLOADS_DIR . '/' . $bdName;
            if (move_uploaded_file($bdTmp, $destPath)) {
                $fondoKey = $bdName;
            }
        }
    }

    // Si no hubo errores, insertar en peliculas
    if (empty($errors)) {
        try {
            if ($destacada) {
                $pdo->exec("UPDATE peliculas SET destacada = 0");
            }

            $stmt = $pdo->prepare("
                INSERT INTO peliculas (
                    titulo, slug, categoria_id, descripcion, duracion, anio,
                    calificacion, clasificacion_edad, destacada, portada_key, trailer_key, fondo_key, tipo_almacenamiento, creado_en
                ) VALUES (
                    ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP
                )
            ");
            $stmt->execute([
                $titulo, $slug, $categoriaId, $descripcion, $duracion, $anio,
                $calificacion, $clasificacionEdad, $destacada, $portadaKey, $trailerKey, $fondoKey, $tipoAlmacenamiento
            ]);

            setFlash('success', "¡Película '{$titulo}' registrada con éxito y recursos asociados guardados!");
            header("Location: " . BASE_URL . "/admin/index.php");
            exit;

        } catch (Exception $e) {
            $errors[] = 'Error al registrar en base de datos: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Registrar Nueva Película';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="admin-container">
    <div class="admin-header">
        <div>
            <h1 class="admin-title">Registrar Nueva Película</h1>
            <p style="color: var(--text-secondary); margin-top: 6px;">
                Carga el trailer y portada a Amazon S3 y almacena la información en MySQL
            </p>
        </div>
        <a href="<?= BASE_URL ?>/admin/index.php" class="btn btn-outline">
            &larr; Volver al Listado
        </a>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <div>
                <strong>No se pudo guardar la película:</strong>
                <ul style="margin-top: 6px; padding-left: 20px;">
                    <?php foreach ($errors as $err): ?>
                        <li><?= e($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    <?php endif; ?>

    <div class="admin-table-card" style="padding: 32px;">
        <form action="<?= BASE_URL ?>/admin/movie_create.php" method="POST" enctype="multipart/form-data">
            
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px; margin-bottom: 20px;">
                <div class="form-group">
                    <label for="title" class="form-label">Título de la Película / Contenido *</label>
                    <input type="text" id="title" name="title" class="form-control" placeholder="Ej. Interstellar, Inception, El Padrino..." required value="<?= e($_POST['title'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label for="category_id" class="form-label">Categoría / Género *</label>
                    <select id="category_id" name="category_id" class="form-control" required>
                        <option value="">-- Seleccionar Categoría --</option>
                        <?php foreach ($categorias as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= (isset($_POST['category_id']) && $_POST['category_id'] == $cat['id']) ? 'selected' : '' ?>>
                                <?= e($cat['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label for="description" class="form-label">Sinopsis / Descripción Detallada *</label>
                <textarea id="description" name="description" class="form-control" rows="4" placeholder="Argumento y detalles de la película..." required><?= e($_POST['description'] ?? '') ?></textarea>
            </div>

            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 24px;">
                <div class="form-group">
                    <label for="duration" class="form-label">Duración (Minutos) *</label>
                    <input type="number" id="duration" name="duration" class="form-control" placeholder="120" min="1" required value="<?= e($_POST['duration'] ?? '115') ?>">
                </div>

                <div class="form-group">
                    <label for="release_year" class="form-label">Año de Estreno *</label>
                    <input type="number" id="release_year" name="release_year" class="form-control" placeholder="2024" min="1900" max="2030" required value="<?= e($_POST['release_year'] ?? date('Y')) ?>">
                </div>

                <div class="form-group">
                    <label for="rating" class="form-label">Puntaje / Calificación (0 - 10)</label>
                    <input type="number" step="0.1" id="rating" name="rating" class="form-control" placeholder="8.5" min="0" max="10" value="<?= e($_POST['rating'] ?? '8.5') ?>">
                </div>

                <div class="form-group">
                    <label for="age_rating" class="form-label">Clasificación de Edad</label>
                    <select id="age_rating" name="age_rating" class="form-control">
                        <option value="ATP">ATP (Todo Público)</option>
                        <option value="+13">+13 (Mayores 13)</option>
                        <option value="+16" selected>+16 (Mayores 16)</option>
                        <option value="+18">+18 (Adultos)</option>
                    </select>
                </div>
            </div>

            <div style="margin-bottom: 24px;">
                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; color: white; font-weight: 600;">
                    <input type="checkbox" name="featured" value="1" <?= !empty($_POST['featured']) ? 'checked' : '' ?> style="width: 18px; height: 18px; accent-color: var(--primary-red);">
                    Destacar en el Hero Billboard de la página principal (Portada grande destacada)
                </label>
            </div>

            <div style="border-top: 1px solid var(--border-subtle); padding-top: 24px; margin-top: 10px;">
                <h3 style="font-size: 1.2rem; margin-bottom: 16px; color: white;">
                    &#9729; Carga de Archivos Multimedia (Amazon S3)
                </h3>
                <p style="color: var(--text-secondary); font-size: 0.88rem; margin-bottom: 20px;">
                    Seleccione los archivos desde su equipo. Si las credenciales de AWS S3 están configuradas, se subirán automáticamente mediante PUT autenticado con SigV4.
                </p>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
                    <!-- Portada -->
                    <div class="form-group">
                        <label class="form-label">Imagen de Portada (Poster vertical) *</label>
                        <div class="file-dropzone" onclick="document.getElementById('cover_file').click();">
                            <div class="dropzone-icon">&#128444;</div>
                            <div style="font-weight: 600; color: white; margin-bottom: 4px;">Seleccionar imagen de portada</div>
                            <div style="font-size: 0.78rem; color: var(--text-muted);">Formatos: JPG, PNG, WEBP (Ratio 2:3 recomendado)</div>
                            <input type="file" id="cover_file" name="cover_file" accept="image/*" style="display: none;" onchange="previewFileName(this, 'cover_name_disp')">
                            <div id="cover_name_disp" style="margin-top: 8px; color: var(--accent-gold); font-weight: 600; font-size: 0.85rem;"></div>
                        </div>
                        <div style="margin-top: 8px;">
                            <input type="text" name="cover_key_manual" class="form-control" placeholder="O ingrese clave/URL S3 existente si ya la subió" style="font-size: 0.82rem; padding: 8px 12px;">
                        </div>
                    </div>

                    <!-- Trailer -->
                    <div class="form-group">
                        <label class="form-label">Video Trailer Oficial (HTML5 Video) *</label>
                        <div class="file-dropzone" onclick="document.getElementById('trailer_file').click();">
                            <div class="dropzone-icon">&#127916;</div>
                            <div style="font-weight: 600; color: white; margin-bottom: 4px;">Seleccionar archivo de video</div>
                            <div style="font-size: 0.78rem; color: var(--text-muted);">Formatos: MP4 (H.264), WEBM (Resolución HD/4K)</div>
                            <input type="file" id="trailer_file" name="trailer_file" accept="video/mp4,video/webm" style="display: none;" onchange="previewFileName(this, 'trailer_name_disp')">
                            <div id="trailer_name_disp" style="margin-top: 8px; color: var(--accent-gold); font-weight: 600; font-size: 0.85rem;"></div>
                        </div>
                        <div style="margin-top: 8px;">
                            <input type="text" name="trailer_key_manual" class="form-control" placeholder="O ingrese clave/URL S3 existente si ya la subió" style="font-size: 0.82rem; padding: 8px 12px;">
                        </div>
                    </div>
                </div>

                <!-- Backdrop opcional -->
                <div class="form-group" style="margin-top: 18px;">
                    <label class="form-label">Imagen de Fondo Panorámica / Backdrop (Opcional para Billboard 16:9)</label>
                    <input type="file" name="backdrop_file" accept="image/*" class="form-control" style="padding: 9px 12px;">
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 14px; margin-top: 30px; border-top: 1px solid var(--border-subtle); padding-top: 24px;">
                <a href="<?= BASE_URL ?>/admin/index.php" class="btn btn-outline">Cancelar</a>
                <button type="submit" class="btn btn-primary" style="padding: 12px 32px;">
                    <span>&#128190;</span> Guardar en MySQL y Subir a S3
                </button>
            </div>

        </form>
    </div>
</div>

<script>
    function previewFileName(input, targetId) {
        if (input.files && input.files[0]) {
            const file = input.files[0];
            const sizeMb = (file.size / (1024 * 1024)).toFixed(2);
            document.getElementById(targetId).textContent = `Seleccionado: ${file.name} (${sizeMb} MB)`;
        }
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
