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

// Get code from GET request
$code = $_GET['code'] ?? '';

if (empty($code)) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'Código de invitación no proporcionado.']);
    exit();
}

$invitation_data = getInvitation($code);

if ($invitation_data === false) {
    http_response_code(404); // Not Found
    echo json_encode(['success' => false, 'message' => 'Invitación no encontrada.']);
    exit();
}

// Check validity status
$isValid = validateInvitation($invitation_data); // Checks 'pendiente' status and expiration

// Prepare response data
$response_data = [
    'success' => true,
    'invitation' => [
        'code' => $invitation_data['code'],
        'invitado_nombre' => htmlspecialchars($invitation_data['invitado_nombre']),
        'anfitrion_name' => htmlspecialchars($invitation_data['anfitrion']['name'] ?? 'N/A'),
        'anfitrion_lote' => htmlspecialchars($invitation_data['anfitrion']['lote'] ?? 'N/A'),
        'fecha_creacion' => date('Y-m-d H:i:s', $invitation_data['fecha_creacion']),
        'fecha_expiracion' => date('Y-m-d H:i:s', $invitation_data['fecha_expiracion']),
        'status' => $invitation_data['status'], // pendiente, aprobado, expirado
        'fecha_aprobacion' => isset($invitation_data['fecha_aprobacion']) ? date('Y-m-d H:i:s', $invitation_data['fecha_aprobacion']) : null,
    ],
    'isValid' => $isValid, // Boolean indicating if it's currently valid AND pending
    'message' => $isValid ? 'Invitación Válida' : ($invitation_data['status'] !== 'pendiente' ? 'Invitación ya ' . htmlspecialchars($invitation_data['status']) : 'Invitación Expirada')
];


echo json_encode($response_data);
?>