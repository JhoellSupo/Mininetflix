<?php
/**
 * MiniNetflix - Generador de Datos de Demostracion (Esquema en Español)
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$pdo = getDB();
$driver = Database::getActiveDriver();

echo "Iniciando carga de datos en modo [{$driver}] con tablas en español...\n";

// Películas de demostración con trailers y portadas
$peliculas = [
    [
        'titulo' => 'Tears of Steel: Resistencia Cyberpunk',
        'categoria_id' => 2, // Ciencia Ficción
        'descripcion' => 'En un futuro distópico dominado por cíborgs y robots de combate, un grupo de científicos y rebeldes en Ámsterdam intenta un experimento desesperado para reescribir el destino de la humanidad a través de recuerdos compartidos.',
        'duracion' => 124,
        'anio' => 2024,
        'calificacion' => 9.2,
        'clasificacion_edad' => '+16',
        'destacada' => 1,
        'portada_key' => 'https://images.unsplash.com/photo-1518709268805-4e9042af9f23?auto=format&fit=crop&w=600&q=80',
        'fondo_key' => 'https://images.unsplash.com/photo-1578632767115-351597cf2477?auto=format&fit=crop&w=1920&q=80',
        'trailer_key' => 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/TearsOfSteel.mp4',
        'tipo_almacenamiento' => 's3'
    ],
    [
        'titulo' => 'Sintel: La Búsqueda del Dragón',
        'categoria_id' => 1, // Acción y Aventura
        'descripcion' => 'Una joven solitaria rescata a un pequeño dragón herido y forja una conexión inquebrantable. Cuando el dragón es arrebatado por una bestia adulta, emprende una peligrosa travesía cruzando desiertos y montañas heladas.',
        'duracion' => 110,
        'anio' => 2023,
        'calificacion' => 8.8,
        'clasificacion_edad' => '+13',
        'destacada' => 0,
        'portada_key' => 'https://images.unsplash.com/photo-1534447677768-be436bb09401?auto=format&fit=crop&w=600&q=80',
        'fondo_key' => 'https://images.unsplash.com/photo-1518709268805-4e9042af9f23?auto=format&fit=crop&w=1920&q=80',
        'trailer_key' => 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/Sintel.mp4',
        'tipo_almacenamiento' => 's3'
    ],
    [
        'titulo' => 'Big Buck Bunny: La Venganza del Bosque',
        'categoria_id' => 4, // Comedia
        'descripcion' => 'Un conejo gigante de corazón noble ve interrumpida su apacible vida en el bosque cuando una pandilla de roedores traviesos agrede a sus amigos voladores. Ingeniosas trampas y risas aseguradas.',
        'duracion' => 95,
        'anio' => 2023,
        'calificacion' => 8.6,
        'clasificacion_edad' => 'ATP',
        'destacada' => 0,
        'portada_key' => 'https://images.unsplash.com/photo-1579783900882-c0d3dad7b119?auto=format&fit=crop&w=600&q=80',
        'fondo_key' => 'https://images.unsplash.com/photo-1579783900882-c0d3dad7b119?auto=format&fit=crop&w=1920&q=80',
        'trailer_key' => 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4',
        'tipo_almacenamiento' => 's3'
    ],
    [
        'titulo' => 'Cosmos Laundromat: El Destino de Fígaro',
        'categoria_id' => 6, // Animación y Fantasía
        'descripcion' => 'En una isla desolada, una oveja deprimida llamada Franck conoce a Rodrigo, un vendedor misterioso que le ofrece la oportunidad de vivir múltiples vidas extraordinarias a través de una lavandería interdimensional.',
        'duracion' => 102,
        'anio' => 2024,
        'calificacion' => 8.9,
        'clasificacion_edad' => '+16',
        'destacada' => 0,
        'portada_key' => 'https://images.unsplash.com/photo-1507679799987-c73779587ccf?auto=format&fit=crop&w=600&q=80',
        'fondo_key' => 'https://images.unsplash.com/photo-1507679799987-c73779587ccf?auto=format&fit=crop&w=1920&q=80',
        'trailer_key' => 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ElephantsDream.mp4',
        'tipo_almacenamiento' => 's3'
    ],
    [
        'titulo' => 'Sombras de Medianoche: El Faro Maldito',
        'categoria_id' => 5, // Terror y Misterio
        'descripcion' => 'Dos guardianes de faro en una remota isla del Atlántico Norte comienzan a perder la noción de la realidad cuando extraños susurros emergen de las profundidades marinas durante una tormenta implacable.',
        'duracion' => 118,
        'anio' => 2023,
        'calificacion' => 8.4,
        'clasificacion_edad' => '+18',
        'destacada' => 0,
        'portada_key' => 'https://images.unsplash.com/photo-1509248961158-e54f6934749c?auto=format&fit=crop&w=600&q=80',
        'fondo_key' => 'https://images.unsplash.com/photo-1509248961158-e54f6934749c?auto=format&fit=crop&w=1920&q=80',
        'trailer_key' => 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ForBiggerBlazes.mp4',
        'tipo_almacenamiento' => 's3'
    ],
    [
        'titulo' => 'Círculo de Sombras: La Conspiración',
        'categoria_id' => 3, // Drama y Suspenso
        'descripcion' => 'Un detective retirado es convocado para resolver un crimen de cuello blanco que involucra a las esferas más altas del poder financiero, revelando una red de secretos y traiciones.',
        'duracion' => 135,
        'anio' => 2024,
        'calificacion' => 8.7,
        'clasificacion_edad' => '+16',
        'destacada' => 0,
        'portada_key' => 'https://images.unsplash.com/photo-1485846234645-a62644f84728?auto=format&fit=crop&w=600&q=80',
        'fondo_key' => 'https://images.unsplash.com/photo-1485846234645-a62644f84728?auto=format&fit=crop&w=1920&q=80',
        'trailer_key' => 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ForBiggerEscapes.mp4',
        'tipo_almacenamiento' => 's3'
    ],
    [
        'titulo' => 'Horizonte Cero: Misión Titán',
        'categoria_id' => 2, // Ciencia Ficción
        'descripcion' => 'La primera tripulación humana enviada a colonizar la luna de Saturno pierde contacto con la Tierra tras descubrir una señal electromagnética anómala en el cráter Menrva.',
        'duracion' => 142,
        'anio' => 2024,
        'calificacion' => 9.0,
        'clasificacion_edad' => '+13',
        'destacada' => 0,
        'portada_key' => 'https://images.unsplash.com/photo-1451187580459-43490279c0fa?auto=format&fit=crop&w=600&q=80',
        'fondo_key' => 'https://images.unsplash.com/photo-1451187580459-43490279c0fa?auto=format&fit=crop&w=1920&q=80',
        'trailer_key' => 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ForBiggerFun.mp4',
        'tipo_almacenamiento' => 's3'
    ],
    [
        'titulo' => 'Risas en Tokio: Perdidos en Akihabara',
        'categoria_id' => 4, // Comedia
        'descripcion' => 'Dos amigos ganan un viaje a Japón en un concurso de comida rápida y terminan accidentalmente involucrados en el rodaje de un programa de variedades clandestino.',
        'duracion' => 98,
        'anio' => 2023,
        'calificacion' => 8.1,
        'clasificacion_edad' => 'ATP',
        'destacada' => 0,
        'portada_key' => 'https://images.unsplash.com/photo-1503899036084-c55cdd92da26?auto=format&fit=crop&w=600&q=80',
        'fondo_key' => 'https://images.unsplash.com/photo-1503899036084-c55cdd92da26?auto=format&fit=crop&w=1920&q=80',
        'trailer_key' => 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ForBiggerJoyBlazes.mp4',
        'tipo_almacenamiento' => 's3'
    ]
];

// Limpiar datos previos
$pdo->exec("DELETE FROM favoritos");
$pdo->exec("DELETE FROM historial_reproducciones");
$pdo->exec("DELETE FROM peliculas");

$ins = $pdo->prepare("
    INSERT INTO peliculas (
        titulo, slug, categoria_id, descripcion, duracion, anio,
        calificacion, clasificacion_edad, destacada, portada_key, fondo_key, trailer_key, tipo_almacenamiento, vistas, creado_en
    ) VALUES (
        ?, ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP
    )
");

$createdIds = [];
foreach ($peliculas as $p) {
    $slug = slugify($p['titulo']);
    $vistas = rand(120, 3500);
    $ins->execute([
        $p['titulo'], $slug, $p['categoria_id'], $p['descripcion'], $p['duracion'], $p['anio'],
        $p['calificacion'], $p['clasificacion_edad'], $p['destacada'], $p['portada_key'], $p['fondo_key'], $p['trailer_key'], $p['tipo_almacenamiento'], $vistas
    ]);
    $createdIds[] = $pdo->lastInsertId();
    echo "✓ Película creada: {$p['titulo']}\n";
}

// Sembrar favoritos
if (count($createdIds) >= 3) {
    $fav = $pdo->prepare("INSERT INTO favoritos (usuario_id, pelicula_id, creado_en) VALUES (?, ?, CURRENT_TIMESTAMP)");
    $fav->execute([2, $createdIds[0]]);
    $fav->execute([2, $createdIds[1]]);
    $fav->execute([1, $createdIds[0]]);
    echo "✓ Favoritos iniciales sembrados.\n";

    // Sembrar historial
    if ($driver === 'mysql') {
        $hist = $pdo->prepare("INSERT INTO historial_reproducciones (usuario_id, pelicula_id, segundos_progreso, segundos_totales, completado, ultima_reproduccion) VALUES (?, ?, ?, ?, ?, NOW())");
    } else {
        $hist = $pdo->prepare("INSERT INTO historial_reproducciones (usuario_id, pelicula_id, segundos_progreso, segundos_totales, completado, ultima_reproduccion) VALUES (?, ?, ?, ?, ?, datetime('now'))");
    }
    $hist->execute([2, $createdIds[0], 48, 120, 0]);
    $hist->execute([2, $createdIds[2], 110, 110, 1]);
    echo "✓ Historial inicial sembrado.\n";
}

// Actualizar contraseñas de prueba
$updatePass = $pdo->prepare("UPDATE usuarios SET clave = ? WHERE correo = ?");
$updatePass->execute([password_hash('admin123', PASSWORD_BCRYPT), 'admin@mininetflix.com']);
$updatePass->execute([password_hash('user123', PASSWORD_BCRYPT), 'user@mininetflix.com']);
echo "✓ Contraseñas de usuario actualizadas y verificadas.\n";

echo "¡Seeding en español completado exitosamente!\n";
