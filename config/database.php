<?php
/**
 * MiniNetflix - Conexión de Base de Datos
 * Compatible con MySQL (esquema en español) y SQLite para ejecución inmediata.
 */

require_once __DIR__ . '/config.php';

class Database {
    private static ?PDO $instance = null;
    private static string $activeDriver = 'mysql';
    private static ?string $connectionError = null;

    public static function getConnection(): PDO {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $connectionPref = defined('DB_CONNECTION') ? strtolower(trim(DB_CONNECTION)) : 'auto';
        $sqlitePath = STORAGE_DIR . '/database.sqlite';

        // 1. Si se configuró explícitamente SQLite, conectar de inmediato (0.2 ms)
        if ($connectionPref === 'sqlite') {
            $sqliteDsn = "sqlite:" . $sqlitePath;
            $pdo = new PDO($sqliteDsn, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
            $pdo->exec('PRAGMA foreign_keys = ON;');
            self::$instance = $pdo;
            self::$activeDriver = 'sqlite';
            self::checkAndInitTables($pdo, 'sqlite');
            return self::$instance;
        }

        // 2. Si es 'auto' o 'mysql', verificar disponibilidad sin bloquear
        $host = defined('DB_HOST') ? DB_HOST : '127.0.0.1';
        $port = defined('DB_PORT') ? (int)DB_PORT : 3306;
        $dbname = defined('DB_NAME') ? DB_NAME : 'mininetflix';
        $user = defined('DB_USER') ? DB_USER : 'root';
        $pass = defined('DB_PASS') ? DB_PASS : '';

        $mysqlAvailable = true;
        $offlineMarker = STORAGE_DIR . '/.mysql_offline';

        // En modo 'auto', comprobar rápido si es localhost para no penalizar cada request
        if ($connectionPref === 'auto') {
            $isRemote = ($host !== '127.0.0.1' && $host !== 'localhost');
            if (!$isRemote) {
                $isOfflineCached = file_exists($offlineMarker) && (time() - filemtime($offlineMarker)) < 30;
                if ($isOfflineCached) {
                    $mysqlAvailable = false;
                } else {
                    $fp = @fsockopen($host, $port, $errno, $errstr, 0.2);
                    if ($fp) {
                        fclose($fp);
                        if (file_exists($offlineMarker)) {
                            @unlink($offlineMarker);
                        }
                    } else {
                        $mysqlAvailable = false;
                        @touch($offlineMarker);
                    }
                }
            }
        }

        if ($mysqlAvailable) {
            try {
                $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
                $timeout = ($host !== '127.0.0.1' && $host !== 'localhost') ? 6 : 3;
                $pdo = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_TIMEOUT => $timeout
                ]);

                self::$instance = $pdo;
                self::$activeDriver = 'mysql';
                self::checkAndInitTables($pdo, 'mysql');
                return self::$instance;

            } catch (PDOException $e) {
                self::$connectionError = $e->getMessage();
                @touch($offlineMarker);
                
                // Si la base de datos no existe en MySQL, intentar crearla
                if (strpos($e->getMessage(), 'Unknown database') !== false) {
                    try {
                        $rootDsn = "mysql:host={$host};port={$port};charset=utf8mb4";
                        $rootPdo = new PDO($rootDsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                        $rootPdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbname}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                        
                        $pdo = new PDO($dsn, $user, $pass, [
                            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                        ]);
                        self::$instance = $pdo;
                        self::$activeDriver = 'mysql';
                        self::checkAndInitTables($pdo, 'mysql');
                        return self::$instance;
                    } catch (Exception $ex) {}
                }
            }
        }

        // 3. Fallback instantáneo a SQLite local
        $sqliteDsn = "sqlite:" . $sqlitePath;
        $pdo = new PDO($sqliteDsn, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);

        $pdo->exec('PRAGMA foreign_keys = ON;');

        self::$instance = $pdo;
        self::$activeDriver = 'sqlite';
        self::checkAndInitTables($pdo, 'sqlite');
        return self::$instance;
    }

    public static function getActiveDriver(): string {
        if (self::$instance === null) {
            self::getConnection();
        }
        return self::$activeDriver;
    }

    public static function getConnectionError(): ?string {
        return self::$connectionError;
    }

    /**
     * Inicializa las tablas necesarias si la base de datos es nueva
     */
    private static function checkAndInitTables(PDO $pdo, string $driver): void {
        try {
            if ($driver === 'mysql') {
                $tableCheck = $pdo->query("SHOW TABLES LIKE 'peliculas'")->fetch();
            } else {
                $tableCheck = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='peliculas'")->fetch();
            }

            if ($tableCheck) {
                return;
            }

            if ($driver === 'mysql') {
                $sqlPath = ROOT_DIR . '/database.sql';
                if (file_exists($sqlPath)) {
                    $sql = file_get_contents($sqlPath);
                    $pdo->exec($sql);
                }
            } else {
                // Esquema SQLite en español
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS usuarios (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        nombre TEXT NOT NULL,
                        correo TEXT NOT NULL UNIQUE,
                        clave TEXT NOT NULL,
                        rol TEXT NOT NULL DEFAULT 'usuario',
                        avatar TEXT DEFAULT 'default_avatar.png',
                        creado_en DATETIME DEFAULT CURRENT_TIMESTAMP
                    );

                    CREATE TABLE IF NOT EXISTS categorias (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        nombre TEXT NOT NULL,
                        slug TEXT NOT NULL UNIQUE,
                        descripcion TEXT,
                        creado_en DATETIME DEFAULT CURRENT_TIMESTAMP
                    );

                    CREATE TABLE IF NOT EXISTS peliculas (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        titulo TEXT NOT NULL,
                        slug TEXT NOT NULL,
                        categoria_id INTEGER NOT NULL,
                        descripcion TEXT NOT NULL,
                        duracion INTEGER NOT NULL,
                        anio INTEGER NOT NULL,
                        calificacion REAL DEFAULT 8.5,
                        clasificacion_edad TEXT DEFAULT '+16',
                        destacada INTEGER DEFAULT 0,
                        portada_key TEXT NOT NULL,
                        trailer_key TEXT NOT NULL,
                        fondo_key TEXT DEFAULT NULL,
                        tipo_almacenamiento TEXT NOT NULL DEFAULT 's3',
                        vistas INTEGER DEFAULT 0,
                        creado_en DATETIME DEFAULT CURRENT_TIMESTAMP,
                        actualizado_en DATETIME DEFAULT CURRENT_TIMESTAMP,
                        FOREIGN KEY (categoria_id) REFERENCES categorias (id) ON DELETE RESTRICT
                    );

                    CREATE TABLE IF NOT EXISTS favoritos (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        usuario_id INTEGER NOT NULL,
                        pelicula_id INTEGER NOT NULL,
                        creado_en DATETIME DEFAULT CURRENT_TIMESTAMP,
                        UNIQUE (usuario_id, pelicula_id),
                        FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE,
                        FOREIGN KEY (pelicula_id) REFERENCES peliculas (id) ON DELETE CASCADE
                    );

                    CREATE TABLE IF NOT EXISTS historial_reproducciones (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        usuario_id INTEGER NOT NULL,
                        pelicula_id INTEGER NOT NULL,
                        segundos_progreso INTEGER DEFAULT 0,
                        segundos_totales INTEGER DEFAULT 0,
                        completado INTEGER DEFAULT 0,
                        ultima_reproduccion DATETIME DEFAULT CURRENT_TIMESTAMP,
                        UNIQUE (usuario_id, pelicula_id),
                        FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE,
                        FOREIGN KEY (pelicula_id) REFERENCES peliculas (id) ON DELETE CASCADE
                    );

                    CREATE TABLE IF NOT EXISTS configuraciones (
                        clave TEXT PRIMARY KEY,
                        valor TEXT,
                        actualizado_en DATETIME DEFAULT CURRENT_TIMESTAMP
                    );
                ");

                // Datos iniciales en SQLite
                $pdo->exec("
                    INSERT OR IGNORE INTO categorias (id, nombre, slug, descripcion) VALUES
                    (1, 'Acción y Aventura', 'accion', 'Explosiones, misiones imposibles, combates y adrenalina pura.'),
                    (2, 'Ciencia Ficción', 'ciencia-ficcion', 'Viajes en el tiempo, universos paralelos, distopías y tecnología futurista.'),
                    (3, 'Drama y Suspenso', 'drama', 'Historias intensas, misterios por resolver y giros inesperados.'),
                    (4, 'Comedia', 'comedia', 'Humor, risas y situaciones entretenidas para toda ocasión.'),
                    (5, 'Terror y Misterio', 'terror', 'Suspenso psicológico, fenómenos paranormales y emociones fuertes.'),
                    (6, 'Animación y Fantasía', 'animacion', 'Mundos mágicos y producciones animadas de última generación.');

                    INSERT OR IGNORE INTO usuarios (id, nombre, correo, clave, rol) VALUES
                    (1, 'Administrador MiniNetflix', 'admin@mininetflix.com', '\$2y\$10\$4wAdAING3Ygnaci/gFY/HuoiEIvJhxoMsx/aJDIzBOjgwXAmD.jvC', 'admin'),
                    (2, 'Usuario Demo', 'user@mininetflix.com', '\$2y\$10\$XLY0JHxnzRaXeJ0gx1BHO.q2gpUbzudlfjJwSUmSXL4ahIXJxeAha', 'usuario');

                    INSERT OR IGNORE INTO configuraciones (clave, valor) VALUES
                    ('aws_access_key_id', ''),
                    ('aws_secret_access_key', ''),
                    ('aws_region', 'us-east-1'),
                    ('aws_bucket', 'mininetflix-media-bucket'),
                    ('s3_presigned_expiry', '3600'),
                    ('storage_driver', 's3');
                ");
            }
        } catch (Exception $e) {
            error_log("Error inicializando tablas: " . $e->getMessage());
        }
    }
}

function getDB(): PDO {
    return Database::getConnection();
}
