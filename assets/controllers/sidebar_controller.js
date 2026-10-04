import { Controller } from '@hotwired/stimulus';

/*
 * Flowbite's drawer hides the sidebar from assistive technologies (aria-hidden) each time it initialises, Turbo renders
 * included, while from the md breakpoint the sidebar stays on screen: give it back to them there, and hide it again
 * when the screen narrows with the drawer closed.
 */
export default class extends Controller {
    connect() {
        this.wide = matchMedia('(min-width: 48rem)');
        this.sync = this.sync.bind(this);
        this.observer = new MutationObserver(this.sync);
        this.observer.observe(this.element, { attributes: true, attributeFilter: ['aria-hidden', 'class'] });
        this.wide.addEventListener('change', this.sync);
        this.sync();
    }

    disconnect() {
        this.observer.disconnect();
        this.wide.removeEventListener('change', this.sync);
    }

    sync() {
        const hidden = !this.wide.matches && this.element.classList.contains('-translate-x-full');
        if (hidden && this.element.getAttribute('aria-hidden') !== 'true') {
            this.element.setAttribute('aria-hidden', 'true');
        } else if (!hidden && this.element.hasAttribute('aria-hidden')) {
            this.element.removeAttribute('aria-hidden');
        }
    }
}
