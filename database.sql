-- ========================================================
-- MiniNetflix Database Schema (MySQL) - Versión en Español
-- Plataforma de Streaming de Películas y Trailers con Amazon S3
-- ========================================================

CREATE DATABASE IF NOT EXISTS `mininetflix` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `mininetflix`;

-- 1. Tabla de Usuarios
CREATE TABLE IF NOT EXISTS `usuarios` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `nombre` VARCHAR(100) NOT NULL,
  `correo` VARCHAR(150) NOT NULL UNIQUE,
  `clave` VARCHAR(255) NOT NULL,
  `rol` ENUM('admin', 'usuario') NOT NULL DEFAULT 'usuario',
  `avatar` VARCHAR(255) DEFAULT 'default_avatar.png',
  `creado_en` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Tabla de Categorías / Géneros
CREATE TABLE IF NOT EXISTS `categorias` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `nombre` VARCHAR(100) NOT NULL,
  `slug` VARCHAR(100) NOT NULL UNIQUE,
  `descripcion` TEXT NULL,
  `creado_en` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Tabla de Películas / Videos
CREATE TABLE IF NOT EXISTS `peliculas` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `titulo` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(255) NOT NULL,
  `categoria_id` INT UNSIGNED NOT NULL,
  `descripcion` TEXT NOT NULL,
  `duracion` INT UNSIGNED NOT NULL COMMENT 'Duración en minutos',
  `anio` INT UNSIGNED NOT NULL COMMENT 'Año de estreno',
  `calificacion` DECIMAL(3,1) DEFAULT 8.5,
  `clasificacion_edad` VARCHAR(10) DEFAULT '+16',
  `destacada` TINYINT(1) DEFAULT 0 COMMENT '1 si es destacada en Hero Billboard',
  `portada_key` VARCHAR(500) NOT NULL COMMENT 'Clave de imagen en S3 o ruta relativa',
  `trailer_key` VARCHAR(500) NOT NULL COMMENT 'Clave de video trailer en S3 o ruta relativa',
  `fondo_key` VARCHAR(500) DEFAULT NULL COMMENT 'Imagen de fondo panorámica',
  `tipo_almacenamiento` ENUM('s3', 'local') NOT NULL DEFAULT 's3',
  `vistas` INT UNSIGNED DEFAULT 0,
  `creado_en` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_peliculas_categoria` FOREIGN KEY (`categoria_id`) REFERENCES `categorias` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Tabla de Favoritos (Mi Lista)
CREATE TABLE IF NOT EXISTS `favoritos` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `usuario_id` INT UNSIGNED NOT NULL,
  `pelicula_id` INT UNSIGNED NOT NULL,
  `creado_en` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_usuario_pelicula_favorito` (`usuario_id`, `pelicula_id`),
  CONSTRAINT `fk_fav_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_fav_pelicula` FOREIGN KEY (`pelicula_id`) REFERENCES `peliculas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Tabla de Historial de Reproducciones
CREATE TABLE IF NOT EXISTS `historial_reproducciones` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `usuario_id` INT UNSIGNED NOT NULL,
  `pelicula_id` INT UNSIGNED NOT NULL,
  `segundos_progreso` INT UNSIGNED DEFAULT 0,
  `segundos_totales` INT UNSIGNED DEFAULT 0,
  `completado` TINYINT(1) DEFAULT 0,
  `ultima_reproduccion` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_usuario_pelicula_historial` (`usuario_id`, `pelicula_id`),
  CONSTRAINT `fk_hist_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_hist_pelicula` FOREIGN KEY (`pelicula_id`) REFERENCES `peliculas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Tabla de Configuraciones del Sistema (AWS S3, Parámetros)
CREATE TABLE IF NOT EXISTS `configuraciones` (
  `clave` VARCHAR(100) PRIMARY KEY,
  `valor` TEXT NULL,
  `actualizado_en` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================================
-- Datos Iniciales (Categorías, Usuario Administrador y Usuario Demo)
-- ========================================================

-- Categorías
INSERT IGNORE INTO `categorias` (`id`, `nombre`, `slug`, `descripcion`) VALUES
(1, 'Acción y Aventura', 'accion', 'Explosiones, misiones imposibles, combates y adrenalina pura.'),
(2, 'Ciencia Ficción', 'ciencia-ficcion', 'Viajes en el tiempo, universos paralelos, distopías y tecnología futurista.'),
(3, 'Drama y Suspenso', 'drama', 'Historias intensas, misterios por resolver y giros inesperados.'),
(4, 'Comedia', 'comedia', 'Humor, risas y situaciones entretenidas para toda ocasión.'),
(5, 'Terror y Misterio', 'terror', 'Suspenso psicológico, fenómenos paranormales y emociones fuertes.'),
(6, 'Animación y Fantasía', 'animacion', 'Mundos mágicos y producciones animadas de última generación.');

-- Usuarios por defecto:
-- Administrador: admin@mininetflix.com / admin123
-- Usuario: user@mininetflix.com / user123
INSERT IGNORE INTO `usuarios` (`id`, `nombre`, `correo`, `clave`, `rol`) VALUES
(1, 'Administrador MiniNetflix', 'admin@mininetflix.com', '$2y$10$4wAdAING3Ygnaci/gFY/HuoiEIvJhxoMsx/aJDIzBOjgwXAmD.jvC', 'admin'),
(2, 'Usuario Demo', 'user@mininetflix.com', '$2y$10$XLY0JHxnzRaXeJ0gx1BHO.q2gpUbzudlfjJwSUmSXL4ahIXJxeAha', 'usuario');

-- Parámetros iniciales de AWS S3
INSERT IGNORE INTO `configuraciones` (`clave`, `valor`) VALUES
('aws_access_key_id', ''),
('aws_secret_access_key', ''),
('aws_region', 'us-east-1'),
('aws_bucket', 'mininetflix-media-bucket'),
('s3_presigned_expiry', '3600'),
('storage_driver', 's3');

-- ========================================================
-- Catálogo Inicial de Películas (22 Títulos)
-- ========================================================
INSERT INTO `peliculas` (
    `titulo`, `slug`, `categoria_id`, `descripcion`, `duracion`, `anio`, `calificacion`, 
    `clasificacion_edad`, `destacada`, `portada_key`, `trailer_key`, `fondo_key`, `tipo_almacenamiento`
) VALUES 
('Terror en el Amazonas', 'terror-en-el-amazonas', 5, 'Una expedición científica se adentra en lo más profundo de la selva, donde descubren un mal antiguo y letal que los acecha entre las sombras.', 120, 2024, 5.0, '+13', 1, 'terrorenelamazonas.jpg', 'YTDown.com_YouTube_Media_1UGgrF7rtAw_TERROR-EN-EL-AMAZONAS-Tráiler-Español-Latino-2026_001_1080p.mp4', NULL, 's3'),

('Resistencia', 'resistencia', 4, 'En un futuro dominado por la inteligencia artificial, un exsoldado debe proteger a una niña sintética que posee la clave para acabar con la guerra.', 120, 2023, 5.0, '+13', 0, 'resistencia.jpg', 'YTDown.com_YouTube_Media_2_lxkJVYv40_RESISTENCIA-Tráiler-Español-Latino-2023_001_1080p.mp4', NULL, 's3'),

('Muhammad Ali', 'muhammad-ali', 3, 'Un viaje íntimo y revelador por la vida, los combates y el legado indiscutible del boxeador más grande de todos los tiempos.', 120, 2026, 5.0, '+13', 0, 'muhamed peliculajpg.jpg', 'YTDown.com_YouTube_Media_3gRhCq4R-b4_MUHAMMAD-ALI-EL-MÁS-GRANDE-Tráiler-Español-Latino-2026_001_1080p.mp4', NULL, 's3'),

('Made in Abyss', 'made-in-abyss', 2, 'Los valientes exploradores continúan su descenso hacia las capas más profundas y peligrosas del abismo, enfrentando horrores inimaginables.', 120, 2024, 5.0, '+16', 1, 'madeinabyss.webp', 'YTDown.com_YouTube_Media_69XDNX-mfwU_劇場シリーズ-第一部-メイドインアビス-目覚める神秘-特報映像_001_1080p.mp4', NULL, 's3'),

('Madre Siniestra', 'madre-siniestra', 5, 'La perfecta vida de dos vecinas se desmorona tras un trágico accidente, desencadenando una espiral de paranoia, culpa y oscuros secretos.', 120, 2026, 5.0, '+18', 0, 'madresiniestra.jfif', 'YTDown.com_YouTube_Media_cBEt-Hk8jns_MADRE-SINIESTRA-Tráiler-2-Español-Latino-Doblado-2026-Jessica-Chastain_001_1080p.mp4', NULL, 's3'),

('Ramayana', 'ramayana', 1, 'La épica adaptación del viaje del príncipe Rama para rescatar a su esposa de un rey demonio, en una aventura llena de magia y deidades.', 120, 2026, 5.0, 'TP', 0, 'ramayana.jfif', 'YTDown.com_YouTube_Media_g3crGsrtUC0_RAMAYANA-EL-NACIMIENTO-DE-UNA-LEYENDA-Tráiler-Español-2026_001_1080p.mp4', NULL, 's3'),

('El Ciclo del Hombre Lobo', 'ciclo-hombre-lobo', 5, 'Una pequeña comunidad rural es aterrorizada por una serie de brutales ataques que coinciden misteriosamente con las noches de luna llena.', 120, 2026, 5.0, '+16', 0, 'hombre-lobo.jpg', 'YTDown.com_YouTube_Media_gCz3KSByExU_EL-CICLO-DEL-HOMBRE-LOBO-LA-TRANSFORMACION-Tráiler-Español-Latino-2026_001_1080p.mp4', NULL, 's3'),

('Narnia El Sobrino del Mago', 'narnia-sobrino-mago', 4, 'Descubre los orígenes de Narnia y cómo unos niños viajan entre mundos mediante anillos mágicos, presenciando la creación de Aslan.', 120, 2026, 5.0, 'TP', 1, 'narniaalsobrinodelmago.jpg', 'YTDown.com_YouTube_Media_GVvP8bf3iqw_NARNIA-EL-SOBRINO-DEL-MAGO-Tráiler-Español-Latino-2026-Daniel-Craig_001_1080p.mp4', NULL, 's3'),

('Flow', 'flow', 2, 'Un gato solitario debe unirse a un peculiar grupo de animales en un barco para sobrevivir a una gran inundación que ha destruido su mundo.', 120, 2024, 5.0, 'TP', 0, 'flow.jfif', 'YTDown.com_YouTube_Media_izIuFUnZkjA_FLOW-Tráiler-Español-Latino-2024_001_1080p.mp4', NULL, 's3'),

('La Razón de Estar Contigo', 'razon-de-estar-contigo', 2, 'La conmovedora historia de un perro leal que busca el propósito de su vida a través de distintas reencarnaciones junto a sus amados dueños.', 120, 2024, 5.0, 'TP', 0, 'larazondeestarcontig.jfif', 'YTDown.com_YouTube_Media_JohzxlqNRso_La-razón-de-estar-contigo-Una-Navidad-perfecta-Tráiler-oficial-Netflix_001_1080p.mp4', NULL, 's3'),

('Hexed', 'hexed', 5, 'Un oscuro y retorcido thriller donde la brujería moderna y los secretos del pasado se entrelazan con consecuencias fatales.', 120, 2024, 5.0, '+13', 0, 'hexced.jfif', 'YTDown.com_YouTube_Media_KXO21xZdUaM_Hexed-Official-Trailer_001_2160p.mp4', NULL, 's3'),

('Guardianes de la Galaxia 3', 'guardianes-galaxia-3', 4, 'El equipo debe unirse en una última y peligrosa misión para proteger a Rocket y enfrentar los fantasmas de su trágico pasado.', 120, 2023, 5.0, '+13', 1, 'guardianesdelagalaxia.jpg', 'YTDown.com_YouTube_Media_nFYA2kdHy0s_GUARDIANES-DE-LA-GALAXIA-3-Tráiler-Español-Latino-Nuevo-2023-Super-Bowl_001_1080p.mp4', NULL, 's3'),

('Gatto', 'gatto', 2, 'Las divertidas y emocionantes aventuras de un astuto felino que se embarca en un viaje inolvidable lleno de peligros y descubrimientos.', 120, 2027, 5.0, 'TP', 0, 'gattodisney.jpeg', 'YTDown.com_YouTube_Media_NIKfR_uF_d0_Gatto-Teaser-Trailer-In-Theaters-March-5-2027_001_2160p.mp4', NULL, 's3'),

('Ray Gunn', 'ray-gunn', 4, 'En una ciudad retro-futurista, un detective privado investiga un complejo misterio que lo llevará a descubrir una conspiración intergaláctica.', 120, 2024, 5.0, '+13', 0, 'Ray_Gunn_poster.jpg', 'YTDown.com_YouTube_Media_nw2qIZJwMP4_Ray-Gunn-Tráiler-oficial-Netflix_001_1080p.mp4', NULL, 's3'),

('The Apprentice', 'the-apprentice', 3, 'Una inmersión en los primeros años de Donald Trump y su ascenso en el mundo inmobiliario bajo la tutela del despiadado abogado Roy Cohn.', 120, 2024, 5.0, '+16', 0, 'The_Apprentice_.jpg', 'YTDown.com_YouTube_Media_Sd2DjmqF350_THE-APPRENTICE-Tráiler-Español-2024-Sebastian-Stan_001_1080p.mp4', NULL, 's3'),

('Robot Salvaje', 'robot-salvaje', 2, 'Una robot inteligente naufraga en una isla deshabitada y debe aprender a adaptarse al entorno salvaje mientras forma lazos con los animales locales.', 120, 2024, 5.0, 'TP', 1, 'robotsalvaje.jpg', 'YTDown.com_YouTube_Media_sEXyPinA4lE_ROBOT-SALVAJE-Tráiler-Español-Latino-2024_001_1080p.mp4', NULL, 's3'),

('Vision Quest', 'vision-quest', 4, 'Tras los eventos de Westview, Vision busca reconstruir su memoria y recuperar su humanidad en un viaje lleno de misterios y peligros.', 120, 2026, 5.0, '+13', 0, 'VisionQuest_-_Póster.webp', 'YTDown.com_YouTube_Media_TzrEY8We5Eg_VISIONQUEST-Tráiler-2-Español-Latino-2026-Marvel_001_1080p.mp4', NULL, 's3'),

('Transformers 7', 'transformers-7', 1, 'Los Autobots y los Maximals se unen para enfrentar a una nueva amenaza cósmica que busca devorar planetas enteros: el temible Unicron.', 120, 2023, 5.0, '+13', 1, 'transformers7jpg.jpg', 'YTDown.com_YouTube_Media_v0d0id78XdE_TRANSFORMERS-7-EL-DESPERTAR-DE-LAS-BESTIAS-Tráiler-Español-Latino-2023-ᴴᴰ_001_1080p.mp4', NULL, 's3'),

('The Chosen', 'the-chosen', 3, 'Un drama histórico que sigue de cerca la vida de Jesús de Nazaret y el impacto transformador que tuvo en sus discípulos y seguidores.', 120, 2024, 5.0, '+13', 0, 'thechosen.jpg', 'YTDown.com_YouTube_Media_VVqrE7azRqw_The-Chosen-Season-6-Official-Trailer_001_2160p.mp4', NULL, 's3'),

('La Odisea', 'la-odisea', 1, 'La clásica y épica travesía de Ulises enfrentando a dioses, monstruos y tempestades en su largo camino de regreso a Ítaca.', 120, 2024, 5.0, '+13', 0, 'odisea.jfif', 'YTDown.com_YouTube_Media_WxMqiqIGFpQ_The-Odyssey-Epic-Trailer-Version-Soundtrack-4K-IMAX_001_2160p.mp4', NULL, 's3'),

('Superman', 'superman', 1, 'El Hombre de Acero regresa en una nueva aventura para inspirar esperanza en Metrópolis mientras equilibra su herencia kryptoniana con su vida humana.', 120, 2025, 5.0, '+13', 1, 'supermannjfif.jfif', 'YTDown.com_YouTube_Media_Z_-PgIzDrEo_Superman-2025-Cause-I-m-a-Punkrocker-yes-I-am-Punkrocker-Feat-Iggy-Pop-ending-4K_001_2160p.mp4', NULL, 's3'),

('Jumanji', 'jumanji', 1, 'El peligroso juego de mesa evoluciona a un mundo abierto donde nuevos jugadores deberán sobrevivir a desafíos mortales para regresar a casa.', 120, 2024, 5.0, '+13', 1, 'jumanjiopenworld.jfif', 'YTDown.com_YouTube_Media_zhApeaHMvfs_JUMANJI-OPEN-WORLD-Official-Trailer-4K_001_2160p.mp4', NULL, 's3');

