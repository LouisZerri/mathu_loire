import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    connect() {
        const observer = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('revealed');
                        observer.unobserve(entry.target);
                    }
                });
            },
            { threshold: 0.1 }
        );

        this.element.querySelectorAll('.reveal').forEach((el) => observer.observe(el));
        this.observer = observer;
    }

    disconnect() {
        this.observer?.disconnect();
    }
}
