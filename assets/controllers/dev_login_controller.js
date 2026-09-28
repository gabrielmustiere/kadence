import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['username', 'password'];
    static values = { password: String };

    fill(event) {
        this.usernameTarget.value = event.currentTarget.value;
        this.passwordTarget.value = this.passwordValue;
    }
}
