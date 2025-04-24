<?php
// htdocs/showqr.php - Página de destino para mostrar una invitación por su código URL amigable /qr/CODE

// --- Includes ---
// Incluimos los archivos necesarios para obtener los datos de la invitación y la configuración (como URL_BASE)
require_once __DIR__ . '/includes/invitation_handler.php'; // Contiene getInvitationByCode()
require_once __DIR__ . '/includes/config.php'; // Contiene URL_BASE, y quizás otras configuraciones

// No necesitamos iniciar sesión ni autenticación de usuario para esta página,
// ya que está diseñada para ser pública para cualquier persona con el enlace de invitación.

// --- Obtener el Código de Invitación de la URL ---
// La regla .htaccess está configurada para que, si accedes a /qr/CODIGO, internamente
// se reescriba a showqr.php?code=CODIGO. Por lo tanto, el código estará en $_GET['code'].
$invitation_code = $_GET['code'] ?? null; // Obtiene el código del parámetro GET

// --- Obtener los Datos de la Invitación usando el Código ---
$invitation = null; // Variable para almacenar los datos de la invitación encontrada
$error_message = ''; // Para mostrar mensajes de error si la invitación no es válida o no se encuentra

if ($invitation_code) {
    // Usamos la función getInvitationByCode() de invitation_handler.php para buscar la invitación.
    $invitation = getInvitationByCode($invitation_code);

    // --- Validaciones Adicionales (Opcional en esta página, la seguridad la valida más a fondo) ---
    // Puedes decidir si quieres mostrar mensajes específicos aquí si la invitación está expirada o usada,
    // aunque la validación principal la hace el escáner de seguridad (scanqr.php).
    if ($invitation) {
        // Ejemplo de validación básica para mostrar un estado en la página de destino
        if (isset($invitation['status']) && $invitation['status'] !== 'pendiente') {
             // $error_message = "Esta invitación ya ha sido " . htmlspecialchars($invitation['status']) . ".";
             // Podrías mostrar el estado sin marcarla como error fatal aquí, dependiendo de lo que quieras que vea el invitado.
        }
        if (isset($invitation['fecha_expiracion']) && $invitation['fecha_expiracion'] < time()) {
             // $error_message = "Esta invitación ha expirado.";
        }
    } else {
        // Si getInvitationByCode() retorna null, significa que el código no se encontró.
        $error_message = "Invitación no encontrada. El enlace podría ser incorrecto o la invitación no existe.";
    }
} else {
    // Si no se proporcionó un código en la URL (ej. acceden a /qr/ sin código)
    $error_message = "Código de invitación no especificado.";
}

// --- Preparar Datos para Mostrar en el HTML ---
// Extraemos los datos de la invitación para mostrarlos, manejando casos donde $invitation es null.
$guest_name = $invitation['invitado_nombre'] ?? 'N/A';
$anfitrion_name = $invitation['anfitrion']['name'] ?? 'N/A';
$anfitrion_lote = $invitation['anfitrion']['lote'] ?? 'N/A';
// Formatea la fecha de expiración si existe el timestamp
$fecha_expiracion_formatted = (isset($invitation['fecha_expiracion']) && $invitation['fecha_expiracion'] > 0) ? date('d/m/Y H:i', $invitation['fecha_expiracion']) : 'N/A';
$invitation_status = $invitation['status'] ?? 'Desconocido'; // Estado de la invitación

// Construye la URL a la imagen del código QR guardado en la carpeta 'qr'.
// Esto asume que el archivo QR se guardó con el nombre del código + extensión .png.
// La ruta 'qr/' es relativa a la ubicación de showqr.php (si showqr.php está en la raíz htdocs/).
//$qr_image_url = $invitation_code ? "qr/" . urlencode($invitation_code) . ".png" : null;
// htdocs/showqr.php

// ... (código antes de esta sección) ...

// Construye la URL COMPLETA (absoluta) a la imagen del código QR guardado en la carpeta 'qr'.
// Usamos URL_BASE para asegurar que la ruta sea correcta independientemente de la URL amigable.
// Asegúrate que URL_BASE esté definida en config.php (ej: 'http://localhost/xsecalpha') y SIN barra final.
if (defined('URL_BASE') && $invitation_code) {
    // === MODIFICA ESTA LÍNEA ===
    $qr_image_url = URL_BASE . "/qr/" . urlencode($invitation_code) . ".png"; // Construye la URL absoluta: http://localhost/xsecalpha/qr/CODIGO.png
} else {
    // Si URL_BASE no está definida o no hay código de invitación válido, la URL de la imagen es null.
    $qr_image_url = null;
     if (!defined('URL_BASE')) {
         error_log("URL_BASE no definida en config.php al construir QR image URL en showqr.php.");
     }
}

// ... (el resto del código, incluyendo el div qr-code-display que usa $qr_image_url) ...

// Construye la URL para el archivo del logo.
// Asumimos que la carpeta 'assets' está en la raíz de htdocs/.
$logo_url = '../assets/logo.png'; // >>> ASEGÚRATE QUE ESTA RUTA ES CORRECTA PARA TU LOGO <<<

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $invitation ? 'Invitación para ' . htmlspecialchars($guest_name) : 'Invitación Inválida'; ?></title>
    <link rel="stylesheet" href="../css/styles.css"> <style>
        /* --- Estilos específicos para la página showqr.php --- */
        body {
            font-family: sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background-color: #f4f4f4; /* Un fondo suave */
            color: #333;
            display: flex; /* Usamos flexbox para centrar el contenido principal */
            justify-content: center; /* Centra horizontalmente */
            align-items: center; /* Centra verticalmente */
            min-height: 95vh; /* Asegura que ocupe casi toda la altura de la ventana */
        }
        .invitation-container {
            background-color: #fff; /* Fondo blanco para el contenido de la invitación */
            padding: 30px;
            border-radius: 8px; /* Bordes redondeados */
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.1); /* Sombra suave */
            text-align: center; /* Centra el texto y elementos en línea */
            max-width: 500px; /* Ancho máximo razonable para dispositivos grandes */
            width: 95%; /* Ancho adaptable para que sea responsive */
            box-sizing: border-box; /* Incluye padding y borde en el ancho total */
        }
        .header-logo {
            margin-bottom: 20px; /* Espacio debajo del logo */
        }
        .header-logo img {
            max-width: 150px; /* Ajusta el tamaño máximo del logo */
            height: auto; /* Mantiene la proporción */
        }
        .invitation-details {
            margin-bottom: 20px; /* Espacio debajo de los detalles */
        }
        .invitation-details p {
            margin: 10px 0; /* Espacio entre líneas de detalles */
            font-size: 1.1em; /* Tamaño de fuente un poco más grande */
            display: flex; /* Usamos flexbox para alinear etiqueta y valor */
            justify-content: center; /* Centra el par etiqueta-valor */
            align-items: baseline; /* Alinea verticalmente el texto */
            flex-wrap: wrap; /* Permite que los elementos se envuelvan en pantallas muy pequeñas */
        }
         .invitation-details strong {
             display: inline-block; /* Etiqueta como bloque en línea para controlar ancho */
             min-width: 100px; /* Ancho mínimo para alinear las etiquetas */
             text-align: right; /* Alinea el texto de la etiqueta a la derecha */
             margin-right: 10px; /* Espacio entre la etiqueta y el valor */
             flex-shrink: 0; /* Evita que la etiqueta se encoja */
         }
        .qr-code-display {
            margin: 20px auto; /* Margen y centrado automático */
            padding: 10px; /* Espacio alrededor del QR */
            border: 1px solid #ddd; /* Borde alrededor del QR */
            display: inline-block; /* Para que el borde se ajuste al contenido */
            background-color: #fff; /* Fondo blanco dentro del borde */
        }
        .qr-code-display img {
            max-width: 250px; /* Ancho máximo para la imagen del QR */
            height: auto; /* Mantiene la proporción */
            display: block; /* Elimina el espacio extra debajo de la imagen */
        }
         /* Estilos para los estados */
         .status-pendiente { color: #007bff; } /* Azul */
         .status-aprobado { color: green; } /* Verde */
         .status-expirado { color: orange; } /* Naranja */
         .status-cancelado { color: gray; } /* Gris */
         .status-desconocido { color: red; } /* Rojo */
         .status-message.status-invalid { color: red; font-weight: bold; } /* Mensaje de error general */


        /* Estilos Responsive (Opcional, mejora la adaptación a móviles) */
        @media (max-width: 400px) {
            .invitation-container {
                padding: 20px;
            }
            .invitation-details p {
                flex-direction: column; /* Apila etiqueta y valor en pantallas muy pequeñas */
                align-items: center; /* Centra la etiqueta y valor apilados */
            }
            .invitation-details strong {
                min-width: auto; /* Elimina el ancho mínimo */
                text-align: center; /* Centra la etiqueta */
                margin-right: 0; /* Elimina el margen */
                margin-bottom: 5px; /* Añade espacio debajo de la etiqueta */
            }
             .qr-code-display img {
                 max-width: 180px; /* Reduce un poco el tamaño del QR en pantallas muy pequeñas */
             }
        }

    </style>
</head>
<body>
    <div class="invitation-container">

        <div class="header-logo">
            <img src="<?php echo htmlspecialchars($logo_url); ?>" alt="Logo de la aplicación">
        </div>

        <?php if ($error_message): ?>
            <div class="status-message status-invalid"><?php echo htmlspecialchars($error_message); ?></div>
        <?php elseif ($invitation): ?>
            <h2>Invitación</h2>
            <div class="invitation-details">
                <p><strong>Invitado:</strong> <?php echo htmlspecialchars($guest_name); ?></p>
                <p><strong>Anfitrión:</strong> <?php echo htmlspecialchars($anfitrion_name); ?></p>
                <p><strong>Lote:</strong> <?php echo htmlspecialchars($anfitrion_lote); ?></p>
                <p><strong>Expira:</strong> <?php echo htmlspecialchars($fecha_expiracion_formatted); ?></p>
                <p><strong>Estado:</strong> <span class="status-<?php echo htmlspecialchars(strtolower($invitation_status)); ?>"><?php echo htmlspecialchars(ucfirst($invitation_status)); ?></span></p>
            </div>

            <div class="qr-code-display">
                <?php
                // === Verifica si la invitación se encontró y si la URL del QR ($qr_image_url) está construida ===
                // ($invitation_code se obtiene de $_GET['code'] al principio del script)
                // $qr_image_url ya está definido arriba como "qr/CODIGO.png" (URL web relativa)

                // === Construye la RUTA COMPLETA DEL SISTEMA DE ARCHIVOS para verificar si el archivo QR existe ===
                // __DIR__ es la ruta del sistema de archivos de la carpeta donde está showqr.php.
                // Asumiendo que la carpeta 'qr' está DENTRO de la misma carpeta donde está showqr.php (la raíz web).
                $qr_filesystem_path = $invitation_code ? __DIR__ . '/qr/' . urlencode($invitation_code) . '.png' : null; // <<< RUTA CORREGIDA PARA file_exists()

                // === Verifica si el archivo QR realmente existe en el servidor antes de intentar mostrar la imagen ===
                // Comprobamos que $qr_image_url no sea null (invitación válida) Y que el archivo exista en la ruta del sistema de archivos calculada.
                if ($invitation && $qr_image_url && $qr_filesystem_path && file_exists($qr_filesystem_path)):
                ?>
                    <img src="<?php echo htmlspecialchars($qr_image_url); ?>" alt="Código QR de Invitación">
                <?php else: ?>
                    <p style="color: red;">Error: No se pudo cargar la imagen del QR.</p>

                    <?php
                    // --- Información de Depuración (Temporal) ---
                    // Esto te ayudará a ver qué ruta está verificando PHP y si el archivo existe allí.
                    if ($invitation_code): // Solo mostramos esto si al menos tenemos un código de invitación
                         echo '<p style="font-size: 0.8em; color: #888;">Depuración:</p>';
                         echo '<p style="font-size: 0.8em; color: #888;">Código de invitación: <code>' . htmlspecialchars($invitation_code) . '</code></p>';
                         echo '<p style="font-size: 0.8em; color: #888;">URL de la imagen (navegador busca): <code>' . htmlspecialchars($qr_image_url ?? 'N/A') . '</code></p>';
                         echo '<p style="font-size: 0.8em; color: #888;">Ruta del sistema (PHP verifica): <code>' . htmlspecialchars($qr_filesystem_path ?? 'N/A') . '</code></p>';
                         if ($qr_filesystem_path):
                             echo '<p style="font-size: 0.8em; color: #888;">Archivo existe en ruta del sistema: <strong>' . (file_exists($qr_filesystem_path) ? 'Sí' : 'No') . '</strong></p>';
                         endif;
                         echo '<p style="font-size: 0.8em; color: #888;">Existe $invitation: <strong>' . ($invitation ? 'Sí' : 'No') . '</strong></p>';
                    endif;
                    // --- Fin Información de Depuración ---
                    ?>
                    <p style="font-size: 0.9em;">Posibles causas: El enlace es incorrecto, la invitación no existe, o el archivo QR no se guardó correctamente en la carpeta "qr" del servidor.</p>
                <?php endif; ?>
            </div>

            <p style="margin-top: 20px; font-size: 0.9em; color: #555;">Presenta este código QR al personal de seguridad para validar tu entrada.</p>

        <?php endif; // Fin de la condición para mostrar error o detalles de invitación ?>

        <p style="margin-top: 30px;"><a href="../index.php">Volver a Inicio</a></p>

    </div>
</body>
</html>