import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['button'];
    static values = { label: { type: String, default: 'Confirmer la réservation' } };

    connect() {
        this.reset();
        window.addEventListener('pageshow', this.boundReset = () => this.reset());
    }

    disconnect() {
        window.removeEventListener('pageshow', this.boundReset);
    }

    reset() {
        const btn = this.buttonTarget;
        btn.disabled = false;
        btn.textContent = this.labelValue;
        btn.classList.remove('opacity-75', 'cursor-not-allowed');
    }

    submit() {
        const btn = this.buttonTarget;
        btn.disabled = true;
        btn.innerHTML = `
            <svg class="animate-spin inline-block w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
            Traitement en cours…
        `;
        btn.classList.add('opacity-75', 'cursor-not-allowed');
    }
}
