<?php
// htdocs/listqr.php - Página para listar las invitaciones creadas

// --- === Configurar Zona Horaria === ---
date_default_timezone_set('America/Argentina/Buenos_Aires'); // Asegúrate de que la zona horaria sea correcta

// --- === Includes y Autenticación === ---
// Asegúrate que estos archivos existan en la carpeta 'includes'.
require_once __DIR__ . '/includes/auth.php'; // Contiene funciones de autenticación y usuario.
require_once __DIR__ . '/includes/invitation_handler.php'; // Contiene funciones para manejar invitaciones (getInvitations, etc.).
require_once __DIR__ . '/includes/config.php'; // Contiene la configuración (URL_BASE, roles permitidos).

// Inicia la sesión si no está iniciada.
start_session_if_not_started();

// Obtiene los datos del usuario logueado.
$current_user = gets_current_user();

// Extrae el rol del usuario logueado.
$user_role = $current_user['role'] ?? 'guest';
// También necesitamos el ID y el lote del usuario logueado para la lógica de filtrado y permisos.
$user_id = $current_user['id'] ?? null;
$user_lote = $current_user['lote'] ?? null;


// Roles permitidos para ver este listado (usado para la autorización de la página misma y enlaces en otros scripts).
// Obtiene el valor de $list_allowed_roles de config.php si existe, de lo contrario usa el array por defecto.
global $list_allowed_roles; // Acceder a la variable global si está definida en config.php
$list_allowed_roles = $list_allowed_roles ?? ['anfitrion', 'seguridad', 'administrador', 'developer'];

// Roles permitidos para crear invitaciones (usado para mostrar el enlace "Crear Invitación").
global $invite_allowed_roles; // Acceder a la variable global si está definida en config.php
$invite_allowed_roles = $invite_allowed_roles ?? ['anfitrion', 'administrador', 'developer'];

// Roles permitidos para escanear QRs (usado para mostrar el enlace "Escanear QR").
global $scan_allowed_roles; // Acceder a la variable global si está definida en config.php
$scan_allowed_roles = $scan_allowed_roles ?? ['seguridad', 'administrador', 'developer'];


// --- === Lógica de Autorización de Página === ---
// Verifica si el usuario NO está logueado O si su rol NO está en la lista de roles permitidos para ver el listado.
if (!is_logged_in() || !in_array($user_role, $list_allowed_roles)) {
    // Si no tiene permiso, redirige a otra página.
    // Asegúrate que la ruta 'index.php' sea correcta.
    redirect('index.php');
    exit(); // Importante: Termina el script después de redirigir.
}


$all_invitations = getInvitations(); // Asumo que getInvitations() sin parámetros trae todas.

// --- Lógica de Filtrado por Rol del Usuario ---
$filtered_invitations = []; // Inicializa el array filtrado

if ($user_role === 'anfitrion') {
    // Si el usuario es un anfitrión, filtrar para mostrar solo las invitaciones cuyo lote coincida con el suyo.
    if ($user_lote !== null) {
        $filtered_invitations = array_filter($all_invitations, function($inv) use ($user_lote) {
            // Verifica si la invitación tiene datos de anfitrión y si el lote coincide.
            return isset($inv['anfitrion']['lote']) && $inv['anfitrion']['lote'] === $user_lote;
        });
    } else {
        // Si el anfitrión logueado no tiene lote definido, no mostrar ninguna invitación.
        // $filtered_invitations ya es [], no necesita hacer nada más.
        error_log("DEBUG_LISTQR: Anfitrión logueado sin lote definido (\$current_user['lote'] es null). Mostrando lista vacía.");
    }
} else {
    // Si el usuario no es un anfitrión (seguridad, administrador, developer, etc.),
    // mostrar todas las invitaciones obtenidas.
    $filtered_invitations = $all_invitations;
}
// --- Fin Lógica de Filtrado por Rol ---


// --- Variables para mostrar en el footer ---
$display_username = htmlspecialchars($current_user['username'] ?? 'Invitado');
$display_user_role = htmlspecialchars($user_role);


?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invitaciones</title>
    <link rel="stylesheet" href="css/styles.css">
    <style>
        /* Estilos para el estado de la invitación */
        .status-pendiente { color: orange; font-weight: bold; }
        .status-aprobado { color: green; font-weight: bold; }
        .status-expirado { color: gray; font-weight: bold; }
        .status-cancelado { color: red; font-weight: bold; }
        .status-desconocido { color: purple; font-weight: bold; } /* Estado por defecto */

        /* Estilos para las filas de detalles expandidos */
        .invitation-details-row td {
            padding: 10px;
            background-color: #f9f9f9; /* Fondo ligeramente diferente para la fila de detalles */
            border-bottom: 1px solid #ddd;
        }
        .invitation-details-expanded {
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #eee; /* Separador visual */
        }
        .detail-item {
            margin-bottom: 8px; /* Espacio entre cada item de detalle */
        }
        .detail-label {
            font-weight: bold;
            display: inline-block; /* Permite controlar el ancho */
            width: 120px; /* Ancho fijo para alinear las etiquetas (ajusta según necesidad) */
            margin-right: 10px;
            text-align: right; /* Alinea el texto de la etiqueta a la derecha */
        }
        .detail-value {
            display: inline-block; /* Permite que el valor siga a la etiqueta */
            word-break: break-all; /* Evita que códigos largos desborden el contenedor */
        }

        /* Estilos para el grupo de botones expandido */
        .button-group-expanded {
             margin-top: 15px;
             padding-top: 10px;
             border-top: 1px solid #eee;
             text-align: center; /* Centra los botones */
             display: flex; /* Usar flexbox para alinear botones */
             justify-content: center; /* Centra los botones horizontalmente */
             gap: 10px; /* Espacio entre botones */
             flex-wrap: wrap; /* Permite que los botones se envuelvan en pantallas pequeñas */
        }
         /* Estilo base para los botones (usando .btn de styles.css si existe) */
         .button-group-expanded .btn {
             margin: 0; /* Anula márgenes si el contenedor flex usa gap */
         }

         /* Estilo específico para el botón Copiar */
         .copy-link-btn {
             background-color: #17a2b8; /* Info color */
             border-color: #17a2b8;
             color: white;
         }
         .copy-link-btn:hover {
             background-color: #138496;
             border-color: #117a8b;
         }

          /* Estilo específico para el botón Eliminar */
          .delete-invite-btn {
              background-color: #dc3545; /* Danger color */
              border-color: #dc3545;
              color: white;
          }
          .delete-invite-btn:hover {
              background-color: #c82333;
              border-color: #bd2130;
          }

          /* Estilo específico para el botón Cancelar */
          .cancel-invite-btn {
              background-color: #ffc107; /* Warning color */
              border-color: #ffc107;
              color: #212529; /* Texto oscuro */
          }
          .cancel-invite-btn:hover {
              background-color: #e0a800;
              border-color: #d39e00;
          }

           /* Estilo específico para el botón Reactivar */
           .reactivate-invite-btn {
               background-color: #28a745; /* Success color */
               border-color: #28a745;
               color: white;
           }
           .reactivate-invite-btn:hover {
               background-color: #218838;
               border-color: #1e7e34;
           }


        /* Estilos para la modal */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7); /* Fondo oscuro semi-transparente */
            display: flex; /* Usar flexbox para centrar el contenido */
            justify-content: center; /* Centrar horizontalmente */
            align-items: center; /* Centrar verticalmente */
            z-index: 1000; /* Asegura que esté por encima de otros elementos */
            /* display: none; /* Inicialmente oculto, se muestra con JavaScript */
        }

        .modal-content {
            background-color: #fff; /* Fondo blanco para el contenido */
            padding: 20px; /* Espacio interno */
            border-radius: 8px; /* Bordes redondeados */
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.3); /* Sombra */
            max-width: 90%; /* Ancho máximo del contenido */
            max-height: 90%; /* Altura máxima del contenido */
            overflow: auto; /* Añade scroll si el contenido es más grande que el modal */
            text-align: center; /* Centra el contenido como la imagen QR */
        }

        .modal-content img {
            max-width: 100%; /* Asegura que la imagen no se desborde del contenedor */
            height: auto; /* Mantiene la proporción */
            display: block; /* Elimina espacio extra debajo de la imagen */
            margin: 0 auto; /* Centra la imagen dentro del modal-content */
            cursor: pointer; /* Cambia el cursor para indicar que es clickeable */
        }

    </style>
</head>
<body>
    <div class="header">
        <h2>Invitaciones</h2>
        <a href="logout.php" class="logout-btn">Salir</a>
        <a href="index.php" class="back-btn">Home</a>
    </div>

    <div class="container">
        <h3>Invitaciones</h3>
        <?php if (empty($filtered_invitations)): ?>
            <p>No hay invitaciones para mostrar con los criterios actuales.</p>
        <?php else: ?>
            <table>
            <thead>
                    <tr>
                        <th>Invitado</th>
                        <th>Lote</th>
                        <th>Estado</th>
                        </tr>
                </thead>
                <tbody>
                    <?php
                    // Recorre cada invitación en el array filtrado
                    foreach ($filtered_invitations as $invitation):
                        // Extrae y prepara todos los datos de la invitación
                        $code = $invitation['code'] ?? 'N/A';
                        $guest_name = $invitation['invitado_nombre'] ?? 'N/A';
                        $anfitrion_name = $invitation['anfitrion']['name'] ?? 'N/A';
                        $anfitrion_lote = $invitation['anfitrion']['lote'] ?? 'N/A';
                        // Formatea timestamps a fechas legibles.
                        $fecha_creacion = isset($invitation['fecha_creacion']) && $invitation['fecha_creacion'] > 0 ? date('d/m/Y H:i', $invitation['fecha_creacion']) : 'N/A';
                        $fecha_expiracion = isset($invitation['fecha_expiracion']) && $invitation['fecha_expiracion'] > 0 ? date('d/m/Y H:i', $invitation['fecha_expiracion']) : 'N/A';
                        $status = $invitation['status'] ?? 'desconocido';
                        $fecha_aprobacion = isset($invitation['fecha_aprobacion']) && $invitation['fecha_aprobacion'] > 0 ? date('d/m/Y H:i', $invitation['fecha_aprobacion']) : 'Pendiente';

                        // --- Construye la URL al archivo de imagen QR (para el botón "QR") ---
                        // Asegúrate que $qr_codes_dir esté definido en config.php y sea accesible vía web.
                        // Si URL_BASE está definida en config.php, la usamos.
                        if (defined('URL_BASE') && $code !== 'N/A') {
                            $qr_image_web_url = URL_BASE . '/qr/' . urlencode($code) . '.png';
                        } else {
                            // Fallback si URL_BASE no está definida o el código es N/A
                            $qr_image_web_url = '#';
                             if (!defined('URL_BASE')) {
                                 error_log("URL_BASE no definida en config.php al construir URL de imagen QR en listqr.php.");
                             }
                        }

                        // --- Construye la URL COMPLETA (absoluta) al LANDING PAGE (/qr/CODE) ---
                        // Esta es la URL que el invitado usaría.
                        if (defined('URL_BASE') && $code !== 'N/A') {
                             $landing_page_url = URL_BASE . "/qr/" . urlencode($code);
                        } else {
                             $landing_page_url = '#';
                             if (!defined('URL_BASE')) {
                                 error_log("URL_BASE no definida en config.php al construir landing page URL en listqr.php.");
                             }
                        }

                         // --- Lógica de Permisos para botones de acción (Eliminar, Cancelar, Reactivar) ---
                         // Roles que pueden eliminar globalmente (además del propio anfitrion)
                         global $delete_allowed_roles_global;
                         // Asegurarse de que $delete_allowed_roles_global esté definido, si no, usar un array vacío.
                         $delete_allowed_roles_global = $delete_allowed_roles_global ?? [];

                         $can_delete_globally = in_array($user_role, $delete_allowed_roles_global);

                         // Verificar si el usuario logueado es el anfitrión de esta invitación
                         $is_host_of_invite = ($user_id && isset($invitation['anfitrion']['id']) && $invitation['anfitrion']['id'] === $user_id);

                         // Determinar si el usuario actual puede eliminar esta invitación
                         // Puede eliminar si tiene permiso global O es el anfitrión Y el estado es 'pendiente'.
                         $can_delete = ($can_delete_globally || $is_host_of_invite) && ($status === 'pendiente');


                         // Determinar si el usuario actual puede cancelar esta invitación
                         // Puede cancelar si tiene permiso global O es el anfitrión Y el estado es 'pendiente' o 'aprobado'.
                         // Usamos $delete_allowed_roles_global para cancelar también por ahora, ajusta si necesitas otro array.
                         $can_cancel = ($can_delete_globally || $is_host_of_invite) && in_array($status, ['pendiente', 'aprobado']);

                         // Determinar si el usuario actual puede reactivar esta invitación
                         // Puede reactivar si tiene permiso global O es el anfitrion Y el estado es 'cancelado'.
                         // Usamos $delete_allowed_roles_global para reactivar también por ahora.
                         $can_reactivate = ($can_delete_globally || $is_host_of_invite) && ($status === 'cancelado');


                    ?>
                        <tr class="invitation-summary-row" data-code="<?php echo htmlspecialchars($code); ?>">
                            <td><?php echo htmlspecialchars($guest_name); ?></td>
                            <td><?php echo htmlspecialchars($anfitrion_lote); ?></td>
                            <td class="status-<?php echo htmlspecialchars(strtolower($status)); ?>"><?php echo htmlspecialchars(ucfirst($status)); ?></td>
                            <td colspan="7"></td>
                        </tr>

                        <tr class="invitation-details-row" data-code="<?php echo htmlspecialchars($code); ?>" style="display: none;">
                            <td colspan="9">
                                <div class="invitation-details-expanded">

                                <div class="button-group-expanded">
                                         <button class="btn show-qr-modal-btn" data-qr-url="<?php echo htmlspecialchars($qr_image_web_url); ?>">QR</button>
                                         <button class="btn copy-link-btn" data-link="<?php echo htmlspecialchars($landing_page_url); ?>">Copiar</button>
                                        <?php if ($can_delete): ?>
                                            <form method="POST" action="includes/delete_invitation.php" style="display: inline-block;">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="code" value="<?php echo htmlspecialchars($code); ?>">
                                                <button type="submit" class="btn delete-invite-btn" onclick="return confirm('¿Estás seguro de que deseas eliminar esta invitación?');">Borrar</button>
                                            </form>
                                        <?php endif; ?>

                                        <?php if ($can_cancel): ?>
                                            <form method="POST" action="includes/invitation_handler.php" style="display: inline-block;">
                                                <input type="hidden" name="action" value="cancel">
                                                <input type="hidden" name="code" value="<?php echo htmlspecialchars($code); ?>">
                                                <button type="submit" class="btn cancel-invite-btn" onclick="return confirm('¿Estás seguro de que deseas cancelar esta invitación?');">Cancelar</button>
                                            </form>
                                        <?php elseif ($can_reactivate): // Mostrar Reactivar si no se puede cancelar y se puede reactivar ?>
                                             <form method="POST" action="includes/invitation_handler.php" style="display: inline-block;">
                                                <input type="hidden" name="action" value="reactivate">
                                                <input type="hidden" name="code" value="<?php echo htmlspecialchars($code); ?>">
                                                <button type="submit" class="btn reactivate-invite-btn" onclick="return confirm('¿Estás seguro de que deseas reactivar esta invitación?');">Reactivar</button>
                                            </form>
                                        <?php endif; ?>

                                    </div>
                                    <br>
                                    <div class="detail-item"><span class="detail-label">Invitado:</span> <span class="detail-value"><?php echo htmlspecialchars($guest_name); ?></span></div>
                                    <div class="detail-item"><span class="detail-label">Anfitrión:</span> <span class="detail-value"><?php echo htmlspecialchars($anfitrion_name); ?> (Lote: <?php echo htmlspecialchars($anfitrion_lote); ?>)</span></div>
                                    <div class="detail-item"><span class="detail-label">Creación:</span> <span class="detail-value"><?php echo htmlspecialchars($fecha_creacion); ?></span></div>
                                    <div class="detail-item"><span class="detail-label">Expira:</span> <span class="detail-value"><?php echo htmlspecialchars($fecha_expiracion); ?></span></div>
                                     <div class="detail-item"><span class="detail-label">Estado:</span> <span class="detail-value"><span class="status-<?php echo htmlspecialchars(strtolower($status)); ?>"><?php echo htmlspecialchars(ucfirst($status)); ?></span></span></div>
                                    <div class="detail-item"><span class="detail-label">Validación:</span> <span class="detail-value"><?php echo htmlspecialchars($fecha_aprobacion); ?></span></div>
                                     <div class="detail-item"><span class="detail-label">URL Invitado:</span> <span class="detail-value"><a href="<?php echo htmlspecialchars($landing_page_url); ?>" target="_blank">ENLACE QR</a></span></div>



                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>

    </div> <div class="footer">
        <span>Usuario actual: <?php echo $display_username; ?> (Rol: <?php echo $display_user_role; ?>)</span>
    </div>

    <div id="qrModalOverlay" class="modal-overlay" style="display: none;">
        <div id="qrModalContent" class="modal-content">
            <img id="qrModalImage" src="" alt="Código QR de Invitación">
        </div>
    </div>
    <script>
        // Espera a que el DOM (Document Object Model) esté completamente cargado y parseado.
        document.addEventListener('DOMContentLoaded', function() {
            // --- === Lógica para expandir/colapsar filas de detalles en la tabla === ---
            const summaryRows = document.querySelectorAll('.invitation-summary-row');

            summaryRows.forEach(row => {
                row.addEventListener('click', function() {
                    // Encuentra la fila de detalles asociada (la siguiente hermana)
                    const detailsRow = this.nextElementSibling;

                    // Verifica si es realmente una fila de detalles y la alterna
                    if (detailsRow && detailsRow.classList.contains('invitation-details-row')) {
                        detailsRow.style.display = detailsRow.style.display === 'none' || detailsRow.style.display === '' ? 'table-row' : 'none';
                    }
                });
            });

            // --- === Lógica para el botón Copiar Enlace === ---
            const copyButtons = document.querySelectorAll('.copy-link-btn');
            copyButtons.forEach(button => {
                button.addEventListener('click', function(event) {
                    event.stopPropagation(); // Evita que el clic en el botón dispare la expansión/colapso de la fila
                    const linkToCopy = this.getAttribute('data-link');
                    if (linkToCopy) {
                        navigator.clipboard.writeText(linkToCopy).then(function() {
                            // Opcional: Mostrar un mensaje de confirmación al usuario
                            alert('Enlace copiado al portapapeles: ' + linkToCopy);
                        }).catch(function(err) {
                            console.error('Error al copiar el enlace: ', err);
                            alert('Error al copiar el enlace.');
                        });
                    }
                });
            });


            // --- === Lógica para la Modal del QR === ---
            // Obtenemos referencias a los elementos del modal por sus IDs
            const qrModalOverlay = document.getElementById('qrModalOverlay'); // El fondo oscuro (overlay)
            const qrModalContent = document.getElementById('qrModalContent'); // El contenedor del contenido (la caja blanca)
            const qrModalImage = document.getElementById('qrModalImage'); // La etiqueta <img> donde se mostrará el QR

            // Seleccionamos todos los botones con la clase 'show-qr-modal-btn'
            const showQrModalButtons = document.querySelectorAll('.show-qr-modal-btn');


            // === Lógica para ABRIR el Modal al hacer clic en el botón "QR" ===
            // Iteramos sobre cada botón "QR" encontrado y le añadimos un escuchador de eventos
            showQrModalButtons.forEach(button => {
                button.addEventListener('click', function(event) {
                    event.stopPropagation(); // Evita que el clic en el botón también colapse/expanda la fila de la tabla

                    // Obtenemos la URL de la imagen del código QR desde el atributo 'data-qr-url' del botón clickeado
                    const qrImageUrl = this.getAttribute('data-qr-url');

                    // Verificamos si obtuvimos una URL válida
                    if (qrImageUrl && qrImageUrl !== '#') {
                        // Establecemos la URL de la imagen obtenida como el 'src' de la etiqueta <img> dentro del modal
                        qrModalImage.src = qrImageUrl;

                        // Mostramos el modal cambiando el estilo 'display' del overlay a 'flex'.
                        // Asumimos que las reglas CSS para '.modal-overlay' usan 'display: flex'
                        // para centrar el contenido (modal-content).
                        qrModalOverlay.style.display = 'flex';

                        // Opcional: Añadir una clase al body para prevenir el scroll de la página principal mientras la modal está abierta.
                        // document.body.classList.add('modal-open'); // Requiere CSS para 'body.modal-open { overflow: hidden; }'

                    } else {
                        // Si no hay URL válida en el atributo data-qr-url
                        console.error('DEBUG_LISTQR_JS: No se encontró URL de imagen QR válida en el atributo data-qr-url.');
                        alert('Error: No se pudo obtener la imagen del QR.');
                    }
                });
            });

            // === Lógica para CERRAR el Modal al hacer clic en la IMAGEN del QR ===
            // Añadimos un escuchador de eventos para el clic en la imagen dentro del modal.
            qrModalImage.addEventListener('click', function() {
                // Ocultamos el modal cambiando el estilo 'display' del overlay a 'none'.
                qrModalOverlay.style.display = 'none';

                // Limpiamos la fuente de la imagen al cerrar la modal.
                // Esto es una buena práctica para liberar recursos y evitar mostrar la imagen anterior brevemente si se abre otra modal.
                qrModalImage.src = '';

                // Opcional: Remover la clase del body si la añadimos al abrir.
                // document.body.classList.remove('modal-open');
            });

            // Opcional: Cerrar la modal al hacer clic en el overlay oscuro (fuera del contenido del modal)
             qrModalOverlay.addEventListener('click', function(event) {
                 // Verificamos si el elemento donde se hizo clic es exactamente el overlay
                 // (y no el contenido del modal o la imagen dentro de él).
                 if (event.target === qrModalOverlay) {
                     // Si el clic fue en el overlay, cerramos la modal.
                     qrModalOverlay.style.display = 'none';
                     qrModalImage.src = ''; // Limpiamos la imagen
                     // document.body.classList.remove('modal-open');
                 }
             });

            // Opcional: Cerrar la modal al presionar la tecla ESC
            document.addEventListener('keydown', function(event) {
                // Verificamos si la tecla presionada es 'Escape' Y si el modal está visible.
                if (event.key === 'Escape' && qrModalOverlay.style.display === 'flex') {
                    // Si se presionó Escape y el modal está visible, lo cerramos.
                    qrModalOverlay.style.display = 'none';
                    qrModalImage.src = ''; // Limpiamos la fuente de la imagen
                    // document.body.classList.remove('modal-open');
                }
            });

             // --- === Lógica para los botones Eliminar, Cancelar y Reactivar (usando AJAX) === ---
             // Seleccionamos todos los botones de acción relevantes.
             document.querySelectorAll('.delete-invite-btn, .cancel-invite-btn, .reactivate-invite-btn').forEach(button => {
                button.addEventListener('click', function(event) {
                    event.preventDefault(); // Prevenir el envío del formulario por defecto

                    // Encuentra el formulario más cercano al botón clickeado
                    const form = button.closest('form');
                    if (!form) {
                        console.error('DEBUG_LISTQR_JS: Botón de acción no encontrado dentro de un formulario.');
                        return;
                    }

                    const invitationCode = form.querySelector('input[name="code"]').value; // Obtiene el código del input hidden dentro del formulario
                    const action = form.querySelector('input[name="action"]').value; // Obtiene la acción del input hidden

                    // Determinar el mensaje de confirmación basado en la acción
                    let confirmMessage;
                    switch(action) {
                        case 'delete':
                            confirmMessage = '¿Estás seguro de que deseas eliminar esta invitación? Esta acción no se puede deshacer.';
                            break;
                        case 'cancel':
                            confirmMessage = '¿Estás seguro de que deseas cancelar esta invitación?';
                            break;
                        case 'reactivate':
                            confirmMessage = '¿Estás seguro de que deseas reactivar esta invitación?';
                            break;
                        default:
                            console.error('DEBUG_LISTQR_JS: Acción desconocida en el formulario:', action);
                            return; // Salir si la acción es desconocida
                    }


                    const confirmAction = confirm(confirmMessage);

                    if (confirmAction) {
                        console.log(`DEBUG_LISTQR_JS: Usuario confirmó acción '${action}' para código:`, invitationCode);

                        // La URL del script PHP que procesará la acción.
                        // Para Eliminar, apuntamos a delete_invitation.php
                        // Para Cancelar/Reactivar, apuntamos a invitation_handler.php
                        const handlerUrl = (action === 'delete') ? form.action : 'includes/delete_invitation.php'; // Usa la acción del formulario si es delete, sino invitation_handler.php

                        // Preparar los datos a enviar en la solicitud POST.
                        const formData = new FormData();
                        formData.append('action', action); // Usar la acción determinada
                        formData.append('code', invitationCode);

                        // Enviar la solicitud Fetch.
                        fetch(handlerUrl, {
                            method: 'POST', // Método HTTP
                            body: formData // Datos a enviar
                        })
                        .then(response => {
                            // Se ejecuta cuando se recibe una respuesta.
                            if (!response.ok) {
                                throw new Error('La respuesta de la red no fue OK. Estado: ' + response.status + ' ' + response.statusText);
                            }
                            // Parsea la respuesta como JSON.
                            return response.json();
                        })
                        .then(data => {
                            // Se ejecuta si el parseo JSON fue exitoso. 'data' es el objeto JSON.
                            console.log(`DEBUG_LISTQR_JS: Respuesta del servidor para acción '${action}':`, data); // Loguear la acción

                            if (data.success) {
                                alert('Invitación ' + (action === 'delete' ? 'eliminada' : (action === 'cancel' ? 'cancelada' : 'reactivada')) + ' con éxito: ' + data.message); // Mensaje dinámico

                                // --- === Actualizar el UI sin recargar === ---
                                // Encontramos la fila de detalles más cercana al botón clickeado.
                                const detailsRow = button.closest('tr.invitation-details-row');
                                if (detailsRow) {
                                     // Encontramos la fila resumen justo antes.
                                     const summaryRow = detailsRow.previousElementSibling;

                                    if (action === 'delete') {
                                        // Si fue eliminación, remover ambas filas
                                         if (summaryRow && summaryRow.classList.contains('invitation-summary-row')) {
                                             summaryRow.remove();
                                             detailsRow.remove();
                                             console.log('Filas eliminadas del DOM.');
                                         } else {
                                              console.warn('DEBUG_LISTQR_JS: No se pudieron encontrar las filas summary/details adyacentes para eliminar. Recargando página.');
                                              window.location.reload(); // Recarga si no se puede eliminar del DOM limpiamente
                                         }
                                    } else if (action === 'cancel' || action === 'reactivate') {
                                        // Si fue cancelación o reactivación, actualizar el estado y los botones
                                        if (data.details) {
                                            // Actualizar el span de estado en la fila de detalles
                                            const statusSpanDetails = detailsRow.querySelector('.detail-item .detail-value span[class^="status-"]');
                                            if (statusSpanDetails) {
                                                statusSpanDetails.className = ''; // Remover clases de estado anteriores
                                                statusSpanDetails.classList.add(`status-${data.details.status}`); // Añadir nueva clase de estado
                                                statusSpanDetails.textContent = data.details.status.charAt(0).toUpperCase() + data.details.status.slice(1); // Actualizar texto
                                            }

                                            // Actualizar el span de estado en la fila resumen si existe
                                            if (summaryRow && summaryRow.classList.contains('invitation-summary-row')) {
                                                 const statusSpanSummary = summaryRow.querySelector('td span[class^="status-"]');
                                                 if (statusSpanSummary) {
                                                     statusSpanSummary.className = ''; // Remover clases de estado anteriores
                                                     statusSpanSummary.classList.add(`status-${data.details.status}`); // Añadir nueva clase de estado
                                                     statusSpanSummary.textContent = data.details.status.charAt(0).toUpperCase() + data.details.status.slice(1); // Actualizar texto
                                                 }
                                            }


                                            // Lógica para mostrar/ocultar botones después de la acción
                                            const actionButtonsContainer = button.closest('.button-group-expanded');
                                            if(actionButtonsContainer) {
                                                // Ocultar todos los formularios de acción (que contienen los botones)
                                                actionButtonsContainer.querySelectorAll('form').forEach(form => form.style.display = 'none');

                                                // Mostrar solo el formulario/botón relevante para el NUEVO estado (data.details.status)
                                                // Nota: Los botones QR, Escanear, Enlace Invitado, Copiar Enlace no están dentro de formularios,
                                                // por lo que permanecen visibles a menos que los ocultemos explícitamente aquí.
                                                // Como queremos que sigan visibles, no los tocamos.

                                                // Mostrar Eliminar si el nuevo estado es 'pendiente'
                                                if (data.details.status === 'pendiente') {
                                                     const deleteForm = actionButtonsContainer.querySelector('form input[name="action"][value="delete"]');
                                                     if(deleteForm) deleteForm.closest('form').style.display = 'inline-block';
                                                }

                                                // Mostrar Cancelar si el nuevo estado es 'pendiente' o 'aprobado'
                                                if (data.details.status === 'pendiente' || data.details.status === 'aprobado') {
                                                    const cancelForm = actionButtonsContainer.querySelector('form input[name="action"][value="cancel"]');
                                                    if(cancelForm) cancelForm.closest('form').style.display = 'inline-block';
                                                }

                                                // Mostrar Reactivar si el nuevo estado es 'cancelado'
                                                if (data.details.status === 'cancelado') {
                                                    const reactivateForm = actionButtonsContainer.querySelector('form input[name="action"][value="reactivate"]');
                                                    if(reactivateForm) reactivateForm.closest('form').style.display = 'inline-block';
                                                }

                                            } else {
                                                console.warn('DEBUG_LISTQR_JS: No se encontró el contenedor de botones de acción. Recargando página para actualizar UI.');
                                                window.location.reload(); // Recarga si no se puede actualizar el UI
                                            }


                                        } else {
                                            console.warn(`DEBUG_LISTQR_JS: Acción '${action}' completada pero no se pudieron obtener detalles para actualizar UI. Recargando página.`);
                                            window.location.reload(); // Recarga para asegurar que el UI esté correcto
                                        }
                                    }


                                } else {
                                     console.warn(`DEBUG_LISTQR_JS: No se pudo encontrar la fila de detalles para actualizar UI después de acción '${action}'. Recargando página.`);
                                     window.location.reload(); // Recarga si no se puede encontrar la fila de detalles
                                }


                            } else {
                                // Si el servidor reporta un error (success: false)
                                alert('Error al ' + (action === 'delete' ? 'eliminar' : (action === 'cancel' ? 'cancelar' : 'reactivar')) + ' la invitación: ' + (data.message || 'Mensaje de error desconocido.'));
                            }
                        })
                        .catch(error => {
                            // Error en la solicitud Fetch (red, etc.)
                            console.error(`DEBUG_LISTQR_JS: Fetch error al realizar acción '${action}' en invitación:`, error);
                            alert('Error en la comunicación con el servidor al intentar ' + (action === 'delete' ? 'eliminar' : (action === 'cancel' ? 'cancelar' : 'reactivar')) + ' la invitación.');
                        });
                    } else {
                        // Si el usuario CANCELÓ la acción.
                        console.log(`DEBUG_LISTQR_JS: Acción '${action}' cancelada por el usuario.`);
                    }
                });
            });


        }); // Fin de DOMContentLoaded
    </script>

    <div id="qrModalOverlay" class="modal-overlay" style="display: none;">
        <div id="qrModalContent" class="modal-content">
            <img id="qrModalImage" src="" alt="Código QR de Invitación">
        </div>
    </div>
    <script>
   // Script de la modal QR (copiado de versiones anteriores para restaurar la funcionalidad)
        document.addEventListener('DOMContentLoaded', function() {
            // Obtenemos referencias a los elementos del modal por sus IDs
            const qrModalOverlay = document.getElementById('qrModalOverlay'); // El fondo oscuro
            const qrModalContent = document.getElementById('qrModalContent'); // El contenedor blanco
            const qrModalImage = document.getElementById('qrModalImage'); // La etiqueta <img> donde irá el QR

            // Seleccionamos todos los botones con la clase 'show-qr-modal-btn'
            const showQrModalButtons = document.querySelectorAll('.show-qr-modal-btn');


            // === Lógica para ABRIR el Modal ===
            showQrModalButtons.forEach(button => {
                 button.addEventListener('click', function(event) {
                    event.stopPropagation(); // Evita que el clic en el botón también colapse/expanda la fila de la tabla

                    const qrImageUrl = this.getAttribute('data-qr-url');

                    if (qrImageUrl && qrImageUrl !== '#') {
                        qrModalImage.src = qrImageUrl;
                        qrModalOverlay.style.display = 'flex';
                    } else {
                        console.error('DEBUG_LISTQR_JS: No se encontró URL de imagen QR válida en el atributo data-qr-url.');
                        alert('Error: No se pudo obtener la imagen del QR.');
                    }
                 });
            });

            // === Lógica para CERRAR el Modal ===
            // Cierra el modal al hacer clic en la IMAGEN del QR
            qrModalImage.addEventListener('click', function() {
                qrModalOverlay.style.display = 'none';
                qrModalImage.src = '';
            });

             // Cierra el modal al hacer clic en el OVERLAY (fondo oscuro)
             qrModalOverlay.addEventListener('click', function(event) {
                 if (event.target === qrModalOverlay) {
                     qrModalOverlay.style.display = 'none';
                     qrModalImage.src = '';
                 }
             });

             // Cierra el modal al presionar la tecla Escape
             document.addEventListener('keydown', function(event) {
                 if (event.key === 'Escape' && qrModalOverlay.style.display === 'flex') {
                     qrModalOverlay.style.display = 'none';
                     qrModalImage.src = '';
                 }
             });

        }); // Fin de DOMContentLoaded para la modal QR
    </script>


</body>
</html>
