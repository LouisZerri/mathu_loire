import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = { fadeTarget: String };

    submit() {
        const target = this.fadeTargetValue
            ? document.querySelector(this.fadeTargetValue)
            : null;

        if (target) {
            target.classList.add('animate-fade-out');
            setTimeout(() => this.element.closest('form').submit(), 300);
        } else {
            this.element.closest('form').submit();
        }
    }
}
