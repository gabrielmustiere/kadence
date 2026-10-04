import { Controller } from '@hotwired/stimulus';

// The choice outlives the session; without one, the theme follows the system (see common/_head.html.twig).
const STORAGE_KEY = 'kadence-theme';

export default class extends Controller {
    connect() {
        this.element.setAttribute('aria-pressed', String(document.documentElement.classList.contains('dark')));
    }

    toggle() {
        const dark = document.documentElement.classList.toggle('dark');
        this.element.setAttribute('aria-pressed', String(dark));
        try {
            localStorage.setItem(STORAGE_KEY, dark ? 'dark' : 'light');
        } catch {
            // Storage refused (private mode): the theme still switches for this page.
        }
    }
}
