import { Controller } from '@hotwired/stimulus';

// Hints only: RegistrationForm still validates unique email and nick when the
// form is submitted, because another registration may win the race.
export default class extends Controller {
    static targets = ['field'];
    static values = { url: String };

    connect() {
        this.states = new Map();
        this.timers = new Map();
        this.requests = new Map();
        this.checkedValues = new Map();
        this.statuses = new Map();
        this.icons = new Map();
        this.submissionCheckInFlight = false;

        this.fieldTargets.forEach((field) => {
            this.states.set(field, 'unknown');
            this.createStatus(field);
        });
    }

    disconnect() {
        this.timers.forEach((timer) => clearTimeout(timer));
        this.requests.forEach((request) => request.abort());
        this.statuses.forEach((status) => status.remove());
        this.icons.forEach((icon) => icon.remove());
    }

    schedule(event) {
        const field = event.currentTarget;
        this.cancelScheduledCheck(field);
        this.requests.get(field)?.abort();
        this.checkedValues.delete(field);
        this.setState(field, 'unknown');

        this.timers.set(field, setTimeout(() => this.validateAndCheck(field), 900));
    }

    check(event) {
        const field = event.currentTarget;
        this.cancelScheduledCheck(field);

        this.validateAndCheck(field);
    }

    async submit(event) {
        event.preventDefault();
        if (this.submissionCheckInFlight) return;

        this.submissionCheckInFlight = true;
        this.fieldTargets.forEach((field) => this.cancelScheduledCheck(field));
        try {
            await Promise.all(this.fieldTargets.map((field) => this.validateAndCheck(field)));

            if (this.fieldTargets.every((field) => this.states.get(field) === 'available')) {
                HTMLFormElement.prototype.submit.call(this.element);
            } else {
                this.focusFirstProblem();
            }
        } finally {
            this.submissionCheckInFlight = false;
        }
    }

    async checkField(field) {
        this.requests.get(field)?.abort();

        const request = new AbortController();
        const timeout = setTimeout(() => request.abort(), 5000);
        this.requests.set(field, request);
        const value = field.value.trim();
        this.checkedValues.set(field, value);
        this.setState(field, 'checking');

        try {
            const response = await fetch(this.urlValue, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ field: this.fieldName(field), value }),
                signal: request.signal,
            });

            if (this.requests.get(field) !== request) return;

            const data = await response.json();
            if (!response.ok) {
                this.setState(field, 'unknown', data.message ?? 'Dostupnost se nepodařilo ověřit.');
                return;
            }

            if (!data.valid) {
                this.setState(field, 'unknown', 'Zadej platný e-mail.');
                return;
            }

            if (field.value.trim() !== value) {
                this.setState(field, 'unknown');
                return;
            }

            const label = this.fieldName(field) === 'email' ? 'E-mail' : 'Nick';
            this.setState(
                field,
                data.available ? 'available' : 'unavailable',
                data.available ? `${label} je možné použít.` : `${label} už je obsazený.`,
            );
        } catch (error) {
            if (this.requests.get(field) === request) {
                this.setState(field, 'unknown', 'Dostupnost se nepodařilo ověřit.');
            }
        } finally {
            clearTimeout(timeout);
            if (this.requests.get(field) === request) this.requests.delete(field);
        }
    }

    createStatus(field) {
        field.parentElement.classList.add('registration-availability__field');

        const icon = document.createElement('span');
        icon.className = 'registration-availability__icon';
        icon.setAttribute('aria-hidden', 'true');
        field.insertAdjacentElement('afterend', icon);
        this.icons.set(field, icon);

        const status = document.createElement('div');
        status.className = 'registration-availability';
        status.id = `${field.id}-availability`;
        status.setAttribute('aria-live', 'polite');
        field.insertAdjacentElement('afterend', status);
        field.dataset.registrationAvailabilityStatus = status.id;
        this.statuses.set(field, status);

        requestAnimationFrame(() => this.positionIcon(field));
    }

    setState(field, state, message = '') {
        this.states.set(field, state);
        const invalid = state === 'invalid' || state === 'unavailable';
        field.classList.toggle('is-invalid', invalid);
        field.setAttribute('aria-invalid', invalid ? 'true' : 'false');
        const status = this.statuses.get(field);
        if (status) {
            status.textContent = message;
            status.dataset.state = state;
        }
    }

    cancelScheduledCheck(field) {
        const timer = this.timers.get(field);
        if (timer) clearTimeout(timer);
        this.timers.delete(field);
    }

    fieldName(field) {
        return field.dataset.registrationAvailabilityFieldParam;
    }

    focusFirstProblem() {
        const field = this.fieldTargets.find((candidate) => this.states.get(candidate) !== 'available');
        field?.focus();
    }

    positionIcon(field) {
        const icon = this.icons.get(field);
        if (!icon) return;

        icon.style.top = `${field.offsetTop + field.offsetHeight / 2}px`;
    }

    validateAndCheck(field) {
        const value = field.value.trim();
        if (!value) {
            this.setState(field, 'unknown');
            return Promise.resolve();
        }

        if (!field.checkValidity()) {
            const message = this.fieldName(field) === 'email' ? 'Zadej platný e-mail.' : 'Zadej platný nick.';
            this.setState(field, 'invalid', message);
            return Promise.resolve();
        }

        if (this.checkedValues.get(field) === value && ['checking', 'available', 'unavailable'].includes(this.states.get(field))) {
            return Promise.resolve();
        }

        return this.checkField(field);
    }
}
