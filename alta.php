<?php
// htdocs/alta.php - Página para dar de alta nuevos usuarios

// --- === Includes y Autenticación === ---
require_once __DIR__ . '/includes/auth.php'; // Contiene funciones de autenticación y gestión de usuarios.
require_once __DIR__ . '/includes/config.php'; // Contiene la configuración (roles permitidos, etc.).

// Inicia la sesión si no está iniciada.
start_session_if_not_started();

// Obtiene los datos del usuario logueado.
$current_user = gets_current_user();
$user_role = $current_user['role'] ?? 'guest';

// --- === Lógica de Autorización de Página === ---
// Define qué roles pueden acceder a esta página para dar de alta usuarios.
// Puedes definir $alta_allowed_roles en config.php si prefieres.
$alta_allowed_roles = $alta_allowed_roles ?? ['administrador', 'developer'];

// Verifica si el usuario NO está logueado O si su rol NO está en la lista de roles permitidos.
if (!is_logged_in() || !in_array($user_role, $alta_allowed_roles)) {
    // Si no tiene permiso, redirige a la página principal o de login.
    redirect('index.php'); // O 'login.php' si prefieres que vuelvan a loguearse.
}

// --- === Variables para mensajes de feedback === ---
$success_message = '';
$error_message = '';

// --- === Manejar el envío del formulario POST === ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Obtener y limpiar los datos del formulario
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? ''; // Rol seleccionado
    $name = trim($_POST['name'] ?? ''); // Nombre completo
    $lote = trim($_POST['lote'] ?? ''); // Lote
    $phone = trim($_POST['phone'] ?? ''); // Teléfono

    // 2. Validar los datos (ejemplos básicos)
    if (empty($username) || empty($password) || empty($role) || empty($name) || empty($lote)) {
        $error_message = 'Todos los campos obligatorios (Usuario, Contraseña, Rol, Nombre, Lote) deben ser completados.';
    } elseif (strlen($password) < 6) { // Ejemplo: Contraseña mínima de 6 caracteres
        $error_message = 'La contraseña debe tener al menos 6 caracteres.';
    } elseif (!is_valid_role($role)) { // Debes implementar is_valid_role() en auth.php o aquí
         $error_message = 'El rol seleccionado no es válido.';
    } else {
        // 3. Verificar si el nombre de usuario ya existe
        // Necesitas una función en auth.php para buscar un usuario por username.
        // Si no la tienes, puedes añadirla.
        $existing_user = get_user_by_username($username); // <<< Asume que esta función existe en auth.php

        if ($existing_user) {
            $error_message = 'El nombre de usuario "' . htmlspecialchars($username) . '" ya existe.';
        } else {
            // 4. Crear el nuevo usuario
            // Necesitas una función en auth.php para añadir un nuevo usuario.
            // Esta función debería hashear la contraseña internamente.
            $new_user_data = [
                'username' => $username,
                'password' => $password, // La función add_user_to_data() debe hashear esto
                'role' => $role,
                'name' => $name,
                'lote' => $lote,
                'phone' => $phone, // Incluir teléfono
                // Puedes añadir otras claves si tu estructura de usuario las requiere (ej: 'id', 'created_at')
                // La función add_user_to_data() debería generar el 'id' si es necesario.
            ];

            // Llama a la función para añadir el usuario al archivo JSON.
            // Esta función debe retornar true en éxito y false en fallo.
            $add_success = add_user_to_data($new_user_data); // <<< Asume que esta función existe en auth.php

            if ($add_success) {
                $success_message = 'Usuario "' . htmlspecialchars($username) . '" creado con éxito.';
                // Opcional: Limpiar los campos del formulario después del éxito si no quieres recargar.
                // Opcional: Redirigir a una página de lista de usuarios si existe.
            } else {
                $error_message = 'Error al intentar guardar el nuevo usuario. Revisa los logs.';
            }
        }
    }
}

// --- Funciones auxiliares (si no están en auth.php) ---
// Si no tienes get_user_by_username(), add_user_to_data(), is_valid_role() en auth.php,
// necesitarás añadirlas allí o definirlas aquí. Es mejor tenerlas centralizadas en auth.php.

/*
// Ejemplo básico de get_user_by_username() si no está en auth.php
function get_user_by_username(string $username): ?array {
    global $users_file; // Asume que $users_file está definido en config.php
    if (!file_exists($users_file)) return null;
    $users = json_decode(file_get_contents($users_file), true) ?? [];
    foreach ($users as $user) {
        if (isset($user['username']) && $user['username'] === $username) {
            return $user;
        }
    }
    return null;
}

// Ejemplo básico de add_user_to_data() si no está en auth.php
function add_user_to_data(array $new_user_data): bool {
    global $users_file; // Asume que $users_file está definido en config.php
    // Generar un ID único si tu estructura lo requiere y no lo hace auth.php
    if (!isset($new_user_data['id'])) {
        $new_user_data['id'] = uniqid('user_', true); // Ejemplo simple de ID
    }
    // Hashear la contraseña si no lo hace auth.php
    if (isset($new_user_data['password'])) {
         $new_user_data['password'] = password_hash($new_user_data['password'], PASSWORD_DEFAULT);
    } else {
         error_log("add_user_to_data error: Password not provided for new user.");
         return false; // No se puede añadir usuario sin contraseña
    }

    $users = json_decode(file_get_contents($users_file) ?? '[]', true) ?? [];
    $users[] = $new_user_data;
    $json_content = json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json_content === false) {
        error_log("add_user_to_data error encoding JSON: " . json_last_error_msg());
        return false;
    }
    $data_dir = dirname($users_file);
    if (!is_dir($data_dir)) {
        if (!mkdir($data_dir, 0775, true)) {
             error_log("add_user_to_data error creating directory: " . $data_dir);
             return false;
        }
    }
    if (file_put_contents($users_file, $json_content, LOCK_EX) === false) {
        error_log("add_user_to_data error writing file: " . $users_file);
        return false;
    }
    return true;
}

// Ejemplo básico de is_valid_role() si no está en auth.php
function is_valid_role(string $role): bool {
    // Define aquí los roles válidos en tu sistema.
    $valid_roles = ['anfitrion', 'seguridad', 'administrador', 'developer']; // <<< Define tus roles válidos
    return in_array($role, $valid_roles);
}
*/


?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alta Usuario - <?php echo $main_name ?? ''; ?></title>
    <link rel="stylesheet" href="css/styles.css"> <style>
        /* Puedes añadir estilos específicos para esta página aquí si es necesario */
        /* Por ejemplo, si el .container de styles.css es muy estrecho, puedes anularlo */
        /*
        .container {
            max-width: 800px; // Un poco más ancho para el formulario de alta
        }
        */
        .form-alta h2 { /* Estilo para el título del formulario de alta */
            text-align: center;
            margin-bottom: 20px;
            color: #333;
        }
         /* Ajustes para los elementos del formulario si los estilos generales no son suficientes */
        .form-alta .form-group {
             margin-bottom: 15px;
        }
        .form-alta label {
             display: block;
             margin-bottom: 5px;
             font-weight: bold;
        }
         .form-alta input[type="text"],
         .form-alta input[type="password"],
         .form-alta select {
             width: 100%; /* Ocupa todo el ancho del form-group */
             padding: 10px;
             border: 1px solid #ccc;
             border-radius: 4px;
             box-sizing: border-box;
         }
         .form-alta button[type="submit"] {
             /* Puedes usar la clase .btn de styles.css si quieres */
             /* O mantener este estilo si es específico de este formulario */
             display: block; /* Para que ocupe todo el ancho */
             width: 100%;
             padding: 10px;
             background-color: #007bff; /* Azul, diferente al botón de login */
             color: white;
             border: none;
             border-radius: 4px;
             cursor: pointer;
             font-size: 16px;
             margin-top: 20px; /* Más espacio arriba */
             box-sizing: border-box;
         }
         .form-alta button[type="submit"]:hover {
             background-color: #0056b3; /* Azul más oscuro */
         }

         /* Estilos para mensajes de feedback */
        .message {
            text-align: center;
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 4px;
        }
        .message.success {
            color: #155724;
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
        }
        .message.error {
            color: #721c24;
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
        }

         /* Estilo para el botón Volver a Inicio */
         .back-to-home {
             display: block; /* Para que ocupe su propia línea */
             text-align: center;
             margin-top: 20px;
             /* Puedes usar la clase .btn o .back-btn de styles.css si prefieres */
             /* O definir un estilo específico aquí */
             color: #007bff;
             text-decoration: none;
         }
         .back-to-home:hover {
             text-decoration: underline;
         }


    </style>
</head>
<body>
    <div class="header">
         <h2>Alta Usuario</h2>
         <?php // Puedes añadir aquí los botones de Logout y Back si los usas en esta página ?>
         <?php // require_once __DIR__ . '/includes/header_nav.php'; // Si tienes un archivo para la navegación del header ?>
         <a href="logout.php" class="logout-btn">Salir</a>
         <a href="index.php" class="back-btn">Home</a>
    </div>

    <div class="container">
        <div class="form-alta">
            <?php if ($success_message): ?>
                <p class="message success"><?php echo htmlspecialchars($success_message); ?></p>
            <?php endif; ?>
            <?php if ($error_message): ?>
                <p class="message error"><?php echo htmlspecialchars($error_message); ?></p>
            <?php endif; ?>

            <form method="POST" action="alta.php">
                <div class="form-group">
                    <label for="username">Usuario:</label>
                    <input type="text" id="username" name="username" required value="<?php echo htmlspecialchars($username ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="password">Contraseña:</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <div class="form-group">
                    <label for="role">Rol:</label>
                    <select id="role" name="role" required>
                        <option value="">Selecciona un rol</option>
                        <?php
                        // Lista de roles válidos. Puedes obtener esto de config.php si lo defines allí.
                        $valid_roles = ['anfitrion', 'seguridad', 'administrador', 'developer']; // <<< Define tus roles válidos aquí o en config.php
                        foreach ($valid_roles as $valid_role) {
                            // Seleccionar el rol si hubo un error para que el usuario no tenga que elegir de nuevo
                            $selected = (isset($role) && $role === $valid_role) ? 'selected' : '';
                            echo '<option value="' . htmlspecialchars($valid_role) . '" ' . $selected . '>' . htmlspecialchars(ucfirst($valid_role)) . '</option>';
                        }
                        ?>
                    </select>
                </div>
                 <div class="form-group">
                    <label for="name">Nombre Completo:</label>
                    <input type="text" id="name" name="name" required value="<?php echo htmlspecialchars($name ?? ''); ?>">
                </div>
                 <div class="form-group">
                    <label for="lote">Lote:</label>
                    <input type="text" id="lote" name="lote" required value="<?php echo htmlspecialchars($lote ?? ''); ?>">
                </div>
                 <div class="form-group">
                    <label for="phone">Teléfono (Opcional):</label>
                    <input type="text" id="phone" name="phone" value="<?php echo htmlspecialchars($phone ?? ''); ?>">
                </div>

                <button type="submit">Crear Usuario</button>
            </form>

            <?php // Opcional: Enlace para volver a la página principal ?>
            </div> </div> <div class="footer">
        <span>Usuario actual: <?php echo htmlspecialchars($current_user['username'] ?? 'Invitado'); ?> (Rol: <?php echo htmlspecialchars($user_role); ?>)</span>
    </div>

</body>
</html>
