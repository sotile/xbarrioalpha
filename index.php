<?php
// htdocs/index.php - Landing Page del Sistema de Control de Acceso

require_once __DIR__ . '/includes/auth.php'; // Incluye el manejo de autenticación
require_once __DIR__ . '/includes/config.php'; // Incluye la configuración (para datos del landing y roles)

start_session_if_not_started();

// Verificar si el usuario está logueado. Si no, redirigir al login.
if (!is_logged_in()) {
    redirect('login.php'); // Asegúrate de que la ruta a login.php sea correcta
}

// Obtener los datos del usuario logueado (necesario si usas restricción por rol en los botones y para mostrar el nombre)
$current_user = gets_current_user();
$user_role = $current_user['role'] ?? 'guest'; // Rol del usuario logueado
$username = $current_user['name'] ?? 'Usuario Desconocido'; // Nombre del usuario logueado para el footer


/**
 * Helper function para verificar si un botón debe mostrarse al usuario actual.
 * Requiere que $user_role y $landing_buttons estén disponibles en el scope.
 *
 * @param array $button La configuración del botón desde $landing_buttons.
 * @param string $user_role El rol del usuario logueado.
 * @return bool True si el botón es visible, False si no.
 */
// Helper function para verificar si un botón debe mostrarse al usuario actual.
// Espera que la clave 'roles' esté presente en la configuración del botón.
function is_button_visible_for_role($button, $user_role) {
    // Verificar si la clave 'roles' existe y es un array
    if (!isset($button['roles']) || !is_array($button['roles'])) {
        // Si la clave 'roles' falta, loguear una advertencia (opcional pero útil para depurar)
        error_log("Advertencia: La configuración del botón '" . ($button['label'] ?? 'Botón desconocido') . "' no tiene el array 'roles'. Se ocultará.");
        // Por seguridad, ocultar el botón si la configuración es incorrecta o falta
        return false;
    }
    // Verificar si el rol del usuario actual está incluido en el array 'roles' permitidos para este botón
    return in_array($user_role, $button['roles']);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inicio <?php echo $main_name ?></title>
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
    <a href="logout.php" class="logout-btn">Salir</a>

    <div class="landing-image-container">
        <img src="<?php echo htmlspecialchars($landing_image_url ?? ''); ?>" alt="Imagen de fondo del barrio">
        <div class="main-title"><?php echo htmlspecialchars($main_title ?? ''); ?></div>
    </div>

    <div class="news-section">
        <h3>Novedades</h3>
        <p><?php echo nl2br(htmlspecialchars($news_text ?? 'No hay novedades disponibles.')); ?></p>
    </div>

    <div class="button-grid">
        <?php
        // Iterar sobre la configuración de botones desde config.php
        foreach ($landing_buttons as $button):
            // === VERIFICAR SI EL BOTÓN ES VISIBLE PARA EL ROL ACTUAL ===
            if (is_button_visible_for_role($button, $user_role)):
        ?>
            <a href="<?php echo htmlspecialchars($button['link'] ?? '#'); ?>" class="grid-button">
                <img src="<?php echo htmlspecialchars($button['icon'] ?? ''); ?>" alt="<?php echo htmlspecialchars($button['label'] ?? ''); ?> Icon">
                <span><?php echo htmlspecialchars($button['label'] ?? ''); ?></span>
            </a>
        <?php
            endif; // Fin de la verificación de visibilidad por rol
        endforeach;
        ?>
    </div>

    <div class="footer">
        <span><?php echo htmlspecialchars($username); ?></span>
    </div>

</body>
</html>