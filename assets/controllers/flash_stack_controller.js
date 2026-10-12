import { Controller } from '@hotwired/stimulus';

/*
 * Shows the flash messages a live component dispatches as app:flash browser events: its action renders in place, with
 * no page for the flashes of the session to show on.
 */
export default class extends Controller {
    static targets = ['list', 'template'];

    push({ detail: { type, message } }) {
        const template = this.templateTargets.find((candidate) => candidate.dataset.type === type) ?? this.templateTargets[0];
        const flash = template.content.firstElementChild.cloneNode(true);
        flash.querySelector('[data-flash-message]').textContent = message;
        flash.setAttribute('role', 'alert');
        this.listTarget.append(flash);
    }
}
