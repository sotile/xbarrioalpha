<?php
// htdocs/includes/invitation_handler.php

// --- === Includes (Asegúrate que estén al principio) === ---
require_once __DIR__ . '/auth.php'; // Necesario para start_session_if_not_started y gets_current_user
require_once __DIR__ . '/config.php'; // Necesario para URL_BASE, $invitations_file, $qr_codes_dir (si definidos allí), $delete_allowed_roles_global
require_once __DIR__ . '/phpqrcode.php'; // Necesario para la generación de QR en createInvitation


// --- Configuración (Variables globales) ---
// Si estas variables están definidas en config.php, se usarán esos valores.
// De lo contrario, se usan los valores por defecto definidos aquí.
$invitations_file = $invitations_file ?? __DIR__ . '/../data/invitations.json';
$qr_codes_dir = $qr_codes_dir ?? __DIR__ . '/../qr/'; // Directorio para guardar las imágenes QR

// Roles permitidos globalmente para eliminar invitaciones
$delete_allowed_roles_global = $delete_allowed_roles_global ?? ['administrador', 'developer'];


// --- Funciones de Bajo Nivel para Manejo de Archivo JSON ---

/**
 * Lee los datos de las invitaciones desde el archivo JSON.
 * @return array Un array de invitaciones, o un array vacío si el archivo no existe, está vacío o es inválido.
 */
function getInvitations(): array {
    global $invitations_file; // Usa la variable global con la ruta al archivo JSON
    if (!file_exists($invitations_file)) {
        return [];
    }
    $file_content = file_get_contents($invitations_file);
    if ($file_content === false || $file_content === '') {
        return [];
    }
    $invitations = json_decode($file_content, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($invitations)) {
        error_log("Error decodificando invitations.json: " . json_last_error_msg());
        return [];
    }
    return $invitations;
}

/**
 * Guarda los datos de las invitaciones en el archivo JSON.
 * @param array $invitations El array de invitaciones a guardar.
 * @return bool True en caso de éxito, false en caso de fallo.
 */
function saveInvitations(array $invitations): bool {
    global $invitations_file; // Usa la variable global con la ruta al archivo JSON
    $json_content = json_encode($invitations, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json_content === false) {
        error_log("Error codificando invitaciones a JSON: " . json_last_error_msg());
        return false;
    }
    $data_dir = dirname($invitations_file);
    if (!is_dir($data_dir)) {
        if (!mkdir($data_dir, 0775, true)) {
            error_log("Error creando directorio de datos: " . $data_dir);
            return false;
        }
    }
    if (file_put_contents($invitations_file, $json_content, LOCK_EX) === false) {
        error_log("Error escribiendo en invitations.json: " . $invitations_file);
        return false;
    }
    return true; // Éxito
}

// --- Funciones CRUD (Create, Read, Update, Delete) ---

/**
 * Crea una nueva invitación, genera un código único, la guarda en JSON y genera la imagen QR.
 * @param string $guest_name El nombre del invitado.
 * @param array $host_user_data Array asociativo con los datos del usuario anfitrión (id, name, lote).
 * @param string $expiration_date_str String que representa la fecha y hora de expiración (ej. 'YYYY-MM-DD HH:MM:SS').
 * @return array|null El array de la invitación recién creada en caso de éxito, null en caso de fallo.
 */
function createInvitation(string $guest_name, array $host_user_data, string $expiration_date_str): ?array {
    global $invitations_file; // Ruta al archivo JSON
    global $qr_codes_dir; // Ruta al directorio de QRs
    // Validaciones básicas de entrada
    if (empty($guest_name) || !isset($host_user_data['id'], $host_user_data['name'], $host_user_data['lote']) || empty($expiration_date_str)) {
        error_log("createInvitation fallo: Faltan datos requeridos.");
        return null;
    }

    // Generar un código único para la invitación
    $unique_seed = uniqid('', true) . microtime(true) . $guest_name . $host_user_data['id'];
    $invitation_code = substr(sha1($unique_seed), 0, 20); // Código de 20 caracteres

    // Convertir fecha de expiración a timestamp
    $creation_timestamp = time();
    $expiration_timestamp = strtotime($expiration_date_str);

    if ($expiration_timestamp === false) {
        error_log("createInvitation fallo: Formato de fecha de expiración inválido: " . $expiration_date_str);
        return null;
    }

    // Crear el array de la nueva invitación
    $new_invitation = [
        'code' => $invitation_code,
        'invitado_nombre' => $guest_name,
        'anfitrion' => $host_user_data, // Guardamos los datos del anfitrión
        'fecha_creacion' => $creation_timestamp,
        'fecha_expiracion' => $expiration_timestamp,
        'status' => 'pendiente', // Estado inicial
        'fecha_aprobacion' => 0, // Timestamp de aprobación (0 si no ha sido aprobada)
    ];

    // Cargar invitaciones existentes, añadir la nueva y guardar
    $invitations = getInvitations();
    $invitations[] = $new_invitation;

    if (!saveInvitations($invitations)) {
        error_log("createInvitation fallo: No se pudo guardar la invitación en invitations.json.");
        return null;
    }

    // --- === Generar Código QR === ---
    // El contenido del QR ahora será SOLAMENTE el código de invitación
    $qr_content = $invitation_code; // <-- ¡CORRECCIÓN CLAVE AQUÍ!

    $qr_filepath = $qr_codes_dir . $invitation_code . '.png'; // Ruta donde se guardará la imagen QR

    // Asegurarse de que el directorio de QRs exista
    if (!is_dir($qr_codes_dir)) {
        if (!mkdir($qr_codes_dir, 0775, true)) {
            error_log("createInvitation fallo: Error creando directorio de QRs: " . $qr_codes_dir);
             // Decidir si fallar aquí o continuar sin generar QR. Si el QR es obligatorio, retorna null.
             return $new_invitation; // Opcional: continuar si el QR no es estrictamente necesario
        }
         error_log("DEBUG: Directorio de QRs creado: " . $qr_codes_dir);
    }

    // Generar la imagen QR
    try {
        \QRcode::png($qr_content, $qr_filepath, 'H', 10, 2, false);
        error_log("DEBUG: QR code guardado exitosamente en: " . $qr_filepath);
    } catch (\Exception $e) {
        error_log("createInvitation fallo: Error generando o guardando código QR para código " . $invitation_code . ": " . $e->getMessage());
         // Decidir si fallar aquí o continuar sin generar QR.
         return $new_invitation; // Opcional: continuar si el QR no es estrictamente necesario
    }

    return $new_invitation; // Retorna los datos de la invitación recién creada
}


/**
 * Busca una invitación por su código único.
 * @param string $code El código de invitación a buscar.
 * @return array|null El array de la invitación si se encuentra, null en caso contrario.
 */
function getInvitationByCode(string $code): ?array {
    $invitations = getInvitations(); // Obtiene todas las invitaciones
    foreach ($invitations as $invitation) {
        if (isset($invitation['code']) && is_string($invitation['code']) && $invitation['code'] === $code) {
            return $invitation;
        }
    }
    return null;
}

/**
 * Actualiza un array existente de invitación por su código.
 * Mezcla los datos existentes con los datos proporcionados en $updated_data.
 * @param string $code El código de la invitación a actualizar.
 * @param array $updated_data Un array asociativo de datos a actualizar (ej., ['status' => 'aprobado', 'fecha_aprobacion' => time()]).
 * @return array|null El array de la invitación actualizada en caso de éxito, null en caso de fallo o si no se encontró la invitación.
 */
function updateInvitation(string $code, array $updated_data): ?array {
    $invitations = getInvitations();
    $updated_invitation = null;
    $found_key = null;

    foreach ($invitations as $key => $invitation) {
        if (isset($invitation['code']) && is_string($invitation['code']) && $invitation['code'] === $code) {
            $found_key = $key;
            $updated_invitation = array_merge($invitation, $updated_data);
            $invitations[$found_key] = $updated_invitation;
            break;
        }
    }

    if ($found_key !== null && saveInvitations($invitations)) {
        return $updated_invitation;
    }
    return null;
}

/**
 * Marca una invitación como 'aprobado' y establece la fecha de aprobación.
 * Verifica que la invitación exista y su estado sea 'pendiente' antes de aprobar.
 * @param string $code El código de invitación a aprobar.
 * @return array|null El array de la invitación actualizada ('aprobado') en caso de éxito, null en caso de fallo o si no se pudo aprobar (ej. no pendiente).
 */
function approveInvitationByCode(string $code): ?array {
    $invitation = getInvitationByCode($code);
    // Solo aprobar si existe y está pendiente
    if ($invitation && isset($invitation['status']) && $invitation['status'] === 'pendiente') {
        $updated_data = ['status' => 'aprobado', 'fecha_aprobacion' => time()];
        return updateInvitation($code, $updated_data);
    }
    return null; // No se encontró o no estaba pendiente
}

/**
 * Marca una invitación como 'cancelado'.
 * @param string $code El código de invitación a cancelar.
 * @return array|null El array de la invitación actualizada ('cancelado') en caso de éxito, null en caso de fallo o si no se pudo cancelar.
 */
function cancelInvitationByCode(string $code): ?array {
    $updated_data = ['status' => 'cancelado'];
    return updateInvitation($code, $updated_data);
}

/**
 * Obtiene todas las invitaciones creadas por un anfitrión específico.
 * @param int|string $host_user_id El ID del usuario anfitrión.
 * @return array Un array de invitaciones de ese anfitrión.
 */
function getInvitationsByHostId($host_user_id): array {
    $all_invitations = getInvitations();
    $host_invitations = [];
    foreach ($all_invitations as $invitation) {
        // Asegurarse de que la clave 'anfitrion' y 'id' existen
        if (isset($invitation['anfitrion']['id']) && $invitation['anfitrion']['id'] === $host_user_id) {
            $host_invitations[] = $invitation;
        }
    }
    return $host_invitations;
}

/**
 * Elimina una invitación del archivo JSON por su código único.
 * NO elimina el archivo QR asociado automáticamente.
 * @param string $code El código único de la invitación a eliminar.
 * @return bool True si se eliminó correctamente, false si no se encontró o falló al guardar.
 */
function deleteInvitationByCode(string $code): bool {
    if (empty($code)) {
        error_log("deleteInvitationByCode failed: Code is empty.");
        return false;
    }

    $invitations = getInvitations();
    $initial_count = count($invitations);
    $invitation_found = false;

    // Filtrar el array para excluir la invitación con el código dado
    $updated_invitations = array_filter($invitations, function($inv) use ($code, &$invitation_found) {
        if (isset($inv['code']) && $inv['code'] === $code) {
            $invitation_found = true;
            return false; // Excluir este elemento
        }
        return true; // Incluir este elemento
    });

    // Si no se encontró la invitación, retornar false
    if (!$invitation_found) {
        error_log("deleteInvitationByCode failed: Invitation with code " . $code . " not found in data.");
        return false;
    }

    // Si el número de elementos se redujo en 1 (la invitación fue encontrada y filtrada)
    if (count($updated_invitations) === $initial_count - 1) {
        $save_success = saveInvitations($updated_invitations);
        if (!$save_success) {
            error_log("deleteInvitationByCode failed: Error saving invitations after deleting code " . $code);
        }
        return $save_success;
    } else {
        // Esto no debería pasar si invitation_found es true, pero es un chequeo de seguridad
        error_log("deleteInvitationByCode failed: Unexpected count after filtering for code " . $code);
        return false;
    }
}


// --- === Bloque para Manejar Solicitudes POST (Ej: Solicitudes AJAX) === ---
// Este bloque se ejecuta SOLAMENTE cuando este script (invitation_handler.php)
// recibe directamente una petición por método POST.
// Está diseñado para actuar como un endpoint para peticiones del lado del cliente.
if (basename(__FILE__) === 'invitation_handler.php' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Establecer encabezados para indicar que la respuesta será JSON.
    header('Content-Type: application/json');

    // Iniciar la sesión y obtener datos del usuario logueado, ya que los permisos dependen de él.
    start_session_if_not_started();
    $current_user = gets_current_user(); // Obtiene los datos del usuario logueado
    $user_role = $current_user['role'] ?? 'guest';
    $user_id = $current_user['id'] ?? null;
    $user_lote = $current_user['lote'] ?? null;


    // Obtener la acción solicitada y los datos necesarios desde la solicitud POST.
    $action = $_POST['action'] ?? ''; // Esperamos un parámetro 'action' (ej: 'delete', 'verify')
    $code = $_POST['code'] ?? ''; // Esperamos un parámetro 'code'

    // Inicializar un array para la respuesta que enviaremos en formato JSON.
    $response = ['success' => false, 'message' => 'Acción no reconocida.'];

    // --- === Manejar la Acción de Eliminación === ---
    if ($action === 'delete') {
        // Validar que se haya proporcionado un código.
        if (empty($code)) {
            $response['message'] = 'Error: Código de invitación no proporcionado para eliminar.';
        } else {
            // --- === Verificación de Permisos para Eliminar === ---
            $can_delete = false;

            // Roles que tienen permiso global para eliminar (Admin, Developer)
            global $delete_allowed_roles_global; // Acceder a la global
            $delete_allowed_roles_global = $delete_allowed_roles_global ?? ['administrador', 'developer'];

            // 1. Verificar si el rol del usuario actual tiene permiso global para eliminar
            if (is_logged_in() && in_array($user_role, $delete_allowed_roles_global)) {
                $can_delete = true;
            } else {
                 // 2. Si no tiene permiso global, verificar si es el ANFITRIÓN creador de ESTA invitación
                 if (!is_logged_in()) {
                     $response['message'] = 'Error: Debes iniciar sesión para eliminar invitaciones.';
                 } else {
                     $invitation_to_delete = getInvitationByCode($code); // Obtiene la invitación

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
                $delete_success = deleteInvitationByCode($code); // Llama a la función de eliminación

                if ($delete_success) {
                    // Si la eliminación del JSON fue exitosa, también intentamos eliminar el archivo QR.
                    global $qr_codes_dir; // Accede a la variable global
                    $qr_filepath = $qr_codes_dir . urlencode($code) . '.png'; // Construye la ruta del archivo QR

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
                    // Si falló deleteInvitationByCode
                     if (!isset($response['message']) || ($response['message'] === 'Acción no reconocida.')) {
                         $response['message'] = 'Error interno al procesar la eliminación de la invitación.';
                     }
                }
            } // else for $can_delete (message already set inside permission checks)
        } // else for empty($code)

    } // Fin del if ($action === 'delete')


    // --- === Manejar la Acción de Verificación (para el escáner) === ---
    if ($action === 'verify') {
        // Validar que se haya proporcionado un código.
        if (empty($code)) {
            $response['message'] = 'Error: Código de invitación no proporcionado para verificar.';
        } else {
            // --- === Verificación de Permisos para Verificar === ---
            // Define qué roles pueden usar el escáner para verificar.
            // Puedes definir $verify_allowed_roles en config.php si prefieres.
            $verify_allowed_roles = $verify_allowed_roles ?? ['seguridad', 'administrador', 'developer'];

            // Verifica si el usuario logueado tiene permiso para verificar.
            if (!is_logged_in() || !in_array($user_role, $verify_allowed_roles)) {
                 $response['message'] = 'Error: No tienes permiso para verificar invitaciones.';
            } else {
                // --- === Lógica de Verificación de Invitación === ---
                $invitation = getInvitationByCode($code); // Obtiene la invitación por código

                if ($invitation) {
                    // Invitación encontrada, ahora verificar estado y expiración.
                    $current_time = time();
                    $is_expired = isset($invitation['fecha_expiracion']) && $invitation['fecha_expiracion'] > 0 && $invitation['fecha_expiracion'] < $current_time;
                    $status = $invitation['status'] ?? 'desconocido';

                    // Prepara los datos de la invitación para la respuesta
                    $invitation_details = [
                        'code' => $invitation['code'] ?? 'N/A',
                        'invitado_nombre' => $invitation['invitado_nombre'] ?? 'N/A',
                        'anfitrion_name' => $invitation['anfitrion']['name'] ?? 'N/A',
                        'anfitrion_lote' => $invitation['anfitrion']['lote'] ?? 'N/A',
                        'fecha_creacion' => isset($invitation['fecha_creacion']) ? date('d/m/Y H:i', $invitation['fecha_creacion']) : 'N/A',
                        'fecha_expiracion' => isset($invitation['fecha_expiracion']) ? date('d/m/Y H:i', $invitation['fecha_expiracion']) : 'N/A',
                        'status' => $status,
                        'fecha_aprobacion' => isset($invitation['fecha_aprobacion']) && $invitation['fecha_aprobacion'] > 0 ? date('d/m/Y H:i', $invitation['fecha_aprobacion']) : 'Pendiente',
                    ];

                    if ($is_expired) {
                        $response = [
                            'success' => false, // O true, dependiendo de si quieres reportar expirada como éxito de verificación
                            'message' => 'Invitación expirada.',
                            'status' => 'expirado',
                            'details' => $invitation_details
                        ];
                    } elseif ($status === 'aprobado') {
                         $response = [
                            'success' => true,
                            'message' => 'Invitación válida y previamente aprobada.',
                            'status' => 'aprobado',
                            'details' => $invitation_details
                        ];
                    } elseif ($status === 'pendiente') {
                        // Si está pendiente y no expirada, es válida para ser aprobada ahora.
                        // Aquí podrías cambiar el estado a 'aprobado' si la lógica lo requiere
                        // approveInvitationByCode($code); // <-- Descomentar si quieres aprobación automática al escanear
                        $response = [
                            'success' => true,
                            'message' => 'Invitación encontrada y válida. Pendiente de aprobación.',
                            'status' => 'pendiente',
                            'details' => $invitation_details
                        ];
                    } elseif ($status === 'cancelado') {
                         $response = [
                            'success' => false,
                            'message' => 'Esta invitación ha sido cancelada.',
                            'status' => 'cancelado',
                            'details' => $invitation_details
                        ];
                    }
                    else {
                         // Otro estado no reconocido
                         $response = [
                            'success' => false,
                            'message' => 'Invitación encontrada pero con estado desconocido.',
                            'status' => 'desconocido',
                            'details' => $invitation_details
                        ];
                    }

                } else {
                    // Invitación no encontrada con ese código
                    $response = [
                        'success' => false,
                        'message' => 'Código de invitación no encontrado.',
                        'status' => 'no_encontrado',
                        'details' => null
                    ];
                }
            } // else for permission check

        } // else for empty($code)
    } // Fin del if ($action === 'verify')


    // --- === Enviar la Respuesta JSON y Salir === ---
    // Si el action no fue 'delete' ni 'verify', la respuesta sigue siendo la inicial 'Acción no reconocida.'
    echo json_encode($response);
    exit; // Termina la ejecución del script.
}

// --- === End Handle AJAX POST Requests === ---

// Las funciones definidas antes de este bloque SÍ están disponibles para otros scripts que incluyan este archivo.

?>
