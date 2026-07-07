import { Controller } from '@hotwired/stimulus';
import { typeColor, escapeHtml } from './map_controller.js';

// Lines-in-viewport list (a pane of .map-panel). Pure view: map_controller
// broadcasts `slack:viewport-lines` on every pan/zoom, we render; a row click
// goes back as `slack:line-focus` so the map centers the line. Collapse and
// height live in map_panel_controller — we only dispatch `collapse` (mobile
// row click) and `rendered` (gradient refresh), wired on the shared root.
export default class extends Controller {
    static targets = ['list', 'empty', 'count'];

    connect() {
        this._onLines = (e) => this.render(e.detail?.lines ?? []);
        document.addEventListener('slack:viewport-lines', this._onLines);
        // The map may have finished loading before we connected — ask for a replay.
        document.dispatchEvent(new CustomEvent('slack:lines-request'));
    }

    disconnect() {
        document.removeEventListener('slack:viewport-lines', this._onLines);
    }

    // Row click = center the map on the line. The name inside is a normal link to
    // the line detail — let that one navigate.
    focus(event) {
        if (event.target.closest('a')) return;
        const id = parseInt(event.currentTarget.dataset.id, 10);
        if (!Number.isFinite(id)) return;
        document.dispatchEvent(new CustomEvent('slack:line-focus', { detail: { id } }));
        // On mobile the panel overlays a big chunk of the map — get it out of
        // the opened popup's way.
        if (window.matchMedia('(max-width: 768px)').matches) {
            this.dispatch('collapse');
        }
    }

    render(lines) {
        if (this.hasCountTarget) this.countTarget.textContent = `– ${lines.length}`;
        if (this.hasEmptyTarget) this.emptyTarget.hidden = lines.length > 0;

        this.listTarget.innerHTML = lines.map((r) => {
            const place = [r.area, r.region].filter(Boolean).join(', ');
            const meta = [`${r.length} m`, `${r.height} m vysoko`, place].filter(Boolean).join(' · ');
            return `
                <li class="line-feed__item" data-id="${r.id}" data-action="click->line-feed#focus">
                    <span class="line-feed__dot" style="background:${typeColor(r.type)}" aria-hidden="true"></span>
                    <span class="line-feed__main">
                        <a class="line-feed__name" href="/lajna/${encodeURIComponent(r.slug)}">${escapeHtml(r.name)}</a>
                        <span class="line-feed__meta">${escapeHtml(meta)}</span>
                    </span>
                </li>
            `;
        }).join('');
        this.dispatch('rendered');
    }
}
