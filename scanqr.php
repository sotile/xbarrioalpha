<?php
// htdocs/scanqr.php - Página para escanear códigos QR de invitación (Usando ZXing)

date_default_timezone_set('America/Argentina/Buenos_Aires');

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/invitation_handler.php';
require_once __DIR__ . '/includes/config.php';

start_session_if_not_started();

$current_user = gets_current_user();
$user_role = $current_user['role'] ?? 'guest';

global $scan_allowed_roles;
$scan_allowed_roles = $scan_allowed_roles ?? ['seguridad', 'administrador', 'developer'];

if (!is_logged_in() || !in_array($user_role, $scan_allowed_roles)) {
    redirect('index.php');
}

$display_username = htmlspecialchars($current_user['username'] ?? 'Invitado');
$display_user_role = htmlspecialchars($user_role);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Escanear QR - XBarrio (ZXing)</title>
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="css/scanqr_styles.css"> </head>
<body>
    <div class="header">
         <h2>Escanear Código QR</h2>
         <a href="logout.php" class="logout-btn">Salir</a>
         <a href="index.php" class="back-btn">Home</a>
    </div>

    <div class="container scan-container">
        <div id="video-container">
             <video id="scanner"></video>
        </div>

        <div id="scan-error"></div>

        <div id="scan-result" class="message status-info-bg">Esperando escaneo...</div>

        <div id="invitation-details-display" class="invitation-details-display">
            </div>

         <div id="scan-action-buttons" class="scan-actions">
             <button id="approve-scan-btn" class="btn">Aprobar Invitación</button>
             </div>

         <button id="scan-again-btn" class="btn">Escanear de Nuevo</button>

        <div id="expiration-debug-info">
            <h4>Información de Depuración de Expiración:</h4>
            </div>

    </div> <div class="footer">
        <span>Usuario actual: <?php echo $display_username; ?> (Rol: <?php echo $display_user_role; ?>)</span>
    </div>

    <script src="js/zxing.min.js"></script>

    <script>

        document.addEventListener('DOMContentLoaded', function() {

            const videoContainer = document.getElementById('video-container');
            const videoElement = document.getElementById('scanner');
            const scanResultDiv = document.getElementById('scan-result');
            const scanErrorDiv = document.getElementById('scan-error');
            const invitationDetailsDiv = document.getElementById('invitation-details-display');
            const actionButtonsDiv = document.getElementById('scan-action-buttons');
            const approveScanBtn = document.getElementById('approve-scan-btn');
             const scanAgainBtn = document.getElementById('scan-again-btn');
             const expirationDebugDiv = document.getElementById('expiration-debug-info'); // Asegúrate de tener esta referencia

            let lastScannedCode = null;
            let codeReader = null;
             let lastVerifiedInvitationCode = null;

            console.log('DEBUG SCANQR (ZXing): DOMContentLoaded disparado. Preparando ZXing...');
            displayResult('Preparando escáner...', 'status-info-bg');
            scanErrorDiv.textContent = '';
            invitationDetailsDiv.style.display = 'none';
             actionButtonsDiv.style.display = 'none';
             scanAgainBtn.style.display = 'none';
             expirationDebugDiv.style.display = 'none'; // Ocultar debug al inicio


            if (typeof ZXing === 'undefined' || typeof ZXing.BrowserMultiFormatReader === 'undefined') {
                 const errorMsg = 'ERROR SCANQR (ZXing): Librería ZXing no cargada correctamente.';
                 console.error(errorMsg);
                 displayResult(errorMsg, 'status-error-bg');
                 scanErrorDiv.textContent = errorMsg;
                 videoContainer.style.display = 'none';
                 scanAgainBtn.style.display = 'none';
                 return;
            }

            codeReader = new ZXing.BrowserMultiFormatReader();
            console.log('DEBUG SCANQR (ZXing): ZXing CodeReader creado.');

            function startScanner() {
                 console.log('DEBUG SCANQR (ZXing): Intentando iniciar escáner...');
                 displayResult('Iniciando escáner...', 'status-info-bg');
                 scanErrorDiv.textContent = '';
                 invitationDetailsDiv.style.display = 'none';
                 actionButtonsDiv.style.display = 'none';
                 scanAgainBtn.style.display = 'none';
                 expirationDebugDiv.style.display = 'none'; // Ocultar debug al iniciar escaneo
                 videoContainer.style.display = 'block';

                 codeReader.listVideoInputDevices()
                 .then((videoInputDevices) => {
                      console.log('DEBUG SCANQR (ZXing): Dispositivos de video encontrados:', videoInputDevices);

                      if (videoInputDevices.length > 0) {
                           let selectedDeviceId = videoInputDevices[0].deviceId;
                           let selectedDeviceLabel = videoInputDevices[0].label || 'Cámara 1';

                           // Intentar encontrar la cámara trasera/ambiental por etiqueta
                           const rearCamera = videoInputDevices.find(device =>
                                device.label && (device.label.toLowerCase().includes('back') || device.label.toLowerCase().includes('environment') || device.label.toLowerCase().includes('rear'))
                           );

                           if (rearCamera) {
                                selectedDeviceId = rearCamera.deviceId;
                                selectedDeviceLabel = rearCamera.label || 'Cámara Trasera';
                                console.log('DEBUG SCANQR (ZXing): Seleccionada cámara trasera/ambiental:', selectedDeviceLabel);
                           } else {
                                // Si no se encuentra cámara trasera por etiqueta, usar la primera detectada
                                console.warn('DEBUG SCANQR (ZXing): No se encontró cámara trasera/ambiental por etiqueta. Usando la primera cámara detectada:', selectedDeviceLabel);
                           }

                           displayResult(`Usando ${selectedDeviceLabel}. Esperando escaneo...`, 'status-info-bg');

                           console.log('DEBUG SCANQR (ZXing): Llamando a decodeFromVideoDevice con deviceId:', selectedDeviceId, 'y videoElementId: scanner');

                           codeReader.decodeFromVideoDevice(selectedDeviceId, 'scanner', (result, err) => {

                                if (result) {
                                     const scannedText = result.text;
                                     console.log('DEBUG SCANQR (ZXing): Código QR detectado:', scannedText);

                                     if (scannedText === lastScannedCode) {
                                         console.log('DEBUG SCANQR (ZXing): Mismo código escaneado de nuevo, ignorando.');
                                         return;
                                     }
                                     lastScannedCode = scannedText;

                                     codeReader.reset();
                                     videoContainer.style.display = 'none';

                                     let invitationCode = scannedText;
                                     try {
                                         const url = new URL(scannedText);
                                         if (url.searchParams.has('code')) {
                                             invitationCode = url.searchParams.get('code');
                                              console.warn('DEBUG SCANQR (ZXing): Se escaneó una URL. Extrayendo código:', invitationCode);
                                         } else {
                                              console.warn('DEBUG SCANQR (ZXing): URL escaneada sin parámetro "code". Inválida.');
                                              displayResult('Error: Código QR inválido (no es un código o URL de invitación reconocida).', 'status-error-bg');
                                              scanErrorDiv.textContent = '';
                                              invitationDetailsDiv.style.display = 'none';
                                              actionButtonsDiv.style.display = 'none';
                                              expirationDebugDiv.style.display = 'none'; // Ocultar debug en caso de QR inválido
                                              lastVerifiedInvitationCode = null;
                                              scanAgainBtn.style.display = 'block';
                                              return;
                                         }
                                     } catch (e) {
                                         console.log('DEBUG SCANQR (ZXing): El contenido escaneado no es una URL válida, asumiendo que es solo el código.');
                                     }

                                     if (!invitationCode) {
                                          console.warn('DEBUG SCANQR (ZXing): Código de invitación extraído está vacío.');
                                          displayResult('Error: No se pudo extraer un código válido del QR.', 'status-error-bg');
                                          scanErrorDiv.textContent = '';
                                          invitationDetailsDiv.style.display = 'none';
                                          actionButtonsDiv.style.display = 'none';
                                          expirationDebugDiv.style.display = 'none'; // Ocultar debug en caso de código vacío
                                          lastVerifiedInvitationCode = null;
                                          scanAgainBtn.style.display = 'block';
                                          return;
                                     }

                                     displayResult('Verificando código...', 'status-info-bg');
                                     scanErrorDiv.textContent = '';
                                     invitationDetailsDiv.style.display = 'none';
                                     actionButtonsDiv.style.display = 'none';
                                     scanAgainBtn.style.display = 'none';
                                     expirationDebugDiv.style.display = 'none'; // Ocultar debug mientras verifica

                                     const verificationUrl = 'includes/invitation_handler.php';
                                     const formData = new FormData();
                                     formData.append('action', 'verify');
                                     formData.append('code', invitationCode);

                                     fetch(verificationUrl, {
                                         method: 'POST',
                                         body: formData
                                     })
                                     .then(response => {
                                         if (!response.ok) {
                                             throw new Error(`Error HTTP: ${response.status} ${response.statusText}`);
                                         }
                                         return response.json();
                                     })
                                     .then(data => {
                                         console.log('DEBUG SCANQR (ZXing): Respuesta del servidor (verificación):', data);

                                          if (data.debug_expiration) {
                                               displayExpirationDebug(data.debug_expiration);
                                          } else {
                                               expirationDebugDiv.style.display = 'none';
                                          }


                                         if (data.success) {
                                             displayResult(data.message, 'status-valid-bg');
                                             displayInvitationDetails(data.details);
                                             lastVerifiedInvitationCode = invitationCode;

                                             if (data.status === 'pendiente') {
                                                  actionButtonsDiv.style.display = 'flex';
                                                  approveScanBtn.disabled = false;
                                              } else {
                                                  actionButtonsDiv.style.display = 'none';
                                                  approveScanBtn.disabled = true;
                                              }

                                         } else {
                                             const statusClass = data.status ? `status-${data.status}-bg` : 'status-error-bg';
                                             displayResult(data.message, statusClass);
                                             if (data.details) {
                                                  displayInvitationDetails(data.details);
                                              } else {
                                                  invitationDetailsDiv.style.display = 'none';
                                              }
                                              actionButtonsDiv.style.display = 'none';
                                              approveScanBtn.disabled = true;
                                              lastVerifiedInvitationCode = null;
                                         }
                                         scanAgainBtn.style.display = 'block';
                                     })
                                     .catch(error => {
                                         console.error('DEBUG SCANQR (ZXing): Error en la verificación Fetch:', error);
                                         displayResult('Error de comunicación al verificar la invitación.', 'status-error-bg');
                                         scanErrorDiv.textContent = '';
                                         invitationDetailsDiv.style.display = 'none';
                                         actionButtonsDiv.style.display = 'none';
                                         approveScanBtn.disabled = true;
                                         lastVerifiedInvitationCode = null;
                                         expirationDebugDiv.style.display = 'none'; // Ocultar debug en caso de error de fetch
                                         scanAgainBtn.style.display = 'block';
                                     });

                                 }

                                 if (err && !(err instanceof ZXing.NotFoundException)) {
                                     console.error('DEBUG SCANQR (ZXing): Error de decodificación:', err);
                                     scanErrorDiv.textContent = '';
                                 } else {
                                      scanErrorDiv.textContent = '';
                                 }
                           });

                      } else {
                           const noCameraMsg = 'Error: No se encontraron cámaras disponibles.';
                           console.error('DEBUG SCANQR (ZXing): No se encontraron dispositivos de video.');
                           displayResult(noCameraMsg, 'status-error-bg');
                           scanErrorDiv.textContent = noCameraMsg;
                           videoContainer.style.display = 'none';
                      }
                 })
                 .catch((err) => {
                      const generalErrorMsg = `Error general al iniciar cámara/escáner: ${err}`;
                      console.error('DEBUG SCANQR (ZXing): Error general al iniciar:', err);
                      displayResult(generalErrorMsg, 'status-error-bg');
                      scanErrorDiv.textContent = generalErrorMsg;
                      videoContainer.style.display = 'none';
                 });
            }

            function displayResult(message, statusClass) {
                scanResultDiv.className = '';
                scanResultDiv.classList.add('message');
                scanResultDiv.classList.add(statusClass);
                scanResultDiv.textContent = message;
            }

            function displayInvitationDetails(details) {
                if (!details) {
                    invitationDetailsDiv.style.display = 'none';
                    return;
                }
                const displayStatus = details.status ? details.status.charAt(0).toUpperCase() + details.status.slice(1) : 'Desconocido';
                invitationDetailsDiv.innerHTML = `
                    <div class="detail-item"><span class="detail-label">Código:</span> <span class="detail-value">${details.code}</span></div>
                    <div class="detail-item"><span class="detail-label">Invitado:</span> <span class="detail-value">${details.invitado_nombre}</span></div>
                    <div class="detail-item"><span class="detail-label">Anfitrión:</span> <span class="detail-value">${details.anfitrion_name}</span></div>
                    <div class="detail-item"><span class="detail-label">Lote:</span> <span class="detail-value">${details.anfitrion_lote}</span></div>
                    <div class="detail-item"><span class="detail-label">Creación:</span> <span class="detail-value">${details.fecha_creacion}</span></div>
                    <div class="detail-item"><span class="detail-label">Expira:</span> <span class="detail-value">${details.fecha_expiracion}</span></div>
                    <div class="detail-item"><span class="detail-label">Estado:</span> <span class="detail-value"><span class="status-${details.status}">${displayStatus}</span></span></div>
                    <div class="detail-item"><span class="detail-label">Validación:</span> <span class="detail-value">${details.fecha_aprobacion}</span></div>
                `;
                invitationDetailsDiv.style.display = 'block';
            }

             function displayExpirationDebug(debugInfo) {
                 const expirationDebugDiv = document.getElementById('expiration-debug-info');
                 if (!debugInfo || !expirationDebugDiv) {
                     if(expirationDebugDiv) expirationDebugDiv.style.display = 'none';
                     return;
                 }

                 expirationDebugDiv.innerHTML = `
                     <h4>Información de Depuración de Expiración:</h4>
                     <p><strong>Invitación encontrada:</strong> ${debugInfo.invitation_found ? 'Sí' : 'No'}</p>
                     ${debugInfo.invitation_found ? `
                         <p><strong>Timestamp Actual (Servidor):</strong> ${debugInfo.current_time_ts} (${debugInfo.current_time_formatted})</p>
                         <p><strong>Timestamp Expiración (JSON):</strong> ${debugInfo.expiration_timestamp_ts} (${debugInfo.expiration_timestamp_formatted})</p>
                         <p><strong>Comparación ($expiration_ts < $current_ts):</strong> ${debugInfo.is_expired_check}</p>
                     ` : ''}
                 `;
                 expirationDebugDiv.style.display = 'block';
             }


            function approveInvitation(code) {
                 if (!code) {
                      console.error("DEBUG SCANQR (ZXing): No hay código de invitación para aprobar.");
                      displayResult('Error: No hay invitación seleccionada para aprobar.', 'status-error-bg');
                      return;
                 }

                 displayResult('Enviando aprobación...', 'status-info-bg');
                 approveScanBtn.disabled = true;

                 const approvalUrl = 'includes/invitation_handler.php';

                 const formData = new FormData();
                 formData.append('action', 'approve');
                 formData.append('code', code);

                 fetch(approvalUrl, {
                     method: 'POST',
                     body: formData
                 })
                 .then(response => {
                     if (!response.ok) {
                         throw new Error(`Error HTTP al aprobar: ${response.status} ${response.statusText}`);
                     }
                     return response.json();
                 })
                 .then(data => {
                     console.log('DEBUG SCANQR (ZXing): Respuesta del servidor (aprobación):', data);

                     if (data.success) {
                         displayResult(data.message, 'status-valid-bg');
                         displayInvitationDetails(data.details);
                         actionButtonsDiv.style.display = 'none';
                     } else {
                         const statusClass = data.status ? `status-${data.status}-bg` : 'status-error-bg';
                         displayResult(data.message, statusClass);
                         if (data.details) {
                              displayInvitationDetails(data.details);
                         }
                         approveScanBtn.disabled = true;
                     }
                 })
                 .catch(error => {
                     console.error('DEBUG SCANQR (ZXing): Error en la aprobación Fetch:', error);
                     displayResult('Error de comunicación al aprobar la invitación.', 'status-error-bg');
                     approveScanBtn.disabled = false;
                 });
            }

            if (approveScanBtn) {
                 approveScanBtn.addEventListener('click', function() {
                     console.log("DEBUG SCANQR (ZXing): Botón Aprobar clickeado.");
                     approveInvitation(lastVerifiedInvitationCode);
                 });
            } else {
                 console.error("DEBUG SCANQR (ZXing): Botón #approve-scan-btn no encontrado.");
            }

             if (scanAgainBtn) {
                  scanAgainBtn.addEventListener('click', function() {
                      console.log('DEBUG SCANQR (ZXing): Botón Escanear de Nuevo clickeado.');
                      lastScannedCode = null;
                      lastVerifiedInvitationCode = null;
                      //startScanner();
                      window.location.reload()
                  });
             } else {
                  console.error("DEBUG SCANQR (ZXing): Botón #scan-again-btn no encontrado.");
             }

            startScanner();

             window.addEventListener('beforeunload', () => {
                 console.log('DEBUG SCANQR (ZXing): Deteniendo lector antes de salir.');
                 if (codeReader) {
                      codeReader.reset();
                 }
             });

        });
    </script>

</body>
</html>
