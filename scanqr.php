<?php
// htdocs/scanqr.php - Página para escanear códigos QR de invitación

// --- === Includes y Autenticación === ---
require_once __DIR__ . '/includes/auth.php'; // Contiene funciones de autenticación y usuario.
require_once __DIR__ . '/includes/invitation_handler.php'; // Contiene funciones para manejar invitaciones (aunque no se usan directamente aquí, se incluye por si acaso).
require_once __DIR__ . '/includes/config.php'; // Contiene la configuración (URL_BASE, roles permitidos).

// Inicia la sesión si no está iniciada.
start_session_if_not_started();

// Obtiene los datos del usuario logueado.
$current_user = gets_current_user();
$user_role = $current_user['role'] ?? 'guest';

// --- === Inicialización de variables de roles permitidos con valores por defecto === ---
// Roles permitidos para acceder a esta página de escaneo.
// Puedes definir $scan_allowed_roles en config.php si prefieres.
$scan_allowed_roles = $scan_allowed_roles ?? ['seguridad', 'administrador', 'developer'];


// --- === Lógica de Autorización de Página === ---
// Verifica si el usuario NO está logueado O si su rol NO está en la lista de roles permitidos para escanear.
if (!is_logged_in() || !in_array($user_role, $scan_allowed_roles)) {
    // Si no tiene permiso, redirige a otra página.
    redirect('index.php'); // Asegúrate que la ruta sea correcta.
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Escanear QR</title>
    <link rel="stylesheet" href="css/styles.css"> <style>
        /* Estilos específicos para la página de escaneo */
        .scan-container {
            max-width: 500px; /* Limita el ancho del contenedor del escáner */
            margin: 20px auto; /* Centra el contenedor */
            padding: 20px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            text-align: center; /* Centra el contenido dentro del contenedor */
        }

        #reader {
            width: 100%; /* El lector ocupará todo el ancho del contenedor */
            max-width: 400px; /* Limita el ancho máximo del lector */
            margin: 0 auto; /* Centra el lector si el contenedor es más ancho */
            border: 1px solid #ccc; /* Borde alrededor del área de escaneo */
            border-radius: 4px;
            overflow: hidden; /* Asegura que el video no se salga del borde redondeado */
        }

        /* Estilos para el área de video dentro del lector */
        #reader video {
             width: 100% !important; /* Asegura que el video llene el contenedor */
             height: auto !important; /* Mantiene la proporción */
        }

        #scan-result {
            margin-top: 20px;
            padding: 15px;
            border-radius: 4px;
            min-height: 50px; /* Altura mínima para mostrar mensajes */
            text-align: center;
            /* Usaremos clases dinámicas para el fondo/color */
        }

        /* Clases para el fondo y color del mensaje de resultado (de styles.css) */
        /* .status-valid-bg, .status-expired-bg, etc. */

        .invitation-details-display {
             margin-top: 15px;
             padding-top: 15px;
             border-top: 1px solid #eee;
             text-align: left; /* Detalles alineados a la izquierda */
        }

        .invitation-details-display p {
             margin: 5px 0;
        }

        .invitation-details-display .detail-label {
            font-weight: bold;
            display: inline-block;
            width: 120px; /* Ajusta para alinear etiquetas */
            margin-right: 10px;
            text-align: right;
        }

        .invitation-details-display .detail-value {
             display: inline-block;
        }

        /* Estilos para botones de acción en la página de escaneo (si los hay) */
        .scan-actions {
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: center;
            gap: 10px;
            flex-wrap: wrap;
        }
         .scan-actions .btn { /* Usamos la clase .btn de styles.css */
             margin: 0; /* Anula márgenes si el contenedor flex usa gap */
         }

         /* Estilos para el botón de aprobación si se muestra aquí */
         #approve-scan-btn {
             /* Puedes definir un estilo específico o usar .btn .btn-success */
             background-color: #28a745;
             color: white;
             border: none;
             border-radius: 4px;
             cursor: pointer;
             padding: 10px 20px;
             font-size: 16px;
         }
         #approve-scan-btn:hover {
              background-color: #218838;
         }
         #approve-scan-btn:disabled {
             background-color: #ccc;
             cursor: not-allowed;
         }


    </style>
</head>
<body>
    <div class="header">
         <h2>Escanear Código QR</h2>
         <a href="logout.php" class="logout-btn">Salir</a>
         <a href="index.php" class="back-btn">Home</a>
    </div>

    <div class="container scan-container">
        <div id="reader"></div>

        <div id="scan-result" class="message status-info-bg">Esperando escaneo...</div>

        <div id="invitation-details-display" class="invitation-details-display" style="display: none;">
            </div>

         <div id="scan-action-buttons" class="scan-actions" style="display: none;">
             <button id="approve-scan-btn" class="btn">Aprobar Invitación</button>
             </div>

    </div> <div class="footer">
        <span>Usuario actual: <?php echo htmlspecialchars($current_user['username'] ?? 'Invitado'); ?> (Rol: <?php echo htmlspecialchars($user_role); ?>)</span>
    </div>

    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>

    <script>
        // Espera a que el DOM esté completamente cargado
        document.addEventListener('DOMContentLoaded', function() {
            // Referencias a elementos del DOM
            const qrCodeReader = new Html5Qrcode("reader"); // Inicializa el lector en el div con id="reader"
            const scanResultDiv = document.getElementById('scan-result'); // Div para mensajes de resultado
            const invitationDetailsDiv = document.getElementById('invitation-details-display'); // Div para detalles de invitación
            const actionButtonsDiv = document.getElementById('scan-action-buttons'); // Div para botones de acción
            const approveScanBtn = document.getElementById('approve-scan-btn'); // Botón de aprobar

            let lastScannedCode = null; // Variable para evitar escanear el mismo código múltiples veces seguidas

            // --- === Configuración del Lector QR === ---
            const config = {
                fps: 10, // Cuadros por segundo para el escaneo
                qrbox: { width: 250, height: 250 }, // Tamaño del área de escaneo (cuadrado)
                rememberLastUsedCamera: true, // Recordar la última cámara usada
                supportedScanTypes: [Html5QrcodeSupportedScanTypes.QR_CODE], // Solo escanear QR

                // Preferencias de cámara: Preferir la cámara trasera/ambiental
                // Esto ayuda a seleccionar la cámara principal en teléfonos con múltiples lentes.
                facingModeConfig: {
                     facingMode: "environment" // 'user' para cámara frontal, 'environment' para trasera
                }
            };

            // --- === Función para manejar el resultado del escaneo === ---
            const onScanSuccess = (decodedText, decodedResult) => {
                // decodedText es el contenido del QR escaneado (ahora será solo el código)
                console.log(`QR escaneado: ${decodedText}`);

                // Extraer solo el código de invitación
                // Si el QR contiene SOLO el código, decodedText ya es el código.
                // Si por alguna razón (QR antiguos) aún contiene la URL, puedes intentar parsearla:
                let invitationCode = decodedText;
                try {
                    const url = new URL(decodedText);
                    // Si es una URL, intenta obtener el parámetro 'code'
                    if (url.searchParams.has('code')) {
                        invitationCode = url.searchParams.get('code');
                         console.warn('Se escaneó una URL antigua. Extrayendo código:', invitationCode);
                    } else {
                         // Si es una URL pero sin parámetro 'code', no es una invitación válida
                         displayResult('Error: Código QR inválido (no es un código o URL de invitación).', 'error');
                         invitationDetailsDiv.style.display = 'none';
                         actionButtonsDiv.style.display = 'none';
                         return; // Salir de la función si no es válido
                    }
                } catch (e) {
                    // Si no es una URL válida, asumimos que es solo el código
                    console.log('El contenido escaneado no es una URL, asumiendo que es solo el código.');
                    // invitationCode ya es decodedText
                }

                // Evitar procesar el mismo código repetidamente
                if (invitationCode === lastScannedCode) {
                    console.log('Mismo código escaneado de nuevo, ignorando.');
                    return;
                }
                lastScannedCode = invitationCode; // Actualizar el último código escaneado

                // Detener el escáner temporalmente para evitar múltiples detecciones
                qrCodeReader.pause(); // Pausa el escaneo

                // Mostrar mensaje de verificación
                displayResult('Verificando código...', 'info');
                invitationDetailsDiv.style.display = 'none'; // Ocultar detalles anteriores
                actionButtonsDiv.style.display = 'none'; // Ocultar botones anteriores


                // --- === Enviar el código al servidor para verificación (AJAX Fetch) === ---
                const verificationUrl = 'includes/invitation_handler.php'; // Endpoint en el servidor

                const formData = new FormData();
                formData.append('action', 'verify'); // Indicar la acción de verificación
                formData.append('code', invitationCode); // Enviar el código escaneado

                fetch(verificationUrl, {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    // Reanudar el escaneo después de recibir la respuesta (independientemente del éxito de la respuesta)
                    // Usa un pequeño timeout para dar tiempo a que el UI se actualice si es necesario
                    setTimeout(() => { qrCodeReader.resume(); lastScannedCode = null; }, 2000); // Reanudar después de 2 segundos y resetear lastScannedCode

                    if (!response.ok) {
                        // Si la respuesta HTTP no es 2xx (ej: 404, 500)
                        throw new Error(`Error HTTP: ${response.status} ${response.statusText}`);
                    }
                    return response.json(); // Parsea la respuesta JSON
                })
                .then(data => {
                    // Se ejecuta si la solicitud fue exitosa y la respuesta es JSON válido
                    console.log('Respuesta del servidor (verificación):', data);

                    // Actualizar el UI con el resultado de la verificación
                    if (data.success) {
                        // Invitación válida
                        displayResult(data.message, 'valid'); // Usar clase 'valid' para verde
                        displayInvitationDetails(data.details); // Mostrar detalles
                        // Mostrar botón de aprobar si el estado es 'pendiente'
                        if (data.status === 'pendiente') {
                             actionButtonsDiv.style.display = 'flex'; // Mostrar el contenedor de botones
                             // Aquí podrías habilitar o deshabilitar botones específicos
                             // approveScanBtn.disabled = false; // Habilitar el botón de aprobar
                         } else {
                             actionButtonsDiv.style.display = 'none'; // Ocultar botones si no está pendiente
                         }

                    } else {
                        // Invitación no válida (no encontrada, expirada, cancelada, etc.)
                        // Usar la clase de estado proporcionada por el servidor para el fondo/color
                        const statusClass = data.status ? `status-${data.status}-bg` : 'status-error-bg';
                        displayResult(data.message, statusClass); // Usar clase de estado del servidor
                        if (data.details) {
                             displayInvitationDetails(data.details); // Mostrar detalles si están disponibles
                         } else {
                             invitationDetailsDiv.style.display = 'none'; // Ocultar detalles si no hay
                         }
                         actionButtonsDiv.style.display = 'none'; // Ocultar botones de acción

                    }
                })
                .catch(error => {
                    // Se ejecuta si hay un error en la solicitud fetch o en el servidor
                    console.error('Error en la verificación Fetch:', error);
                    displayResult('Error de comunicación al verificar la invitación.', 'error-bg'); // Usar clase de error
                    invitationDetailsDiv.style.display = 'none';
                    actionButtonsDiv.style.display = 'none';
                     // Asegurarse de que el escáner se reanude incluso si hay un error de fetch
                     setTimeout(() => { qrCodeReader.resume(); lastScannedCode = null; }, 2000);
                });
            };

            // --- === Función para manejar errores del escaneo === ---
            const onScanError = (errorMessage) => {
                // console.warn(`Error de escaneo: ${errorMessage}`); // Puedes loguear errores si DEBUG_MODE está activo
                // Evitar mostrar errores constantes si no se detecta nada
            };

            // --- === Función para mostrar mensajes de resultado === ---
            function displayResult(message, statusClass) {
                // Limpiar clases de estado anteriores
                scanResultDiv.className = ''; // Remueve todas las clases
                scanResultDiv.classList.add('message'); // Añade la clase base 'message'
                scanResultDiv.classList.add(statusClass); // Añade la clase de estado (ej: 'status-valid-bg', 'status-error-bg')
                scanResultDiv.textContent = message; // Establece el texto del mensaje
            }

            // --- === Función para mostrar detalles de la invitación === ---
            function displayInvitationDetails(details) {
                if (!details) {
                    invitationDetailsDiv.style.display = 'none';
                    return;
                }
                invitationDetailsDiv.innerHTML = `
                    <div class="detail-item"><span class="detail-label">Código:</span> <span class="detail-value">${details.code}</span></div>
                    <div class="detail-item"><span class="detail-label">Invitado:</span> <span class="detail-value">${details.invitado_nombre}</span></div>
                    <div class="detail-item"><span class="detail-label">Anfitrión:</span> <span class="detail-value">${details.anfitrion_name}</span></div>
                    <div class="detail-item"><span class="detail-label">Lote:</span> <span class="detail-value">${details.anfitrion_lote}</span></div>
                    <div class="detail-item"><span class="detail-label">Creación:</span> <span class="detail-value">${details.fecha_creacion}</span></div>
                    <div class="detail-item"><span class="detail-label">Expira:</span> <span class="detail-value">${details.fecha_expiracion}</span></div>
                    <div class="detail-item"><span class="detail-label">Estado:</span> <span class="detail-value"><span class="status-${details.status}">${details.status.charAt(0).toUpperCase() + details.status.slice(1)}</span></span></div>
                    <div class="detail-item"><span class="detail-label">Validación:</span> <span class="detail-value">${details.fecha_aprobacion}</span></div>
                `;
                invitationDetailsDiv.style.display = 'block'; // Mostrar el contenedor de detalles
            }

            // --- === Event Listener para el botón Aprobar === ---
            // Si decides implementar la aprobación desde esta página
            approveScanBtn.addEventListener('click', function() {
                 if (lastScannedCode) {
                     // Implementar lógica para enviar solicitud de aprobación al servidor
                     console.log("Botón Aprobar clickeado para código:", lastScannedCode);
                     // Aquí llamarías a otra función fetch para enviar action='approve' y el código
                     // updateInvitationStatus(lastScannedCode, 'aprobado'); // Ejemplo
                 }
            });


            // --- === Iniciar el Lector QR === ---
            // Esto iniciará la cámara y el proceso de escaneo.
            qrCodeReader.start(
                { facingMode: { exact: "environment" } }, // Preferir cámara trasera (más específica)
                config,
                onScanSuccess,
                onScanError
            ).catch((err) => {
                // Manejar errores al iniciar la cámara (ej: permisos denegados)
                console.error(`Error al iniciar el lector QR: ${err}`);
                displayResult('Error al iniciar la cámara. Asegúrate de dar permisos.', 'error-bg');
                 invitationDetailsDiv.style.display = 'none';
                 actionButtonsDiv.style.display = 'none';
            });

             // Opcional: Detener el escáner cuando el usuario sale de la página
             // window.addEventListener('beforeunload', function() {
             //     qrCodeReader.stop().catch(err => console.error("Error al detener el lector QR:", err));
             // });

        }); // Fin de DOMContentLoaded
    </script>

</body>
</html>
