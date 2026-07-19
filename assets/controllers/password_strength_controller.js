import { Controller } from '@hotwired/stimulus';

// Client-side guidance for new passwords. The server-side constraints in
// NewPasswordType remain the source of truth for accepting a password.
export default class extends Controller {
    connect() {
        this.indicator = document.createElement('div');
        this.indicator.className = 'password-strength';
        this.indicator.id = `${this.element.id}-strength`;
        this.indicator.setAttribute('role', 'status');
        this.indicator.setAttribute('aria-live', 'polite');

        this.element.insertAdjacentElement('afterend', this.indicator);
        this.element.setAttribute('aria-describedby', this.indicator.id);
        this.update();
    }

    disconnect() {
        this.indicator?.remove();
    }

    update() {
        const { score, label } = this.strength(this.element.value);

        this.indicator.dataset.strength = score;
        this.indicator.textContent = `Síla hesla: ${label}`;
    }

    strength(password) {
        if (!password) {
            return { score: 1, label: '😿' };
        }

        if (password.length < 6) {
            return { score: 1, label: '😿' };
        }

        let score = password.length >= 10 ? 2 : 1;
        const characterGroups = [/[a-z]/, /[A-Z]/, /\d/, /[^A-Za-z\d]/]
            .filter((pattern) => pattern.test(password)).length;

        if (characterGroups >= 2) score += 1;
        if (password.length >= 14 && characterGroups >= 3) score += 1;

        score = Math.min(score, 3);
        const labels = ['😿', '😺', '😻'];

        return { score, label: labels[score - 1] };
    }
}
