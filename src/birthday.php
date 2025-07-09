<?php
// Importar las clases de PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Cargar el autoload de Composer
require __DIR__ . '/vendor/autoload.php';

// Configuración de conexión SMTP
$smtp_host = "mail.cybermatica.com.py";
$smtp_port = 587;
$smtp_username = "cursos@cybermatica.com.py";
$smtp_password = "Cyb3rm4t1ca";
$from_email = "cursos@cybermatica.com.py";
$from_name = "Cybermatica Academy";

// Configuración de WhatsApp API
$whatsapp_instance_id = "72552";
$whatsapp_token = "kPlGtmv3vp5JlCJaKOleqLNXisAjS43n0OHI0NW81ce15e9c";
$cumple_image_url = "https://www.cybermatica.com.py/registro/image/cumple.jpeg";

// Número para notificaciones (opcional)
define('NOTIFICATION_NUMBER', '0981123456'); // Cambiar por tu número

// Configuración de conexión a la base de datos
$host_db = 'localhost';
$user_db = 'root';
$password_db = 'Orlando4375820321';
$dbname = 'registro_curso';

// Conexión a la base de datos
$conn = new mysqli($host_db, $user_db, $password_db, $dbname);
if ($conn->connect_error) {
    die("Error al conectar a la base de datos: " . $conn->connect_error);
}
$conn->set_charset("utf8");

// ==================== FUNCIONES DE WHATSAPP ====================

// Updated function to format phone number
function format_phone_number($phone) {
    $phone = preg_replace('/\D/', '', $phone);
    if (substr($phone, 0, 3) !== '595') {
        $phone = '595' . substr($phone, 1);
    }
    return $phone . '@c.us';
}

// Function to generate public URL for uploaded file
function generatePublicURL($filename) {
    $public_base_url = 'https://www.cybermatica.com.py/registro/image/';
    $public_url = $public_base_url . $filename;
    
    error_log("URL pública generada: " . $public_url);
    return $public_url;
}

// Function to verify if public URL is accessible
function verifyPublicURL($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $result = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $is_accessible = ($http_code >= 200 && $http_code < 300);
    error_log("Verificación de URL " . $url . ": " . ($is_accessible ? "ACCESIBLE (HTTP $http_code)" : "NO ACCESIBLE (HTTP $http_code)"));
    
    return $is_accessible;
}

// Función para formatear bytes
function formatBytes($size, $precision = 2) {
    $base = log($size, 1024);
    $suffixes = array('B', 'KB', 'MB', 'GB', 'TB');
    return round(pow(1024, $base - floor($base)), $precision) . ' ' . $suffixes[floor($base)];
}

// Función para enviar requests cURL
function sendCurlRequest($url, $headers, $data) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    return [
        'success' => $http_code === 200,
        'http_code' => $http_code,
        'response' => $response,
        'error' => $curl_error ?: ($http_code !== 200 ? "HTTP $http_code: $response" : null)
    ];
}

// FUNCIÓN PARA ACTUALIZAR ESTADO INDIVIDUAL EN BD (adaptada para cumpleaños)
function updateBirthdayStatus($conn, $phone, $status, $error_message = null) {
    // Crear tabla de logs de cumpleaños si no existe
    $create_table = "CREATE TABLE IF NOT EXISTS cumple_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        telefono VARCHAR(20),
        estado VARCHAR(20),
        fecha_envio DATETIME,
        error_message TEXT,
        INDEX(telefono, fecha_envio)
    )";
    $conn->query($create_table);
    
    $stmt = $conn->prepare("INSERT INTO cumple_logs (telefono, estado, fecha_envio, error_message) VALUES (?, ?, NOW(), ?)");
    $stmt->bind_param("sss", $phone, $status, $error_message);
    $result = $stmt->execute();
    $stmt->close();
    
    error_log("Estado de cumpleaños registrado: $phone -> $status" . ($error_message ? " (Error: $error_message)" : ""));
    return $result;
}

// NUEVA FUNCIÓN PARA REGISTRO DE EMAILS
function updateEmailStatus($conn, $email, $status, $error_message = null) {
    $create_table = "CREATE TABLE IF NOT EXISTS email_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255),
        estado VARCHAR(20),
        fecha_envio DATETIME,
        error_message TEXT,
        INDEX(email, fecha_envio)
    )";
    $conn->query($create_table);
    
    $stmt = $conn->prepare("INSERT INTO email_logs (email, estado, fecha_envio, error_message) VALUES (?, ?, NOW(), ?)");
    $stmt->bind_param("sss", $email, $status, $error_message);
    $stmt->execute();
    $stmt->close();
}

// FUNCIÓN PARA ENVIAR WHATSAPP DE CUMPLEAÑOS
function sendBirthdayWhatsApp($recipient, $name, $image_url) {
    global $whatsapp_instance_id, $whatsapp_token, $conn;
    
    $headers = [
        'Accept: application/json',
        'Content-Type: application/json',
        'Authorization: Bearer ' . $whatsapp_token
    ];

    // Formatear número si no contiene @c.us
    if (strpos($recipient, '@c.us') === false) {
        $recipient = format_phone_number($recipient);
    }

    error_log("=== ENVIANDO CUMPLEAÑOS POR WHATSAPP ===");
    error_log("Destinatario: $recipient");
    error_log("Nombre: $name");

    // Mensaje llamativo para WhatsApp
    $whatsapp_message = "🎉🎂 ¡FELIZ CUMPLEAÑOS, " . strtoupper($name) . "! 🎂🎉\n\n"
        . "🌟 ¡Hoy es tu día especial! 🌟\n\n"
        . "🎈 Que este nuevo año de vida esté lleno de:\n"
        . "✨ Alegría infinita\n"
        . "💫 Sueños cumplidos  \n"
        . "🌈 Momentos inolvidables\n"
        . "🎁 Sorpresas maravillosas\n"
        . "💖 Amor y felicidad\n"
        . "🚀 Éxito en todos tus proyectos\n\n"
        . "🎊 Desde Cybermatica Academy te enviamos los mejores deseos en tu día especial.\n\n"
        . "¡Que tengas un cumpleaños ESPECTACULAR! 🥳🎉\n\n"
        . "#FelizCumpleaños #CybermaticaFamily";

    // PASO 1: Enviar la imagen
    $media_url = "https://waapi.app/api/v1/instances/{$whatsapp_instance_id}/client/action/send-media";
    
    $media_data = [
        "chatId" => $recipient,
        "mediaUrl" => $image_url,
        "asDocument" => false,
        "asVoice" => false,
        "asSticker" => false,
        "previewLink" => true
    ];

    error_log("Enviando imagen de cumpleaños: $image_url");
    $media_result = sendCurlRequest($media_url, $headers, $media_data);
    
    if ($media_result['success']) {
        error_log("✅ Imagen de cumpleaños enviada exitosamente a $recipient");
        
        // PASO 2: Esperar un momento y enviar el mensaje
        usleep(2000000); // Esperar 2 segundos
        
        $text_url = "https://waapi.app/api/v1/instances/{$whatsapp_instance_id}/client/action/send-message";
        $text_data = [
            "chatId" => $recipient,
            "message" => $whatsapp_message
        ];
        
        error_log("Enviando mensaje de felicitación");
        $text_result = sendCurlRequest($text_url, $headers, $text_data);
        
        if ($text_result['success']) {
            error_log("✅ Mensaje de felicitación enviado exitosamente a $recipient");
            updateBirthdayStatus($conn, $recipient, 'enviado');
            return ['success' => true, 'message' => 'WhatsApp enviado correctamente'];
        } else {
            error_log("❌ Error enviando mensaje de felicitación a $recipient: " . $text_result['error']);
            updateBirthdayStatus($conn, $recipient, 'error', 'Error enviando mensaje: ' . $text_result['error']);
            return ['success' => false, 'message' => 'Error enviando mensaje: ' . $text_result['error']];
        }
    } else {
        error_log("❌ Error enviando imagen de cumpleaños a $recipient: " . $media_result['error']);
        updateBirthdayStatus($conn, $recipient, 'error', 'Error enviando imagen: ' . $media_result['error']);
        return ['success' => false, 'message' => 'Error enviando imagen: ' . $media_result['error']];
    }
}

// ==================== PROCESO PRINCIPAL ====================

// Consulta para obtener las personas que cumplen años hoy (incluyendo teléfono)
$sql = "SELECT nombre, mail, telefono FROM personas WHERE DATE_FORMAT(fecha_cumple, '%m-%d') = DATE_FORMAT(CURDATE(), '%m-%d')";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    echo "<h2>🎉 Procesando cumpleaños del día: " . date('d/m/Y') . " 🎉</h2>";
    
    // Verificar que la imagen de cumpleaños esté accesible
    echo "<h3>🔍 Verificando imagen de cumpleaños...</h3>";
    if (verifyPublicURL($cumple_image_url)) {
        echo "✅ <strong>Imagen de cumpleaños accesible:</strong> $cumple_image_url<br><br>";
    } else {
        echo "⚠️ <strong>ADVERTENCIA:</strong> La imagen de cumpleaños no está accesible: $cumple_image_url<br><br>";
    }
    
    $total_processed = 0;
    $email_success = 0;
    $whatsapp_success = 0;
    $email_errors = 0;
    $whatsapp_errors = 0;
    
    while ($row = $result->fetch_assoc()) {
        $to_email = $row['mail'];
        $name = $row['nombre'];
        $telefono = $row['telefono'];
        $total_processed++;
        
        echo "<hr><h3>👤 Procesando: $name (#$total_processed)</h3>";
        
        // ==================== ENVÍO DE CORREO ====================
        $mail = new PHPMailer(true);
        
        try {
            // Configurar servidor SMTP
            $mail->isSMTP();
            $mail->Host = $smtp_host;
            $mail->SMTPAuth = true;
            $mail->Username = $smtp_username;
            $mail->Password = $smtp_password;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $smtp_port;

            // Configurar UTF-8
            $mail->CharSet = 'UTF-8';

            // Configuración del correo
            $mail->setFrom($from_email, $from_name);
            $mail->addAddress($to_email, $name);
            
            // Asunto llamativo
            $subject = "🎉🎂 ¡FELIZ CUMPLEAÑOS, $name! 🎂🎉";
            $mail->Subject = "=?UTF-8?B?" . base64_encode($subject) . "?=";

            // Adjuntar la imagen de la firma
            $firma_path = __DIR__ . '/image/firma.png';
            if (file_exists($firma_path)) {
                $mail->AddEmbeddedImage($firma_path, 'firma', 'firma.png');
            }

            // Cuerpo del correo en HTML más llamativo (SIN imagen de cumpleaños)
            $mail->isHTML(true);
            $mail->Body = "
                <html>
                <head>
                    <style>
                        body {
                            font-family: 'Arial', sans-serif;
                            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                            margin: 0;
                            padding: 20px;
                        }
                        .container {
                            background-color: #ffffff;
                            padding: 30px;
                            margin: 10px auto;
                            border-radius: 20px;
                            width: 80%;
                            max-width: 600px;
                            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
                            border: 3px solid #ff6b6b;
                        }
                        .header {
                            text-align: center;
                            background: linear-gradient(45deg, #ff6b6b, #feca57);
                            -webkit-background-clip: text;
                            -webkit-text-fill-color: transparent;
                            background-clip: text;
                            font-size: 32px;
                            font-weight: bold;
                            margin-bottom: 20px;
                            text-shadow: 2px 2px 4px rgba(0,0,0,0.1);
                        }
                        .birthday-emoji {
                            font-size: 48px;
                            text-align: center;
                            margin: 20px 0;
                        }
                        .message {
                            font-size: 18px;
                            color: #2c3e50;
                            text-align: center;
                            line-height: 1.6;
                            margin: 20px 0;
                        }
                        .wishes {
                            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                            color: white;
                            padding: 20px;
                            border-radius: 15px;
                            margin: 20px 0;
                            text-align: center;
                        }
                        .wishes h3 {
                            margin-top: 0;
                            font-size: 22px;
                        }
                        .wishes ul {
                            list-style: none;
                            padding: 0;
                        }
                        .wishes li {
                            margin: 10px 0;
                            font-size: 16px;
                        }
                        hr {
                            border: none;
                            border-top: 2px solid #ff6b6b;
                            margin: 30px 0;
                        }
                        .firma {
                            text-align: left;
                            margin-top: 20px;
                        }
                        .firma img {
                            max-width: 400px;
                        }
                        .footer {
                            text-align: center;
                            color: #7f8c8d;
                            font-style: italic;
                            margin-top: 20px;
                        }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>¡FELIZ CUMPLEAÑOS!</div>
                        <div class='birthday-emoji'>🎉🎂🎈🎁🥳</div>
                        
                        <div class='message'>
                            <h2 style='color: #e74c3c; text-align: center;'>¡Querido/a $name!</h2>
                            <p>Hoy es un día muy especial porque celebramos tu cumpleaños. 🌟</p>
                        </div>
                        
                        <div class='wishes'>
                            <h3>🎊 Te deseamos que este nuevo año de vida esté lleno de:</h3>
                            <ul>
                                <li>✨ Alegría infinita y sonrisas constantes</li>
                                <li>💫 Sueños cumplidos y metas alcanzadas</li>
                                <li>🌈 Momentos inolvidables con tus seres queridos</li>
                                <li>🎁 Sorpresas maravillosas en cada día</li>
                                <li>💖 Amor, salud y mucha felicidad</li>
                                <li>🚀 Éxito en todos tus proyectos</li>
                            </ul>
                        </div>
                        
                        <div class='message'>
                            <p><strong>Desde todo el equipo de Cybermatica Soluciones Tecnológicas, te enviamos nuestros mejores deseos en tu día especial.</strong></p>
                            <p style='font-size: 20px; color: #e74c3c;'><strong>¡Que tengas un cumpleaños ESPECTACULAR! 🥳</strong></p>
                        </div>
                        
                        <hr>";
            
            // Solo agregar firma si existe el archivo
            if (file_exists($firma_path)) {
                $mail->Body .= "<div class='firma'><img src='cid:firma' alt='Firma de Cybermatica'></div>";
            }
            
            $mail->Body .= "
                        <div class='footer'>
                            Con cariño, el equipo de Cybermatica 💙
                        </div>
                    </div>
                </body>
                </html>
            ";

            // Enviar correo
            $mail->send();
            echo "✅ <strong>Correo enviado correctamente a:</strong> $to_email<br>";
            $email_success++;
            updateEmailStatus($conn, $to_email, 'enviado');
        } catch (Exception $e) {
            echo "❌ <strong>Error al enviar el correo a $to_email:</strong> {$mail->ErrorInfo}<br>";
            $email_errors++;
            updateEmailStatus($conn, $to_email, 'error', $mail->ErrorInfo);
        }
        
        // ==================== ENVÍO DE WHATSAPP ====================
        if (!empty($telefono)) {
            $formatted_phone = format_phone_number($telefono);
            echo "📱 <strong>Enviando WhatsApp a:</strong> $telefono (formateado: $formatted_phone)<br>";
            
            $whatsapp_result = sendBirthdayWhatsApp($formatted_phone, $name, $cumple_image_url);
            
            if ($whatsapp_result['success']) {
                echo "✅ <strong>WhatsApp enviado correctamente</strong><br>";
                $whatsapp_success++;
            } else {
                echo "❌ <strong>Error al enviar WhatsApp:</strong> " . $whatsapp_result['message'] . "<br>";
                $whatsapp_errors++;
            }
        } else {
            echo "⚠️ <strong>No se pudo enviar WhatsApp:</strong> Número de teléfono vacío<br>";
            $whatsapp_errors++;
        }
        
        echo "<br>";
        
        // Pausa entre contactos para evitar sobrecarga
        if ($total_processed % 5 == 0) {
            echo "<div style='background: #f0f0f0; padding: 10px; margin: 10px 0; border-radius: 5px;'>";
            echo "⏸️ <strong>Pausa técnica...</strong> (procesados: $total_processed)<br>";
            echo "Memoria actual: " . formatBytes(memory_get_usage(true)) . "<br>";
            echo "</div>";
            sleep(2); // Pausa de 2 segundos cada 5 contactos
        }
    }
    
    // ==================== RESUMEN FINAL ====================
    echo "<hr><div style='background: #e8f5e8; padding: 20px; border-radius: 10px; margin: 20px 0;'>";
    echo "<h3>📊 RESUMEN FINAL</h3>";
    echo "<strong>Total procesados:</strong> $total_processed<br>";
    echo "<strong>📧 Correos:</strong> ✅ $email_success exitosos | ❌ $email_errors errores<br>";
    echo "<strong>📱 WhatsApp:</strong> ✅ $whatsapp_success exitosos | ❌ $whatsapp_errors errores<br>";
    echo "<strong>🕒 Finalizado:</strong> " . date('d/m/Y H:i:s') . "<br>";
    echo "<strong>💾 Memoria final:</strong> " . formatBytes(memory_get_usage(true)) . "<br>";
    echo "</div>";
    
} else {
    echo "<h2>📅 No hay cumpleaños el día de hoy (" . date('d/m/Y') . ")</h2>";
}

// Cerrar conexión a la base de datos
$conn->close();
?>
