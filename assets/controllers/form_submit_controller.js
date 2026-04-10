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
        btn.textContent = '';

        const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        svg.setAttribute('class', 'animate-spin inline-block w-4 h-4 mr-2');
        svg.setAttribute('fill', 'none');
        svg.setAttribute('viewBox', '0 0 24 24');

        const circle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
        circle.setAttribute('class', 'opacity-25');
        circle.setAttribute('cx', '12');
        circle.setAttribute('cy', '12');
        circle.setAttribute('r', '10');
        circle.setAttribute('stroke', 'currentColor');
        circle.setAttribute('stroke-width', '4');

        const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
        path.setAttribute('class', 'opacity-75');
        path.setAttribute('fill', 'currentColor');
        path.setAttribute('d', 'M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z');

        svg.appendChild(circle);
        svg.appendChild(path);
        btn.appendChild(svg);
        btn.appendChild(document.createTextNode(' Traitement en cours…'));

        btn.classList.add('opacity-75', 'cursor-not-allowed');
    }
}
