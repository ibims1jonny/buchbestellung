// Captcha-Token speichern
let captchaToken = '';

// Captcha laden, wenn das Formular angezeigt wird
function loadCaptcha() {
    // console.log("Lade Captcha..."); // Debug-Ausgabe deaktiviert
    fetch('get-captcha.php')
        .then(response => {
            // console.log("Captcha-Antwort erhalten:", response); // Debug-Ausgabe deaktiviert
            return response.json();
        })
        .then(data => {
            // console.log("Captcha-Daten:", data); // Debug-Ausgabe deaktiviert
            if (data.success) {
                document.getElementById('captcha-question').textContent = data.captcha_question;
                // Token speichern
                captchaToken = data.token;
                // Token in ein verstecktes Feld einfügen
                if (!document.getElementById('captcha_token')) {
                    const tokenInput = document.createElement('input');
                    tokenInput.type = 'hidden';
                    tokenInput.id = 'captcha_token';
                    tokenInput.name = 'captcha_token';
                    document.getElementById('captcha-container').appendChild(tokenInput);
                }
                document.getElementById('captcha_token').value = captchaToken;
            }
        })
        .catch(error => {
            // console.error('Fehler beim Laden des Captchas:', error); // Debug-Ausgabe deaktiviert
        });
}

// Captcha beim Laden der Seite initialisieren
document.addEventListener('DOMContentLoaded', loadCaptcha);

// WICHTIG: Wir entfernen den Event-Listener für das Formular, 
// da dieser bereits in index.html definiert ist 