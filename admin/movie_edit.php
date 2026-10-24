<?php
/**
 * MiniNetflix - Edición de Película y Reemplazo de Archivos en Amazon S3 (Esquema en Español)
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

$peliculaId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($peliculaId <= 0) {
    header("Location: " . BASE_URL . "/admin/index.php");
    exit;
}

$pdo = getDB();
$s3Client = getS3Client();

$stmt = $pdo->prepare("SELECT * FROM peliculas WHERE id = ?");
$stmt->execute([$peliculaId]);
$pelicula = $stmt->fetch();

if (!$pelicula) {
    setFlash('error', 'Película no encontrada.');
    header("Location: " . BASE_URL . "/admin/index.php");
    exit;
}

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

    if (empty($titulo)) $errors[] = 'El título es obligatorio.';
    if ($categoriaId <= 0) $errors[] = 'Debe seleccionar una categoría.';
    if ($duracion <= 0) $errors[] = 'La duración debe ser mayor a 0.';
    if (empty($descripcion)) $errors[] = 'La descripción es obligatoria.';

    $portadaKey = $pelicula['portada_key'];
    $trailerKey = $pelicula['trailer_key'];
    $fondoKey = $pelicula['fondo_key'];
    $tipoAlmacenamiento = $pelicula['tipo_almacenamiento'];
    $slug = slugify($titulo);
    $timestamp = time();

    // Reemplazo de portada
    if (isset($_FILES['cover_file']) && $_FILES['cover_file']['error'] === UPLOAD_ERR_OK) {
        $coverTmp = $_FILES['cover_file']['tmp_name'];
        $coverExt = strtolower(pathinfo($_FILES['cover_file']['name'], PATHINFO_EXTENSION));
        $coverName = "covers/{$slug}-{$timestamp}.{$coverExt}";
        $coverMime = $_FILES['cover_file']['type'] ?: 'image/jpeg';

        if ($s3Client->isConfigured()) {
            $s3Upload = $s3Client->putObject($coverName, $coverTmp, $coverMime);
            if ($s3Upload['success']) {
                $portadaKey = $coverName;
                $tipoAlmacenamiento = 's3';
            } else {
                $errors[] = "Error subiendo portada a S3: " . ($s3Upload['error'] ?? '');
            }
        } else {
            $destPath = UPLOADS_DIR . '/' . $coverName;
            if (move_uploaded_file($coverTmp, $destPath)) {
                $portadaKey = $coverName;
                $tipoAlmacenamiento = 'local';
            }
        }
    }

    // Reemplazo de trailer
    if (isset($_FILES['trailer_file']) && $_FILES['trailer_file']['error'] === UPLOAD_ERR_OK) {
        $trailerTmp = $_FILES['trailer_file']['tmp_name'];
        $trailerExt = strtolower(pathinfo($_FILES['trailer_file']['name'], PATHINFO_EXTENSION));
        $trailerName = "trailers/{$slug}-{$timestamp}.{$trailerExt}";
        $trailerMime = $_FILES['trailer_file']['type'] ?: 'video/mp4';

        if ($s3Client->isConfigured()) {
            $s3Upload = $s3Client->putObject($trailerName, $trailerTmp, $trailerMime);
            if ($s3Upload['success']) {
                $trailerKey = $trailerName;
                $tipoAlmacenamiento = 's3';
            } else {
                $errors[] = "Error subiendo trailer a S3: " . ($s3Upload['error'] ?? '');
            }
        } else {
            $destPath = UPLOADS_DIR . '/' . $trailerName;
            if (move_uploaded_file($trailerTmp, $destPath)) {
                $trailerKey = $trailerName;
                $tipoAlmacenamiento = 'local';
            }
        }
    }

    if (empty($errors)) {
        try {
            if ($destacada) {
                $pdo->exec("UPDATE peliculas SET destacada = 0");
            }

            $update = $pdo->prepare("
                UPDATE peliculas SET
                    titulo = ?, slug = ?, categoria_id = ?, descripcion = ?, duracion = ?,
                    anio = ?, calificacion = ?, clasificacion_edad = ?, destacada = ?,
                    portada_key = ?, trailer_key = ?, fondo_key = ?, tipo_almacenamiento = ?,
                    actualizado_en = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $update->execute([
                $titulo, $slug, $categoriaId, $descripcion, $duracion,
                $anio, $calificacion, $clasificacionEdad, $destacada,
                $portadaKey, $trailerKey, $fondoKey, $tipoAlmacenamiento,
                $peliculaId
            ]);

            setFlash('success', "Película '{$titulo}' actualizada con éxito.");
            header("Location: " . BASE_URL . "/admin/index.php");
            exit;

        } catch (Exception $e) {
            $errors[] = 'Error al actualizar base de datos: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Editar Película: ' . $pelicula['titulo'];
require_once __DIR__ . '/../includes/header.php';
?>

<div class="admin-container">
    <div class="admin-header">
        <div>
            <h1 class="admin-title">Editar Película</h1>
            <p style="color: var(--text-secondary); margin-top: 6px;">
                Modifique los metadatos descriptivos en MySQL o sustituya los archivos en Amazon S3
            </p>
        </div>
        <a href="<?= BASE_URL ?>/admin/index.php" class="btn btn-outline">&larr; Volver</a>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <ul>
                <?php foreach ($errors as $e): ?>
                    <li><?= e($e) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="admin-table-card" style="padding: 32px;">
        <form action="<?= BASE_URL ?>/admin/movie_edit.php?id=<?= $pelicula['id'] ?>" method="POST" enctype="multipart/form-data">
            
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px; margin-bottom: 20px;">
                <div class="form-group">
                    <label for="title" class="form-label">Título *</label>
                    <input type="text" id="title" name="title" class="form-control" required value="<?= e($pelicula['titulo']) ?>">
                </div>

                <div class="form-group">
                    <label for="category_id" class="form-label">Categoría *</label>
                    <select id="category_id" name="category_id" class="form-control" required>
                        <?php foreach ($categorias as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= $pelicula['categoria_id'] == $cat['id'] ? 'selected' : '' ?>>
                                <?= e($cat['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label for="description" class="form-label">Sinopsis / Descripción *</label>
                <textarea id="description" name="description" class="form-control" rows="4" required><?= e($pelicula['descripcion']) ?></textarea>
            </div>

            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 24px;">
                <div class="form-group">
                    <label for="duration" class="form-label">Duración (Minutos) *</label>
                    <input type="number" id="duration" name="duration" class="form-control" min="1" required value="<?= e($pelicula['duracion']) ?>">
                </div>

                <div class="form-group">
                    <label for="release_year" class="form-label">Año de Estreno *</label>
                    <input type="number" id="release_year" name="release_year" class="form-control" min="1900" max="2030" required value="<?= e($pelicula['anio']) ?>">
                </div>

                <div class="form-group">
                    <label for="rating" class="form-label">Puntaje (0 - 10)</label>
                    <input type="number" step="0.1" id="rating" name="rating" class="form-control" min="0" max="10" value="<?= e($pelicula['calificacion']) ?>">
                </div>

                <div class="form-group">
                    <label for="age_rating" class="form-label">Clasificación</label>
                    <select id="age_rating" name="age_rating" class="form-control">
                        <option value="ATP" <?= $pelicula['clasificacion_edad'] === 'ATP' ? 'selected' : '' ?>>ATP</option>
                        <option value="+13" <?= $pelicula['clasificacion_edad'] === '+13' ? 'selected' : '' ?>>+13</option>
                        <option value="+16" <?= $pelicula['clasificacion_edad'] === '+16' ? 'selected' : '' ?>>+16</option>
                        <option value="+18" <?= $pelicula['clasificacion_edad'] === '+18' ? 'selected' : '' ?>>+18</option>
                    </select>
                </div>
            </div>

            <div style="margin-bottom: 24px;">
                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; color: white; font-weight: 600;">
                    <input type="checkbox" name="featured" value="1" <?= $pelicula['destacada'] ? 'checked' : '' ?> style="width: 18px; height: 18px; accent-color: var(--primary-red);">
                    Destacar en el Hero Billboard de la página principal
                </label>
            </div>

            <div style="border-top: 1px solid var(--border-subtle); padding-top: 24px; margin-top: 10px;">
                <h3 style="font-size: 1.2rem; margin-bottom: 16px; color: white;">
                    Archivos en Almacenamiento (<?= strtoupper($pelicula['tipo_almacenamiento']) ?>)
                </h3>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
                    <div>
                        <div style="margin-bottom: 8px; font-weight: 600; font-size: 0.9rem;">Portada Actual:</div>
                        <img src="<?= e(getMediaUrl($pelicula['portada_key'], $pelicula['tipo_almacenamiento'])) ?>" style="width: 120px; border-radius: 6px; margin-bottom: 10px;">
                        <label class="form-label">Subir nueva portada (opcional)</label>
                        <input type="file" name="cover_file" accept="image/*" class="form-control">
                    </div>

                    <div>
                        <div style="margin-bottom: 8px; font-weight: 600; font-size: 0.9rem;">Trailer Actual:</div>
                        <div style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 10px; word-break: break-all;">
                            Clave S3: <code><?= e($pelicula['trailer_key']) ?></code>
                        </div>
                        <label class="form-label">Subir nuevo video trailer (opcional)</label>
                        <input type="file" name="trailer_file" accept="video/mp4,video/webm" class="form-control">
                    </div>
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 14px; margin-top: 30px; border-top: 1px solid var(--border-subtle); padding-top: 24px;">
                <a href="<?= BASE_URL ?>/admin/index.php" class="btn btn-outline">Cancelar</a>
                <button type="submit" class="btn btn-primary" style="padding: 12px 32px;">
                    Guardar Cambios
                </button>
            </div>

        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
