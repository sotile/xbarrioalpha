<?php
// config.php

$main_title = "Accesos";
$main_name = "Pampa Pilar";


define('URL_BASE', 'http://pampa.prodigma.com'); // Reemplaza 'TU_URL_BASE_AQUI' con la URL real de tu sitio (ej: 'http://localhost/_aalocal/xsecalpha')


// Define the directory to store invitation files
// !!! IMPORTANT: This path must be OUTSIDE your web server's document root (htdocs) for security !!!
// Adjust this path based on your server's file structure.
//define('INVITATIONS_DIR', __DIR__ . '/../visitas/'); // Example: two levels up from 'includes'


// Directorio donde se guardan los archivos de invitación (.sl)
// Base URL para la página de seguridad (usada para generar el QR/enlace)
// Asegúrate de que 'xsecalpha' sea el nombre correcto de tu carpeta
//define('BASE_SECURITY_URL', 'http://' . $_SERVER['HTTP_HOST'] . '/xsecalpha/seguridad/'); // Ajusta la ruta si es necesario
// Base URL for the security check page
// !!! IMPORTANT: Replace with your actual domain and path !!!
//define('BASE_SECURITY_URL', 'http://yourbarriodomain.com/seguridad/');
//define('BASE_SECURITY_URL', 'http://localhost/seguridad/');

// User credentials (Anfitriones and Seguridad)
// Passwords MUST be hashed using password_hash()
// Use a tool or script to generate hashes for your desired passwords.
// e.g., echo password_hash('your_anfitrion_password', PASSWORD_DEFAULT);
// e.g., echo password_hash('your_seguridad_password', PASSWORD_DEFAULT);

define('DYNAMIC_USERS_FILE', __DIR__ . '/users.json'); // Renombramos el archivo JSON para mayor claridad

define('DEBUG_MODE', false); // <<< Cámbialo a false cuando no necesites depurar >>>
define('CUSTOM_DEBUG_LOG_FILE', __DIR__ . '/../custom_debug.log');

$allowed_roles = ['anfitrion', 'seguridad', 'administrador', 'developer'];
// Define qué roles tienen "acceso total" a listados (ven todas las invitaciones)
// Roles no listados aquí (como 'anfitrion' normal) solo verán sus propias invitaciones en listados restringidos.
$all_access_roles = ['seguridad', 'administrador', 'developer'];


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

/*
$xusers = [
    'developer' => [
        'password' => '$2y$10$b/dzbcei6UMgTGzkIjr7ku8iKNYjFWK1AHYp5nookDCq3Lgx06sZS', // tester
        'role' => 'developer',
        'name' => 'Diego Sotile',
        'lote' => '63',
        'whatsapp' => '541164920364' // Include country code
    ],
    'anfitrion1' => [
        'password' => '$2y$10$b/dzbcei6UMgTGzkIjr7ku8iKNYjFWK1AHYp5nookDCq3Lgx06sZS', // tester
        'role' => 'anfitrion',
        'name' => 'Juan Perez',
        'lote' => '60',
        'whatsapp' => '+5491112345678' // Include country code
    ],
    'anfitrion2' => [
         'password' => '$2y$10$b/dzbcei6UMgTGzkIjr7ku8iKNYjFWK1AHYp5nookDCq3Lgx06sZS', // Replace with a real hash
        'role' => 'anfitrion',
        'name' => 'Maria Garcia',
        'lote' => '15',
        'whatsapp' => '+5491187654321'
    ],
    'seguridad1' => [
        'password' => '$2y$10$rpUUXd658iUBJxiJ.YRS7uFQg/4R88F9qffpe3Gl8jd56wupieJaS', // vigilancia
        'name' => 'Seguridad 1',
        'role' => 'seguridad'
    ],
    'seguridad2' => [
        'password' => '$2y$10$hashed_password_for_seguridad2...', // Replace with a real hash
        'name' => 'Seguridad 2',
        'role' => 'seguridad'
    ]
];
*/


// Define los roles válidos en tu sistema (debe incluir todos los roles, estáticos y dinámicos)
$allowed_roles = ['anfitrion', 'seguridad', 'administrador', 'developer'];

// Define qué roles tienen "acceso total" a listados (ven todas las invitaciones)
$all_access_roles = ['seguridad', 'administrador', 'developer'];

// Opcional: Define qué roles tienen permiso para acceder a alta.php (gestionar usuarios dinámicos)
$user_management_roles = ['administrador', 'developer'];



// Validity duration options for invitations (in seconds)
$validity_options = [
    '24h' => 24 * 3600,
    '48h' => 48 * 3600,
    '72h' => 72 * 3600,
    'end_of_day' => 'end_of_day' // Special flag to calculate till 23:59:59 of selected date
];


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


?>