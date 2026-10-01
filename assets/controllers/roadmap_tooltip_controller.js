import { Controller } from '@hotwired/stimulus';
import { createPopper } from '@popperjs/core';

/*
 * The tooltips of the roadmap: an element carrying data-roadmap-tooltip shows the tooltip of that id while hovered or
 * focused. Listening by delegation spares an instance per segment, and keeps a single tooltip shown at a time.
 */
export default class extends Controller {
    #trigger = null;
    #tooltip = null;
    #popper = null;

    show({ target }) {
        const trigger = target.closest?.('[data-roadmap-tooltip]');
        if (!trigger || trigger === this.#trigger) {
            return;
        }

        this.hide();
        const tooltip = document.getElementById(trigger.dataset.roadmapTooltip);
        if (!tooltip) {
            return;
        }

        tooltip.hidden = false;
        this.#trigger = trigger;
        this.#tooltip = tooltip;
        this.#popper = createPopper(trigger, tooltip, {
            placement: 'top',
            strategy: 'fixed',
            modifiers: [
                { name: 'offset', options: { offset: [0, 8] } },
                { name: 'preventOverflow', options: { boundary: this.element, padding: 8 } },
            ],
        });
    }

    leave({ target, relatedTarget }) {
        if (this.#trigger && target.closest?.('[data-roadmap-tooltip]') === this.#trigger && !this.#trigger.contains(relatedTarget)) {
            this.hide();
        }
    }

    hide() {
        this.#popper?.destroy();
        if (this.#tooltip) {
            this.#tooltip.hidden = true;
        }
        this.#trigger = null;
        this.#tooltip = null;
        this.#popper = null;
    }

    disconnect() {
        this.hide();
    }
}
