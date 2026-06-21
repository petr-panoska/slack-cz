import { Controller } from '@hotwired/stimulus';

// Intercepts the like form submit and toggles via fetch — no page reload.
// Markup contract (see templates/line_photo/detail.html.twig):
//   <form data-controller="photo-like" data-action="submit->photo-like#toggle"
//         data-photo-like-url-value="..." action="..." method="post">
//     <input type="hidden" name="_token" value="...">
//     <button data-photo-like-target="button" aria-pressed="...">
//       <span data-photo-like-target="icon">❤/♡</span>
//       <span data-photo-like-target="count">N</span>
//     </button>
//   </form>
export default class extends Controller {
    static targets = ['button', 'icon', 'count'];
    static values  = { url: String };

    async toggle(event) {
        event.preventDefault();
        if (this.buttonTarget.disabled) return;

        const token = this.element.querySelector('input[name="_token"]')?.value ?? '';
        const body = new FormData();
        body.append('_token', token);

        this.buttonTarget.disabled = true;
        try {
            const url = this.hasUrlValue ? this.urlValue : this.element.action;
            const res = await fetch(url, {
                method: 'POST',
                body,
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            if (!res.ok) throw new Error(`HTTP ${res.status}`);

            const { liked, count } = await res.json();
            this.buttonTarget.classList.toggle('is-liked', !!liked);
            this.buttonTarget.setAttribute('aria-pressed', liked ? 'true' : 'false');
            if (this.hasIconTarget)  this.iconTarget.textContent  = liked ? '❤' : '♡';
            if (this.hasCountTarget) this.countTarget.textContent = String(count);
        } catch (err) {
            console.error('photo-like toggle failed:', err);
            // Fall back to normal form submit so the user isn't stuck.
            this.element.submit();
        } finally {
            this.buttonTarget.disabled = false;
        }
    }
}
