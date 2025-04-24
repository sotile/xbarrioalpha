<?php
// htdocs/includes/create_invitation.php - Endpoint para procesar la creación de invitaciones via POST

// --- === Includes === ---
// Necesitamos auth.php para start_session_if_not_started, gets_current_user, redirect
require_once __DIR__ . '/auth.php';
// Necesitamos invitation_handler.php para la función createInvitation
require_once __DIR__ . '/invitation_handler.php';
// Necesitamos config.php para URL_BASE y roles permitidos si se verifican aquí (aunque la verificación principal está en invitar.php)
require_once __DIR__ . '/config.php';


// --- === Iniciar Sesión y Obtener Usuario Actual === ---
start_session_if_not_started();
$current_user = gets_current_user();
$user_role = $current_user['role'] ?? 'guest';
$user_id = $current_user['id'] ?? null;
$user_name = $current_user['name'] ?? 'Anfitrión Desconocido';
$user_lote = $current_user['lote'] ?? null; // Necesitamos el lote del anfitrión para createInvitation


// --- === Lógica de Autorización Básica (Redundante pero seguro) === ---
// Aunque invitar.php ya verifica permisos, es bueno tener una verificación aquí también.
$allowed_roles_for_invitar = $invite_allowed_roles ?? ['anfitrion', 'administrador', 'developer']; // Define tus roles permitidos

if (!is_logged_in() || !in_array($user_role, $allowed_roles_for_invitar)) {
    // Si no tiene permiso, redirige (no debería llegar aquí si invitar.php funciona bien)
    redirect('index.php');
}


// --- === Manejar la Solicitud POST === ---
// Este script solo debe procesar solicitudes POST.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. Recuperar y validar datos del formulario
    $guest_name = trim($_POST['guest_name'] ?? '');
    $expiration_date_str = $_POST['expiration_date'] ?? ''; // Viene en formato 'YYYY-MM-DDTHH:MM'

    // Validar campos requeridos
    if (empty($guest_name) || empty($expiration_date_str)) {
        // Si faltan datos, redirige de vuelta a invitar.php con un mensaje de error.
        // Usamos parámetros GET en la URL para pasar el mensaje.
        $error_message = urlencode("Por favor, completa todos los campos.");
        redirect('invitar.php?status=error&message=' . $error_message);
        exit; // Asegura que el script se detenga
    }

    // Asegurarse de tener los datos completos del anfitrión
    if (!$current_user || !isset($user_id, $user_name, $user_lote)) {
         $error_message = urlencode("Error interno: No se pudo obtener la información completa del anfitrión logueado.");
         redirect('invitar.php?status=error&message=' . $error_message);
         exit;
    }

    // Preparar los datos del anfitrión para la función createInvitation
    $host_user_data_for_invite = [
        'id' => $user_id,
        'username' => $current_user['username'] ?? 'N/A', // Incluir username por si acaso
        'name' => $user_name,
        'lote' => $user_lote,
        // Puedes añadir otros datos si createInvitation los necesita
    ];


    // 2. Llamar a la función createInvitation
    // Esta función está definida en invitation_handler.php
    $new_invitation_data = createInvitation(
        $guest_name,
        $host_user_data_for_invite,
        $expiration_date_str
    );

    // 3. Verificar el resultado y redirigir
    if ($new_invitation_data) {
        // Invitación creada con éxito. Redirige a invitar.php con un mensaje de éxito
        // y el código de la nueva invitación para que invitar.php lo muestre.
        $success_message = urlencode("Invitación creada con éxito!");
        $invitation_code = urlencode($new_invitation_data['code'] ?? '');
        // Redirige a invitar.php, pasando el código y el mensaje en la URL
        redirect('invitar.php?status=success&code=' . $invitation_code . '&message=' . $success_message);
        exit; // Asegura que el script se detenga

    } else {
        // Error al crear la invitación (la función createInvitation ya loguea el error interno)
        $error_message = urlencode("Error al crear la invitación. Revisa los logs del servidor.");
        redirect('invitar.php?status=error&message=' . $error_message);
        exit; // Asegura que el script se detenga
    }

} else {
    // Si la solicitud no es POST (ej. alguien intenta acceder directamente por GET)
    // Redirige a la página principal o a invitar.php
    redirect('invitar.php'); // O index.php si prefieres
    exit; // Asegura que el script se detenga
}

?>
