<?php
// htdocs/includes/config.php - Archivo de Configuración Global

// --- === Configuración General === ---
$is_local = in_array($_SERVER['HTTP_HOST'], ['localhost', '127.0.0.1']) ||
            in_array($_SERVER['SERVER_NAME'], ['localhost', '127.0.0.1']);
if ($is_local) {
    define('URL_BASE', 'http://localhost/xbarrioalpha'); 
} else {

    define('URL_BASE', 'http://pampa.prodigma.com'); 
}

$devtelid = '-603449663'; //telegram dev
$protelid = '-4672661371'; //telegram pampa
$whatserver = 'http://149.50.129.89:3004';


define('IS_LOCAL_ENV', $is_local);
// --- === Configuración de Usuarios y Autenticación === ---
// Ruta al archivo JSON donde se guardan los usuarios dinámicos (creados via alta.php).
// __DIR__ es la carpeta actual (includes). '/../' sube un nivel (a htdocs). '/data/' entra en la carpeta data.
define('DYNAMIC_USERS_FILE', __DIR__ . '/../data/users.json'); // <<< RUTA CORREGIDA

// Lista de usuarios estáticos (ej: administradores iniciales, developers).
// Estos usuarios NO se guardan en users.json. Su contraseña debe estar HASHEADA.
// Usa password_hash('tu_contraseña', PASSWORD_DEFAULT) para generar el hash.
$static_users = [
    [
        'id' => 'developer*', // Un ID único para este usuario estático
        'username' => 'developer',
        'name' => 'Developer Principal',
        'lote' => '*', // Puede ser null o "" si no aplica a este rol
        'phone' => '541164920364', // Puede ser null o ""
        'role' => 'developer',
        // Asegúrate que esta contraseña esté hasheada:
        'password' => '$2y$10$b/dzbcei6UMgTGzkIjr7ku8iKNYjFWK1AHYp5nookDCq3Lgx06sZS',
        'created_at' => 1713926400 // Un timestamp de ejemplo de creación
    ],
     [
        'id' => 'admin*', // Otro ID único para un usuario estático (ej. administrador)
        'username' => 'admin',
        'name' => 'Administrador Principal',
        'lote' => null,
        'phone' => null,
        'role' => 'administrador',
        // ¡IMPORTANTE! Añade el hash de la contraseña para tu usuario administrador aquí
        'password' => '$2y$10$b/dzbcei6UMgTGzkIjr7ku8iKNYjFWK1AHYp5nookDCq3Lgx06sZS',
        'created_at' => 1713926405 // Un timestamp de ejemplo
    ],
    [
        'id' => 'seguridad*', // Otro ID único para un usuario estático (ej. administrador)
        'username' => 'seguridad',
        'name' => 'Seguridad Principal',
        'lote' => null,
        'phone' => null,
        'role' => 'seguridad',
        // ¡IMPORTANTE! Añade el hash de la contraseña para tu usuario administrador aquí
        'password' => '$2y$10$b/dzbcei6UMgTGzkIjr7ku8iKNYjFWK1AHYp5nookDCq3Lgx06sZS',
        'created_at' => 1713926405 // Un timestamp de ejemplo
    ],
    /*
    // Si quisieras que algunos usuarios de Seguridad también sean estáticos, añádelos aquí:
    [
        'id' => 'static_seguridad1',
        'username' => 'seguridad1',
        'name' => 'Seguridad Fija 1',
        'lote' => null,
        'phone': null,
        'role': 'seguridad',
        'password': '<<< HASH DE CONTRASEÑA PARA SEGURIDAD 1 >>>',
        'created_at': 1713926406
    ],
    */
];

// Lista maestra de roles válidos en la aplicación.
// Usada para validación (ej: al crear/editar usuarios) y lógica de permisos.
$valid_app_roles = ['anfitrion', 'seguridad', 'administrador', 'developer']; // <<< Define AQUÍ todos tus roles válidos

// --- === Configuración de Invitaciones y QR === ---
// Ruta al archivo JSON donde se guardan las invitaciones.
$invitations_file = __DIR__ . '/../data/invitations.json'; // Ruta al archivo JSON de invitaciones

// Directorio donde se guardarán las imágenes de los códigos QR generados.
$qr_codes_dir = __DIR__ . '/../qr/'; // Directorio dentro de htdocs para los archivos QR

$qr_default_validity_hours = 24; // 24 horas por defecto


// --- === Configuración de Permisos por Roles === ---
// Define qué roles tienen acceso a qué funcionalidades o páginas.
// Estos arrays se usan en los scripts PHP para verificar permisos.

// Roles permitidos para acceder a la página de crear invitación (invitar.php)
$invite_allowed_roles = ['anfitrion', 'administrador', 'developer'];

// Roles permitidos para acceder a la página de listado de QRs (listqr.php)
$list_allowed_roles = ['anfitrion', 'seguridad', 'administrador', 'developer'];

// Roles permitidos para acceder a la página de escanear QR (scanqr.php)
$scan_allowed_roles = ['seguridad', 'administrador', 'developer'];

// Roles que tienen permiso global para eliminar invitaciones (además del propio anfitrión)
$delete_allowed_roles_global = ['administrador', 'developer'];


// --- === Configuración de Debugging === ---
define('DEBUG_MODE', true); // <<< Cambia a false para producción
define('CUSTOM_DEBUG_LOG_FILE', __DIR__ . '/../logs/custom_debug.log');


// --- Configuración del Landing Page (index.php) ---
// Ruta a la imagen de fondo del contenedor superior
$landing_image_url = 'assets/images/landing_background.jpg'; // Asegúrate que la ruta y nombre sean correctos

// Texto para la sección de novedades
$news_text = "¡Bienvenido al Sistema de Control de Acceso del Barrio!"; // O el texto que hayas puesto

// --- ESTA VARIABLE DEBE ESTAR DEFINIDA Y NO COMENTADA ---
// Configuración de los botones de la grid en el landing page
// 'icon': ruta al archivo del ícono (relativa a la raíz htdocs)
// 'label': texto que se muestra en el botón
// 'link': URL a la que dirige el botón (relativa a la raíz htdocs)
// 'roles': (Opcional) Array de roles permitidos para ver este botón.
$landing_buttons = [
    [
        'icon' => 'assets/icons/qr.png',
        'label' => 'Crear QR',
        'link' => 'invitar.php',
        'roles' => ['anfitrion', 'administrador', 'developer'], // Descomentar y ajustar si quieres restringir
    ],
    [
        'icon' => 'assets/icons/qr.png',
        'label' => 'Leer QR',
        'link' => 'scanqr.php',
        'roles' => ['seguridad', 'administrador', 'developer'], // Descomentar y ajustar si quieres restringir
    ],
    [
        'icon' => 'assets/icons/list.png',
        'label' => 'Listado QR',
        'link' => 'listqr.php',
        'roles' => ['anfitrion', 'seguridad', 'administrador', 'developer'], // Descomentar y ajustar si quieres restringir
    ],
    [
        'icon' => 'assets/icons/camera.png',
        'label' => 'Cámaras',
        'link' => '#',
        'roles' => ['anfitrion', 'seguridad', 'administrador', 'developer'], // Descomentar y ajustar si quieres restringir
    ],
    [
        'icon' => 'assets/icons/neighbors.png',
        'label' => 'Vecinos',
        'link' => '#', // Enlace temporal
        'roles' => ['anfitrion', 'seguridad', 'administrador', 'developer'], // Descomentar y ajustar si quieres restringir
    ],
    [
        'icon' => 'assets/icons/administration.png',
        'label' => 'Admin',
        'link' => '#', // Enlace temporal
        'roles' => ['anfitrion', 'seguridad', 'administrador', 'developer'], // Descomentar y ajustar si quieres restringir
    ],
    [
        'icon' => 'assets/icons/settings.png',
        'label' => 'Config',
        'link' => '#', // Enlace temporal
        'roles' => ['anfitrion', 'seguridad', 'administrador', 'developer'], // Descomentar y ajustar si quieres restringir
    ],
];




function sendTel($str, $telid)
{
    $ch = curl_init();     // create curl resource
    $urli = 'https://api.telegram.org/bot1250866565:AAF4XC35GL5QHB9adje_uM-QEfkd4VQOZQE/sendMessage?chat_id=' . $telid . '&text=' .$_SERVER['SERVER_NAME'].' ' . $str;
    curl_setopt($ch, CURLOPT_URL, $urli);     //return the transfer as a string
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);     // $output contains the output string
    $output = curl_exec($ch);
    curl_close($ch);     // close curl resource to free up system resources
    //echo $output; //debug
}


?>
