<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prueba de Escáner QR (HTML5-QR)</title>
    <style>
        /* Estilos básicos para centrar y dar un poco de estructura */
        body {
            font-family: sans-serif;
            display: flex;
            flex-direction: column; /* Apilar elementos verticalmente */
            align-items: center; /* Centrar horizontalmente */
            min-height: 100vh;
            background-color: #f4f4f4;
            margin: 0;
            padding: 20px; /* Añadir padding general */
        }

        h1 {
            text-align: center;
            color: #333;
            margin-bottom: 20px;
        }

        #reader { /* ID usado por html5-qrcode para el contenedor del video */
            width: 100%; /* El lector ocupará todo el ancho disponible */
            max-width: 400px; /* Limitar ancho máximo */
            border: 2px solid #007bff; /* Borde para destacar el área del escáner */
            border-radius: 8px;
            overflow: hidden; /* Asegura que el video no se salga del borde redondeado */
            margin-bottom: 20px; /* Espacio debajo del escáner */
        }

        /* Estilos para el área de video dentro del lector */
        #reader video {
             width: 100% !important; /* Asegura que el video llene el contenedor */
             height: auto !important; /* Mantiene la proporción */
             display: block; /* Evita espacio extra debajo del video */
        }

        #scanned-result {
            margin-top: 20px;
            padding: 15px;
            border: 1px solid #ccc;
            border-radius: 4px;
            background-color: #fff;
            min-height: 50px; /* Altura mínima para mostrar mensajes */
            text-align: center;
            word-break: break-all; /* Rompe palabras largas si es necesario */
            max-width: 400px; /* Mismo ancho máximo que el lector */
            width: 100%;
            box-sizing: border-box; /* Incluir padding en el ancho */
        }

        /* Estilos para el área de mensajes de la consola */
        #console-output {
            margin-top: 20px;
            padding: 15px;
            border: 1px solid #f0ad4e; /* Borde amarillo/naranja para advertencias */
            border-radius: 4px;
            background-color: #fff;
            max-width: 400px; /* Mismo ancho máximo */
            width: 100%;
            box-sizing: border-box;
            white-space: pre-wrap; /* Preserva saltos de línea y espacios */
            font-family: monospace; /* Fuente monoespaciada para logs */
            font-size: 0.9em;
            color: #333;
            text-align: left; /* Alinear texto a la izquierda */
            display: none; /* Inicialmente oculto, se muestra si hay mensajes */
        }

        .console-error { color: red; font-weight: bold; } /* Rojo para errores */
        .console-warn { color: orange; } /* Naranja para advertencias */
        .console-log { color: gray; } /* Gris para logs normales */


        .status-info { color: #007bff; font-weight: bold; } /* Azul para mensajes iniciales */
        .status-success { color: green; font-weight: bold; } /* Verde para resultado exitoso */
        .status-error { color: red; font-weight: bold; } /* Rojo para errores */

    </style>
</head>
<body>

    <h1>Prueba de Escáner QR (HTML5-QR)</h1>

    <div id="reader"></div>

    <div id="scanned-result" class="status-info">Esperando escaneo...</div>

    <div id="console-output"></div>


    <script src="js/html5-qrcode.min.js" type="text/javascript"></script>

    <script>
        // --- === VERIFICACIÓN TEMPRANA: ¿Html5QrcodeSupportedScanTypes existe inmediatamente? === ---
        // Esto se ejecuta tan pronto como el script principal se carga, después de la librería.
        if (typeof Html5QrcodeSupportedScanTypes === 'undefined') {
             const earlyErrorMsg = "ERROR DEBUG (Temprano): Html5QrcodeSupportedScanTypes NO está definido inmediatamente después de cargar el script de la librería local.";
             alert(earlyErrorMsg); // << DEBUG ALERT 1: Si falla la verificación temprana
             console.error(earlyErrorMsg); // Log en consola
        } else {
             alert("DEBUG (Temprano): Html5QrcodeSupportedScanTypes ENCONTRADO inmediatamente después de cargar el script de la librería local."); // << DEBUG ALERT 1B: Si la verificación temprana es exitosa
             console.log("DEBUG (Temprano): Html5QrcodeSupportedScanTypes ENCONTRADO inmediatamente después de cargar el script de la librería local.");
        }
         // --- === Fin Verificación Temprana === ---


        // --- === Script para Capturar la Salida de la Consola y Mostrarla en HTML === ---
        // Este script intenta capturar mensajes de la consola y mostrarlos en un div HTML.
        // Puede que no funcione en todos los entornos o para todos los tipos de errores.
        const consoleOutputDiv = document.getElementById('console-output');
        const originalConsoleError = console.error;
        const originalConsoleWarn = console.warn;
        const originalConsoleLog = console.log; // Capturar también log si quieres

        function appendConsoleMessage(level, message, ...optionalParams) {
            // Reconstruir el mensaje de forma básica
            let fullMessage = message;
            if (optionalParams.length > 0) {
                fullMessage += ' ' + optionalParams.map(p => {
                    try {
                        // Intentar convertir objetos a string JSON para mejor visualización
                        return JSON.stringify(p, null, 2);
                    } catch (e) {
                        // Si falla JSON.stringify, usar la representación por defecto
                        return String(p);
                    }
                }).join(' ');
            }

            const messageElement = document.createElement('div');
            messageElement.textContent = `[${level.toUpperCase()}] ${fullMessage}`;
            messageElement.classList.add(`console-${level}`); // Añadir clase para estilizar (error, warn, log)

            consoleOutputDiv.appendChild(messageElement);
            consoleOutputDiv.style.display = 'block'; // Asegurarse de que el div sea visible

            // Opcional: Limitar el número de mensajes para no saturar la página
            while (consoleOutputDiv.children.length > 50) { // Mantener solo los últimos 50 mensajes
                consoleOutputDiv.removeChild(consoleOutputDiv.firstChild);
            }
        }

        // Sobrescribir las funciones de la consola
        console.error = function(message, ...optionalParams) {
            appendConsoleMessage('error', message, ...optionalParams);
            originalConsoleError(message, ...optionalParams); // Llamar a la función original también
        };

        console.warn = function(message, ...optionalParams) {
            appendConsoleMessage('warn', message, ...optionalParams);
            originalConsoleWarn(message, ...optionalParams); // Llamar a la función original también
        };

        // Opcional: Capturar también console.log si es necesario para depuración
        /*
        console.log = function(message, ...optionalParams) {
            appendConsoleMessage('log', message, ...optionalParams);
            originalConsoleLog(message, ...optionalParams); // Llamar a la función original también
        };
        */
        // --- === Fin Script para Capturar Consola === ---


        // Espera a que el DOM esté completamente cargado.
        document.addEventListener('DOMContentLoaded', function() {
             alert("DEBUG: DOMContentLoaded disparado."); // << DEBUG ALERT 2: DOM está listo?
             console.log("DEBUG: DOMContentLoaded disparado.");

            // --- === VERIFICACIÓN CLAVE 2 (Dentro de DOMContentLoaded): ¿Html5QrcodeSupportedScanTypes existe? === ---
            // Esta verificación es la que fallaba antes.
            if (typeof Html5QrcodeSupportedScanTypes === 'undefined') {
                 const errorMsg = "ERROR DEBUG (DOMContentLoaded): Html5QrcodeSupportedScanTypes no está definido después de DOMContentLoaded. La librería local no cargó o no se inicializó correctamente.";
                 alert(errorMsg); // << DEBUG ALERT 3: Si falla la verificación
                 console.error(errorMsg); // Log en consola (y div)
                 scannedResultDiv.textContent = errorMsg; // Mostrar error en el div de resultado
                 scannedResultDiv.className = 'status-error';
                 // Ocultar el div del lector si la librería no está lista
                 document.getElementById('reader').style.display = 'none';
                 return; // Detener la ejecución si la librería no está lista
            }
            alert("DEBUG (DOMContentLoaded): Html5QrcodeSupportedScanTypes encontrado. Procediendo..."); // << DEBUG ALERT 3B: Si la verificación es exitosa
            console.log("DEBUG (DOMContentLoaded): Html5QrcodeSupportedScanTypes encontrado. Procediendo...");


            // Referencia al div donde se mostrará el lector.
            const qrCodeReader = new Html5Qrcode("reader");
            // Referencia al div donde se mostrará el resultado.
            const scannedResultDiv = document.getElementById('scanned-result');

            // --- === Configuración del Lector QR === ---
            // Configuración básica para el escaneo.
            // Ahora sabemos que Html5QrcodeSupportedScanTypes debería estar definido aquí.
            const config = {
                fps: 10, // Cuadros por segundo para el escaneo
                qrbox: { width: 250, height: 250 }, // Tamaño del área de escaneo
                rememberLastUsedCamera: true, // Recordar la última cámara usada
                supportedScanTypes: [Html5QrcodeSupportedScanTypes.QR_CODE] // Esto debería funcionar ahora

                // Preferencia de cámara: Intenta usar la cámara trasera.
                // facingModeConfig: {
                //      facingMode: "environment"
                // } // <<< Comentar o eliminar esta línea temporalmente para la prueba básica si da problemas
            };

            // --- === Mensaje de Depuración antes de iniciar el escáner === ---
            scannedResultDiv.textContent = 'DEBUG: Llamando a qrCodeReader.start()...'; // << DEBUG MESSAGE 4: Llegó hasta aquí?
            console.log('DEBUG: Llamando a qrCodeReader.start()...');


            // --- === Función que se ejecuta cuando se detecta un código QR con éxito === ---
            // decodedText: El string con el contenido del código QR.
            const onScanSuccess = (decodedText, decodedResult) => {
                console.log(`QR escaneado con éxito: ${decodedText}`); // Loguea en la consola (y ahora en el div HTML).

                // Mostrar el contenido escaneado en el div de resultado.
                scannedResultDiv.textContent = `Contenido del QR: ${decodedText}`;
                scannedResultDiv.className = 'status-success'; // Cambia la clase para mostrar en verde.

                // Para permitir escanear múltiples QRs sin recargar, puedes pausar y luego reanudar:
                // html5-qrcode tiene pause/resume
                qrCodeReader.pause(); // Pausa el escaneo
                setTimeout(() => {
                    scannedResultDiv.textContent = 'Esperando escaneo...';
                    scannedResultDiv.className = 'status-info'; // Vuelve al estado inicial
                    qrCodeReader.resume(); // Reanuda el escaneo después de un tiempo
                }, 3000); // Reanudar después de 3 segundos
            };

            // --- === Función que se ejecuta si hay un error durante el escaneo (no errores de inicio) === ---
            const onScanError = (errorMessage) => {
                // Esta función se llama repetidamente si el lector está activo pero no detecta un código.
                // No mostramos mensajes constantes al usuario aquí para evitar saturar la UI.
                // console.warn(`Error de escaneo: ${errorMessage}`); // Descomentar para depuración detallada (ahora se capturará en el div).
            };

            // --- === Iniciar el Lector QR === ---
            // Este es el punto donde se solicita acceso a la cámara y se inicia el proceso de escaneo.
            // La función start() retorna una Promise. Usamos .catch() para manejar errores si la cámara no se puede iniciar.
            // Intentamos iniciar con la configuración más básica posible temporalmente ({})
             qrCodeReader.start(
                 {}, // <<< Usar un objeto vacío para preferencias de cámara (cualquier cámara por defecto)
                 config, // Pasa la configuración definida arriba.
                 onScanSuccess, // Función a llamar en caso de escaneo exitoso.
                 onScanError // Función a llamar en caso de error de escaneo continuo (no errores de inicio).
             )
             .then(() => {
                 // << DEBUG MESSAGE 5: Se ejecuta si start() tiene éxito
                 scannedResultDiv.textContent = 'Cámara iniciada. Esperando escaneo...';
                 scannedResultDiv.className = 'status-info';
                 console.log('DEBUG: qrCodeReader.start() successful.');
             })
             .catch((err) => {
                 // Esta parte se ejecuta si la llamada a start() falla (ej: permisos denegados, cámara no encontrada).
                 // << DEBUG ALERT 5: Se ejecuta si start() falla
                 alert("DEBUG: qrCodeReader.start() falló: " + err.name + " - " + err.message);
                 console.error(`DEBUG: qrCodeReader.start() falló:`, err); // Loguea el error en la consola (y ahora en el div HTML).
                 scannedResultDiv.textContent = `Error al iniciar la cámara: ${err.name || 'Error desconocido'} - ${err.message || 'Sin detalles'}`; // Muestra el error al usuario.
                 scannedResultDiv.className = 'status-error'; // Muestra el error en rojo.
                  // Ocultar el div del lector si falla
                 document.getElementById('reader').style.display = 'none';
             });


             // Opcional: Detener el escáner cuando el usuario sale de la página o cierra la pestaña.
             window.addEventListener('beforeunload', function() {
                 // Verifica si el lector está en un estado que no sea 'NOT_STARTED'.
                 // Html5QrcodeScannerState debería estar definido si la librería cargó.
                 if (typeof qrCodeReader !== 'undefined' && typeof Html5QrcodeScannerState !== 'undefined' && qrCodeReader.getState() !== Html5QrcodeScannerState.NOT_STARTED) {
                      qrCodeReader.stop().catch(err => console.error("DEBUG: Error al detener el lector QR al salir:", err)); // Loguea el error (y ahora en el div HTML).
                 }
             });

        }); // Fin de DOMContentLoaded
    </script>

</body>
</html>
