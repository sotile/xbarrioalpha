// htdocs/js/app.js

// --- Variables para Referencias a Elementos (Declaradas con let en scope superior) ---
// Serán ASIGNADAS dentro del DOMContentLoaded para asegurar que los elementos existen.
// Declaradas aquí para ser accesibles en múltiples funciones y listeners.
let videoElement = null; // Referencia al elemento <video id="scanner">
let scannerContainer = null; // Referencia al contenedor <div id="scanner-container">
let statusDisplay = null; // Referencia al elemento para mensajes de estado
let invitationDetailsDiv = null; // Referencia al div que muestra los detalles de la invitación
let approveBtn = null; // Referencia al botón de aprobar entrada

// Referencias específicas para la página de Invitar
let qrcodeDiv = null; // Referencia al div donde se muestra el QR
let whatsappShareBtn = null; // Referencia al botón de compartir por WhatsApp

// Referencia específica para la página de Listado
let invitationsTable = null; // Referencia a la tabla de invitaciones

// Instancia del lector ZXing (declarada aquí para ser accesible en beforeunload)
let codeReaderInstance = null;

// Variable para almacenar el código de la invitación actualmente mostrada en seguridad
let currentScannedCode = null;


// --- Funciones de Interfaz (Usadas por varias páginas) ---
// Estas funciones usan las variables declaradas arriba. Asegurarse de llamarlas
// SOLO después de que las variables hayan sido ASIGNADAS dentro de DOMContentLoaded.

/**
 * Actualiza el mensaje y estilo del display de estado.
 * @param {string} message - El mensaje a mostrar.
 * @param {string} type - Tipo de mensaje ('valid', 'expired', 'aprobado', 'error', 'info').
 */
function updateStatusDisplay(message, type) {
    // Verifica que statusDisplay haya sido asignado correctamente en DOMContentLoaded
    if (statusDisplay) {
        statusDisplay.textContent = message;
        statusDisplay.className = 'status-message'; // Reset classes
        if (type) {
            statusDisplay.classList.add('status-' + type); // valid, expired, aprobado, error, info
        }
        // Asegurarse de que el display de estado es visible
        statusDisplay.style.display = 'block';
    } else {
        console.warn('updateStatusDisplay llamado pero statusDisplay (#status-display) no fue encontrado/asignado en DOMContentLoaded.');
    }
}

/**
 * Muestra u oculta los detalles de la invitación escaneada.
 * @param {object|null} details - Objeto con los detalles de la invitación o null para ocultar.
 */
function updateInvitationDetails(details) {
    // Verifica que invitationDetailsDiv haya sido asignado correctamente en DOMContentLoaded
    if (invitationDetailsDiv) {
        if (details) {
            // Asume que los elementos hijos existen dentro de invitationDetailsDiv
            const guestNameSpan = invitationDetailsDiv.querySelector('#detail-guest-name');
            const anfitrionNameSpan = invitationDetailsDiv.querySelector('#detail-anfitrion-name');
            const anfitrionLoteSpan = invitationDetailsDiv.querySelector('#detail-anfitrion-lote');
            const expirationSpan = invitationDetailsDiv.querySelector('#detail-expiration');

            if (guestNameSpan) guestNameSpan.textContent = details.invitado_nombre ?? 'N/A';
            if (anfitrionNameSpan) anfitrionNameSpan.textContent = details.anfitrion_name ?? 'N/A';
            if (anfitrionLoteSpan) anfitrionLoteSpan.textContent = details.anfitrion_lote ?? 'N/A';
            if (expirationSpan) expirationSpan.textContent = details.fecha_expiracion ?? 'N/A';


            invitationDetailsDiv.style.display = 'block'; // Muestra el div de detalles
        } else {
            invitationDetailsDiv.style.display = 'none'; // Oculta el div de detalles
        }
    } else {
        console.warn('updateInvitationDetails llamado pero invitationDetailsDiv (#invitation-details) no fue encontrado/asignado en DOMContentLoaded.');
    }
}

/**
 * Muestra u oculta el botón de aprobar.
 * @param {boolean} show - true para mostrar, false para ocultar.
 */
function showApproveButton(show) {
    // Verifica que approveBtn haya sido asignado correctamente en DOMContentLoaded
    if (approveBtn) {
        approveBtn.style.display = show ? 'block' : 'none'; // Muestra u oculta el botón
        approveBtn.disabled = !show; // Desactiva el botón si está oculto
    } else {
        console.warn('showApproveButton llamado pero approveBtn (#approve-btn) no fue encontrado/asignado en DOMContentLoaded.');
    }
}


// --- Funciones de Interacción AJAX (Página Seguridad) ---

/**
 * Verifica un código de invitación enviando una solicitud AJAX al backend.
 * Esta función ASUME que los elementos de UI (statusDisplay, invitationDetailsDiv, approveBtn)
 * ya fueron asignados en el listener DOMContentLoaded.
 * @param {string} code - El código único de la invitación.
 */
function checkInvitation(code) {
    currentScannedCode = null; // Reset on new check
    // Actualiza la interfaz al inicio del proceso de verificación
    updateStatusDisplay('Verificando invitación...', 'info');
    updateInvitationDetails(null); // Oculta detalles anteriores
    showApproveButton(false); // Oculta botón de aprobar anterior

    console.log('DEBUG_APP_JS: checkInvitation llamado con código:', code);

    // La ruta al endpoint PHP (asegúrate que sea correcta desde la URL actual de scanqr.php)
    // Si scanqr.php está en la raíz y check_invitation.php está en includes/, la ruta es 'includes/...'
    const checkUrl = `includes/check_invitation.php?code=${encodeURIComponent(code)}`;

    fetch(checkUrl)
        .then(response => {
            console.log('DEBUG_APP_JS: Respuesta recibida de check_invitation.php', response);
            // Verificar si la respuesta es OK (estado HTTP 200-299)
            if (!response.ok) {
                // Si no es OK, intentar leer el JSON de error del servidor
                // Si falla leer JSON, crear un error genérico
                return response.json().catch(() => {
                    throw new Error('Error de red o servidor al verificar. Estado: ' + response.status);
                });
            }
            // Si es OK, parsear el JSON de éxito
            return response.json();
        })
        .then(data => {
            console.log('DEBUG_APP_JS: Datos JSON de verificación:', data);
            if (data.success) {
                updateInvitationDetails(data.invitation); // Muestra los detalles si son válidos
                currentScannedCode = data.invitation.code; // Almacenar código válido

                if (data.isValid) {
                    updateStatusDisplay(data.message || 'Invitación Válida', 'valid');
                    // Solo muestra aprobar si es válido Y su estado es pendiente
                    if (data.invitation.status === 'pendiente') {
                        showApproveButton(true); // Muestra el botón de aprobar
                    } else {
                        // Es válido (no expirado) pero ya fue procesado (aprobado, o quizás 'usado', 'cancelado')
                        const statusType = data.invitation.status === 'aprobado' ? 'approved' : 'expired'; // Estilo basado en estado final
                        updateStatusDisplay(data.message || 'Invitación Válida, pero ya está ' + data.invitation.status, statusType);
                        showApproveButton(false); // Oculta el botón de aprobar si ya está procesada
                    }
                } else {
                    // No es válido (probablemente expirado, usado, cancelado o status no pendiente)
                    // updateInvitationDetails(data.invitation); // Opcional: mostrar detalles aunque sea inválida? Depende del requerimiento.
                    const statusType = data.invitation.status === 'aprobado' ? 'approved' : 'expired'; // Estilo
                    updateStatusDisplay(data.message || 'Invitación Inválida o Expirada.', statusType);
                    updateInvitationDetails(null); // Oculta detalles si la invitación no es válida
                    showApproveButton(false); // Oculta el botón de aprobar si no es válida
                }
            } else {
                // La respuesta del servidor indica fallo en la lógica (ej. código no encontrado)
                console.error('DEBUG_APP_JS: Fallo lógico en verificación:', data.message);
                updateStatusDisplay(data.message || 'Error al verificar invitación.', 'error');
                updateInvitationDetails(null); // Oculta detalles en caso de fallo lógico
                showApproveButton(false); // Oculta el botón de aprobar en caso de fallo lógico
            }
        })
        .catch(error => {
            // Error en la promesa fetch (red, servidor no responde, JSON inválido, error lanzado antes)
            console.error('DEBUG_APP_JS: Error en fetch de verificación:', error);
            updateStatusDisplay('Error de comunicación al verificar.', 'error'); // Mensaje más general
            updateInvitationDetails(null); // Oculta detalles en caso de error de comunicación
            showApproveButton(false); // Oculta el botón de aprobar en caso de error de comunicación
        });
}

/**
 * Envía una solicitud AJAX para aprobar la entrada de una invitación.
 * Esta función ASUME que los elementos de UI (statusDisplay, invitationDetailsDiv, approveBtn)
 * ya fueron asignados en el listener DOMContentLoaded.
 */
function approveInvitation() {
    if (!currentScannedCode) {
        updateStatusDisplay('No hay invitación seleccionada para aprobar.', 'error');
        showApproveButton(false);
        return;
    }

    if (confirm('¿Está seguro de aprobar la entrada para esta invitación?')) {
        showApproveButton(false); // Oculta/desactiva inmediatamente el botón
        updateStatusDisplay('Aprobando...', 'info');

        const formData = new FormData();
        formData.append('code', currentScannedCode);

        console.log('DEBUG_APP_JS: Enviando solicitud de aprobación para:', currentScannedCode);

        // La ruta al endpoint PHP (asegúrate que sea correcta desde la URL actual de scanqr.php)
        // Si scanqr.php está en la raíz y approve_invitation.php está en includes/, la ruta es 'includes/...'
        const approveUrl = 'includes/approve_invitation.php';

        fetch(approveUrl, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log('DEBUG_APP_JS: Respuesta recibida de approve_invitation.php', response);
                if (!response.ok) {
                    return response.json().catch(() => {
                        throw new Error('Error de red o servidor al aprobar. Estado: ' + response.status);
                    });
                }
                return response.json();
            })
            .then(data => {
                console.log('DEBUG_APP_JS: Datos JSON de aprobación:', data);
                if (data.success) {
                    updateStatusDisplay(data.message || 'Entrada Aprobada.', 'approved');
                    updateInvitationDetails(null); // Limpiar detalles después de aprobar exitosamente
                    currentScannedCode = null; // Limpiar código actual
                    // Opcional: Podrías añadir un botón "Escanear Nuevo" aquí para reiniciar el escáner y UI
                } else {
                    // Si la aprobación falló (ej. ya aprobada por otro guardia, expiró justo antes)
                    console.error('DEBUG_APP_JS: Fallo lógico en aprobación:', data.message);
                    updateStatusDisplay(data.message || 'Error al aprobar la entrada.', 'error');
                    // Opcional: re-verificar el estado actual de la invitación tras el fallo
                    // if (currentScannedCode) checkInvitation(currentScannedCode);
                    // Si la aprobación falló, el botón debería volver a mostrarse si la invitación es válida pero pendiente?
                    // Esto depende de la lógica de re-verificación. Si no re-verificas, el botón queda oculto.
                }
            })
            .catch(error => {
                // Error en la promesa fetch (red, servidor no responde, JSON inválido, error lanzado antes)
                console.error('DEBUG_APP_JS: Error en fetch de aprobación:', error);
                updateStatusDisplay('Error de comunicación al aprobar.', 'error'); // Mensaje más general
                // Considera re-mostrar el botón si la llamada AJAX falló COMPLETAMENTE antes de obtener respuesta del servidor
                // y si currentScannedCode todavía tiene un valor válido.
                // if (currentScannedCode) showApproveButton(true); // Esto puede ser confuso si el estado real cambió en el servidor
            });
    }
}


// --- Lógica que se ejecuta cuando el DOM está completamente cargado ---

document.addEventListener('DOMContentLoaded', () => {

    console.log('DEBUG_APP_JS: DOMContentLoaded. Inicializando lógica de página.');

    // --- === ASIGNAR REFERENCIAS A ELEMENTOS AQUÍ DENTRO DEL DOMContentLoaded === ---
    // ESTO ES CRUCIAL. Asegura que los elementos HTML ya existen en el DOM
    // cuando intentamos obtener su referencia con document.getElementById().
    videoElement = document.getElementById('scanner'); // Referencia al elemento <video>
    scannerContainer = document.getElementById('scanner-container'); // Referencia al contenedor del escáner
    statusDisplay = document.getElementById('status-display'); // Referencia al display de estado
    invitationDetailsDiv = document.getElementById('invitation-details'); // Referencia al div de detalles
    approveBtn = document.getElementById('approve-btn'); // Referencia al botón de aprobar

    // Referencias para la página de Invitar
    qrcodeDiv = document.getElementById("qrcode"); // Referencia al div del código QR
    whatsappShareBtn = document.getElementById('whatsapp-share-btn'); // Referencia al botón de WhatsApp

    // Referencia para la página de Listado
    invitationsTable = document.getElementById('invitations-table'); // Referencia a la tabla de invitaciones
    // --- === FIN ASIGNACIÓN DE REFERENCIAS === ---


    // --- === Lógica para manejar initialCode desde la URL (solo en página de seguridad) === ---
    // Esta lógica ahora se ejecuta DESPUÉS de que los elementos del DOM han sido asignados.
    // initialCode es una variable global que debería haber sido definida en un script tag
    // ANTES de que este script app.js sea ejecutado en scanqr.php.
    // Verifica si la variable initialCode existe Y tiene un valor (no es una cadena vacía)
    if (typeof initialCode !== 'undefined' && initialCode) {
        console.log('DEBUG_APP_JS: initialCode detectado en DOMContentLoaded:', initialCode);
        // Asegúrate de que la función checkInvitation exista (debería estar definida en este archivo)
        if (typeof checkInvitation === 'function') {
            // Llama a checkInvitation solo si hay un initialCode Y DOMContentLoaded ha terminado
            checkInvitation(initialCode);
        } else {
            console.error("DEBUG_APP_JS: Error: checkInvitation function not found when processing initialCode!");
            // Si checkInvitation no está lista, muestra un mensaje de error en la interfaz
            updateStatusDisplay('Error interno: Lógica de verificación no cargada.', 'error'); // Ahora statusDisplay ya fue asignado arriba
        }
    }
    // --- === FIN Lógica para manejar initialCode === ---


    // --- Lógica específica para la página de Invitar ---
    // Comprobar si el div del código QR existe (indica que estamos en la página de Invitar)
    // qrcodeDiv ya fue asignado al principio de este bloque DOMContentLoaded
    if (qrcodeDiv) {
        console.log('DEBUG_APP_JS: Página Invitar detectada.');

        // Comprobar si la variable qrCodeUrl fue definida por PHP en el HTML de invitar.php
        // (Esto sucede en invitar.php SOLO si se genera una invitación exitosamente)
        if (typeof qrCodeUrl !== 'undefined' && qrCodeUrl) {

            // ### NUEVA VERIFICACIÓN: Comprobar si el div #qrcode ya contiene un QR ###
            // Ayuda a evitar duplicados si el script se ejecuta más de una vez (aunque DOMContentLoaded evita esto).
            // Si quieres forzar la limpieza y regeneración, podrías usar: qrcodeDiv.innerHTML = '';
            if (qrcodeDiv.querySelector('canvas, img')) {
                console.warn('DEBUG_INVITAR: ## El div #qrcode ya contiene un QR. Cancelando nueva generación. ##');
                // No retornamos aquí para que la lógica de compartir se siga añadiendo.
            } else {
                console.log('DEBUG_INVITAR: ### PREPARANDO LLAMADA a new QRCode() ###');
                console.log('DEBUG_APP_JS: Página Invitar detectada con QR generado. Inicializando QR y compartir.');

                // Verificar si la librería QRCode (qrcode.min.js) está cargada antes de usarla
                if (typeof QRCode !== 'undefined') {
                    console.log('DEBUG_APP_JS: ### Llamando a new QRCode() ###');

                    // rawCode también se define en un script in-line en invitar.php
                    if (typeof rawCode !== 'undefined' && rawCode) {
                        // qrcodeDiv ya fue asignado al principio de este bloque
                        var qrcode = new QRCode(qrcodeDiv, {
                            text: rawCode, // rawCode se define en el script in-line en invitar.php
                            width: 200,
                            height: 200,
                            colorDark: "#000000",
                            colorLight: "#ffffff",
                            correctLevel: QRCode.CorrectLevel.H
                        });
                        console.log('DEBUG_APP_JS: ### new QRCode() fue llamado. ###');
                    } else {
                        console.error('Error: rawCode no definido o vacío para generar QR.');
                        // Mostrar mensaje de error en la UI
                        if (qrcodeDiv) qrcodeDiv.innerHTML = '<p style="color:red;">Error: Código QR vacío.</p>';
                    }
                } else {
                    console.error('Error: Librería QRCode (qrcode.min.js) no cargada. Asegúrate de que esté incluida en invitar.php.');
                    // Mostrar mensaje de error en la UI si el QR no se puede generar
                    if (qrcodeDiv) qrcodeDiv.innerHTML = '<p style="color:red;">Error al cargar librería QR.</p>';
                }
            } // Fin if (!qrcodeDiv.querySelector('canvas, img'))


            // Añadir listener al botón de compartir por WhatsApp
            // whatsappShareBtn ya fue asignado al principio de este bloque
            if (whatsappShareBtn) {
                whatsappShareBtn.addEventListener('click', function () {
                    var whatsappText = "¡Hola! Tienes una invitación para ingresar al barrio.\n\nPresenta este enlace en seguridad:\n" + qrCodeUrl;
                    // Usar api.whatsapp.com para mayor compatibilidad fuera de móvil
                    var whatsappUrl = "https://api.whatsapp.com/send?text=" + encodeURIComponent(whatsappText);
                    window.open(whatsappUrl, '_blank');
                });
            }

        } // Fin if (typeof qrCodeUrl !== 'undefined' && qrCodeUrl)
    } // Fin if (qrcodeDiv)


    // --- Lógica específica para la página de Seguridad (Escáner) ---

    // Comprobar si el elemento de video #scanner existe (indica que estamos en la página de Seguridad)
    // videoElement ya fue asignado justo al principio de este bloque DOMContentLoaded
    if (videoElement) {

        console.log('DEBUG_APP_JS: Página Seguridad detectada con elemento #scanner. Inicializando ZXing.');

        // Verificar si la librería ZXing está cargada y lista (zxing.min.js)
        // ZXing.min.js define ZXing, y BrowserMultiFormatReader es parte de ella.
        if (typeof ZXing !== 'undefined' && typeof ZXing.BrowserMultiFormatReader !== 'undefined') {

            // Crear el lector de códigos ZXing y ASIGNARLO a la variable de scope superior
            // codeReaderInstance ya está declarada con 'let' fuera de este bloque
            codeReaderInstance = new ZXing.BrowserMultiFormatReader(); // <<< Creación de instancia aquí
            console.log('DEBUG_APP_JS: ZXing BrowserMultiFormatReader creado y asignado a codeReaderInstance.');

            // --- === INICIAR LA DECODIFICACIÓN USANDO CONSTRAINTS PARA PEDIR CÁMARA TRASERA === ---
            // Definir las restricciones para solicitar la cámara trasera
            const videoConstraints = {
                video: {
                    facingMode: {
                        exact: "environment"
                    } // <<< PEDIR CÁMARA TRASERA
                    // Puedes añadir otras constraints aquí si las necesitas
                    // width: { ideal: 1280 },
                    // height: { ideal: 720 }
                }
                // audio: false // Generalmente no necesitas audio para escanear
            };

            console.log('DEBUG_APP_JS: Llamando a decodeFromConstraints con facingMode:', videoConstraints.video.facingMode);

            // Iniciar la decodificación usando las constraints en el elemento video
            // codeReaderInstance ya fue asignado arriba
            // videoElement ya fue asignado al principio de este bloque DOMContentLoaded
            codeReaderInstance.decodeFromConstraints(
                    videoConstraints, // Pasar las constraints
                    videoElement, // Pasar la referencia al elemento video (NO el ID 'scanner')
                    (result, err) => { // Callback para resultados y errores de decodificación
                        // --- Lógica para manejar el resultado (result) o errores (err) ---
                        if (result) { // Si se detectó un código QR
                            console.log('DEBUG_APP_JS: Código detectado:', result.text);
                            const scannedCode = result.text.trim();

                            if (scannedCode) {
                                console.log('DEBUG_APP_JS: Código detectado (sin parsear URL):', scannedCode);
                                // updateStatusDisplay('Código escaneado. Verificando...', 'info'); // checkInvitation lo actualiza

                                // Detener el escaneo para evitar múltiples lecturas rápidas ANTES de verificar
                                if (codeReaderInstance) {
                                    codeReaderInstance.reset(); // Detiene la cámara y el escaneo
                                    console.log('DEBUG_APP_JS: Scanner reseteado tras lectura.');
                                }

                                // Ocultar el elemento <video> y su contenedor ANTES de verificar
                                // Usamos las variables que ya están asignadas dentro de DOMContentLoaded
                                if (videoElement) videoElement.style.display = 'none';
                                if (scannerContainer) { // scannerContainer ya fue asignado al principio de este bloque
                                    scannerContainer.style.display = 'none'; // Oculta el div contenedor
                                    console.log('DEBUG_APP_JS: Contenedor del escáner oculto.');
                                }

                                // Llamar a tu función de verificación AJAX
                                // checkInvitation actualizará el statusDisplay y detailsDiv
                                checkInvitation(scannedCode);


                            } else {
                                console.log('DEBUG_APP_JS: Texto escaneado vacío o solo espacios.');
                                // updateStatusDisplay('Código escaneado vacío.', 'info');
                            }
                        }
                        // Si hay un error de decodificación (que no sea NotFoundException, que es normal)
                        if (err && !(err instanceof ZXing.NotFoundException)) {
                            console.error('DEBUG_APP_JS: Error de decodificación/escaneo:', err);
                            // updateStatusDisplay('Error durante el escaneo: ' + err.message, 'error'); // checkInvitation lo actualiza si falla AJAX

                            // Opcional: resetear escáner y ocultar elementos en caso de error persistente de escaneo
                            // if (codeReaderInstance) codeReaderInstance.reset();
                            // if (videoElement) videoElement.style.display = 'none';
                            // if (scannerContainer) scannerContainer.style.display = 'none';
                        }
                    } // Cierre del callback de decodeFromConstraints
                )
                // >>> CATCH para manejar errores al iniciar la cámara con constraints <<<
                // Este catch se ejecuta si falla al obtener el stream de la cámara con las constraints dadas
                // (ej. Permiso denegado, cámara no encontrada, constraints no cumplidas)
                .catch((err) => {
                    console.error('DEBUG_APP_JS: Error al iniciar cámara/escáner con constraints:', err);
                    let errorMessage = 'Error al iniciar escáner.';
                    if (err && err.name) {
                        errorMessage += ' (' + err.name + ')';
                        if (err.name === 'NotAllowedError' || err.name === 'PermissionDeniedError') {
                            errorMessage = 'Permiso de cámara denegado. Por favor, permite el acceso en la configuración del navegador/dispositivo.';
                        } else if (err.name === 'NotFoundError' || err.name === 'OverconstrainedError') {
                            errorMessage = 'Cámara trasera no encontrada o no cumple los requisitos.';
                            // Opcional: Aquí podrías intentar iniciar con facingMode: "user" como fallback si la trasera falla
                            // console.log("DEBUG_APP_JS: Intentando con cámara frontal como fallback...");
                            // const fallbackConstraints = { video: { facingMode: "user" }, audio: false };
                            // codeReaderInstance.decodeFromConstraints(fallbackConstraints, videoElement, (r, e) => {
                            //      console.log('DEBUG_APP_JS: Fallback frontal, código detectado:', r?.text); // Usa optional chaining
                            //      // Lógica de resultado similar al primer callback
                            //      if (r) { /* ... procesar código ... checkInvitation(r.text) */ }
                            //      if (e && !(e instanceof ZXing.NotFoundException)) console.error('DEBUG_APP_JS: Error fallback frontal:', e);
                            // })
                            // .catch(ee => {
                            //      console.error("DEBUG_APP_JS: Error al iniciar cualquier cámara:", ee);
                            //      updateStatusDisplay('No se pudo acceder a ninguna cámara.', 'error');
                            //      if (videoElement) videoElement.style.display = 'none';
                            //      if (scannerContainer) scannerContainer.style.display = 'none';
                            // });
                            // return; // Salir de este catch si se intentó el fallback
                        }
                    }
                    updateStatusDisplay(errorMessage, 'error'); // Muestra el error en el display de estado
                    // Ocultar el elemento <video> y su contenedor en caso de error al iniciar
                    // Usamos las variables que ya están asignadas dentro de DOMContentLoaded
                    if (videoElement) videoElement.style.display = 'none';
                    if (scannerContainer) { // scannerContainer ya fue asignado al principio de este bloque
                        scannerContainer.style.display = 'none'; // Oculta el div contenedor
                        console.log('DEBUG_APP_JS: Contenedor del escáner oculto debido a error de inicio.');
                    }
                });


        } else {
            // Lógica si la librería ZXing no está cargada (zxing.min.js o BrowserMultiFormatReader)
            console.log('DEBUG_APP_JS: Librería ZXing o BrowserMultiFormatReader no cargada o no disponible.');
            // Usa las variables que ya están asignadas dentro de DOMContentLoaded
            if (videoElement) {
                videoElement.style.display = 'none';
            }
            if (scannerContainer) { // scannerContainer ya fue asignado al principio de este bloque
                scannerContainer.style.display = 'none'; // Oculta contenedor si librería no carga
                console.log('DEBUG_APP_JS: Contenedor del escáner oculto porque ZXing no cargó.');
            }
            updateStatusDisplay('Error: Librería de escaneo no cargada.', 'error'); // Muestra error
        }

        // Añadir el listener al botón de aprobar
        // approveBtn ya fue asignado al principio de este bloque DOMContentLoaded
        if (approveBtn) {
            approveBtn.addEventListener('click', approveInvitation); // approveBtn es la referencia al elemento
        }


    } else {
        // Lógica si el elemento video #scanner NO se encuentra en el DOMContentLoaded
        // (Significa que probablemente no estamos en la página de seguridad)
        console.log('DEBUG_APP_JS: Elemento video #scanner NO encontrado en DOMContentLoaded. No se inicializa lógica de escaneo de seguridad.');
        // Usa las variables que ya están asignadas dentro de DOMContentLoaded
        // Si el elemento escáner no se encuentra en el DOM, ocultar el contenedor por si acaso
        if (scannerContainer) { // scannerContainer ya fue asignado al principio de este bloque
            scannerContainer.style.display = 'none'; // Oculta contenedor si #scanner no existe
            console.log('DEBUG_APP_JS: Contenedor del escáner oculto porque el elemento video no se encontró en el DOM.');
        }
        // updateStatusDisplay('Error interno: Elemento de video no encontrado para escanear.', 'error'); // Solo si estamos SEGUROS que deberia estar
    }


    // --- NUEVO: Lógica para la página de Listado de Invitaciones (expandir filas) ---
    // Solo ejecutar si estamos en la página con la tabla de invitaciones
    // invitationsTable ya fue asignado al principio de este bloque DOMContentLoaded
    if (invitationsTable) {
        console.log('DEBUG_APP_JS: Página de Listado de Invitaciones detectada. Inicializando lógica de filas expandibles.');

        // Añadir listener a clics en la tabla (usa event delegation)
        // Escuchamos clics en la tabla y verificamos si el clic ocurrió dentro de una fila principal
        invitationsTable.addEventListener('click', function (event) {
            // event.target es el elemento exacto clickeado (td, span, etc.)
            // closest('tr.invitation-row') busca el ancestro <tr> más cercano con la clase 'invitation-row'
            let targetRow = event.target.closest('tr.invitation-row');

            // Si se hizo clic en una fila de invitación principal (o dentro de una)
            if (targetRow) {
                console.log('DEBUG_APP_JS: Clic en fila de invitación principal.');

                // Encontrar la fila de detalles inmediatamente siguiente a la fila principal
                let detailsRow = targetRow.nextElementSibling;

                // Verificar que la siguiente fila es la de detalles y tiene la clase correcta
                if (detailsRow && detailsRow.classList.contains('invitation-details-row')) {
                    // Toggle display: si está visible, ocúltala; si está oculta, muéstrala
                    if (detailsRow.style.display === 'none') {
                        detailsRow.style.display = 'table-row'; // Mostrar la fila de detalles como fila de tabla
                        console.log('DEBUG_APP_JS: Fila de detalles mostrada.');
                        // Opcional: Añadir una clase a la fila principal para indicar que está expandida (para CSS)
                        targetRow.classList.add('expanded');
                    } else {
                        detailsRow.style.display = 'none'; // Ocultar la fila de detalles
                        console.log('DEBUG_APP_JS: Fila de detalles ocultada.');
                        // Opcional: Remover la clase de expandida
                        targetRow.classList.remove('expanded');
                        // Ocultar el QR si estaba visible al colapsar la fila
                        const qrDisplayDiv = detailsRow.querySelector('.qr-code-display');
                        if (qrDisplayDiv) qrDisplayDiv.style.display = 'none';
                    }
                }
            } else {
                // Si el clic no fue directamente en una fila principal (ej. clic en el botón "Ver QR"),
                // la lógica del botón se maneja en el otro listener abajo.
                console.log('DEBUG_APP_JS: Clic no en fila de invitación principal.');
            }
        }); // Fin listener de clics en la tabla para expandir/colapsar


        // --- NUEVO: Listener para el botón "Ver QR" dentro de las filas de detalles ---
        // Usamos event delegation en la tabla porque los botones están dentro de las filas que se muestran/ocultan
        invitationsTable.addEventListener('click', function (event) {
            //closest() busca el ancestro más cercano que coincida con el selector
            const viewQrBtn = event.target.closest('button.view-qr-btn');

            // Si se hizo clic en un botón con la clase 'view-qr-btn'
            if (viewQrBtn) {
                console.log('DEBUG_APP_JS: Clic en botón Ver QR.');
                // event.stopPropagation() evita que este clic "burbujee" y también active el listener de expandir fila
                event.stopPropagation();

                // Obtener el código de invitación almacenado en el atributo data-code del botón
                const invitationCode = viewQrBtn.dataset.code;
                // Obtener la referencia al div donde se mostrará la imagen QR (justo después del botón)
                const qrDisplayDiv = viewQrBtn.nextElementSibling;

                if (invitationCode && qrDisplayDiv) {
                    console.log('DEBUG_APP_JS: Código de invitación del botón:', invitationCode);

                    // === Lógica para mostrar/ocultar el QR dentro de la fila de detalles ===
                    // Si el div de display QR está oculto, lo mostramos y cargamos la imagen
                    if (qrDisplayDiv.style.display === 'none') {
                        // Construir la URL del QR (Asumiendo que se guardan en /qr/codigo.png o .jpg)
                        // Asegúrate que esta ruta coincida con donde realmente guardas tus archivos QR
                        // encodeURIComponent es buena práctica por si el código tiene caracteres especiales
                        const qrImageUrl = `qr/${encodeURIComponent(invitationCode)}.png`; // <<< ASUMIMOS RUTA Y EXTENSIÓN

                        // Limpiar contenido anterior (ej. si hubo un error antes)
                        qrDisplayDiv.innerHTML = '';
                        const qrImage = document.createElement('img');
                        qrImage.src = qrImageUrl;
                        qrImage.alt = `Código QR para ${invitationCode}`;
                        qrImage.style.maxWidth = '150px'; // Estilo opcional para el tamaño del QR mostrado
                        qrImage.style.height = 'auto';

                        qrImage.onerror = function () {
                            console.error('DEBUG_APP_JS: Error al cargar imagen QR:', qrImageUrl);
                            qrDisplayDiv.innerHTML = '<p style="color: red;">Error al cargar QR.</p>';
                            qrDisplayDiv.style.display = 'block'; // Asegura que el div de error sea visible
                        };
                        qrImage.onload = function () {
                            console.log('DEBUG_APP_JS: Imagen QR cargada exitosamente:', qrImageUrl);
                            // No necesitamos establecer display: block aquí, ya lo haremos fuera del load/error
                        };

                        qrDisplayDiv.appendChild(qrImage); // Añadir la imagen al div
                        qrDisplayDiv.style.display = 'block'; // Mostrar el div contenedor del QR

                    } else {
                        // Si el div de display QR ya está visible, lo ocultamos
                        qrDisplayDiv.style.display = 'none';
                        qrDisplayDiv.innerHTML = ''; // Limpiar el contenido al ocultar
                        console.log('DEBUG_APP_JS: Ocultando display de QR.');
                    }


                } else {
                    console.error('DEBUG_APP_JS: Código de invitación o div de display QR no encontrado para el botón.');
                    // Opcional: Mostrar un mensaje de error en la UI cerca del botón
                    // const errorSpan = document.createElement('span');
                    // errorSpan.style.color = 'red';
                    // errorSpan.textContent = ' Error.';
                    // viewQrBtn.parentNode.insertBefore(errorSpan, qrDisplayDiv); // Insertar antes del div
                }
            }
        }); // Fin listener de clics en botón Ver QR para mostrar QR

    } // Fin if (invitationsTable)


    // --- Lógica Común para el Botón Atrás ---
    // Este código se ejecutará en CUALQUIER página que cargue app.js
    // y tenga un elemento con la clase 'back-btn'.
    const backButton = document.querySelector('.back-btn'); // Buscar el botón "Atrás"
    if (backButton) {
        console.log('DEBUG_APP_JS: Botón Atrás encontrado. Añadiendo listener para history.back().');
        backButton.addEventListener('click', function (e) {
            e.preventDefault(); // Prevenir la acción por defecto del enlace (ej: ir a la URL en href)
            history.back(); // Navegar a la página anterior en el historial del navegador
        });
    }


    // Selecciona todos los botones con la clase 'delete-invite-btn'
    const deleteButtons = document.querySelectorAll('.delete-invite-btn');

    // Itera sobre cada botón Eliminar encontrado
    deleteButtons.forEach(button => {
        // Añade un escuchador de eventos para el clic en cada botón Eliminar
        button.addEventListener('click', function () {
            // 'this' se refiere al botón clickeado.
            // Obtenemos el código de la invitación del atributo 'data-code'.
            const invitationCode = this.dataset.code;

            // Mostramos un cuadro de diálogo de confirmación al usuario.
            // confirm() retorna true si el usuario pulsa OK, false si pulsa Cancelar.
            const confirmDelete = confirm('¿Estás seguro de que deseas eliminar esta invitación con código ' + invitationCode + '? Esta acción no se puede deshacer.');

            // Si el usuario confirmó la eliminación (pulso OK)
            if (confirmDelete) {
                console.log('Usuario confirmó eliminación para código:', invitationCode);

                // === === Lógica para enviar la solicitud de eliminación al servidor (AJAX) === ===

                // La URL del script PHP que procesará la eliminación.
                // Apuntamos a invitation_handler.php que añadiremos lógica para manejar POSTs.
                const handlerUrl = 'includes/delete_invitation.php'; // <<< Asegúrate que esta ruta sea correcta

                // Preparamos los datos a enviar en la solicitud POST.
                // Usamos FormData, que es útil para enviar datos de formulario, incluyendo archivos (aunque aquí no enviamos archivos).
                const formData = new FormData();
                formData.append('action', 'delete'); // Le decimos al script qué acción queremos ('delete')
                formData.append('code', invitationCode); // Le enviamos el código de la invitación a eliminar

                // Usamos la API Fetch para enviar la solicitud POST de forma asíncrona.
                fetch(handlerUrl, {
                        method: 'POST', // El método de la solicitud HTTP
                        body: formData // Los datos a enviar en el cuerpo de la solicitud
                    })
                    .then(response => {
                        // Esta función se ejecuta cuando la solicitud llega al servidor y se recibe una respuesta.
                        // Primero, verificamos si la respuesta fue exitosa (códigos de estado 200-299).
                        if (!response.ok) {
                            // Si la respuesta no es OK, lanzamos un error con el estado de la respuesta.
                            throw new Error('La respuesta de la red no fue OK. Estado: ' + response.status + ' ' + response.statusText);
                        }
                        // Si la respuesta es OK, intentamos parsear el cuerpo de la respuesta como JSON.
                        return response.json(); // Esto retorna una Promise con el objeto JSON.
                    })
                    .then(data => {
                        // Esta función se ejecuta si el parseo JSON fue exitoso. 'data' es el objeto JSON recibido del servidor.
                        console.log('Respuesta del servidor:', data);

                        // Verificamos el contenido de la respuesta JSON del servidor (nuestro script PHP retornará { success: true/false, message: '...' }).
                        if (data.success) {
                            // Si el servidor reporta éxito
                            alert('Invitación eliminada con éxito: ' + data.message);

                            // === Opcional: Eliminar la fila de la tabla en el navegador SIN recargar la página ===
                            // Encontramos la fila más cercana que contenga el botón clickeado (la fila de detalles expandida).
                            const detailsRow = button.closest('tr.invitation-details-row');
                            if (detailsRow) {
                                // Si encontramos la fila de detalles, encontramos la fila de resumen que está justo antes.
                                const summaryRow = detailsRow.previousElementSibling;
                                // Verificamos que sea la fila de resumen correcta antes de intentar eliminar.
                                if (summaryRow && summaryRow.classList.contains('invitation-summary-row')) {
                                    // Eliminamos ambas filas (resumen y detalles) del DOM del navegador.
                                    summaryRow.remove();
                                    detailsRow.remove();
                                    console.log('Fila eliminada del DOM.');
                                } else {
                                    // Si no pudimos encontrar las filas correctamente para eliminar, recargamos la página para actualizar el listado.
                                    console.warn('No se pudieron encontrar las filas summary/details adyacentes para eliminar del DOM. Recargando página.');
                                    window.location.reload(); // Recarga la página.
                                }
                            } else {
                                // Si ni siquiera pudimos encontrar la fila de detalles (muy raro), recargamos.
                                console.warn('No se pudo encontrar la fila de detalles para eliminar del DOM. Recargando página.');
                                window.location.reload(); // Recarga la página.
                            }

                        } else {
                            // Si el servidor reporta un error (success: false)
                            alert('Error al eliminar la invitación: ' + (data.message || 'Mensaje de error desconocido.'));
                        }
                    })
                    .catch(error => {
                        // Esta función se ejecuta si hubo un error durante la solicitud Fetch (problemas de red, error al parsear JSON, etc.).
                        console.error('Fetch error al eliminar invitación:', error);
                        alert('Error en la comunicación con el servidor al intentar eliminar la invitación.');
                    });

                // === Fin Lógica para enviar la solicitud de eliminación ===

            } else {
                // Si el usuario CANCELÓ la eliminación en el cuadro de confirmación.
                console.log('Eliminación cancelada por el usuario.');
            }
        });
    });





}); // Fin del listener DOMContentLoaded


// --- Es buena práctica detener el lector y la cámara al salir de la página ---
// Este listener va fuera del DOMContentLoaded para que se registre siempre
// y pueda acceder a codeReaderInstance que declaramos con let arriba.
window.addEventListener('beforeunload', () => {
    // Si codeReader fue creado por la lógica de seguridad (y es accesible), asegúrate de resetearlo
    if (codeReaderInstance && typeof codeReaderInstance.reset === 'function') {
        codeReaderInstance.reset();
        console.log('DEBUG_APP_JS: Lector ZXing reseteado en beforeunload.');
    }
});

// --- Funciones checkInvitation y approveInvitation ---
// (Estas funciones se mantienen igual, dependen de las variables declaradas con let arriba
// y ASUMIMOS que solo se llaman DESPUÉS de que las variables sean ASIGNADAS en DOMContentLoaded,
// por la lógica de la página de seguridad o el manejo de initialCode)

// Nota: El código de estas funciones es el mismo que te proporcioné en la respuesta anterior.
// Asegúrate de que estén presentes en tu archivo app.js completo.
// No las repito aquí para no hacer esta respuesta demasiado larga, pero son necesarias.

// Si usaste el código completo de la respuesta anterior para app.js, ya deberían estar.
// Si no, cópialas de nuevo y pégalas en este archivo.
// Aquí un recordatorio de las firmas:
/*
function checkInvitation(code) { ... }
function approveInvitation() { ... }
*/



// --- NUEVA Función para guardar la imagen QR en el servidor ---
/**
 * Obtiene la imagen del canvas del QR y la envía al servidor para guardarla.
 * Esta función ASUME que el div #qrcode y la variable rawCode ya fueron asignados/definidos.
 * @param {string} code - El código de la invitación (para el nombre del archivo).
 * @param {HTMLElement} qrcodeDivElement - La referencia al div donde se generó el QR.
 */
function saveQrImage(code, qrcodeDivElement) {
    console.log('DEBUG_APP_JS: saveQrImage llamado para código:', code);

    // qrcodejs dibuja el QR dentro del div, puede ser un <canvas> o un <img>
    // Intentamos encontrar el canvas primero
    const canvas = qrcodeDivElement.querySelector('canvas');
    let imageData = null;
    let imageType = 'image/png'; // Preferimos PNG por defecto

    if (canvas) {
        try {
            // Obtener los datos de la imagen del canvas como base64
            imageData = canvas.toDataURL(imageType);
            console.log('DEBUG_APP_JS: Imagen QR obtenida del canvas.');
        } catch (e) {
            console.error('DEBUG_APP_JS: Error al obtener datos del canvas:', e);
            // Si falla el canvas, intentar con img (menos común para qrcodejs, pero posible)
            const img = qrcodeDivElement.querySelector('img');
            if (img) {
                try {
                    // Para una imagen, a veces necesitas cargarla de nuevo o usar un proxy si es cross-origin
                    // Pero si qrcodejs la generó directamente, dataURL debería funcionar si no hay restricciones
                    imageData = img.src; // img.src ya podría ser una data URL
                    console.log('DEBUG_APP_JS: Imagen QR obtenida de <img>.');
                    // Intentar determinar el tipo de imagen si es una data URL
                    if (imageData.startsWith('data:')) {
                        const match = imageData.match(/^data:([^;]+)/);
                        if (match && match[1]) {
                            imageType = match[1];
                        }
                    } else {
                        // Si img.src no es data URL, puede ser difícil obtener los datos sin un proxy
                        console.warn('DEBUG_APP_JS: img.src no es una data URL, no se puede guardar fácilmente.');
                        imageData = null; // No podemos obtener los datos
                    }
                } catch (e) {
                    console.error('DEBUG_APP_JS: Error al obtener datos de <img>:', e);
                    imageData = null;
                }
            }
        }
    } else {
        // Si no hay canvas ni img dentro del div, algo salió mal al generar el QR.
        console.error('DEBUG_APP_JS: No se encontró elemento canvas o img dentro del div QR.');
    }


    if (imageData) {
        // Preparar los datos para enviar al servidor
        // Enviamos el código y los datos de la imagen (base64)
        const postData = new FormData();
        postData.append('code', code);
        postData.append('image_data', imageData);
        postData.append('image_type', imageType); // Enviar el tipo de imagen

        // Ruta al endpoint PHP que guardará la imagen
        // Asume que save_qr_image.php está en includes/ y app.js está en js/
        // La ruta desde app.js (en js/) a includes/save_qr_image.php (en includes/) es '../includes/save_qr_image.php'
        const saveUrl = 'includes/save_qr_image.php'; // <<< VERIFICA ESTA RUTA RELATIVA >>>

        console.log('DEBUG_APP_JS: Enviando imagen QR al servidor para guardar:', saveUrl);

        fetch(saveUrl, {
                method: 'POST',
                body: postData
            })
            .then(response => {
                console.log('DEBUG_APP_JS: Respuesta recibida de save_qr_image.php', response);
                if (!response.ok) {
                    // Si la respuesta HTTP no es OK, intentar leer el error del servidor
                    return response.json().catch(() => {
                        throw new Error('Error de red o servidor al guardar imagen QR. Estado: ' + response.status);
                    });
                }
                return response.json(); // Esperamos una respuesta JSON del servidor
            })
            .then(data => {
                console.log('DEBUG_APP_JS: Datos JSON de guardado de imagen QR:', data);
                if (data.success) {
                    console.log('DEBUG_APP_JS: Imagen QR guardada exitosamente en:', data.file_path);
                    // Opcional: Mostrar un mensaje de éxito al usuario si es necesario
                } else {
                    console.error('DEBUG_APP_JS: Fallo lógico al guardar imagen QR:', data.message);
                    // Opcional: Mostrar un mensaje de error al usuario
                }
            })
            .catch(error => {
                console.error('DEBUG_APP_JS: Error en fetch al guardar imagen QR:', error);
                // Opcional: Mostrar un mensaje de error al usuario
            });

    } else {
        console.error('DEBUG_APP_JS: No se pudieron obtener los datos de la imagen QR para guardar.');
    }
}




// --- Nota sobre initialCode ---
// La variable `initialCode` DEBE ser definida en un bloque <script> en scanqr.php
// *después* de incluir app.js. La lógica para usarla está integrada
// en el listener DOMContentLoaded en este archivo app.js.
// Ejemplo en scanqr.php:
/*
    <script src="js/app.js"></script>
    <script>
        const initialCode = "<?php echo htmlspecialchars($initial_code); ?>"; // Definición
    </script>
</body>
</html>
*/