<?php

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/config.php'; // Asegúrate que esté al principio (si usas algo de config.php aquí, como $main_name)

start_session_if_not_started();

$error = '';

if (is_logged_in()) {
    redirect('index.php'); // Redirige usando la función de auth.php
    }


    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Obtener y limpiar (trim) el nombre de usuario y la contraseña del formulario
        $username = trim($_POST['username'] ?? ''); // Usamos trim para eliminar espacios extra
        $password = $_POST['password'] ?? '';     // Obtener la contraseña
    
        // --- === Llamar a la NUEVA función de autenticación de auth.php === ---
        // authenticate_user() retorna el array de datos del usuario si las credenciales son correctas,
        // o retorna false si el usuario no se encuentra o la contraseña es incorrecta.
        $authenticated_user_data = authenticate_user($username, $password); // <<< ¡CORRECCIÓN CLAVE! LLAMAR A ESTA FUNCIÓN
    
        // Verificar el resultado de la autenticación
        if ($authenticated_user_data) {
            // --- === Login Exitoso === ---
    
            // La sesión ya está iniciada por start_session_if_not_started() al principio del archivo
    
            // Establecer las variables de sesión usando los datos del usuario retornados por authenticate_user()
            $_SESSION['user_id'] = $authenticated_user_data['id'];       // Guarda el ID único del usuario
            $_SESSION['username'] = $authenticated_user_data['username']; // Guarda el nombre de usuario
            $_SESSION['user_role'] = $authenticated_user_data['role'];   // Guarda el rol del usuario
    
            // Opcional: Almacenar otros datos relevantes del usuario en la sesión si existen y los necesitas frecuentemente
            $_SESSION['user_name'] = $authenticated_user_data['name'] ?? null; // Operador null coalescing ?? maneja si la clave no existe
            $_SESSION['user_lote'] = $authenticated_user_data['lote'] ?? null;
            $_SESSION['user_phone'] = $authenticated_user_data['phone'] ?? null; // Usamos 'phone' ahora según la nueva estructura
    
            // Redirigir a la página principal (landing) después de un login exitoso
            redirect('index.php'); // Usa la función redirect() definida en auth.php
    
        } else {
            // --- === Login Fallido === ---
            // authenticate_user() retorna false, mostrar mensaje de error
            $error = 'Usuario o contraseña incorrectos.'; // Este mensaje ya lo tienes
        }
    }


?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso <?php echo $main_name ?></title>
    <link rel="stylesheet" href="css/styles.css"> <!-- Enlaza tu CSS principal -->
    <style>

    </style>
</head>
<body>
<main>
    <div class="login-container">
    <div class="logo-container">
            <img src="assets/logo.png" alt="Logo de la Aplicación">
        </div>
        <?php if ($error): ?>
            <p class="error"><?php echo $error; ?></p>
        <?php endif; ?>
        <form method="POST" action="login.php">
            <div class="form-group">
                <label for="username">Usuario:</label>
                <input type="text" id="username" name="username" required>
            </div>
            <div class="form-group">
                <label for="password">Contraseña:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit">Ingresar</button>
        </form>
    </div>
        </main>
</body>
</html>