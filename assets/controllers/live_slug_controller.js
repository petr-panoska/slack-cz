import { Controller } from '@hotwired/stimulus';

/**
 * Live URL preview for the highline edit form on unverified lajny.
 *
 * Mirrors AsciiSlugger's behaviour for Czech (NFD strip + lowercase + non-alnum→hyphen)
 * so the displayed URL matches what the server will generate on save. Pure preview —
 * no validation, no submit blocking.
 */
export default class extends Controller {
    static values = { nameInput: String };
    static targets = ['urlPath'];

    connect() {
        this.nameInput = document.getElementById(this.nameInputValue);
        if (!this.nameInput) return;
        this.handler = () => this.update();
        this.nameInput.addEventListener('input', this.handler);
    }

    disconnect() {
        if (this.nameInput && this.handler) {
            this.nameInput.removeEventListener('input', this.handler);
        }
    }

    update() {
        if (!this.hasUrlPathTarget) return;
        const slug = slugify(this.nameInput.value) || '…';
        this.urlPathTarget.textContent = `/line/${slug}`;
    }
}

function slugify(name) {
    return name
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
}
