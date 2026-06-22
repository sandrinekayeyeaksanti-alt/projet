// Fonctions pour le système d'inscription Multi-étapes
let currentStep = 1;

function showStep(step) {
    const sections = document.querySelectorAll('.form-section');
    const indicators = document.querySelectorAll('.step');

    if (sections.length === 0) return; // Si pas sur la page d'inscription

    // Masquer toutes les sections
    sections.forEach((s) => {
        s.classList.remove('active');
    });

    // Mettre à jour les indicateurs
    indicators.forEach((ind, index) => {
        if (index + 1 < step) {
            ind.classList.add('completed');
            ind.classList.remove('active');
        } else if (index + 1 === step) {
            ind.classList.add('active');
            ind.classList.remove('completed');
        } else {
            ind.classList.remove('active', 'completed');
        }
    });

    // Afficher la section cible
    const targetSection = document.getElementById(`step-${step}`);
    if (targetSection) {
        targetSection.classList.add('active');
    }
}

function nextStep(current) {
    // Validation très basique pour la démo
    const currentSection = document.getElementById(`step-${current}`);
    const inputs = currentSection.querySelectorAll('input[required], select[required]');
    
    let isValid = true;
    inputs.forEach(input => {
        if (!input.value) {
            input.style.borderColor = 'red';
            isValid = false;
        } else {
            input.style.borderColor = '#cbd5e1';
        }
    });

    if (isValid) {
        currentStep = current + 1;
        showStep(currentStep);
        window.scrollTo({ top: 150, behavior: 'smooth' });
    } else {
        alert("Veuillez remplir tous les champs obligatoires avant de continuer.");
    }
}

function prevStep(current) {
    currentStep = current - 1;
    showStep(currentStep);
    window.scrollTo({ top: 150, behavior: 'smooth' });
}

function submitForm() {
    alert("Dossier validé et paiement de 25$ initié avec succès ! Dès confirmation, vous recevrez l'accès par SMS.");
    // Redirection vers le portail
    window.location.href = "portail.html";
}

// Initialisation au chargement
document.addEventListener('DOMContentLoaded', () => {
    // Si la page comporte des icônes feather
    if(typeof feather !== 'undefined') {
        feather.replace();
    }
});
