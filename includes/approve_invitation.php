<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/invitation_handler.php';

start_session_if_not_started();

// Set header for JSON response
header('Content-Type: application/json');

// Check if logged in as seguridad
if (!is_seguridad()) {
    http_response_code(403); // Forbidden
    echo json_encode(['success' => false, 'message' => 'Acceso denegado.']);
    exit();
}

// Get code from POST request
$code = $_POST['code'] ?? '';

if (empty($code)) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'Código de invitación no proporcionado para aprobar.']);
    exit();
}

// Attempt to approve the invitation
$success = approveInvitation($code);

if ($success) {
    echo json_encode(['success' => true, 'message' => 'Invitación aprobada con éxito.']);
    // TODO: Future - Trigger WhatsApp notification here
    // For example:
    // $invitation_data = getInvitation($code);
    // if ($invitation_data && isset($invitation_data['anfitrion']['whatsapp'])) {
    //     notifyAnfitrion($invitation_data['anfitrion']['whatsapp'], $invitation_data);
    // }

} else {
     // Fetch updated data to provide specific reason if possible
    $invitation_data = getInvitation($code);
    $message = 'No se pudo aprobar la invitación.';
    if ($invitation_data) {
         if ($invitation_data['status'] !== 'pendiente') {
             $message = 'La invitación ya está ' . htmlspecialchars($invitation_data['status']) . '.';
         } elseif (!validateInvitation($invitation_data)) {
             $message = 'La invitación ha expirado o no es válida.';
         }
    }

    http_response_code(409); // Conflict (or 400 Bad Request depending on specific reason)
    echo json_encode(['success' => false, 'message' => $message]);
}
?>