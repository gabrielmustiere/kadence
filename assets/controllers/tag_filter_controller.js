import { Controller } from '@hotwired/stimulus';

/*
 * Restricts the people offered in each team row to those carrying every chosen tag. The options are rebuilt rather
 * than hidden: Safari shows hidden <option> elements anyway. A row keeps the person already chosen.
 * The filter is cleared before Turbo caches the page: restored from the cache, the rows would otherwise be taken for
 * complete with only the filtered people.
 */
export default class extends Controller {
    static targets = ['filter', 'person'];

    initialize() {
        this.allOptions = new WeakMap();
    }

    personTargetConnected(select) {
        this.allOptions.set(select, Array.from(select.options));
        this.filter(select);
    }

    apply() {
        this.personTargets.forEach((select) => this.filter(select));
    }

    reset() {
        this.filterTargets.forEach((filter) => {
            filter.value = '';
        });
        this.apply();
    }

    filter(select) {
        const required = this.filterTargets.map((filter) => filter.value).filter((value) => value !== '');
        const current = select.value;
        const kept = this.allOptions.get(select).filter((option) => {
            if (option.value === '' || option.value === current) {
                return true;
            }
            const tags = (option.dataset.tags ?? '').split(' ');

            return required.every((tag) => tags.includes(tag));
        });

        select.replaceChildren(...kept);
        select.value = current;
    }
}
