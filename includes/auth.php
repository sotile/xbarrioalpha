<?php
// auth.php
require_once __DIR__ . '/config.php';

// Start the session (call at the very beginning of any page that uses sessions)
function start_session_if_not_started() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
}

// Función para redirigir
function redirect($url) {
    header("Location: " . $url);
    exit(); // Importante detener la ejecución después de redirigir
}



// Attempt to log the user in
function login($username, $password) {
    global $xusers;
    start_session_if_not_started();

    if (isset($xusers[$username]) && password_verify($password, $xusers[$username]['password'])) {
        $_SESSION['user_id'] = $username;
        $_SESSION['role'] = $xusers[$username]['role'];
        // Store user specific data for anfitrion
        if ($_SESSION['role'] === 'anfitrion') {
            $_SESSION['user_name'] = $xusers[$username]['name'];
            $_SESSION['user_lote'] = $xusers[$username]['lote'];
            $_SESSION['user_whatsapp'] = $xusers[$username]['whatsapp'];
        }
        return true; // Login successful
    }
    return false; // Login failed
}

// Check if a user is logged in
function is_logged_in() {
    start_session_if_not_started();
    return isset($_SESSION['user_id']);
}


// Función para obtener los datos del usuario logueado actualmente
// Ahora busca el usuario en la lista COMBINADA de estáticos y dinámicos
function gets_current_user() {
    start_session_if_not_started();
    if (!is_logged_in()) {
        return null;
    }
    $user_id = $_SESSION['user_id'];
    // Usamos get_users(true) para obtener la lista combinada,
    // pasando 'true' para evitar un bucle infinito si get_users() llamara a gets_current_user()
    $all_users = get_users(true);

    foreach ($all_users as $user) {
        if (isset($user['id']) && $user['id'] === $user_id) {
            return $user; // Retorna el array completo de datos del usuario
        }
    }
    // Si el usuario de la sesión no se encuentra en los datos combinados (caso raro)
    error_log("Usuario con ID de sesión " . $user_id . " no encontrado en los datos de usuario combinados.");
    logout(); // Cerrar sesión por seguridad
    return null;
}

// Función para verificar si el usuario logueado tiene un rol específico (usa gets_current_user)
function has_role($role_name) {
    $user = gets_current_user();
    if ($user && isset($user['role'])) {
        return $user['role'] === $role_name;
    }
    return false;
}

// Funciones helper para roles específicos (mantener existentes)
function is_anfitrion() { return has_role('anfitrion'); }
function is_seguridad() { return has_role('seguridad'); }
function is_admin() { return has_role('administrador'); }
function is_developer() { return has_role('developer'); }


// --- >>> Funciones de Manejo de Datos de Usuarios DINÁMICOS (desde/hacia archivo JSON) <<< ---

/**
 * Lee los datos de los usuarios DINÁMICOS desde el archivo JSON configurado.
 *
 * @return array Un array de arrays asociativos con los datos de usuarios dinámicos.
 * Retorna un array vacío en caso de error o si el archivo está vacío/inválido.
 */
function get_dynamic_users() {
    $users = [];
    $file = DYNAMIC_USERS_FILE; // Ruta del archivo definida en config.php

    if (!defined('DYNAMIC_USERS_FILE') || !is_string(DYNAMIC_USERS_FILE)) {
         error_log("Error de configuración: La constante DYNAMIC_USERS_FILE no está definida o no es válida.");
         return [];
    }

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
        return [];
    }

    // Decodificar el contenido JSON
    $data = json_decode($content, true);

    if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
        error_log("Error al decodificar JSON en el archivo de usuarios dinámicos: " . $file . " Error: " . json_last_error_msg());
        return [];
    }

    if (!is_array($data)) {
         error_log("Error: El contenido del archivo de usuarios dinámicos no es un array JSON: " . $file);
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
function save_dynamic_users($users_data) {
    $file = DYNAMIC_USERS_FILE; // Ruta del archivo definida en config.php

     if (!defined('DYNAMIC_USERS_FILE') || !is_string(DYNAMIC_USERS_FILE)) {
         error_log("Error de configuración: La constante DYNAMIC_USERS_FILE no está definida o no es válida durante el guardado.");
         return false;
     }

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

    $f = fopen($file, 'c+');
    if ($f === false) {
        error_log("Error al abrir el archivo de usuarios dinámicos para escritura: " . $file);
        return false;
    }

    if (flock($f, LOCK_EX)) {
        ftruncate($f, 0);
        fwrite($f, $json_data);
        fflush($f);
        flock($f, LOCK_UN);
        fclose($f);
        return true;
    } else {
        error_log("Error al adquirir bloqueo exclusivo del archivo de usuarios dinámicos: " . $file);
        fclose($f);
        return false;
    }
}


/**
 * Obtiene la lista COMBINADA de usuarios estáticos (desde config.php)
 * y usuarios dinámicos (desde el archivo JSON).
 *
 * @param bool $skip_current_user_fetch Si es true, evita llamar a gets_current_user() internamente para prevenir bucles infinitos
 * cuando gets_current_user() llama a esta función.
 * @return array Un array conteniendo todos los usuarios (estáticos + dinámicos).
 */
function get_users($skip_current_user_fetch = false) {
     global $static_users; // Acceder a la variable global definida en config.php
    
    // --- Debug 1: Estado inicial de los usuarios estáticos ---
    custom_debug_log("DEBUG AUTH (get_users): Estado de \$static_users al inicio: " . (isset($static_users) ? 'Definida' : 'Indefinida') . ", Tipo: " . gettype($static_users));
    if (isset($static_users) && is_array($static_users)) {
    custom_debug_log("DEBUG AUTH (get_users): Contenido de \$static_users: " . print_r($static_users, true));
    }
    
    
    // 1. Obtener usuarios estáticos desde config.php
    $all_users = $static_users ?? []; // Usa el array estático o un array vacío si no está definido
    if (!is_array($all_users)) {
    custom_debug_log("DEBUG AUTH (get_users): La variable \$static_users en config.php no es un array o no está definida correctamente.");
    $all_users = []; // Asegurarse de que $all_users sea un array
    }


    // 2. Obtener usuarios dinámicos desde el archivo JSON
    $dynamic_users = get_dynamic_users();

    if (is_array($dynamic_users)) {
        custom_debug_log("DEBUG AUTH (get_users): Usuarios dinámicos cargados (via get_dynamic_users()): " . print_r($dynamic_users, true));

        // 3. Combinar usuarios estáticos y dinámicos
        // Nota: array_merge puede no manejar duplicados de forma deseada si los hay.
        // Si necesitas garantizar unicidad por ID o username, se requeriría lógica adicional aquí.
        // Asumimos que los IDs de estáticos no chocan con los generados por uniqid() para dinámicos.
        $all_users = array_merge($all_users, $dynamic_users);
    } else {
       // custom_debug_log("No se pudieron cargar los usuarios dinámicos desde el archivo JSON.");
        custom_debug_log("DEBUG AUTH (get_users): get_dynamic_users() no retornó un array válido. No se pudieron cargar usuarios dinámicos.");

         // $all_users ya contiene los estáticos, así que procedemos con solo estáticos si falla la carga dinámica.
    }

    // Opcional: Ordenar la lista combinada si es necesario (ej. por username, rol)
    // usort($all_users, function($a, $b) { ... });
    custom_debug_log("DEBUG AUTH (get_users): Lista COMBINADA FINAL de usuarios cargados. Total: " . count($all_users) . ". Contenido: " . print_r($all_users, true));

    return $all_users; // Retornar la lista combinada de todos los usuarios
}


/**
 * Añade un nuevo usuario DINÁMICO al array de usuarios dinámicos y lo guarda en el archivo JSON.
 * Esta función SOLO añade usuarios al archivo JSON, no a los estáticos.
 *
 * @param array $new_user_data Array asociativo con los datos del nuevo usuario (sin 'id' ni 'created_at').
 * Debe contener al menos 'username' y 'password' (hasheada).
 * @return bool True si el usuario se añadió y guardó exitosamente, false en caso de error.
 */
function add_user($new_user_data) {
    // Esta función solo opera en los usuarios dinámicos

    $dynamic_users = get_dynamic_users(); // Cargar usuarios dinámicos existentes

    if (!is_array($dynamic_users)) {
        custom_debug_log("add_user falló: No se pudieron cargar los usuarios dinámicos para añadir.");
         return false;
    }

    // Generar un ID único para el nuevo usuario DINÁMICO
    // Asegúrate de que el formato de ID no choque con los IDs estáticos si usas formatos diferentes (ej. prefijos)
    $new_user_data['id'] = uniqid('dynamic_', true); // Añadimos prefijo 'dynamic_' para diferenciar

    $new_user_data['created_at'] = time(); // Añadir marca de tiempo de creación

    // Opcional: Verificar si el username o ID ya existe en la lista COMBINADA (estáticos + dinámicos) antes de añadir
    // Esto requiere llamar a get_users() aquí y buscar duplicados, lo cual añade complejidad.
    // Por simplicidad actual, asumimos que la lógica en alta.php maneja la validación necesaria.
    // Si la validación de duplicados es crítica, debe implementarse aquí o antes de llamar a add_user().


    $dynamic_users[] = $new_user_data; // Añadir el nuevo usuario al array dinámico

    return save_dynamic_users($dynamic_users); // Guardar el array actualizado de usuarios dinámicos
}


/**
 * Genera una contraseña temporal aleatoria. (Mantener existente)
 * ...
 */
 function generate_temporary_password($length = 10) {
     $chars = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ0123456789!@#$%^&*()_+';
     $password = '';
     $chars_length = strlen($chars);
     if ($chars_length === 0) return '';

     for ($i = 0; $i < $length; $i++) {
         try {
             $random_index = random_int(0, $chars_length - 1);
             $password .= $chars[$random_index];
         } catch (Exception $e) {
            custom_debug_log("random_int falló al generar contraseña, usando mt_rand como fallback: " . $e->getMessage());
             $password .= $chars[mt_rand(0, $chars_length - 1)];
         }
     }
     return $password;
 }


// --- Función de Autenticación (Modificada para usar get_users que da la lista combinada) ---

/**
 * Autentica un usuario verificando credenciales contra la lista COMBINADA
 * de usuarios estáticos y dinámicos.
 *
 * @param string $username El nombre de usuario a autenticar.
 * @param string $password La contraseña (sin hashear) proporcionada por el usuario.
 * @return array|false Retorna el array de datos del usuario si la autenticación es exitosa, false si falla.
 */
function authenticate_user($username, $password) {

    custom_debug_log("DEBUG AUTH (authenticate_user): Intentando autenticar usuario: '" . $username . "'");

    // Usamos get_users() para obtener la lista combinada de usuarios estáticos y dinámicos
    $all_users = get_users();

    // get_users() ya retorna [] en caso de error o lista vacía,
    // así que verificamos que sea un array antes de iterar.
    if (!is_array($all_users)) {
        custom_debug_log("Autenticación falló: No se pudieron cargar los datos de usuario (estáticos+dinámicos).");
        return false;
    }

    // Iterar sobre la lista COMBINADA para encontrar el nombre de usuario
    foreach ($all_users as $user) {

        custom_debug_log("DEBUG AUTH (authenticate_user): Revisando usuario en lista COMBINADA: " . ($user['username'] ?? 'Usuario sin username'));


        // Verificar si la clave 'username' existe y si coincide (insensible a mayúsculas)
        if (isset($user['username']) && is_string($user['username']) && strtolower($user['username']) === strtolower($username)) {

            custom_debug_log("DEBUG AUTH (authenticate_user): Coincidencia de username encontrada: '" . $user['username'] . "'. Verificando hash...");
            custom_debug_log("DEBUG AUTH (authenticate_user): Hash almacenado para verificar: " . ($user['password'] ?? 'N/A'));


            // Si encontramos el nombre de usuario, verificar la contraseña hasheada
            // password_verify() compara una cadena (la contraseña ingresada) con un hash existente
            if (isset($user['password']) && is_string($user['password']) && password_verify($password, $user['password'])) {

                custom_debug_log("DEBUG AUTH (authenticate_user): Resultado de password_verify para '" . $user['username'] . "': " . (password_verify($password, $user['password']) ? 'VERDADERO' : 'FALSO'));


                // Autenticación exitosa
                // Retorna el array completo de datos del usuario autenticado (ya sea estático o dinámico)
                return $user;
            }
        }
    }

    // Si el bucle termina sin encontrar un nombre de usuario o la contraseña no coincide
    return false; // Autenticación fallida
}

// Función para cerrar sesión (mantener existente)
function logout() {
    start_session_if_not_started();
    $_SESSION = array();
    session_destroy();
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httptoken"]
        );
    }
}




//debug
function custom_debug_log($message) {
    // Define la ruta al archivo de debug personalizado.
    // '__DIR__ . '/../custom_debug.log'' apunta a custom_debug.log en la carpeta superior (htdocs)
    // Si la constante no está definida o es false, no hacemos nada.
    if (!defined('DEBUG_MODE') || !DEBUG_MODE) {
        return false; // No se hizo logging
    }
    if (!defined('CUSTOM_DEBUG_LOG_FILE') || !is_string(CUSTOM_DEBUG_LOG_FILE)) {
        // Si la ruta no está bien definida, logueamos un error crítico usando error_log por si acaso
        error_log("ERROR CRÍTICO: La constante CUSTOM_DEBUG_LOG_FILE no está definida o es inválida en config.php. Mensaje de debug original: " . $message);
        return false; // No se hizo logging
   }
    $log_file = CUSTOM_DEBUG_LOG_FILE; // Usar la constante para la ruta del archivo
    $timestamp = date('Y-m-d H:i:s'); // Obtener la hora actual
    $log_message = "[{$timestamp}] " . $message . PHP_EOL; // Formato del mensaje (hora + mensaje + salto de línea)

    // Verificar si el directorio donde se creará el archivo es escribible si el archivo no existe
    // dirname($log_file) obtiene el directorio padre del archivo
    if (!file_exists($log_file) && !is_writable(dirname($log_file))) {
         // Si el archivo no existe y el directorio no es escribible, no podemos escribir.
         // Como fallback, intentamos usar error_log() de nuevo (aunque no funcionó antes).
         custom_debug_log("ERROR FATAL DEBUG: No se puede escribir en el log personalizado: Directorio '" . dirname($log_file) . "' no es escribible.");
         custom_debug_log("Mensaje de debug original: " . $message); // Intentar loguear via error_log
         return false; // Indica fallo
    }

    // Intentar escribir en el archivo, añadiendo al final (FILE_APPEND) y usando bloqueo (LOCK_EX)
    // LOCK_EX ayuda a prevenir problemas si varios procesos intentan escribir al mismo tiempo
    if (file_put_contents($log_file, $log_message, FILE_APPEND | LOCK_EX) === false) {
         // Si file_put_contents falla (ej. problemas de permisos inesperados), reportar.
         custom_debug_log("ERROR: Falló al escribir en el archivo de log personalizado: " . $log_file);
         ecustom_debug_log("Mensaje de debug original: " . $message); // Intentar loguear via error_log
         return false; // Indica fallo
    }
    return true; // Indica éxito
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

function get_user_by_username(string $username): ?array {
    // Esta función necesita cargar la lista de usuarios para buscar.
    // Asume que load_users_from_data() está definida en este archivo (auth.php).
    $users = load_users_from_data(); // Carga todos los usuarios (estáticos + dinámicos si get_users() combina)

    // Si load_users_from_data() solo carga dinámicos y necesitas buscar en estáticos también,
    // deberías usar get_users() aquí si esa función combina estáticos y dinámicos.
    // Basado en el último auth.php que enviaste, get_users() combina, pero load_users_from_data() parece leer solo dinámicos.
    // Si necesitas buscar en AMBOS, usa get_users():
    // $users = get_users(); // Usar esta línea en su lugar si necesitas buscar en estáticos también.

    foreach ($users as $user) {
        // Verifica si la clave 'username' existe y si coincide.
        if (isset($user['username']) && $user['username'] === $username) {
            return $user; // Usuario encontrado
        }
    }
    return null; // Usuario no encontrado
}

function load_users_from_data(): array {
    global $users_file; // Necesita acceder a la variable global definida en config.php
    // Asegurarse de que $users_file tenga un valor por defecto si no está en config.php
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

function add_user_to_data(array $new_user_data): bool {
    // Validar que al menos el nombre de usuario y la contraseña estén presentes
    if (empty($new_user_data['username']) || empty($new_user_data['password'])) {
        error_log("add_user_to_data error: Username or password missing for new user.");
        return false;
    }

    // Generar un ID único si no está presente
    if (!isset($new_user_data['id'])) {
        $new_user_data['id'] = uniqid('user_', true); // Ejemplo simple de ID basado en tiempo
    }

    // Hashear la contraseña proporcionada
    $new_user_data['password'] = password_hash($new_user_data['password'], PASSWORD_DEFAULT);

    // Cargar usuarios existentes
    // Esta función necesita load_users_from_data() y save_users_to_data()
    // Asegúrate de que esas funciones estén definidas en este archivo (auth.php).
    $users = load_users_from_data();

    // Verificar si el ID generado ya existe (altamente improbable con uniqid, pero buena práctica)
    // O si prefieres que el username sea el identificador único, verifica eso aquí también.
    // Ya tenemos get_user_by_username() para verificar si el username existe.

    // Añadir el nuevo usuario al array
    $users[] = $new_user_data;

    // Guardar el array actualizado de vuelta al archivo JSON
    return save_users_to_data($users);
}

function save_users_to_data(array $users): bool {
    // Esta función necesita la ruta al archivo de usuarios.
    // Asume que $users_file está definido en config.php.
    global $users_file;

    // Si $users_file no está definido en config.php, usa un valor por defecto.
    // Asegúrate de que esta ruta sea correcta para tu archivo JSON de usuarios.
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
/*
function gets_current_user() {
        global $xusers;
        start_session_if_not_started();
        if (is_logged_in()) {
            // Editor might still highlight $xusers here due to static analysis
            return $xusers[$_SESSION['user_id']];
        }
        return null;
    }
*/

/*
// Log the user out
function logout() {
    start_session_if_not_started();
    session_unset(); // Unset all session variables
    session_destroy(); // Destroy the session
}
*/

?>