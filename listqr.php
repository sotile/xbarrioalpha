<?php
// htdocs/listqr.php - Página para listar las invitaciones creadas

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

// --- === Inicialización de variables de roles permitidos con valores por defecto === ---
// Estas variables controlan qué enlaces de navegación se muestran en el encabezado.
// Si config.php define variables globales con estos nombres, esas definiciones las sobrescribirán.
// Inicializarlas aquí garantiza que siempre existan como arrays.

// Roles permitidos para ver este listado (usado para la autorización de la página misma y enlaces en otros scripts).
// Obtiene el valor de $allowed_roles de config.php si existe, de lo contrario usa el array por defecto.
$list_allowed_roles = $allowed_roles ?? ['anfitrion', 'seguridad', 'administrador', 'developer'];

// Roles permitidos para crear invitaciones (usado para mostrar el enlace "Crear Invitación").
$invite_allowed_roles = $invite_allowed_roles ?? ['anfitrion', 'administrador', 'developer'];

// Roles permitidos para escanear QRs (usado para mostrar el enlace "Escanear QR").
$scan_allowed_roles = $scan_allowed_roles ?? ['seguridad', 'administrador', 'developer'];


// --- === Lógica de Autorización de Página === ---
// Verifica si el usuario NO está logueado O si su rol NO está en la lista de roles permitidos para ver el listado.
if (!is_logged_in() || !in_array($user_role, $list_allowed_roles)) {
    // Si no tiene permiso, redirige a otra página.
    redirect('index.php'); // Asegúrate que la ruta sea correcta.
}


// --- === Obtener las Invitaciones === ---
// Obtiene todas las invitaciones del archivo JSON usando la función del handler.
$all_invitations = getInvitations();

// --- Lógica de Filtrado y Ordenamiento (Opcional) ---
// Puedes añadir aquí lógica para filtrar $all_invitations
// basada en parámetros GET (ej: ?status=pendiente) o POST.
// También puedes añadir lógica para ordenar las invitaciones.
// Por ahora, simplemente usaremos todas las invitaciones.
$filtered_invitations = $all_invitations; // En un caso real, esta variable se llenaría después de filtrar.

// --- Si quieres filtrar por anfitrión logueado (solo mostrar las suyas) ---
// if (!in_array($user_role, ['administrador', 'developer'])) { // Si no es admin/dev
//     $filtered_invitations = array_filter($all_invitations, function($inv) use ($current_user) {
//         return isset($inv['anfitrion']['id']) && $inv['anfitrion']['id'] === ($current_user['id'] ?? null);
//     });
// }
// Esto requiere que $current_user['id'] exista y sea consistente.

// --- Fin Lógica de Filtrado ---


?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invitaciones</title>
    <link rel="stylesheet" href="css/styles.css">
    <!-- Si app.js contiene lógica necesaria para esta página (ej: validación, etc.), inclúyelo. -->
    <!-- <script src="js/app.js"></script> -->
</head>
<body>
    <div class="header">
        <h2>Invitaciones</h2>
        <a href="logout.php" class="logout-btn">Salir</a>
        <a href="index.php" class="back-btn">Home</a>
        <?php // Enlace para ir a Crear Invitación (solo si el rol del usuario lo permite)
              if (in_array($user_role, $invite_allowed_roles)):
        ?>
        <?php endif; ?>
        <?php // Enlace para ir a Escanear QR (solo si el rol del usuario lo permite)
              if (in_array($user_role, $scan_allowed_roles)):
        ?>
              <a href="scanqr.php" class="scan-qr-btn">Escanear QR</a>
        <?php endif; ?>
    </div>

    <div class="container">
  

        <!-- Formulario de Filtrado/Ordenamiento (Opcional, implementa según necesidad) -->
        <!-- <form method="GET" action="listqr.php"> ... </form> -->

        <?php if (empty($filtered_invitations)): ?>
            <p>No hay invitaciones para mostrar con los criterios actuales.</p>
        <?php else: ?>
            <!-- === === Tabla de Invitaciones === === -->
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
                    // Recorre cada invitación en el array filtrado/ordenado
                    foreach ($filtered_invitations as $invitation):
                        // Extrae y prepara todos los datos de la invitación como lo hacías antes
                        $code = $invitation['code'] ?? 'N/A';
                        $guest_name = $invitation['invitado_nombre'] ?? 'N/A';
                        $anfitrion_name = $invitation['anfitrion']['name'] ?? 'N/A';
                        $anfitrion_lote = $invitation['anfitrion']['lote'] ?? 'N/A';
                        // Formatea timestamps a fechas legibles.
                        $fecha_creacion = isset($invitation['fecha_creacion']) ? date('d/m/Y H:i', $invitation['fecha_creacion']) : 'N/A';
                        $fecha_expiracion = isset($invitation['fecha_expiracion']) ? date('d/m/Y H:i', $invitation['fecha_expiracion']) : 'N/A';
                        $status = $invitation['status'] ?? 'desconocido';
                        $fecha_aprobacion = isset($invitation['fecha_aprobacion']) && $invitation['fecha_aprobacion'] > 0 ? date('d/m/Y H:i', $invitation['fecha_aprobacion']) : 'Pendiente';

                        // --- Construye la URL al archivo de imagen QR (para el viejo "Ver QR" si lo mantienes) ---
                        $qr_image_file_url = $code !== 'N/A' ? 'qr/' . urlencode($code) . '.png' : '#'; // Ruta web relativa a la imagen

                        // --- Construye la URL COMPLETA (absoluta) al LANDING PAGE (/qr/CODE) ---
                        $landing_page_url = $code !== 'N/A' && defined('URL_BASE') ? URL_BASE . "/qr/" . urlencode($code) : '#';
                         if (!defined('URL_BASE')) {
                            // Esto ya se loguea al inicio del script, pero lo mantenemos por si acaso
                            // error_log("URL_BASE no definida en config.php al construir landing page URL en listqr.php.");
                         }

                         $can_delete = false;

                         // Roles que SIEMPRE pueden eliminar (Administrador, Developer)
                         // Puedes definir $delete_allowed_roles en config.php si prefieres
                         $delete_allowed_roles = ['administrador', 'developer'];

   // 1. Verificar si el rol del usuario actual está en los roles permitidos globales para eliminar
   if (in_array($user_role, $delete_allowed_roles)) {
    $can_delete = true;
} else {
    // 2. Si no tiene un rol global de eliminación, verificar si es el ANFITRIÓN que creó esta invitación específica
    $current_user_id = $current_user['id'] ?? null; // ID del usuario logueado
    $current_user_lote = $current_user['lote'] ?? null; // Lote del usuario logueado

    // Datos del anfitrión GUARDADOS en la invitación
    $invite_anfitrion_id = $invitation['anfitrion']['id'] ?? null;
    $invite_anfitrion_lote = $invitation['anfitrion']['lote'] ?? null;

    // Si tenemos los datos del usuario logueado Y los datos del anfitrión en la invitación, comparamos.
    if ($current_user_id && $current_user_lote &&
        $current_user_id === $invite_anfitrion_id &&
        $current_user_lote === $invite_anfitrion_lote) {
        // Si el ID y Lote del usuario logueado coinciden con el ID y Lote del anfitrión guardado en la invitación
        $can_delete = true;
    }
}
// --- === Fin Lógica de Permisos === ---



                    ?>
                        <tr class="invitation-summary-row" data-code="<?php echo htmlspecialchars($code); ?>">
                            <td><?php echo htmlspecialchars($guest_name); ?></td>
                            <td><?php echo htmlspecialchars($anfitrion_lote); ?></td>
                            <td><span class="status-<?php echo htmlspecialchars(strtolower($status)); ?>"><?php echo htmlspecialchars(ucfirst($status)); ?></span></td>
                            <td colspan="7"></td> </tr>

                            <tr class="invitation-details-row" data-code="<?php echo htmlspecialchars($code); ?>" style="display: none;">
                            <td colspan="10"> 
                            <div class="invitation-details-expanded">
                                    <div class="detail-item"><span class="detail-label">Código:</span> <span class="detail-value"><?php echo htmlspecialchars($code); ?></span></div>
                                    <div class="detail-item"><span class="detail-label">Invitado:</span> <span class="detail-value"><?php echo htmlspecialchars($guest_name); ?></span></div>
                                    <div class="detail-item"><span class="detail-label">Anfitrión:</span> <span class="detail-value"><?php echo htmlspecialchars($anfitrion_name); ?></span></div>
                                    <div class="detail-item"><span class="detail-label">Lote:</span> <span class="detail-value"><?php echo htmlspecialchars($anfitrion_lote); ?></span></div>
                                    <div class="detail-item"><span class="detail-label">Creación:</span> <span class="detail-value"><?php echo htmlspecialchars($fecha_creacion); ?></span></div>
                                    <div class="detail-item"><span class="detail-label">Expira:</span> <span class="detail-value"><?php echo htmlspecialchars($fecha_expiracion); ?></span></div>
                                     <div class="detail-item"><span class="detail-label">Estado:</span> <span class="detail-value"><span class="status-<?php echo htmlspecialchars(strtolower($status)); ?>"><?php echo htmlspecialchars(ucfirst($status)); ?></span></span></div>
                                    <div class="detail-item"><span class="detail-label">Validación:</span> <span class="detail-value"><?php echo htmlspecialchars($fecha_aprobacion); ?></span></div>

                                    <div class="button-group-expanded">
                                         <a href="<?php echo htmlspecialchars($qr_image_file_url); ?>" target="_blank" class="btn">Ver QR</a>
                                         <a href="<?php echo htmlspecialchars($landing_page_url); ?>" target="_blank" class="btn">Ver Enlace</a>

                                         <button class="btn copy-link-btn" data-url="<?php echo htmlspecialchars($landing_page_url); ?>">Copiar Enlace</button>
                                         <?php if ($can_delete): ?>
                                             <button class="btn delete-invite-btn" data-code="<?php echo htmlspecialchars($code); ?>">Eliminar</button>
                                         <?php endif; ?>
                                     </div>
                                     </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <!-- === === Fin Tabla de Invitaciones === === -->
        <?php endif; ?>

    </div> <!-- Cierre del div.container -->

    <!-- === === JavaScript para el Botón Copiar Enlace === === -->
    <!-- Este script debe ir después de que los botones con la clase 'copy-link-btn' existen en el DOM. -->
    <script>
        // Selecciona todos los botones con la clase 'copy-link-btn' en la página.
        document.querySelectorAll('.copy-link-btn').forEach(button => {
            // Añade un "escuchador de eventos" (event listener) para el clic en cada botón.
            button.addEventListener('click', function() {
                // 'this' se refiere al botón que fue clickeado.
                // Obtenemos la URL que queremos copiar del atributo 'data-url' de ese botón.
                const urlToCopy = this.dataset.url;

                // Intenta usar la API del Portapapeles moderna (navigator.clipboard).
                // Es asíncrona y requiere permisos, pero es el método recomendado.
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(urlToCopy)
                        .then(() => {
                            // Si la copia fue exitosa:
                            console.log('URL copiada al portapapeles:', urlToCopy);
                            // Opcional: Dar feedback visual al usuario cambiando el texto del botón.
                            const originalText = this.textContent; // Guarda el texto original del botón.
                            this.textContent = '¡Copiado!'; // Cambia el texto del botón.
                            // Restaura el texto original después de 2 segundos.
                            setTimeout(() => {
                                this.textContent = originalText;
                            }, 2000);
                        })
                        .catch(err => {
                            // Si hubo un error al usar la API moderna (ej: permisos denegados).
                            console.error('Error al copiar al portapapeles (modern API):', err);
                            // Intenta el método de fallback para asegurar la copia.
                            fallbackCopyTextToClipboard(urlToCopy, this);
                        });
                } else {
                    // Si la API moderna no está disponible (navegadores más antiguos).
                    console.warn('navigator.clipboard no disponible. Usando fallback.');
                    fallbackCopyTextToClipboard(urlToCopy, this);
                }
            });
        });

        // Función de fallback para copiar texto al portapapeles en navegadores antiguos
        // Crea un área de texto temporal, pone el texto, lo selecciona, copia y elimina el área de texto.
        function fallbackCopyTextToClipboard(text, buttonElement) {
            const textArea = document.createElement("textarea");
            textArea.value = text; // Asigna el texto al área de texto.

            // Estilos para hacer el área de texto invisible y evitar que afecte el layout/scroll.
            textArea.style.top = "0";
            textArea.style.left = "0";
            textArea.style.position = "fixed"; // Posición fija fuera del flujo normal.
            textArea.style.opacity = "0"; // Completamente transparente.

            document.body.appendChild(textArea); // Añade el área de texto al final del cuerpo.
            textArea.focus(); // Enfoca el área de texto.
            textArea.select(); // Selecciona todo el texto dentro del área de texto.

            try {
                // Intenta ejecutar el comando de copia heredado.
                const successful = document.execCommand('copy');
                const msg = successful ? '¡Copiado (fallback)!' : 'Error al copiar (fallback).';
                 console.log('Fallback: Copying text command was ' + (successful ? 'successful' : 'unsuccessful'));

                 // Opcional: Dar feedback visual al usuario.
                 const originalText = buttonElement.textContent;
                 buttonElement.textContent = msg;
                 setTimeout(() => {
                     buttonElement.textContent = originalText;
                 }, 2000);

            } catch (err) {
                // Si incluso el comando heredado falla.
                console.error('Fallback: Oops, unable to copy', err);
                 // Opcional: Feedback de error.
                 buttonElement.textContent = 'Error';
            }

            document.body.removeChild(textArea); // Elimina el área de texto temporal del DOM.
        }


 // Espera a que el DOM esté completamente cargado antes de ejecutar el script
 document.addEventListener('DOMContentLoaded', function() {
            // Selecciona todas las filas de resumen en la tabla (las que tienen la clase 'invitation-summary-row')
            const summaryRows = document.querySelectorAll('.invitation-summary-row');

            // Itera sobre cada fila de resumen encontrada
            summaryRows.forEach(row => {
                // Añade un "escuchador de eventos" (event listener) para el evento 'click' en cada fila de resumen
                row.addEventListener('click', function() {
                    // 'this' se refiere a la fila de resumen que fue clickeada.
                    // Buscamos el elemento SIBLING (hermano) siguiente a la fila de resumen clickeada.
                    // En nuestra estructura, la fila de detalles está inmediatamente después de la fila de resumen en el HTML.
                    const detailsRow = this.nextElementSibling;

                    // Verificamos si el siguiente elemento hermano existe Y si tiene la clase 'invitation-details-row'.
                    // Esto nos asegura de que estamos actuando sobre la fila de detalles correcta.
                    if (detailsRow && detailsRow.classList.contains('invitation-details-row')) {
                        // === === Lógica para TOGGLE (mostrar/ocultar) la fila de detalles === ===
                        // Comprueba el estado actual de la propiedad CSS 'display' de la fila de detalles.
                        if (detailsRow.style.display === 'none' || detailsRow.style.display === '') {
                            // Si está oculta (display es 'none' o no está definido), la mostramos.
                            // 'table-row' es el valor display correcto para las filas de tabla.
                            detailsRow.style.display = 'table-row';
                            // Opcional: Añadir una clase para cambiar el estilo de la fila resumen cuando está expandida
                            // this.classList.add('expanded');
                        } else {
                            // Si está visible, la ocultamos.
                            detailsRow.style.display = 'none';
                            // Opcional: Quitar la clase 'expanded'
                            // this.classList.remove('expanded');
                        }
                    }
                });
            });




     // Selecciona todos los botones con la clase 'delete-invite-btn'
     const deleteButtons = document.querySelectorAll('.delete-invite-btn');

// Itera sobre cada botón Eliminar encontrado
deleteButtons.forEach(button => {
    // Añade un escuchador de eventos para el clic en cada botón Eliminar
    button.addEventListener('click', function() {
        // 'this' se refiere al botón clickeado.
        // Obtenemos el código de la invitación del atributo 'data-code'.
        const invitationCode = this.dataset.code;

        // Mostramos un cuadro de diálogo de confirmación al usuario.
        const confirmDelete = confirm('¿Estás seguro de que deseas eliminar esta invitación con código ' + invitationCode + '? Esta acción no se puede deshacer.');

        // Si el usuario confirmó la eliminación (pulso OK)
        if (confirmDelete) {
            console.log('Usuario confirmó eliminación para código:', invitationCode);

            // === === Lógica para enviar la solicitud de eliminación al servidor (AJAX) === ===

            // La URL del script PHP que procesará la eliminación.
            // Apuntamos a invitation_handler.php donde pusimos el bloque POST.
            const handlerUrl = 'includes/delete_invitation.php'; // <<< ASEGÚRATE QUE ESTA RUTA SEA CORRECTA

            // Preparamos los datos a enviar en la solicitud POST.
            const formData = new FormData();
            formData.append('action', 'delete'); // Le decimos al script qué acción queremos ('delete')
            formData.append('code', invitationCode); // Le enviamos el código de la invitación a eliminar

            // Usamos la API Fetch para enviar la solicitud POST de forma asíncrona.
            fetch(handlerUrl, {
                method: 'POST',
                body: formData
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
                console.log('Respuesta del servidor (eliminar):', data); // Loguea la respuesta del servidor

                if (data.success) {
                    alert('Invitación eliminada con éxito: ' + data.message);

                    // === Opcional: Eliminar la fila de la tabla en el navegador SIN recargar ===
                    // Encontramos la fila de detalles más cercana al botón clickeado.
                    const detailsRow = button.closest('tr.invitation-details-row');
                    if (detailsRow) {
                        // Encontramos la fila de resumen justo antes.
                        const summaryRow = detailsRow.previousElementSibling;
                        if (summaryRow && summaryRow.classList.contains('invitation-summary-row')) {
                            // Eliminamos ambas filas del DOM.
                            summaryRow.remove();
                            detailsRow.remove();
                            console.log('Filas eliminadas del DOM.');
                        } else {
                             console.warn('No se pudieron encontrar las filas summary/details adyacentes. Recargando página.');
                             window.location.reload(); // Recarga si no se puede eliminar del DOM limpiamente
                        }
                    } else {
                         console.warn('No se pudo encontrar la fila de detalles. Recargando página.');
                         window.location.reload(); // Recarga si no se puede encontrar la fila de detalles
                    }

                } else {
                    // Si el servidor reporta un error (success: false)
                    alert('Error al eliminar la invitación: ' + (data.message || 'Mensaje de error desconocido.'));
                }
            })
            .catch(error => {
                // Error en la solicitud Fetch (red, etc.)
                console.error('Fetch error al eliminar invitación:', error);
                alert('Error en la comunicación con el servidor al intentar eliminar la invitación.');
            });

            // === Fin Lógica para enviar la solicitud de eliminación ===

        } else {
            // Si el usuario CANCELÓ la eliminación.
            console.log('Eliminación cancelada por el usuario.');
        }
    });
});


        });



    </script>

    <!-- Puedes incluir app.js si contiene lógica global necesaria para esta página. -->
    <!-- <script src="js/app.js"></script> -->


    </div> <div id="qrModalOverlay" style="display: none;">
        <div id="qrModalContent">
            <img id="qrModalImage" src="" alt="Código QR en Modal">
        </div>
    </div>
    <script>
   
        document.addEventListener('DOMContentLoaded', function() {
            // Obtenemos referencias a los elementos del modal por sus IDs
            const modalOverlay = document.getElementById('qrModalOverlay'); // El fondo oscuro
            const modalContent = document.getElementById('qrModalContent'); // El contenedor blanco
            const modalImage = document.getElementById('qrModalImage'); // La etiqueta <img> donde irá el QR

            // Seleccionamos todos los enlaces "Ver QR" en la tabla.
            // Buscamos enlaces <a> con clase 'btn' que tengan el texto "Ver QR".
            // Esto nos asegura de seleccionar solo el botón "Ver QR" y no "Ver Enlace" o "Copiar Enlace" si también tienen la clase 'btn'.
            const viewQrLinks = document.querySelectorAll('a.btn'); // Selecciona todos los enlaces con clase btn
            const viewQrLinksFiltered = Array.from(viewQrLinks).filter(link =>
                link.textContent.trim() === 'Ver QR' // Filtra para quedarte solo con los que dicen "Ver QR"
            );


            // === === Lógica para ABRIR el Modal === ===
            // Iteramos sobre cada enlace "Ver QR" encontrado
            viewQrLinksFiltered.forEach(link => {
                 // Añadimos un escuchador de eventos para el clic en cada enlace
                 link.addEventListener('click', function(event) {
                    event.preventDefault(); // ¡Importante! Previene la acción por defecto del enlace (abrir en nueva pestaña)

                    // Obtenemos la URL de la imagen del código QR desde el atributo 'href' del enlace clickeado
                    const qrImageUrl = this.href;

                    // Establecemos la URL de la imagen obtenida como el 'src' de la etiqueta <img> dentro del modal
                    modalImage.src = qrImageUrl;

                    // Mostramos el modal cambiando su estilo 'display' a 'flex' (que usamos en CSS para centrar)
                    modalOverlay.style.display = 'flex'; // Hace visible el overlay y centra el contenido
                 });
            });

            // === === Lógica para CERRAR el Modal === ===
            // Queremos que se cierre al hacer clic en la imagen o en el fondo oscuro.

            // Cierra el modal al hacer clic en la IMAGEN dentro del modal
            modalImage.addEventListener('click', function() {
                modalOverlay.style.display = 'none'; // Oculta el overlay (y todo el modal)
                modalImage.src = ''; // Limpia la fuente de la imagen (opcional pero buena práctica)
            });

             // Opcional: Cierra el modal al hacer clic en el OVERLAY (el fondo oscuro), pero no en el contenido del modal
             modalOverlay.addEventListener('click', function(event) {
                 // Si el clic fue directamente en el overlay (no en el contenido del modal)
                 if (event.target === modalOverlay) {
                     modalOverlay.style.display = 'none'; // Oculta el modal
                     modalImage.src = ''; // Limpia la fuente de la imagen
                 }
             });

             // Opcional: Cierra el modal al presionar la tecla Escape
             document.addEventListener('keydown', function(event) {
                 if (event.key === 'Escape' || event.key === 'Esc') { // Verifica si la tecla es Escape
                     if (modalOverlay.style.display !== 'none') { // Solo si el modal está visible
                          modalOverlay.style.display = 'none'; // Oculta el modal
                          modalImage.src = ''; // Limpia la fuente de la imagen
                     }
                 }
             });

        }); // Fin de DOMContentLoaded
    </script>





</body>
</html>