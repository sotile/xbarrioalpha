<?php
// htdocs/delete_invitation.php - Endpoint para eliminar invitaciones via AJAX

// --- === Includes y Autenticación === ---
require_once __DIR__ . '/auth.php'; // Necesario para start_session_if_not_started, gets_current_user, is_logged_in
require_once __DIR__ . '/invitation_handler.php'; // Necesario para deleteInvitationByCode, getInvitationByCode
require_once __DIR__ . '/config.php'; // Necesario para $delete_allowed_roles_global, $qr_codes_dir (si se define allí)

// Inicia la sesión y obtén datos del usuario logueado.
start_session_if_not_started();
$current_user = gets_current_user();
$user_role = $current_user['role'] ?? 'guest';
$user_id = $current_user['id'] ?? null;
$user_lote = $current_user['lote'] ?? null;

// --- === Preparar Respuesta JSON === ---
header('Content-Type: application/json');
$response = ['success' => false, 'message' => 'Método de solicitud no permitido.'];

// --- === Manejar la Solicitud POST === ---
// Este script solo debe responder a solicitudes POST.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Inicializar respuesta para el caso POST
    $response['message'] = 'Acción no válida.'; // Cambiamos el mensaje por defecto si es POST pero no hay acción reconocida

    // Obtener la acción solicitada y los datos necesarios.
    $action = $_POST['action'] ?? ''; // Esperamos 'delete'
    $code = $_POST['code'] ?? ''; // Esperamos el código de la invitación

    // --- === Manejar la Acción de Eliminación === ---
    if ($action === 'delete') {
        // Validar que se haya proporcionado un código.
        if (empty($code)) {
            $response['message'] = 'Error: Código de invitación no proporcionado para eliminar.';
        } else {
            // --- === Verificación de Permisos para Eliminar === ---
            $can_delete = false;

            // Asegúrate que $delete_allowed_roles_global está definido en config.php
            $delete_allowed_roles_global = $delete_allowed_roles_global ?? ['administrador', 'developer'];

            // 1. Verificar si el rol del usuario actual tiene permiso global para eliminar
            if (is_logged_in() && in_array($user_role, $delete_allowed_roles_global)) {
                $can_delete = true;
            } else {
                 // 2. Si no tiene permiso global, verificar si es el ANFITRIÓN creador de ESTA invitación
                 // O si no está logueado (en cuyo caso no puede eliminar)
                 if (!is_logged_in()) {
                     $response['message'] = 'Error: Debes iniciar sesión para eliminar invitaciones.';
                 } else {
                     $invitation_to_delete = getInvitationByCode($code); // Obtiene la invitación (función de invitation_handler.php)

                     if ($invitation_to_delete) {
                          $invite_anfitrion_id = $invitation_to_delete['anfitrion']['id'] ?? null;
                          $invite_anfitrion_lote = $invitation_to_delete['anfitrion']['lote'] ?? null;

                          if ($user_id && $user_lote &&
                              $user_id === $invite_anfitrion_id &&
                              $user_lote === $invite_anfitrion_lote) {
                              $can_delete = true;
                          } else {
                              $response['message'] = 'Error: No tienes permiso para eliminar esta invitación (no eres el creador).';
                          }
                      } else {
                           $response['message'] = 'Error: Invitación no encontrada con el código proporcionado.';
                      }
                 }
            }

            // --- === Ejecutar Eliminación si el Usuario Tiene Permiso === ---
            if ($can_delete) {
                // El usuario está autorizado para eliminar esta invitación.
                $delete_success = deleteInvitationByCode($code); // Llama a la función de invitation_handler.php

                if ($delete_success) {
                    // Si la eliminación del JSON fue exitosa, también intentamos eliminar el archivo QR.
                    // Usa $qr_codes_dir (definida en config.php o en invitation_handler.php)
                    // Acceder a la variable global si no se define en config.php
                    global $qr_codes_dir;
                    $qr_filepath = ($qr_codes_dir ?? __DIR__ . '/../qr/') . urlencode($code) . '.png';


                    if (file_exists($qr_filepath)) {
                         if (unlink($qr_filepath)) {
                             error_log("DEBUG: Archivo QR eliminado exitosamente: " . $qr_filepath);
                             $response = ['success' => true, 'message' => 'Invitación y archivo QR eliminados con éxito.'];
                         } else {
                             error_log("ERROR: No se pudo eliminar el archivo QR: " . $qr_filepath);
                             $response = ['success' => true, 'message' => 'Invitación eliminada, pero no se pudo eliminar el archivo QR asociado.'];
                         }
                     } else {
                          error_log("DEBUG: Archivo QR no encontrado para eliminar: " . $qr_filepath);
                          $response = ['success' => true, 'message' => 'Invitación eliminada, el archivo QR ya no existía.'];
                     }

                } else {
                    // Si falló deleteInvitationByCode (ej: no encontró la invitación o falló al guardar el JSON).
                    // El mensaje de error ya fue logueado dentro de deleteInvitationByCode.
                    // Si el mensaje ya fue seteado porque la invitación no se encontró, no lo sobrescribimos.
                     if (!isset($response['message']) || $response['message'] === 'Acción no válida.') {
                         $response['message'] = 'Error interno al procesar la eliminación de la invitación.';
                     }
                }
            } // else for $can_delete (message already set inside permission checks)
        } // else for empty($code)
    } // else for $action === 'delete'
} // else for $_SERVER['REQUEST_METHOD'] === 'POST' (message 'Método no permitido' set initially)


// --- === Enviar la Respuesta JSON y Salir === ---
echo json_encode($response);
exit; // Termina la ejecución del script.

?>