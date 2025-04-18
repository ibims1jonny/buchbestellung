<?php
// Am Anfang der Datei
ob_start();

// Session-Konfiguration
ini_set('session.cookie_lifetime', 3600);
ini_set('session.gc_maxlifetime', 3600);
ini_set('session.use_cookies', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));

// Vor session_start()
session_name('CAPTCHASESSION');

// Session starten (muss vor jeder Ausgabe stehen)
session_start();

// Direkt nach session_start()
error_log("SEND-MAIL: Session-ID: " . session_id());
error_log("SEND-MAIL: SESSION-Daten: " . print_r($_SESSION, true));

// Fehlermeldungen deaktivieren für Produktion
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ERROR); // Nur schwerwiegende Fehler melden

// Einfache Funktion zum Laden von .env-Dateien
function loadEnv($path) {
    if (!file_exists($path)) {
        error_log("ENV-Datei nicht gefunden: $path");
        return false;
    }
    
    if (!is_readable($path)) {
        error_log("ENV-Datei nicht lesbar: $path");
        return false;
    }
    
    try {
        $content = file_get_contents($path);
        if ($content === false) {
            error_log("Konnte ENV-Datei nicht lesen: $path");
            return false;
        }
        
        // Zeilenumbrüche normalisieren
        $content = str_replace(["\r\n", "\r"], "\n", $content);
        $lines = explode("\n", $content);
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Leere Zeilen und Kommentare überspringen
            if (empty($line) || strpos($line, '#') === 0) {
                continue;
            }
            
            // Prüfen, ob die Zeile ein '=' enthält
            if (strpos($line, '=') === false) {
                continue;
            }
            
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            
            // Anführungszeichen entfernen, falls vorhanden
            if (strpos($value, '"') === 0 && substr($value, -1) === '"') {
                $value = substr($value, 1, -1);
            } elseif (strpos($value, "'") === 0 && substr($value, -1) === "'") {
                $value = substr($value, 1, -1);
            }
            
            // Versuche putenv zu verwenden, falls verfügbar
            if (function_exists('putenv') && !in_array('putenv', explode(',', ini_get('disable_functions')))) {
                putenv("$name=$value");
            }
            
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Fehler beim Laden der ENV-Datei: " . $e->getMessage());
        return false;
    }
}

// Absoluten Pfad zur .env-Datei bestimmen
$envPath = __DIR__ . '/.env';

// Debug-Ausgabe zum Testen
error_log("Versuche .env zu laden von: $envPath");
error_log("Datei existiert: " . (file_exists($envPath) ? 'Ja' : 'Nein'));

// .env-Datei laden
$loaded = loadEnv($envPath);

// Debug-Ausgabe zum Testen
error_log("ENV geladen: " . ($loaded ? 'Ja' : 'Nein'));
if ($loaded) {
    error_log("ENV Variablen: " . print_r($_ENV, true));
}

// Fallback-Werte definieren, falls die .env-Datei nicht geladen werden kann
if (!$loaded || empty($_ENV['SMTP_HOST'])) {
    // Fehler protokollieren
    error_log("KRITISCH: .env-Datei konnte nicht geladen werden oder enthält unvollständige Daten!");
    
    // Statt Zugangsdaten im Code zu haben, geben wir einen Fehler zurück
    echo json_encode([
        'success' => false, 
        'message' => 'Serverkonfiguration unvollständig. Bitte kontaktiere den Administrator.'
    ]);
    exit;
}

// Logs-Verzeichnis erstellen, falls es nicht existiert
$logDir = __DIR__ . '/logs';
if (!file_exists($logDir)) {
    mkdir($logDir, 0777, true);
}

// Logdateipfade definieren
$formLogFile = $logDir . '/form_data.log';
$smtpLogFile = $logDir . '/smtp_debug.log';
$successLogFile = $logDir . '/email_success.log';
$errorLogFile = $logDir . '/email_error.log';

// PHPMailer-Klassen einbinden
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require 'phpmailer/src/Exception.php';
require 'phpmailer/src/PHPMailer.php';
require 'phpmailer/src/SMTP.php';

// Überprüfen, ob das Formular abgesendet wurde
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Debug-Informationen für Session und Captcha
    error_log("POST-Anfrage erhalten");
    error_log("Session-ID: " . session_id());
    error_log("SESSION-Daten: " . print_r($_SESSION, true));
    error_log("POST-Daten: " . print_r($_POST, true));
    
    // Captcha-Überprüfung
    if (isset($_POST['captcha_answer']) && isset($_POST['captcha_token'])) {
        error_log("SEND-MAIL: Captcha-Antwort erhalten: " . $_POST['captcha_answer']);
        error_log("SEND-MAIL: Captcha-Token erhalten: " . $_POST['captcha_token']);
        
        $userAnswer = (int)$_POST['captcha_answer'];
        $token = $_POST['captcha_token'];
        
        // Überprüfe, ob die Datei existiert
        $tempDir = __DIR__ . '/temp';
        $captchaFile = $tempDir . '/' . $token . '.json';
        
        error_log("SEND-MAIL: Suche Captcha-Datei: $captchaFile");
        error_log("SEND-MAIL: Temp-Verzeichnis existiert: " . (file_exists($tempDir) ? 'Ja' : 'Nein'));
        
        if (file_exists($tempDir)) {
            error_log("SEND-MAIL: Dateien im Temp-Verzeichnis: " . implode(", ", scandir($tempDir)));
        }
        
        if (file_exists($captchaFile)) {
            error_log("SEND-MAIL: Captcha-Datei gefunden");
            
            $captchaData = json_decode(file_get_contents($captchaFile), true);
            if ($captchaData === null) {
                error_log("SEND-MAIL: Fehler beim Dekodieren der Captcha-Daten: " . json_last_error_msg());
                error_log("SEND-MAIL: Dateiinhalt: " . file_get_contents($captchaFile));
            }
            
            $correctAnswer = isset($captchaData['result']) ? (int)$captchaData['result'] : null;
            $timestamp = isset($captchaData['timestamp']) ? (int)$captchaData['timestamp'] : null;
            
            error_log("SEND-MAIL: Korrekte Antwort: " . $correctAnswer);
            error_log("SEND-MAIL: Benutzerantwort: " . $userAnswer);
            
            // Lösche die Datei, um Wiederverwendung zu verhindern
            if (!unlink($captchaFile)) {
                error_log("SEND-MAIL: Konnte Captcha-Datei nicht löschen: $captchaFile");
            }
            
            // Überprüfe, ob das Captcha nicht älter als 10 Minuten ist
            if ($timestamp && time() - $timestamp > 600) {
                error_log("SEND-MAIL: Captcha abgelaufen");
                
                // Puffer leeren
                ob_clean();
                
                // Content-Type-Header setzen
                header('Content-Type: application/json');
                
                echo json_encode(['success' => false, 'message' => 'Das Captcha ist abgelaufen. Bitte versuche es erneut.']);
                exit;
            }
            
            // Überprüfe die Antwort
            if ($correctAnswer === null || $userAnswer !== $correctAnswer) {
                error_log("SEND-MAIL: Captcha falsch: $userAnswer != $correctAnswer");
                
                // Puffer leeren
                ob_clean();
                
                // Content-Type-Header setzen
                header('Content-Type: application/json');
                
                echo json_encode(['success' => false, 'message' => 'Die Captcha-Antwort ist falsch. Bitte versuche es erneut.']);
                exit;
            }
            
            error_log("SEND-MAIL: Captcha korrekt gelöst");
        } else {
            error_log("SEND-MAIL: Captcha-Datei nicht gefunden: $captchaFile");
            
            // Puffer leeren
            ob_clean();
            
            // Content-Type-Header setzen
            header('Content-Type: application/json');
            
            echo json_encode(['success' => false, 'message' => 'Bitte löse das Captcha erneut.']);
            exit;
        }
    } else {
        error_log("SEND-MAIL: Keine Captcha-Antwort oder Token erhalten");
        
        // Puffer leeren
        ob_clean();
        
        // Content-Type-Header setzen
        header('Content-Type: application/json');
        
        echo json_encode(['success' => false, 'message' => 'Bitte löse das Captcha.']);
        exit;
    }
    
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
    
    // E-Mail-Empfänger und Absender aus Umgebungsvariablen
    $to = $_ENV['EMAIL_TO'];
    $from = $_ENV['EMAIL_FROM'];
    
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
        $mail->SMTPDebug = 0; // Debug-Ausgabe deaktivieren
        $mail->Debugoutput = function($str, $level) use ($smtpLogFile) {
            file_put_contents($smtpLogFile, date('Y-m-d H:i:s').": $str\n", FILE_APPEND);
        };
        $mail->isSMTP();                           // SMTP verwenden
        $mail->Host       = $_ENV['SMTP_HOST'];    // SMTP-Server aus Umgebungsvariable
        $mail->SMTPAuth   = true;                  // SMTP-Authentifizierung aktivieren
        $mail->Username   = $_ENV['SMTP_USERNAME']; // SMTP-Benutzername aus Umgebungsvariable
        $mail->Password   = $_ENV['SMTP_PASSWORD']; // SMTP-Passwort aus Umgebungsvariable
        $mail->SMTPSecure = $_ENV['SMTP_ENCRYPTION'] === 'tls' ? PHPMailer::ENCRYPTION_STARTTLS : PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = (int)$_ENV['SMTP_PORT']; // TCP-Port aus Umgebungsvariable
        
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
        
        // Puffer leeren
        ob_clean();
        
        // Content-Type-Header setzen
        header('Content-Type: application/json');
        
        echo json_encode(['success' => true, 'message' => 'Vielen Dank für deine Bestellung! Wir werden uns bald bei dir melden.']);
        exit;
    } catch (Exception $e) {
        // Fehler in Logdatei schreiben
        file_put_contents($errorLogFile, date('Y-m-d H:i:s') . " - Fehler beim Senden der E-Mail: " . $mail->ErrorInfo . "\n", FILE_APPEND);
        
        // Puffer leeren
        ob_clean();
        
        // Content-Type-Header setzen
        header('Content-Type: application/json');
        
        echo json_encode(['success' => false, 'message' => 'Beim Senden der Bestellung ist ein Fehler aufgetreten: ' . $mail->ErrorInfo]);
        exit;
    }
    
} else {
    error_log("Direkter Aufruf von send-mail.php");
    
    // Generiere zwei zufällige Zahlen zwischen 1 und 10
    $num1 = rand(1, 10);
    $num2 = rand(1, 10);
    $result = $num1 + $num2;
    
    // Erstelle einen Token für das Captcha
    $token = bin2hex(random_bytes(16));
    error_log("Token generiert: $token");
    
    // Speichere das Ergebnis und den Token in einer temporären Datei
    $captchaData = [
        'result' => $result,
        'token' => $token,
        'timestamp' => time(),
        'ip' => $_SERVER['REMOTE_ADDR']
    ];
    
    // Speichere die Daten in einer temporären Datei
    $tempDir = __DIR__ . '/temp';
    if (!file_exists($tempDir)) {
        error_log("Temp-Verzeichnis existiert nicht, erstelle es: $tempDir");
        mkdir($tempDir, 0777, true);
    } else {
        error_log("Temp-Verzeichnis existiert bereits: $tempDir");
    }
    
    $captchaFile = $tempDir . '/' . $token . '.json';
    error_log("Speichere Captcha-Daten in: $captchaFile");
    
    $result = file_put_contents($captchaFile, json_encode($captchaData));
    if ($result === false) {
        error_log("Fehler beim Speichern der Captcha-Daten");
    } else {
        error_log("Captcha-Daten erfolgreich gespeichert: $result Bytes");
    }
    
    // Gib die Captcha-Frage zurück
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'captcha_question' => "Was ist $num1 + $num2?",
        'token' => $token
    ]);
    exit;
}
?> 