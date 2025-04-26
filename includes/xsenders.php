<?php
require_once __DIR__ . '/config.php'; 
echo "xsenders.php";

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

// Ejemplo de uso:
$telefono = '549116492ss';
$mensaje = 'check';
$respuesta = sendws($telefono, $mensaje);
echo "Respuesta del servidor: " . $respuesta;



if ($_POST['sendWhatsapp']) {
    $number = $_POST['number'];
    $name = $_POST['msg'];

    echo $_POST['sendWhatsapp'];
}






function sendWhatsapp($number, $msg){
  
//check country ar and add 9
$variable = $number;
if (substr($variable, 0, 2) == "54") {
    if (substr($variable, 2, 1) != "9") {
        $number = substr_replace($variable, "9", 2, 0);
    }
}


        $url = $GLOBALS['whatserver'].'/send/message';	
        $data = array(
        'phone' => $number,
        'message' => $msg);  
    
    // use key 'http' even if you send the request to https://...
    $options = array(
        'http' => array(
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
           // 'header' => "Content-Type: application/x-www-form-urlencoded\r\n".
           // 'Authorization: Basic ' . base64_encode( $GLOBALS['whatsuser'] . ':' .  $GLOBALS['whatspass']),
            //'header' => 'Authorization: Basic ' . base64_encode( $GLOBALS['whatsuser'] . ':' .  $GLOBALS['whatspass']),
            'method'  => 'POST',
            'content' => http_build_query($data)
        )
    );
    $context  = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    echo $result;
    if (!$result) { /* Handle error */
        $str =  "ALERTA BOT ".$_SERVER['HTTP_HOST']." ERROR al enviar mensaje a WS: $number >>".$_REQUEST['xsendwhatsapp'];
        sendTel($str, $GLOBALS['devtelid']);
        exit;
     
    }

}


function sendWhatsappWeb($version, $number, $name, $link, $data1, $data2, $data3, $wsmsg, $attach){

$XsubVersions = explode(".", $version); //experience and subversion
$xExperience = $XsubVersions[0]; //experience - client
$xVersion = $XsubVersions[1]; //subversion
$xpfullname = $name.' '.$data1; //fullname
//check country ar and add 9
$variable = $number;
if (substr($variable, 0, 2) == "54") {
    if (substr($variable, 2, 1) != "9") {
        $number = substr_replace($variable, "9", 2, 0);
    }

}
$xbodyrep = str_replace(
    array("%%xpname%%","%%xplink%%","%%xpdata1%%","%%xpdata2%%","%%xpdata3%%"),
    array(ucwords($name), "\n".$link, $data1, $data2, $data3),
    $wsmsg
);

if ($attach != '') {
    $urlinkbase = $GLOBALS['urlinkbase']; //mainsite url
    $xattachrep = str_replace(
        //NAMEFIRST first name only for attach.            
        array("%%xpname%%","%%xpfullname%%","%%xpdata1%%","%%xpdata2%%","%%xpdata3%%"),
        array(nameFirst($name), slugify($xpfullname), slugify($data1), slugify($data2), slugify($data3)),
       //array($name, $data1, $data2, $data3),
        $attach
    );

$medialink = "$urlinkbase/aaxreactor/$xExperience/aaxfiles/$xattachrep"; //version files 
$headers = get_headers($medialink);

if (($headers && strpos($headers[0], "200") !== false)) {
    $url = $GLOBALS['whatserver'].'/send/media';	
} else {
    $url = $GLOBALS['whatserver'].'/send/message';	
    //$url = $GLOBALS['whatserver'].'/send/media';
} 

$data = array(
'phone' => $number,
'message' => urldecode($xbodyrep), //added urldecode prevention
'media' =>  $medialink
    );
} else { //if not, only text message
    $url = $GLOBALS['whatserver'].'/send/message';	
    $data = array(
    'phone' => $number,
    'message' => $xbodyrep);
}

// use key 'http' even if you send the request to https://...
$options = array(
    'http' => array(
        //'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
        'header' => "Content-Type: application/x-www-form-urlencoded\r\n".
        'Authorization: Basic ' . base64_encode( $GLOBALS['whatsuser'] . ':' .  $GLOBALS['whatspass']),
        //'header' => 'Authorization: Basic ' . base64_encode( $GLOBALS['whatsuser'] . ':' .  $GLOBALS['whatspass']),
        'method'  => 'POST',
        'content' => http_build_query($data)
    )
);
$context  = stream_context_create($options);
$result = file_get_contents($url, false, $context);
echo $result;
if (!$result) { /* Handle error */
    $str =  "ALERTA BOT ".$GLOBALS['projectname']." ERROR al enviar mensaje a $name WS: $number >>".$_REQUEST['xsendwhatsapp'];
    sendTel($str, $GLOBALS['devtelid']);
    exit;
} 

// sendwsdbrecord($version, $number, $name, $link, $data1, $data2, $data3);
sendwsdbrecord($version, $number, $name, $link, $data1, $data2, $data3);
$str = "Mensaje a $name WS: $number enviado. >>".$_REQUEST['xsendwhatsapp'];
sendTel($str, $GLOBALS['devtelid']);

var_dump($result);
};