# MiniNetflix - Plataforma Web de Streaming con PHP, MySQL y Amazon S3

**MiniNetflix** es una aplicación web moderna y responsiva inspirada en plataformas de streaming como Netflix y Prime Video. Permite a los usuarios explorar un catálogo multimedia por categorías, ver detalles, reproducir trailers en **HTML5** mediante **URLs prefirmadas de Amazon S3 (SigV4)**, gestionar su lista de favoritos e historial de reproducciones, y a los administradores registrar contenidos y subir sus recursos multimedia de forma segura.

---

## 🚀 Características Principales

### 1. 🔐 Autenticación y Control de Acceso
- **Roles de Usuario**: `admin` y `user`.
- Registro de nuevos usuarios con contraseñas encriptadas mediante `password_hash` (**BCRYPT**).
- Control de sesión seguro con protección contra accesos no autorizados.
- Cuentas de prueba preconfiguradas:
  - **Administrador**: `admin@mininetflix.com` / `admin123`
  - **Usuario Demo**: `user@mininetflix.com` / `user123`

### 2. 🎬 Catálogo y Experiencia Streaming
- **Interfaz Moderna**: Tema oscuro obsidian (`#111215`), microanimaciones, efectos glassmorphism y tipografía Google Fonts (`Outfit` e `Inter`).
- **Hero Billboard**: Banner cinematográfico destacado en cabecera con trailer principal, insignia 4K Ultra HD y sinopsis.
- **Carruseles por Categoría**:
  - *Continuar Viendo* (con barra de progreso visual de visualización).
  - *Mi Lista* (películas favoritas del usuario).
  - *Acción y Aventura*, *Ciencia Ficción*, *Drama y Suspenso*, *Comedia*, *Terror y Misterio*, *Animación y Fantasía*.
- **Buscador en Tiempo Real**: Filtrado interactivo por título, descripción y categorías mediante API JSON (`api/search.php`).
- **Filtros por Categoría**: Navegación rápida por géneros.

### 3. 🎥 Reproductor HTML5 y Modo Cine
- **Modal Integrado**: Permite previsualizar el trailer sin perder la navegación actual.
- **Modo Cine (`watch.php`)**: Reproductor a pantalla completa con controles que se desvanecen automáticamente, soporte para reanudar desde el minuto exacto donde se quedó el usuario y atajos de teclado (`Espacio` = Pausa/Play, `F` = Pantalla completa, `Flechas` = Rebobinar/Adelantar 10s).
- **Historial de Reproducciones (`api/history.php`)**: Registro asíncrono del tiempo visto, porcentaje y estado completado.
- **Mi Lista / Favoritos (`api/favorite.php`)**: Toggle asíncrono con notificaciones toast flotantes.

### 4. ☁️ Integración con Amazon S3 y Seguridad SigV4
- **Cliente AWS Nativo en PHP (`includes/s3.php`)**: Implementación ligera con **AWS Signature Version 4 (SigV4)** sin necesidad de dependencias pesadas de Composer.
- **Protección de Archivos Privados**: Los archivos del bucket permanecen **privados**; nunca se exponen URLs públicas fijas.
- **URLs Prefirmadas (Presigned URLs)**: Cada trailer y portada se sirve mediante una URL temporal firmada criptográficamente con tiempo de expiración programable (`X-Amz-Expires`, por defecto 3600 segundos). Pasado ese tiempo, cualquier enlace expira automáticamente ante AWS S3.
- **Transmisor Seguro con HTTP Range (`media_stream.php`)**: Fallback local seguro para pruebas que imita la firma de S3 mediante tokens HMAC temporales y soporta peticiones HTTP 206 Partial Content para scrubbing fluido de video.

### 5. 🛠️ Panel Administrativo (`/admin`)
- **Dashboard de Métricas**: Estadísticas de películas, categorías, visualizaciones y estado del bucket.
- **Registro de Películas**: Formulario para cargar trailers (MP4/WebM) y portadas (JPG/PNG/WebP) directamente a Amazon S3 y guardar la metadata en MySQL.
- **Edición y Eliminación**: Actualización de metadatos y borrado sincronizado en MySQL y en el bucket de S3.
- **Ajustes de AWS S3 (`admin/settings.php`)**: Configuración de `Access Key`, `Secret Key`, `Región` y `Bucket Name` con herramienta de prueba de conexión en tiempo real (**HEAD request** a S3).

---

## 📂 Estructura del Proyecto

```text
Mininetflix/
├── index.php                 # Página principal del catálogo de streaming
├── watch.php                 # Reproductor a pantalla completa (Modo Cine)
├── my_list.php               # Vista de películas favoritas
├── history.php               # Vista del historial de reproducciones
├── login.php                 # Inicio de sesión con accesos rápidos 1-click
├── register.php              # Registro de usuarios
├── logout.php                # Cierre de sesión seguro
├── media_stream.php          # Transmisor seguro local con tokens HMAC y HTTP Range
├── sample_data.php           # Script generador de datos y trailers de prueba
├── database.sql              # Esquema relacional MySQL completo y datos iniciales
├── .env.example              # Plantilla de variables de entorno
├── admin/
│   ├── index.php             # Dashboard administrativo y listado de películas
│   ├── movie_create.php      # Formulario para registrar película y subir archivos a S3
│   ├── movie_edit.php        # Edición de película y reemplazo de archivos
│   ├── movie_delete.php      # Eliminación en base de datos y borrado en S3
│   └── settings.php          # Configuración de credenciales AWS S3 y conexión
├── api/
│   ├── favorite.php          # Endpoint AJAX para agregar/quitar de favoritos
│   ├── history.php           # Endpoint AJAX para registrar progreso de video
│   ├── search.php            # Endpoint AJAX para búsqueda y filtro en vivo
│   └── test_s3.php           # Endpoint AJAX para probar conexión con el bucket S3
├── config/
│   ├── config.php            # Constantes globales, rutas y carga de .env
│   └── database.php          # Conexión PDO a MySQL (con fallback SQLite)
├── includes/
│   ├── auth.php              # Funciones de sesión y protección de rutas
│   ├── functions.php         # Utilidades, URLs prefirmadas y formateo
│   ├── header.php            # Barra de navegación, perfil y buscador
│   ├── footer.php            # Modal de video HTML5 y pie de página
│   └── s3.php                # Cliente ligero AWS S3 con firma SigV4
├── assets/
│   ├── css/style.css         # Hoja de estilos con diseño moderno de streaming
│   └── js/app.js             # Lógica de reproducción, carruseles y AJAX
└── storage/
    └── uploads/              # Almacenamiento local de respaldo (covers/trailers)
```

---

## 🗄️ Esquema de Base de Datos (MySQL)

El archivo [`database.sql`](file:///c:/Users/Usuario/Desktop/Mininetflix/database.sql) contiene la creación de la base de datos `mininetflix` y las siguientes tablas con nombres en español, manteniendo `id`:

1. **`usuarios`**: `id`, `nombre`, `correo`, `clave`, `rol`, `avatar`, `creado_en`.
2. **`categorias`**: `id`, `nombre`, `slug`, `descripcion`, `creado_en`.
3. **`peliculas`**: `id`, `titulo`, `slug`, `categoria_id`, `descripcion`, `duracion`, `anio`, `calificacion`, `clasificacion_edad`, `destacada`, `portada_key`, `trailer_key`, `fondo_key`, `tipo_almacenamiento`, `vistas`, `creado_en`, `actualizado_en`.
4. **`favoritos`**: `id`, `usuario_id`, `pelicula_id`, `creado_en` (restricción única por usuario y película).
5. **`historial_reproducciones`**: `id`, `usuario_id`, `pelicula_id`, `segundos_progreso`, `segundos_totales`, `completado`, `ultima_reproduccion`.
6. **`configuraciones`**: `clave`, `valor`, `actualizado_en` (credenciales de AWS S3 y expiración).

---

## ⚙️ Puesta en Marcha

### 1. Iniciar Servidor Web PHP
Para iniciar el servidor de desarrollo integrado de PHP:
```powershell
php -S 127.0.0.1:8000
```
Luego abra su navegador en: **`http://127.0.0.1:8000`**

### 2. Configuración de Base de Datos MySQL
1. Inicie su servidor MySQL (por ejemplo mediante XAMPP, Laragon o el servicio local de MySQL).
2. Cree la base de datos importando el archivo `database.sql`:
   ```bash
   mysql -u root -p < database.sql
   ```
3. Si requiere cambiar el usuario o contraseña de MySQL, edite el archivo `.env` o configure los valores en `config/config.php`:
   ```env
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_NAME=mininetflix
   DB_USER=root
   DB_PASS=
   ```
*(Nota: Si MySQL no se encuentra activo al iniciar, MiniNetflix utiliza de manera transparente un almacenamiento local SQLite para permitir la prueba inmediata de la interfaz sin bloqueos).*

### 3. Configuración de Amazon S3
1. Inicie sesión como Administrador (`admin@mininetflix.com` / `admin123`).
2. Diríjase a **Panel Admin** &rarr; **Configurar AWS S3 & BD** (`/admin/settings.php`).
3. Ingrese los datos de su cuenta de AWS:
   - **Nombre del Bucket**: ej. `mi-mininetflix-bucket`
   - **Región AWS**: ej. `us-east-1`
   - **AWS Access Key ID**: Credencial IAM con permisos `s3:PutObject`, `s3:GetObject`, `s3:DeleteObject`.
   - **AWS Secret Access Key**: Clave secreta.
   - **Expiración**: ej. `3600` (1 hora).
4. Pulse el botón **Probar Conexión con AWS S3** para verificar el acceso en tiempo real.
