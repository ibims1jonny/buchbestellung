<?php
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
    mkdir($tempDir, 0777, true);
}

file_put_contents($tempDir . '/' . $token . '.json', json_encode($captchaData));

// Debug-Informationen
error_log("Captcha generiert - Token: " . $token);
error_log("Captcha-Ergebnis: " . $result);

// Gib die Captcha-Frage zurück
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'captcha_question' => "Was ist $num1 + $num2?",
    'token' => $token
]);
?> 