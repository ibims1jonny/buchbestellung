<?php
// Debug-Modus aktivieren
error_log("GET-CAPTCHA: Starte Captcha-Generierung");

// Generiere zwei zufällige Zahlen zwischen 1 und 10
$num1 = rand(1, 10);
$num2 = rand(1, 10);
$result = $num1 + $num2;

// Erstelle einen Token für das Captcha
$token = bin2hex(random_bytes(16));

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
    error_log("GET-CAPTCHA: Temp-Verzeichnis existiert nicht, erstelle es: $tempDir");
    mkdir($tempDir, 0777, true);
}

// Stelle sicher, dass das Verzeichnis beschreibbar ist
if (!is_writable($tempDir)) {
    error_log("GET-CAPTCHA: Temp-Verzeichnis ist nicht beschreibbar: $tempDir");
    chmod($tempDir, 0777);
}

$captchaFile = $tempDir . '/' . $token . '.json';
error_log("GET-CAPTCHA: Speichere Captcha-Daten in: $captchaFile");

$writeResult = file_put_contents($captchaFile, json_encode($captchaData));
if ($writeResult === false) {
    error_log("GET-CAPTCHA: Fehler beim Speichern der Captcha-Daten");
} else {
    error_log("GET-CAPTCHA: Captcha-Daten erfolgreich gespeichert: $writeResult Bytes");
    error_log("GET-CAPTCHA: Datei existiert nach Speichern: " . (file_exists($captchaFile) ? 'Ja' : 'Nein'));
}

// Debug-Informationen
error_log("GET-CAPTCHA: Captcha generiert - Token: " . $token);
error_log("GET-CAPTCHA: Captcha-Ergebnis: " . $result);
error_log("GET-CAPTCHA: Captcha-Frage: Was ist $num1 + $num2?");

// Gib die Captcha-Frage zurück
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'captcha_question' => "Was ist $num1 + $num2?",
    'token' => $token
]);
?> 