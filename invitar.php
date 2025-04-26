<?php
// htdocs/invitar.php - Página para crear nuevas Invitaciones

// --- === Configurar Zona Horaria === ---
// Establece la zona horaria a la de Buenos Aires, Argentina.
date_default_timezone_set('America/Argentina/Buenos_Aires');


// --- === Includes y Autenticación === ---
// Asegúrate que estos archivos existan en la carpeta 'includes'.
require_once __DIR__ . '/includes/auth.php'; // Contiene funciones de autenticación como start_session_if_not_started, gets_current_user, is_logged_in, redirect
require_once __DIR__ . '/includes/invitation_handler.php'; // Contiene funciones para manejar invitaciones como getInvitationByCode (necesaria si mostramos detalles después de crear)
require_once __DIR__ . '/includes/config.php'; // Contiene la configuración, incluyendo URL_BASE, roles permitidos ($invite_allowed_roles) y la nueva variable $qr_default_validity_hours

// Inicia la sesión si aún no se ha iniciado.
// Esta función debe estar en auth.php.
start_session_if_not_started();

// Obtiene los datos del usuario logueado.
// Esta función debe estar en auth.php y retornar un array con datos del usuario o null/vacío si no logueado.
$current_user = gets_current_user();

// Extrae datos específicos del usuario logueado para usarlos fácilmente.
// Usa el operador de fusión null (??) para evitar errores si alguna clave no existe.
$user_role = $current_user['role'] ?? 'guest'; // Rol del usuario, 'guest' por defecto si no logueado o rol no definido
$user_lote = $current_user['lote'] ?? null; // Lote del anfitrión
$user_id = $current_user['id'] ?? null; // ID del anfitrión
$user_name = $current_user['name'] ?? 'Anfitrión Desconocido'; // Nombre del anfitrión

// --- === Lógica de Autorización de Página === ---
// Define los roles que tienen permiso para acceder a esta página (Crear Invitación).
// Es altamente recomendable definir $invite_allowed_roles en config.php.
// Si no está definido en config.php, usa este array por defecto.
$allowed_roles_for_invitar = $invite_allowed_roles ?? ['anfitrion', 'administrador', 'developer'];

// Verifica si el usuario NO está logueado O si su rol NO está en la lista de roles permitidos para crear invitaciones.
if (!is_logged_in() || !in_array($user_role, $allowed_roles_for_invitar)) {
    // Si no tiene permiso, redirige a otra página (ej: index.php o login.php).
    // Asegúrate que la función redirect() esté definida en auth.php.
    redirect('index.php'); // Redirige a la página principal si no autorizado.
}

// --- === Inicialización de Variables de Estado y Datos de la Nueva Invitación === ---
// Estas variables se usarán para controlar qué se muestra en el HTML y para pasar datos al JavaScript.
$success_message = ''; // Para mensajes de éxito.
$error_message = ''; // Para mensajes de error.
$new_invitation_data = null; // Almacenará el array de la invitación creada si el proceso es exitoso y se recibe el código en la URL.

// --- Variables para el contenido del QR (lo que escanea seguridad) y la URL de Compartir (lo que el invitado usa) ---
// Inicializadas a vacío; se llenarán si la invitación se crea con éxito y se recibe el código en la URL.
$qr_code_content_for_js = ''; // Contiene la URL a scanqr.php con el código (para dibujar el QR).
$scan_url_for_sharing = ''; // Contiene la URL amigable (/qr/CODE) para compartir.


// --- === Calcular la Fecha y Hora de Expiración por Defecto === ---
// Obtiene la fecha y hora actual.
$now = new DateTime();

// Lee la duración por defecto desde config.php. Usa 24 horas si no está definida.
// Asegúrate de que $qr_default_validity_hours esté disponible desde config.php.
global $qr_default_validity_hours; // Asegura que podemos acceder a la variable global
$default_validity_hours = $qr_default_validity_hours ?? 24; // Usa la variable global de config.php

// Clona la fecha actual y añade la duración por defecto.
$default_expiration_datetime = clone $now;
$default_expiration_datetime->modify('+ ' . $default_validity_hours . ' hours');

// Formatea la fecha y hora de expiración por defecto para el input type="datetime-local" (YYYY-MM-DDTHH:MM).
$default_expiration_formatted = $default_expiration_datetime->format('Y-m-d\TH:i');


// --- === Manejar la Redirección desde el Endpoint (Método GET) === ---
// Este bloque se ejecuta si la página es cargada por GET después de una redirección desde create_invitation_endpoint.php
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Verificar si hay parámetros 'status' y 'message' en la URL
    $status = $_GET['status'] ?? '';
    $message = $_GET['message'] ?? '';
    $created_code = $_GET['code'] ?? ''; // Obtiene el código si se pasó en la URL

    if ($status === 'success' && !empty($created_code)) {
        $success_message = htmlspecialchars($message); // Mostrar mensaje de éxito

        // Intentar obtener los detalles completos de la invitación recién creada por su código
        $new_invitation_data = getInvitationByCode($created_code); // <-- Usamos getInvitationByCode de invitation_handler.php

        if ($new_invitation_data) {
            // Si se encontraron los datos de la invitación, preparar las URLs para mostrar el QR y compartir.
            $invitation_code = $new_invitation_data['code'] ?? '';

            // === PREPARAR EL CONTENIDO DEL QR QUE DIBUJARÁ JAVASCRIPT EN ESTA PÁGINA ===
            // Este es el texto real que se codifica en la imagen QR. Debe ser la URL a scanqr.php con el código.
            // Asegúrate que URL_BASE esté definida en config.php (ej: 'http://tu_dominio.com'). SIN barra final.
            if (defined('URL_BASE')) {
                 $qr_code_content_for_js = URL_BASE . "/scanqr.php?code=" . urlencode($invitation_code);
            } else {
                 // Fallback si URL_BASE no está definida (deberías definirla en config.php)
                 $qr_code_content_for_js = "Código: " . $invitation_code; // Solo muestra el código si no hay URL base.
                 error_log("URL_BASE no definida en config.php al crear QR content para JS.");
            }

            // === PREPARAR LA URL AMIGABLE PARA COMPARTIR CON EL INVITADO (formato /qr/CODE) ===
            // Esta es la URL que el invitado usará para ver el landing page showqr.php con el QR.
            // Requiere que tengas configurada la regla .htaccess para redirigir /qr/CODE a showqr.php?code=CODE.
            if (defined('URL_BASE')) {
                 // Construye la URL amigable usando URL_BASE, el prefijo /qr/, y el código de la invitación.
                 $scan_url_for_sharing = URL_BASE . "/qr/" . urlencode($invitation_code);
            } else {
                 // Fallback si URL_BASE no está definida
                 $scan_url_for_sharing = "Código: " . $invitation_code; // O solo mostrar el código.
                 error_log("URL_BASE no definida en config.php al crear sharing URL.");
            }

            // $new_invitation_data ya está seteada, lo que hará que la sección HTML de abajo para "Invitación Creada" se muestre.

        } else {
            // Si no se pudieron obtener los detalles de la invitación por el código (raro si la creación fue éxito)
            $error_message = htmlspecialchars($message) . " (Pero no se pudieron cargar los detalles de la invitación).";
            $success_message = ''; // Limpiar mensaje de éxito si hay error al cargar detalles
        }

    } elseif ($status === 'error' && !empty($message)) {
        $error_message = htmlspecialchars($message); // Mostrar mensaje de error
        // Si hubo un error, el formulario se mostrará de nuevo.
        // Los valores de los campos se mantendrán si usas $_POST en el 'value' del input (aunque es GET ahora, no habrá $_POST).
        // Podrías pasar los valores del formulario en la URL de error si quieres mantenerlos, pero es más complejo.
    }
    // Si no hay parámetros status/message, simplemente se muestra el formulario vacío por defecto.
}
// --- Fin del Manejo de Redirección GET ---


// Si la página se carga por GET sin parámetros de status (primera vez) o con un error,
// la variable $new_invitation_data será null, y se mostrará el formulario.
// Si se creó la invitación exitosamente en el endpoint y se redirigió con el código,
// $new_invitation_data tendrá datos y se mostrará la sección de invitación creada.


?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Invitación</title>
    <link rel="stylesheet" href="css/styles.css"> <script src="js/qrcode.min.js"></script>
</head>
<body>
    <div class="header">
        <h2>Crear Invitación</h2>
        <a href="logout.php" class="logout-btn">Salir</a>
        <a href="index.php" class="back-btn">Home</a>

        <?php
             // Define los roles que tienen permiso para ver el listado.
             // Si $list_allowed_roles no está definido en config.php, usa este array por defecto.
             $list_allowed_roles = $list_allowed_roles ?? ['anfitrion', 'seguridad', 'administrador', 'developer'];
             // Muestra el enlace al listado solo si el usuario tiene uno de los roles permitidos.
             if (in_array($user_role, $list_allowed_roles)):
        ?>
             <a href="listqr.php" class="view-list-btn btn">Ver Listado</a>
        <?php endif; ?>

        <?php
             // Define los roles que tienen permiso para escanear QRs.
             // Si $scan_allowed_roles no está definido en config.php, usa este array por defecto.
             $scan_allowed_roles = $scan_allowed_roles ?? ['seguridad', 'administrador', 'developer'];
             // Muestra el enlace para escanear QR solo si el usuario tiene uno de los roles permitidos.
             if (in_array($user_role, $scan_allowed_roles)):
        ?>
              <a href="scanqr.php" class="scan-qr-btn btn">Escanear QR</a>
        <?php endif; ?>
    </div>

    <div class="container">
        <h3>Crear Nueva Invitación</h3>

        <?php // Mostrar mensajes de éxito o error si existen ?>
        <?php if ($success_message): ?>
            <div class="status-message status-valid-bg"><?php echo $success_message; // Ya se hizo htmlspecialchars al recibirlo ?></div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="status-message status-error-bg"><?php echo $error_message; // Ya se hizo htmlspecialchars al recibirlo ?></div>
        <?php endif; ?>

        <?php
        // --- === Sección para mostrar los detalles de la invitación creada y dibujar el QR === ---
        // Este bloque de código se muestra SI Y SOLO SI la variable $new_invitation_data NO es null.
        // Esto ocurre si se recibió el código en la URL después de una redirección exitosa.
        if ($new_invitation_data):
            // Si llegamos aquí, la invitación se creó y pudimos cargar sus detalles.
            // Las variables $qr_code_content_for_js y $scan_url_for_sharing ya se calcularon arriba.
        ?>
            <div class="invitation-details-display">
                <h4>Invitación Creada:</h4>
                <p><strong>Código:</strong> <?php echo htmlspecialchars($new_invitation_data['code'] ?? 'N/A'); ?></p>
                <p><strong>Invitado:</strong> <?php echo htmlspecialchars($new_invitation_data['invitado_nombre'] ?? 'N/A'); ?></p>
                <p><strong>Anfitrión:</strong> <?php echo htmlspecialchars($new_invitation_data['anfitrion']['name'] ?? 'N/A'); ?></p>
                <p><strong>Lote:</strong> <?php echo htmlspecialchars($new_invitation_data['anfitrion']['lote'] ?? 'N/A'); ?></p>
                <p><strong>Expira:</strong> <?php echo isset($new_invitation_data['fecha_expiracion']) && $new_invitation_data['fecha_expiracion'] > 0 ? date('d/m/Y H:i', $new_invitation_data['fecha_expiracion']) : 'N/A'; ?></p>

                <div class="qr-code-container" style="margin-top: 20px; text-align: center;">
                    <h4>Código QR:</h4>
                    <div id="qrcode"></div>
                </div>

                <div class="share-options" style="margin-top: 20px;">
                    <h4>Compartir Invitación:</h4>
                    <p>Enlace directo: <a href="<?php echo htmlspecialchars($scan_url_for_sharing); ?>" target="_blank"><?php echo htmlspecialchars($scan_url_for_sharing); ?></a></p>

                    <button id="whatsapp-share-btn" class="share-button btn">Compartir por WhatsApp</button>

                    <script>
                        // Obtenemos la referencia al div donde dibujar el QR (con id="qrcode").
                        const qrcodeDiv = document.getElementById("qrcode");

                        // Obtenemos el contenido del QR (la URL a scanqr.php) que PHP ha pasado a esta variable JS.
                        // Este contenido YA NO es la URL completa, es solo el código de invitación.
                        // Sin embargo, para que el escáner de seguridad funcione como endpoint, el QR debe contener la URL.
                        // La generación del QR en el servidor (createInvitation) ahora solo guarda el código.
                        // PERO, para que qrcode.min.js dibuje un QR escaneable por el scanner de seguridad,
                        // DEBEMOS poner la URL COMPLETA aquí para que el JS la dibuje.
                        // El escáner en scanqr.php está diseñado para recibir esta URL y extraer el código.
                        // La distinción es:
                        // 1. El archivo PNG guardado en el servidor (para referencia) solo tiene el código.
                        // 2. El QR dibujado en esta página (para que el usuario lo escanee con su teléfono o el de seguridad) debe tener la URL.
                        const qrCodeContentForJs = "<?php echo htmlspecialchars($qr_code_content_for_js); ?>"; // <-- Usamos la URL preparada por PHP

                        // Verificamos si el div para el QR existe Y si tenemos contenido para dibujar.
                        if (qrcodeDiv && qrCodeContentForJs) {
                             console.log('DEBUG_INVITAR_JS: Dibujando QR con contenido:', qrCodeContentForJs);

                             // Verificamos si la librería QRCode (qrcode.min.js) está cargada.
                             if (typeof QRCode !== 'undefined') {
                                  // Limpiar contenido anterior del div (por si acaso).
                                  qrcodeDiv.innerHTML = '';

                                  // Dibujar el código QR en el div con las opciones.
                                  var qrcode = new QRCode(qrcodeDiv, {
                                       text: qrCodeContentForJs, // Contenido del QR (URL para el scanner)
                                       width: 200, // Tamaño en píxeles
                                       height: 200,
                                       colorDark: "#000000", // Color oscuro
                                       colorLight: "#ffffff", // Color claro
                                       correctLevel: QRCode.CorrectLevel.H // Nivel de corrección de error (Alto)
                                  });
                                  console.log('DEBUG_INVITAR_JS: QR dibujado con qrcodejs.');
                             } else {
                                  console.error('DEBUG_INVITAR_JS: Error: Librería QRCode (qrcode.min.js) no cargada.');
                                  qrcodeDiv.innerHTML = '<p style="color:red;">Error: No se pudo cargar la librería para dibujar el QR.</p>';
                             }
                        } else {
                             console.log('DEBUG_INVITAR_JS: No se dibujó QR (div o contenido faltante).');
                        }

                        // Script JavaScript para el botón de WhatsApp.
                        const whatsappShareBtn = document.getElementById('whatsapp-share-btn');
                        if (whatsappShareBtn) {
                            whatsappShareBtn.addEventListener('click', function () {
                                // La URL a compartir es la URL amigable (/qr/CODE) que PHP ha pasado a esta variable JS.
                                const scanUrlForSharing = "<?php echo htmlspecialchars($scan_url_for_sharing); ?>";
                                const whatsappText = "¡Hola! Tienes una invitación para ingresar al barrio.\n\nPresenta este enlace:\n" + scanUrlForSharing;
                                const whatsappUrl = "https://api.whatsapp.com/send?text=" + encodeURIComponent(whatsappText);
                                window.open(whatsappUrl, '_blank'); // Abrir en una nueva pestaña/ventana.
                            });
                        }
                    </script>
                </div>

                <p style="margin-top: 20px;"><a href="invitar.php" class="create-new-btn btn">Crear Otra Invitación</a></p>

            </div>

        <?php else: // === Sección para mostrar el formulario si aún no se ha creado una invitación === ?>

            <form method="POST" action="includes/create_invitation.php"> <div class="form-group">
                    <label for="guest_name">Nombre del Invitado:</label>
                    <input type="text" id="guest_name" name="guest_name" required value=""> </div>

                <div class="form-group">
                    <label for="expiration_date">Fecha y Hora de Expiración:</label>
                    <input type="datetime-local"
                           id="expiration_date"
                           name="expiration_date"
                           required
                           value="<?php echo htmlspecialchars($default_expiration_formatted); // Usa la fecha/hora por defecto calculada ?>"
                    >
                </div>

                <button type="submit" class="btn">Generar Invitación</button>
            </form>

            <script>
                // Script para ajustar la hora a 23:59 al cambiar la fecha en el input datetime-local
                document.addEventListener('DOMContentLoaded', function() {
                    const expirationDateInput = document.getElementById('expiration_date');

                    if (expirationDateInput) {
                        expirationDateInput.addEventListener('change', function() {
                            const currentDateValue = this.value; // Obtiene el valor actual (YYYY-MM-DDTHH:MM)

                            if (currentDateValue) {
                                // Extrae solo la parte de la fecha (YYYY-MM-DD)
                                const datePart = currentDateValue.split('T')[0];

                                // Construye la nueva cadena de fecha y hora con la hora fija a 23:59
                                const newDateTimeValue = datePart + 'T23:59';

                                // Establece el nuevo valor en el input
                                this.value = newDateTimeValue;
                                console.log('DEBUG_INVITAR_JS: Fecha cambiada, hora ajustada a 23:59:', newDateTimeValue);
                            } else {
                                // Si el valor se vacía (aunque required lo impide), puedes manejarlo si es necesario
                                console.log('DEBUG_INVITAR_JS: Input de fecha vaciado.');
                            }
                        });
                    } else {
                        console.error('DEBUG_INVITAR_JS: Elemento #expiration_date no encontrado.');
                    }
                });
            </script>

        <?php endif; // Fin de la condición para mostrar QR/Detalles o Formulario ?>

    </div> <?php
    // En invitar.php, el QR se dibuja directamente en la página usando qrcode.min.js.
    // No necesitas la estructura de modal que usamos en listqr.php aquí a menos que quieras
    // que al hacer clic en el QR dibujado se abra una versión más grande en un modal.
    // Por ahora, la lógica estándar de invitar.php no usa ese modal. Puedes omitir esta sección.
    ?>
    </body>
</html>
