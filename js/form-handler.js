$(document).ready(function() {
    console.log('Dokument bereit, Formular gefunden:', $('#bookOrderForm').length > 0);
    
    // Formular-Validierung vor dem Absenden
    $('#bookOrderForm').on('submit', function(e) {
        e.preventDefault();
        
        // Manuelle Validierung durchführen
        var email = $('#email').val().trim();
        if (!email) {
            alert('Bitte geben Sie eine E-Mail-Adresse ein.');
            $('#email').focus();
            return false;
        }
        
        // Formulardaten sammeln
        var formData = $(this).serialize();
        
        // AJAX-Anfrage an send-mail.php senden
        $.ajax({
            type: 'POST',
            url: 'send-mail.php',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Erfolgsmeldung anzeigen
                    alert(response.message);
                    // Formular zurücksetzen
                    $('#bookOrderForm')[0].reset();
                } else {
                    // Fehlermeldung anzeigen
                    alert(response.message || 'Ein Fehler ist aufgetreten. Bitte versuchen Sie es später erneut.');
                }
            },
            error: function(xhr) {
                console.log('Fehler:', xhr.responseText);
                alert('Ein Fehler ist bei der Übermittlung aufgetreten. Bitte versuchen Sie es später erneut.');
            }
        });
    });
    
    // Anzeigen/Ausblenden des Adressfelds basierend auf der Lieferoption
    $('input[name="delivery"]').on('change', function() {
        if ($(this).val() === 'shipping') {
            $('#address').parent('.form-group').show();
        } else {
            $('#address').parent('.form-group').hide();
        }
    });
    
    // Initial ausführen
    $('input[name="delivery"]:checked').trigger('change');
}); 