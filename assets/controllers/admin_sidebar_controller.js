import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['sidebar'];

    toggle() {
        const sidebar = this.sidebarTarget;
        sidebar.classList.toggle('hidden');

        if (!sidebar.classList.contains('hidden')) {
            sidebar.classList.add('fixed', 'inset-0', 'z-50', 'bg-white');
        } else {
            sidebar.classList.remove('fixed', 'inset-0', 'z-50', 'bg-white');
        }
    }
}
