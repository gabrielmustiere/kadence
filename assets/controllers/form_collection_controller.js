import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['collection'];
    static values = { index: Number, prototype: String };

    add() {
        const template = document.createElement('template');
        template.innerHTML = this.prototypeValue.replace(/__name__/g, String(this.indexValue)).trim();
        const row = template.content.firstElementChild;
        this.collectionTarget.appendChild(row);
        this.indexValue++;
        row.querySelector('select, input')?.focus();
    }

    remove(event) {
        event.currentTarget.closest('[data-form-collection-item]').remove();
    }
}
