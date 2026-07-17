import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['menu', 'selected'];

    select(event) {
        this.selectedTarget.textContent = event.target.value;
        this.menuTarget.open = false;
    }
}
