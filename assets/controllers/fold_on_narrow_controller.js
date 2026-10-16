import { Controller } from '@hotwired/stimulus';

/*
 * A details rendered open, so that it reads in full without JavaScript and from the md breakpoint: below it, fold it
 * on arrival, where its content would stretch a long page.
 */
export default class extends Controller {
    connect() {
        if (!matchMedia('(min-width: 48rem)').matches) {
            this.element.open = false;
        }
    }
}
