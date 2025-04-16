// Captcha laden, wenn das Formular angezeigt wird
function loadCaptcha() {
    fetch('get-captcha.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('captcha-question').textContent = data.captcha_question;
            }
        })
        .catch(error => console.error('Fehler beim Laden des Captchas:', error));
}

// Captcha beim Laden der Seite initialisieren
document.addEventListener('DOMContentLoaded', loadCaptcha);

// WICHTIG: Wir entfernen den Event-Listener für das Formular, 
// da dieser bereits in index.html definiert ist 