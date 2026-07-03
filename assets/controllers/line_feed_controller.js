import { Controller } from '@hotwired/stimulus';
import { typeColor, escapeHtml } from './map_controller.js';

const COLLAPSED_KEY = 'slack.cz:map:lines-collapsed';

// Czech plural for the header caption: 1 lajna / 2–4 lajny / 5+ lajn.
function plural(n) {
    if (n === 1) return 'lajna';
    if (n >= 2 && n <= 4) return 'lajny';
    return 'lajn';
}

// Sidebar box with the lines currently inside the map viewport (sreality-style).
// Pure view: map_controller broadcasts `slack:viewport-lines` on every pan/zoom,
// we render; a click goes back as `slack:line-focus` so the map centers the line.
export default class extends Controller {
    static targets = ['list', 'empty', 'caption'];

    connect() {
        try {
            if (sessionStorage.getItem(COLLAPSED_KEY) === '1') {
                this.element.classList.add('is-collapsed');
            }
        } catch {
            /* sessionStorage may be unavailable; non-fatal */
        }

        this._onLines = (e) => this.render(e.detail?.lines ?? []);
        document.addEventListener('slack:viewport-lines', this._onLines);
        // The map may have finished loading before we connected — ask for a replay.
        document.dispatchEvent(new CustomEvent('slack:lines-request'));
    }

    disconnect() {
        document.removeEventListener('slack:viewport-lines', this._onLines);
    }

    toggle(event) {
        event?.preventDefault();
        const collapsed = this.element.classList.toggle('is-collapsed');
        try {
            sessionStorage.setItem(COLLAPSED_KEY, collapsed ? '1' : '0');
        } catch {
            /* non-fatal */
        }
    }

    // Row click = center the map on the line. The name inside is a normal link to
    // the line detail — let that one navigate.
    focus(event) {
        if (event.target.closest('a')) return;
        const id = parseInt(event.currentTarget.dataset.id, 10);
        if (!Number.isFinite(id)) return;
        document.dispatchEvent(new CustomEvent('slack:line-focus', { detail: { id } }));
        // On mobile the box overlays the map — collapse it so the popup is visible.
        if (window.matchMedia('(max-width: 768px)').matches) {
            this.element.classList.add('is-collapsed');
            try {
                sessionStorage.setItem(COLLAPSED_KEY, '1');
            } catch {
                /* non-fatal */
            }
        }
    }

    render(lines) {
        if (this.hasCaptionTarget) {
            this.captionTarget.textContent = `${lines.length} ${plural(lines.length)} ve výřezu`;
        }
        if (this.hasEmptyTarget) this.emptyTarget.hidden = lines.length > 0;

        this.listTarget.innerHTML = lines.map((r) => {
            const place = [r.area, r.region].filter(Boolean).join(', ');
            const meta = [`${r.length} m`, `${r.height} m vysoko`, place].filter(Boolean).join(' · ');
            return `
                <li class="line-feed-item" data-id="${r.id}" data-action="click->line-feed#focus">
                    <span class="line-feed-dot" style="background:${typeColor(r.type)}" aria-hidden="true"></span>
                    <span class="line-feed-main">
                        <a class="line-feed-name" href="/lajna/${encodeURIComponent(r.slug)}">${escapeHtml(r.name)}</a>
                        <span class="line-feed-meta">${escapeHtml(meta)}</span>
                    </span>
                </li>
            `;
        }).join('');
    }
}
