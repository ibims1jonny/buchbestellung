<?php
// Fehlermeldungen aktivieren (während der Entwicklung)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Logs-Verzeichnis erstellen, falls es nicht existiert
$logDir = __DIR__ . '/logs';
if (!file_exists($logDir)) {
    mkdir($logDir, 0777, true);
}

// Logdateipfade definieren
$formLogFile = $logDir . '/logs/form_data.log';
$smtpLogFile = $logDir . '/logs/smtp_debug.log';
$successLogFile = $logDir . '/logs/email_success.log';
$errorLogFile = $logDir . '/logs/email_error.log';

// PHPMailer-Klassen einbinden
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require 'phpmailer/src/Exception.php';
require 'phpmailer/src/PHPMailer.php';
require 'phpmailer/src/SMTP.php';

// Überprüfen, ob das Formular abgesendet wurde
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Temporärer Test: Alle POST-Daten in eine Datei schreiben
    file_put_contents($formLogFile, date('Y-m-d H:i:s') . " - POST-Daten: " . print_r($_POST, true) . "\n\n", FILE_APPEND);
    
    // Zusätzliche Debug-Informationen
    file_put_contents($formLogFile, date('Y-m-d H:i:s') . " - REQUEST_METHOD: " . $_SERVER["REQUEST_METHOD"] . "\n", FILE_APPEND);
    if (isset($_SERVER["CONTENT_TYPE"])) {
        file_put_contents($formLogFile, date('Y-m-d H:i:s') . " - Content-Type: " . $_SERVER["CONTENT_TYPE"] . "\n", FILE_APPEND);
    } else {
        file_put_contents($formLogFile, date('Y-m-d H:i:s') . " - Content-Type nicht gesetzt\n", FILE_APPEND);
    }
    file_put_contents($formLogFile, date('Y-m-d H:i:s') . " - HTTP_ACCEPT: " . $_SERVER["HTTP_ACCEPT"] . "\n\n", FILE_APPEND);
    
    // Debug-Ausgaben entfernen
    // echo "<pre>POST-Daten: ";
    // print_r($_POST);
    // echo "</pre>";
    
    // Formulardaten sammeln und bereinigen
    $name = isset($_POST['name']) ? htmlspecialchars($_POST['name'], ENT_QUOTES, 'UTF-8') : '';
    $email = isset($_POST['email']) ? filter_var($_POST['email'], FILTER_SANITIZE_EMAIL) : '';
    $address = isset($_POST['address']) ? htmlspecialchars($_POST['address'], ENT_QUOTES, 'UTF-8') : '';
    $delivery = isset($_POST['delivery']) ? htmlspecialchars($_POST['delivery'], ENT_QUOTES, 'UTF-8') : '';
    $message = isset($_POST['message']) ? htmlspecialchars($_POST['message'], ENT_QUOTES, 'UTF-8') : '';
    
    // Validierung der E-Mail-Adresse mit besserer Fehlermeldung
    if (empty($email)) {
        file_put_contents($formLogFile, date('Y-m-d H:i:s') . " - Fehler: E-Mail ist leer\n", FILE_APPEND);
        echo json_encode(['success' => false, 'message' => 'Bitte gib eine E-Mail-Adresse ein.']);
        exit;
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        file_put_contents($formLogFile, date('Y-m-d H:i:s') . " - Fehler: E-Mail ist ungültig: $email\n", FILE_APPEND);
        echo json_encode(['success' => false, 'message' => 'Die eingegebene E-Mail-Adresse ist ungültig.']);
        exit;
    }
    
    // E-Mail-Empfänger und Absender
    $to = 'jonathan.steindl@jsteindl.at'; // Alternative E-Mail-Adresse
    $from = 'noreply@jsteindl.at';
    
    // E-Mail-Betreff
    $subject = 'Neue Buchbestellung von ' . $name;
    
    // E-Mail-Inhalt
    $htmlMessage = '
    <html>
    <head>
        <title>Neue Buchbestellung</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            h2 { color: #2e7d32; }
            table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
            th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
            th { background-color: #f2f2f2; }
        </style>
    </head>
    <body>
        <div class="container">
            <h2>Neue Buchbestellung</h2>
            <table>
                <tr>
                    <th>Name:</th>
                    <td>' . $name . '</td>
                </tr>
                <tr>
                    <th>E-Mail:</th>
                    <td>' . $email . '</td>
                </tr>
                <tr>
                    <th>Lieferoption:</th>
                    <td>' . ($delivery == 'shipping' ? 'Versand' : 'Selbstabholung') . '</td>
                </tr>';
    
    // Adresse nur anzeigen, wenn Versand gewählt wurde und Adresse angegeben wurde
    if ($delivery == 'shipping' && !empty($address)) {
        $htmlMessage .= '
                <tr>
                    <th>Adresse:</th>
                    <td>' . nl2br($address) . '</td>
                </tr>';
    }
    
    // Nachricht nur anzeigen, wenn sie nicht leer ist
    if (!empty($message)) {
        $htmlMessage .= '
                <tr>
                    <th>Nachricht:</th>
                    <td>' . nl2br($message) . '</td>
                </tr>';
    }
    
    $htmlMessage .= '
            </table>
        </div>
    </body>
    </html>';
    
    // PHPMailer initialisieren
    $mail = new PHPMailer(true);
    
    try {
        // Server-Einstellungen
        $mail->SMTPDebug = 2;                      // Debug-Ausgabe aktivieren
        $mail->Debugoutput = function($str, $level) use ($smtpLogFile) {
            file_put_contents($smtpLogFile, date('Y-m-d H:i:s').": $str\n", FILE_APPEND);
        };
        $mail->isSMTP();                           // SMTP verwenden
        $mail->Host       = 'smtp.world4you.com';  // SMTP-Server
        $mail->SMTPAuth   = true;                  // SMTP-Authentifizierung aktivieren
        $mail->Username   = 'noreply@jsteindl.at'; // SMTP-Benutzername
        $mail->Password   = 'xpihTYJbHL3DeDbh6dHr'; // SMTP-Passwort
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // TLS-Verschlüsselung aktivieren
        $mail->Port       = 587;                   // TCP-Port (meist 587 für TLS)
        
        // Absender und Empfänger
        $mail->setFrom($from, 'Buchbestellung');
        $mail->addAddress($to);                    // Empfänger hinzufügen
        $mail->addReplyTo($email, $name);          // Reply-To-Adresse setzen
        
        // Inhalt
        $mail->isHTML(true);                       // E-Mail als HTML senden
        $mail->Subject = $subject;
        $mail->Body    = $htmlMessage;
        $mail->CharSet = 'UTF-8';
        
        // E-Mail senden
        $mail->send();
        
        // Erfolg in Logdatei schreiben
        file_put_contents($successLogFile, date('Y-m-d H:i:s') . " - E-Mail erfolgreich gesendet an: $to\n", FILE_APPEND);
        
        echo json_encode(['success' => true, 'message' => 'Vielen Dank für deine Bestellung! Wir werden uns bald bei dir melden.']);
    } catch (Exception $e) {
        // Fehler in Logdatei schreiben
        file_put_contents($errorLogFile, date('Y-m-d H:i:s') . " - Fehler beim Senden der E-Mail: " . $mail->ErrorInfo . "\n", FILE_APPEND);
        
        echo json_encode(['success' => false, 'message' => 'Beim Senden der Bestellung ist ein Fehler aufgetreten: ' . $mail->ErrorInfo]);
    }
    
} else {
    // Wenn das Skript direkt aufgerufen wird
    echo json_encode(['success' => false, 'message' => 'Ungültiger Zugriff']);
}
?> 