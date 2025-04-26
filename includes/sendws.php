<?php


if ($_POST['sendWhatsapp']) {
$number = $_POST['number'];
$msg = $_POST['msg'];
    // Ejemplo de uso:
$respuesta = sendws($number, $msg);
echo "Respuesta del servidor: " . $respuesta;
}

function sendws($telefono, $mensaje) {
$url = 'http://149.50.129.89:3004/send/message';
$postData = array(
        'phone' => $telefono,
        'message' => $mensaje
    );
    $options = array(
        'http' => array(
            'method'  => 'POST',
            'header'  => 'Content-type: application/x-www-form-urlencoded',
            'content' => http_build_query($postData)
        )
    );
    $context  = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    return $result;
}

