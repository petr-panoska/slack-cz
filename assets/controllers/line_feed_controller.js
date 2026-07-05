import { Controller } from '@hotwired/stimulus';
import { typeColor, escapeHtml } from './map_controller.js';

const COLLAPSED_KEY = 'slack.cz:map:lines-collapsed';

// How long the map must sit still before the box height animates to fit the
// list content (mobile). Height changes resize the map, so they must not chase
// every viewport update — see render()/_fitHeight().
const FIT_DELAY = 250;

// Sidebar box with the lines currently inside the map viewport (sreality-style).
// Pure view: map_controller broadcasts `slack:viewport-lines` on every pan/zoom,
// we render; a click goes back as `slack:line-focus` so the map centers the line.
// The whole header is the collapse/expand toggle; the count lives in the title.
export default class extends Controller {
    static targets = ['list', 'empty', 'count', 'header', 'body'];

    connect() {
        // On mobile the box starts collapsed (header bar only); the session
        // remembers when the user expands it. Desktop starts expanded.
        let stored = null;
        try {
            stored = sessionStorage.getItem(COLLAPSED_KEY);
        } catch {
            /* sessionStorage may be unavailable; non-fatal */
        }
        const mobile = window.matchMedia('(max-width: 768px)').matches;
        this.setCollapsed(stored === '1' || (stored === null && mobile), { remember: false });

        this._onLines = (e) => this.render(e.detail?.lines ?? [], e.detail?.reason ?? 'interaction');
        document.addEventListener('slack:viewport-lines', this._onLines);
        // The map may have finished loading before we connected — ask for a replay.
        document.dispatchEvent(new CustomEvent('slack:lines-request'));

    }

    disconnect() {
        document.removeEventListener('slack:viewport-lines', this._onLines);
        clearTimeout(this._fitTimer);
    }

    toggle(event) {
        event?.preventDefault();
        this.setCollapsed(!this.element.classList.contains('is-collapsed'));
    }

    setCollapsed(collapsed, { remember = true } = {}) {
        this.element.classList.toggle('is-collapsed', collapsed);
        if (this.hasHeaderTarget) this.headerTarget.setAttribute('aria-expanded', String(!collapsed));
        if (!collapsed) {
            // The body just became visible — size it to the content right away
            // (next frame, once it has layout; no animation on first show).
            requestAnimationFrame(() => this._fitHeight({ animate: false }));
        }
        if (!remember) return;
        try {
            sessionStorage.setItem(COLLAPSED_KEY, collapsed ? '1' : '0');
        } catch {
            /* non-fatal */
        }
    }

    // Animate the body to its content height (mobile only, capped so the whole
    // box stays within a third of the screen). Never called for updates caused
    // by our own resize of the map ('resize' reason) — the box height changing
    // the viewport changing the list changing the box height would flicker
    // forever. Desktop keeps the CSS-driven auto height.
    _fitHeight({ animate = true } = {}) {
        if (!this.hasBodyTarget) return;
        const body = this.bodyTarget;
        if (!window.matchMedia('(max-width: 768px)').matches) {
            body.style.height = '';
            return;
        }
        if (this.element.classList.contains('is-collapsed')) return;

        const previous = body.style.height;
        body.style.height = 'auto';
        const natural = body.offsetHeight;
        // Cap: box (header + body) ≤ ⅓ of the screen minus the .map-side padding.
        const headerH = this.hasHeaderTarget ? this.headerTarget.offsetHeight : 0;
        const cap = Math.max(Math.round(window.innerHeight * 0.33) - 20 - headerH - 2, 0);
        const target = Math.min(natural, cap);

        if (!animate || previous === '') {
            body.style.height = `${target}px`;
            return;
        }
        body.style.height = previous;
        body.offsetHeight; // reflow so the height transition has a start value
        body.style.height = `${target}px`;
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
            this.setCollapsed(true);
        }
    }

    render(lines, reason = 'interaction') {
        if (this.hasCountTarget) this.countTarget.textContent = `– ${lines.length}`;
        if (this.hasEmptyTarget) this.emptyTarget.hidden = lines.length > 0;

        // Re-fit the height only for user-driven updates, once the map rests.
        if (reason !== 'resize') {
            clearTimeout(this._fitTimer);
            this._fitTimer = setTimeout(() => this._fitHeight(), FIT_DELAY);
        }

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
