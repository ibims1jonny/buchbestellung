document.addEventListener('DOMContentLoaded', function() {
  // Smooth scroll für Anker-Links
  document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
      e.preventDefault();
      
      const targetId = this.getAttribute('href');
      const targetElement = document.querySelector(targetId);
      
      if (targetElement) {
        window.scrollTo({
          top: targetElement.offsetTop - 100,
          behavior: 'smooth'
        });
      }
    });
  });
  
  // Formularvalidierung
  const form = document.getElementById('bookOrderForm');
  
  form.addEventListener('submit', function(e) {
    e.preventDefault();
    
    // Validierung
    const name = form.querySelector('#name').value.trim();
    const email = form.querySelector('#email').value.trim();
    const delivery = form.querySelector('input[name="delivery"]:checked');
    
    if (!name || !email || !delivery) {
      alert('Bitte füllen Sie alle Pflichtfelder aus.');
      return;
    }
    
    // Adresse prüfen wenn Versand gewählt
    if (delivery.value === 'shipping') {
      const address = form.querySelector('#address').value.trim();
      if (!address) {
        alert('Bitte geben Sie eine Lieferadresse an.');
        return;
      }
    }
    
    // Formulardaten sammeln
    const formData = new FormData(form);
    const orderData = {
      name: formData.get('name'),
      email: formData.get('email'),
      address: formData.get('address'),
      delivery: formData.get('delivery'),
      message: formData.get('message')
    };
    
    // Hier würde normalerweise der API-Call kommen
    
    // Erfolgsmeldung
    alert('Vielen Dank für Ihre Bestellung! Sie erhalten in Kürze eine E-Mail mit den weiteren Details.');
    form.reset();
    
    // Scroll nach oben
    window.scrollTo({
      top: 0,
      behavior: 'smooth'
    });
  });
  
  // Animation für das Buchcover
  const bookCover = document.querySelector('.book-cover img');
  if (bookCover) {
    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.style.opacity = 1;
          entry.target.style.transform = 'translateY(0) scale(1)';
        }
      });
    }, { threshold: 0.1 });
    
    bookCover.style.opacity = 0;
    bookCover.style.transform = 'translateY(20px) scale(0.95)';
    bookCover.style.transition = 'opacity 0.8s ease, transform 0.8s ease';
    
    observer.observe(bookCover);
  }
}); 