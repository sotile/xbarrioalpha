<?php
// htdocs/includes/auth.php - Funciones de Autenticación y Gestión de Usuarios
date_default_timezone_set('America/Argentina/Buenos_Aires');


// --- === Includes === ---
// Se necesita config.php para variables globales como $static_users, DYNAMIC_USERS_FILE, $valid_app_roles, CUSTOM_DEBUG_LOG_FILE, DEBUG_MODE, URL_BASE
require_once __DIR__ . '/config.php';


// --- Funciones de Sesión y Redirección ---

// Asegúrate de que la sesión esté iniciada antes de usar $_SESSION
function start_session_if_not_started(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

/**
 * Redirige a una URL dada y termina la ejecución del script.
 * Maneja rutas relativas a la raíz del documento si empiezan con '/' y URL_BASE está definida.
 *
 * @param string $url La URL a la que redirigir. Puede ser absoluta o relativa a la raíz del documento (empezando con '/').
 */
function redirect(string $url): void {
    $redirect_url = $url;

    // Si la URL es relativa a la raíz del documento (empieza con /)
    // y URL_BASE está definida, prepend URL_BASE.
    // Esto es crucial cuando se redirige desde scripts dentro de subcarpetas (como 'includes').
    if (strpos($url, '/') === 0 && defined('URL_BASE')) {
        $redirect_url = URL_BASE . $url;
    }
    // Si no empieza con '/' o URL_BASE no está definida, asumimos que es una URL completa
    // o una ruta relativa al script actual (menos seguro desde includes, pero necesario si URL_BASE no existe).


    header("Location: " . $redirect_url);
    exit(); // Importante: Termina la ejecución después de redirigir
}

// Verifica si hay un usuario logueado en la sesión.
function is_logged_in(): bool {
    start_session_if_not_started(); // Asegura que la sesión esté iniciada
    return isset($_SESSION['user_id']); // O cualquier otra variable de sesión que uses para identificar al usuario logueado
}

// Obtiene los datos del usuario logueado desde la sesión.
// Retorna un array asociativo con los datos del usuario o null si no hay nadie logueado.
function gets_current_user(): ?array {
    start_session_if_not_started(); // Asegura que la sesión esté iniciada
    if (!is_logged_in()) {
        return null; // No hay usuario logueado
    }
    $user_id = $_SESSION['user_id'];

    // Usamos get_users(true) para obtener la lista combinada,
    // pasando 'true' para evitar un bucle infinito si get_users() llamara a gets_current_user()
    $all_users = get_users(true); // <-- Llama a get_users para obtener la lista combinada

    foreach ($all_users as $user) {
        // Buscar al usuario logueado por su ID en la lista combinada
        if (isset($user['id']) && is_string($user['id']) && $user['id'] === $user_id) {
            return $user; // Retorna el array completo de datos del usuario
        }
    }
    // Si el usuario de la sesión no se encuentra en los datos combinados (caso raro)
    error_log("Usuario con ID de sesión " . $user_id . " no encontrado en los datos de usuario combinados.");
    logout(); // Cerrar sesión por seguridad
    return null;
}

// Función para verificar si el usuario logueado tiene un rol específico (usa gets_current_user)
function has_role(string $role_name): bool {
    $user = gets_current_user();
    if ($user && isset($user['role']) && is_string($user['role'])) {
        return $user['role'] === $role_name;
    }
    return false;
}

// Funciones helper para roles específicos
function is_anfitrion(): bool { return has_role('anfitrion'); }
function is_seguridad(): bool { return has_role('seguridad'); }
function is_admin(): bool { return has_role('administrador'); }
function is_developer(): bool { return has_role('developer'); }

// Función para cerrar sesión
function logout(): void {
    start_session_if_not_started();
    $_SESSION = array(); // Vaciar el array $_SESSION
    session_destroy(); // Destruir la sesión
    // Limpiar la cookie de sesión
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"] // Corregido httptoken a httponly
        );
    }
}


// --- >>> Funciones de Manejo de Datos de Usuarios DINÁMICOS (desde/hacia archivo JSON) <<< ---
// Estas funciones operan específicamente con el archivo JSON de usuarios dinámicos definido por DYNAMIC_USERS_FILE.

/**
 * Lee los datos de los usuarios DINÁMICOS desde el archivo JSON configurado.
 *
 * @return array Un array de arrays asociativos con los datos de usuarios dinámicos.
 * Retorna un array vacío en caso de error o si el archivo está vacío/inválido.
 */
function get_dynamic_users(): array {
    // DYNAMIC_USERS_FILE debe estar definido en config.php
    if (!defined('DYNAMIC_USERS_FILE') || !is_string(DYNAMIC_USERS_FILE)) {
         error_log("Error de configuración: La constante DYNAMIC_USERS_FILE no está definida o no es válida.");
         return [];
    }
    $file = DYNAMIC_USERS_FILE;

    // Verificar si el archivo existe
    if (!file_exists($file)) {
        // Si el archivo no existe, intentar crearlo con un array JSON vacío
        $initial_content = json_encode([]);
        if (file_put_contents($file, $initial_content) === false) {
             error_log("Error: El archivo de usuarios dinámicos no existe y no se pudo crear en: " . $file);
             $dir = dirname($file);
             if (!is_dir($dir) || !is_writable($dir)) {
                 error_log("El directorio padre '" . $dir . "' para el archivo de usuarios dinámicos no existe o no es escribible.");
             }
             return []; // Retornar vacío si no se pudo crear
        }
        return []; // Retornar array vacío después de crear
    }

    // Verificar si el archivo es legible
    if (!is_readable($file)) {
        error_log("Error: El archivo de usuarios dinámicos no es legible: " . $file);
        return [];
    }

    // Leer el contenido del archivo
    $content = file_get_contents($file);
    if ($content === false) {
         error_log("Error al leer el contenido del archivo de usuarios dinámicos: " . $file);
         return [];
    }

    if ($content === '') {
        return []; // Archivo vacío
    }

    // Decodificar el contenido JSON
    $data = json_decode($content, true);

    if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
        error_log("Error al decodificar JSON en el archivo de usuarios dinámicos: " . $file . " Error: " . json_last_error_msg());
        return [];
    }

    return $data; // Retorna el array de usuarios dinámicos
}

/**
 * Guarda el array de datos de usuarios DINÁMICOS de vuelta en el archivo JSON, usando bloqueo de archivo.
 * Esta función SOLO escribe en el archivo de usuarios dinámicos.
 *
 * @param array $users_data El array de datos de usuarios dinámicos a guardar.
 * @return bool True si se guardó exitosamente, false en caso de error.
 */
function save_dynamic_users(array $users_data): bool {
    // DYNAMIC_USERS_FILE debe estar definido en config.php
    if (!defined('DYNAMIC_USERS_FILE') || !is_string(DYNAMIC_USERS_FILE)) {
         error_log("Error de configuración: La constante DYNAMIC_USERS_FILE no está definida o no es válida durante el guardado.");
         return false;
    }
    $file = DYNAMIC_USERS_FILE;

     if (!file_exists($file)) {
          $dir = dirname($file);
          if (!is_dir($dir) || !is_writable($dir)) {
               error_log("Error: El archivo de usuarios dinámicos no existe y el directorio padre '" . $dir . "' no es escribible para crearlo.");
               return false;
          }
     } elseif (!is_writable($file)) {
          error_log("Error: El archivo de usuarios dinámicos no es escribible: " . $file);
          return false;
     }

    $json_data = json_encode($users_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    if ($json_data === false) {
        error_log("Error al codificar datos de usuario dinámicos a JSON: " . json_last_error_msg());
        return false;
    }

    // Usar file_put_contents con bloqueo exclusivo es más simple y seguro que fopen/flock/fwrite/ftruncate/fclose
    if (file_put_contents($file, $json_data, LOCK_EX) === false) {
        error_log("Error al escribir en el archivo de usuarios dinámicos: " . $file);
        return false;
    }

    return true; // Éxito
}


/**
 * Obtiene la lista COMBINADA de usuarios estáticos (desde config.php)
 * y usuarios dinámicos (desde el archivo JSON).
 *
 * @param bool $skip_current_user_fetch Si es true, evita llamar a gets_current_user() internamente para prevenir bucles infinitos
 * cuando gets_current_user() llama a esta función.
 * @return array Un array conteniendo todos los usuarios (estáticos + dinámicos).
 */
function get_users(bool $skip_current_user_fetch = false): array {
    global $static_users; // Acceder a la variable global definida en config.php

    // 1. Obtener usuarios estáticos desde config.php
    $all_users = $static_users ?? []; // Usa el array estático o un array vacío si no está definido
    if (!is_array($all_users)) {
        error_log("DEBUG AUTH (get_users): La variable \$static_users en config.php no es un array o no está definida correctamente.");
        $all_users = []; // Asegurarse de que $all_users sea un array
    }

    // 2. Obtener usuarios dinámicos desde el archivo JSON
    $dynamic_users = get_dynamic_users(); // <-- Usa get_dynamic_users()

    if (is_array($dynamic_users)) {
        // 3. Combinar usuarios estáticos y dinámicos
        // Nota: array_merge puede no manejar duplicados de forma deseada si los hay.
        // Si necesitas garantizar unicidad por ID o username, se requeriría lógica adicional aquí.
        // Asumimos que los IDs de estáticos no chocan con los generados por uniqid() para dinámicos.
        $all_users = array_merge($all_users, $dynamic_users);
    } else {
        error_log("DEBUG AUTH (get_users): get_dynamic_users() no retornó un array válido. No se pudieron cargar usuarios dinámicos.");
         // $all_users ya contiene los estáticos, así que procedemos con solo estáticos si falla la carga dinámica.
    }

    // Opcional: Ordenar la lista combinada si es necesario (ej. por username, rol)
    // usort($all_users, function($a, $b) { ... });

    return $all_users; // Retornar la lista combinada de todos los usuarios
}


/**
 * Busca un usuario por su nombre de usuario en la lista COMBINADA de usuarios.
 * @param string $username El nombre de usuario a buscar.
 * @return array|null Retorna el array de datos del usuario si se encuentra, null en caso contrario.
 */
function get_user_by_username(string $username): ?array {
    // Busca en la lista COMBINADA (estáticos + dinámicos)
    $all_users = get_users(); // <-- Usa get_users() para buscar en ambos

    foreach ($all_users as $user) {
        // Verifica si la clave 'username' existe y si coincide (insensible a mayúsculas por seguridad/usabilidad).
        if (isset($user['username']) && is_string($user['username']) && strtolower($user['username']) === strtolower($username)) {
            return $user; // Usuario encontrado
        }
    }
    return null; // Usuario no encontrado
}


/**
 * Agrega un nuevo usuario DINÁMICO al array de usuarios dinámicos y lo guarda en el archivo JSON.
 * Esta función SOLO añade usuarios al archivo JSON de usuarios dinámicos.
 *
 * @param array $new_user_data Array asociativo con los datos del nuevo usuario (sin 'id' ni 'created_at').
 * Debe contener al menos 'username' y 'password' (sin hashear, esta función la hashea).
 * @return bool True si el usuario se añadió y guardó exitosamente, false en caso de error.
 */
function add_user_to_data(array $new_user_data): bool {
    // Validar que al menos el nombre de usuario y la contraseña estén presentes
    if (empty($new_user_data['username']) || empty($new_user_data['password'])) {
        error_log("add_user_to_data error: Username or password missing for new user.");
        return false;
    }

    // Generar un ID único si no está presente
    if (!isset($new_user_data['id'])) {
        // Asegúrate de que el formato de ID no choque con los IDs estáticos si usas formatos diferentes (ej. prefijos)
        $new_user_data['id'] = uniqid('dynamic_', true); // Añadimos prefijo 'dynamic_' para diferenciar
    }

    // Hashear la contraseña proporcionada
    // password_hash() es la forma segura de hashear contraseñas.
    if (isset($new_user_data['password']) && is_string($new_user_data['password']) && !empty($new_user_data['password'])) {
         // No verificar password_needs_rehash aquí; asumimos que la contraseña viene sin hashear desde el formulario de alta.
         $new_user_data['password'] = password_hash($new_user_data['password'], PASSWORD_DEFAULT);
    } else {
         error_log("add_user_to_data error: Password field is empty or not set for new user.");
         return false; // No se puede añadir usuario sin contraseña válida
    }


    // Añadir marca de tiempo de creación si no está presente
    if (!isset($new_user_data['created_at'])) {
         $new_user_data['created_at'] = time();
    }

    // Cargar usuarios dinámicos existentes
    $dynamic_users = get_dynamic_users(); // <-- Usa get_dynamic_users()

    if (!is_array($dynamic_users)) {
        error_log("add_user_to_data falló: No se pudieron cargar los usuarios dinámicos para añadir.");
         return false;
    }

    // Opcional: Verificar si el username o ID ya existe en la lista COMBINADA (estáticos + dinámicos) antes de añadir
    // Esto requiere llamar a get_users() aquí y buscar duplicados.
    // La validación de duplicados por username se hace en alta.php antes de llamar a esta función, lo cual es correcto.
    // Si necesitas validar duplicados de ID, hazlo aquí.


    $dynamic_users[] = $new_user_data; // Añadir el nuevo usuario al array dinámico

    // Guardar el array actualizado de usuarios dinámicos
    return save_dynamic_users($dynamic_users); // <-- Usa save_dynamic_users()
}


/**
 * Verifica si un string de rol es uno de los roles válidos permitidos en el sistema.
 * @param string $role El rol a verificar.
 * @return bool True si el rol es válido, false en caso contrario.
 */
function is_valid_role(string $role): bool {
    // Define aquí la lista maestra de roles válidos para tu aplicación.
    // Es ideal que esta lista provenga de config.php para centralizarla.
    global $valid_app_roles; // Asume que $valid_app_roles está definido en config.php

    // Si $valid_app_roles no está definido en config.php, usa un valor por defecto aquí.
    // Asegúrate de que esta lista sea la fuente de verdad para todos los roles válidos.
    $valid_app_roles = $valid_app_roles ?? ['anfitrion', 'seguridad', 'administrador', 'developer']; // <<< Define tus roles válidos aquí o en config.php

    return in_array($role, $valid_app_roles);
}


/**
 * Genera una contraseña temporal aleatoria.
 * @param int $length Longitud de la contraseña.
 * @return string La contraseña generada.
 */
function generate_temporary_password(int $length = 10): string {
    $chars = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ0123456789!@#$%^&*()_+';
    $password = '';
    $chars_length = strlen($chars);
    if ($chars_length === 0) return '';

    for ($i = 0; $i < $length; $i++) {
        try {
            $random_index = random_int(0, $chars_length - 1);
            $password .= $chars[$random_index];
        } catch (Exception $e) {
            error_log("random_int falló al generar contraseña, usando mt_rand como fallback: " . $e->getMessage());
            $password .= $chars[mt_rand(0, $chars_length - 1)];
        }
    }
    return $password;
}


// --- Función de Autenticación ---

/**
 * Autentica un usuario verificando credenciales contra la lista COMBINADA
 * de usuarios estáticos y dinámicos.
 *
 * @param string $username El nombre de usuario a autenticar.
 * @param string $password La contraseña (sin hashear) proporcionada por el usuario.
 * @return array|null Retorna el array de datos del usuario si la autenticación es exitosa, null si falla.
 * Nota: Retorna null en lugar de false para compatibilidad de tipo con ?array.
 */
function authenticate_user(string $username, string $password): ?array { // <-- Cambiado return type a ?array

    // Usamos get_users() para obtener la lista combinada de usuarios estáticos y dinámicos
    $all_users = get_users(); // <-- Usa get_users() para obtener la lista completa

    // get_users() ya retorna [] en caso de error o lista vacía,
    // así que verificamos que sea un array antes de iterar.
    if (!is_array($all_users)) {
        error_log("Autenticación falló: No se pudieron cargar los datos de usuario (estáticos+dinámicos).");
        return null; // <-- Retorna null en caso de fallo
    }

    // Iterar sobre la lista COMBINADA para encontrar el nombre de usuario
    foreach ($all_users as $user) {
        // Verificar si la clave 'username' existe y si coincide (insensible a mayúsculas)
        if (isset($user['username']) && is_string($user['username']) && strtolower($user['username']) === strtolower($username)) {
            // Si encontramos el nombre de usuario, verificar la contraseña hasheada
            // password_verify() es la función segura para verificar contraseñas hasheadas.
            if (isset($user['password']) && is_string($user['password']) && password_verify($password, $user['password'])) {
                // Autenticación exitosa
                // Retorna el array completo de datos del usuario autenticado (ya sea estático o dinámico)
                return $user;
            }
        }
    }

    // Si el bucle termina sin encontrar un nombre de usuario o la contraseña no coincide
    return null; // <-- Retorna null en caso de fallo
}


// --- Funciones de Debugging (Mantener si son útiles) ---
// Asegúrate de que DEBUG_MODE y CUSTOM_DEBUG_LOG_FILE estén definidos en config.php
function custom_debug_log($message): bool { // <-- Añadido return type bool
    if (!defined('DEBUG_MODE') || !DEBUG_MODE) {
        return false;
    }
    if (!defined('CUSTOM_DEBUG_LOG_FILE') || !is_string(CUSTOM_DEBUG_LOG_FILE)) {
        error_log("ERROR CRÍTICO: La constante CUSTOM_DEBUG_LOG_FILE no está definida o es inválida.");
        return false;
    }
    $log_file = CUSTOM_DEBUG_LOG_FILE;
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[{$timestamp}] " . $message . PHP_EOL;

    // Asegura que el directorio de logs exista y sea escribible
    $log_dir = dirname($log_file);
    if (!is_dir($log_dir)) {
        if (!mkdir($log_dir, 0775, true)) {
             error_log("Error creando directorio de logs: " . $log_dir);
             return false;
        }
    } elseif (!is_writable($log_dir)) {
         error_log("Error: El directorio de logs no es escribible: " . $log_dir);
         return false;
    }


    // Escribe en el archivo con bloqueo exclusivo
    if (file_put_contents($log_file, $log_message, FILE_APPEND | LOCK_EX) === false) {
        error_log("Error escribiendo en el archivo de log personalizado: " . $log_file);
        return false;
    }
    return true;
}

// --- Funciones de manejo de usuarios dinámicos usando $users_file (Mantenidas por compatibilidad con tu código original) ---
// Nota: Las funciones get_dynamic_users y save_dynamic_users son preferibles si solo manejas un archivo JSON de usuarios dinámicos.
// Estas funciones se mantienen para compatibilidad con tu código original que las usa en alta.php.

/**
 * Carga los datos de los usuarios desde el archivo JSON definido por $users_file.
 * @return array Un array de usuarios, o un array vacío si el archivo no existe, está vacío o es inválido.
 */
function load_users_from_data(): array {
    global $users_file; // Necesita acceder a la variable global definida en config.php
    // Asegurarse de que $users_file tenga un valor por defecto si no está en config.php
    // NOTA: Esta ruta debe coincidir con DYNAMIC_USERS_FILE en config.php si solo usas un archivo JSON para usuarios dinámicos.
    $users_file = $users_file ?? __DIR__ . '/../data/users.json';

    if (!file_exists($users_file)) {
        return []; // Archivo no existe, retorna vacío
    }
    $file_content = file_get_contents($users_file);
    if ($file_content === false || $file_content === '') {
        return []; // Error de lectura o archivo vacío
    }
    $users = json_decode($file_content, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($users)) {
        error_log("Error decodificando users.json: " . json_last_error_msg());
        return []; // Error de JSON o no es un array
    }
    return $users;
}

/**
 * Guarda el array de usuarios en el archivo JSON definido por $users_file.
 * @param array $users El array de usuarios a guardar.
 * @return bool True en caso de éxito, false en caso de fallo.
 */
function save_users_to_data(array $users): bool {
    global $users_file; // Necesita acceder a la variable global definida en config.php
    // Asegurarse de que $users_file tenga un valor por defecto si no está en config.php
    // NOTA: Esta ruta debe coincidir con DYNAMIC_USERS_FILE en config.php si solo usas un archivo JSON para usuarios dinámicos.
    $users_file = $users_file ?? __DIR__ . '/../data/users.json';

    $json_content = json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); // Añadido UNICODE por si acaso

    if ($json_content === false) {
        error_log("Error codificando usuarios a JSON: " . json_last_error_msg());
        return false;
    }

    // Asegura que el directorio de datos exista y sea escribible.
    $data_dir = dirname($users_file);
    if (!is_dir($data_dir)) {
        // Intenta crear el directorio recursivamente si no existe.
        if (!mkdir($data_dir, 0775, true)) {
             error_log("Error creando directorio de usuarios: " . $data_dir);
             return false;
        }
    } elseif (!is_writable($data_dir)) {
         error_log("Error: El directorio de usuarios no es escribible: " . $data_dir);
         return false;
    }

    // Escribe en el archivo con bloqueo exclusivo para evitar problemas de concurrencia.
    // LOCK_EX: Adquiere un bloqueo exclusivo (escritura)
    if (file_put_contents($users_file, $json_content, LOCK_EX) === false) {
        error_log("Error escribiendo en users.json: " . $users_file);
        return false;
    }

    return true; // Éxito
}


?>
