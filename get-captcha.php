<?php
// Starte die Session
session_start();

// Generiere zwei zufällige Zahlen zwischen 1 und 10
$num1 = rand(1, 10);
$num2 = rand(1, 10);
$result = $num1 + $num2;

// Speichere das Ergebnis in der Session
$_SESSION['captcha_result'] = $result;

// Gib die Captcha-Frage zurück
echo json_encode([
    'success' => true,
    'captcha_question' => "Was ist $num1 + $num2?",
    'num1' => $num1,
    'num2' => $num2
]);
?> 