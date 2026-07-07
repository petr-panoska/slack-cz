import { Controller } from '@hotwired/stimulus';
import { typeColor, escapeHtml } from './map_controller.js';

const COLLAPSED_KEY = 'slack.cz:map:lines-collapsed';
const HEIGHT_KEY = 'slack.cz:map:lines-height';

// Fixed default body heights (≈ 5 rows). The height must NEVER derive from the
// list content: the box height resizes the map, that changes the lines in the
// viewport, that would change the content height again — an endless feedback
// loop. The user adjusts the height by dragging the bottom-edge handle.
const DEFAULT_HEIGHT_DESKTOP = 280;
const DEFAULT_HEIGHT_MOBILE = 190;
const MIN_HEIGHT = 60;
const KEY_STEP = 40;

// Sidebar box with the lines currently inside the map viewport (sreality-style).
// Pure view: map_controller broadcasts `slack:viewport-lines` on every pan/zoom,
// we render; a click goes back as `slack:line-focus` so the map centers the line.
// The whole header is the collapse/expand toggle; the count lives in the title.
export default class extends Controller {
    static targets = ['list', 'empty', 'count', 'header', 'body'];

    connect() {
        // On mobile the box starts collapsed (header bar only); the session
        // remembers the collapsed state and the dragged height.
        let collapsed = null;
        let height = NaN;
        try {
            collapsed = sessionStorage.getItem(COLLAPSED_KEY);
            height = parseInt(sessionStorage.getItem(HEIGHT_KEY), 10);
        } catch {
            /* sessionStorage may be unavailable; non-fatal */
        }
        const mobile = window.matchMedia('(max-width: 768px)').matches;
        this._height = Number.isFinite(height) && height > 0
            ? height
            : (mobile ? DEFAULT_HEIGHT_MOBILE : DEFAULT_HEIGHT_DESKTOP);
        this.setCollapsed(collapsed === '1' || (collapsed === null && mobile), { remember: false });

        this._onLines = (e) => this.render(e.detail?.lines ?? []);
        document.addEventListener('slack:viewport-lines', this._onLines);
        // The "more below" gradient goes out once the list is scrolled to its
        // end — and needs a re-check after the expand transition lands.
        this._onScroll = () => this._updateOverflow();
        if (this.hasBodyTarget) {
            this.bodyTarget.addEventListener('scroll', this._onScroll, { passive: true });
            this.bodyTarget.addEventListener('transitionend', this._onScroll);
        }
        // The map may have finished loading before we connected — ask for a replay.
        document.dispatchEvent(new CustomEvent('slack:lines-request'));
    }

    disconnect() {
        document.removeEventListener('slack:viewport-lines', this._onLines);
        if (this.hasBodyTarget) {
            this.bodyTarget.removeEventListener('scroll', this._onScroll);
            this.bodyTarget.removeEventListener('transitionend', this._onScroll);
        }
    }

    toggle(event) {
        event?.preventDefault();
        this.setCollapsed(!this.element.classList.contains('is-collapsed'));
    }

    setCollapsed(collapsed, { remember = true } = {}) {
        this.element.classList.toggle('is-collapsed', collapsed);
        if (this.hasHeaderTarget) this.headerTarget.setAttribute('aria-expanded', String(!collapsed));
        if (this.hasBodyTarget) {
            // Collapse animates the body height to 0 — the body stays rendered,
            // display:none would kill the transition. Expand goes back to the
            // fixed user height.
            this.bodyTarget.style.height = collapsed ? '0px' : `${this._clampHeight(this._height)}px`;
        }
        this._updateOverflow();
        if (!remember) return;
        try {
            sessionStorage.setItem(COLLAPSED_KEY, collapsed ? '1' : '0');
        } catch {
            /* non-fatal */
        }
    }

    // Bottom-edge handle drag = resize the list. Pointer events cover mouse and
    // touch (the handle has touch-action: none).
    resizeStart(event) {
        if (this.element.classList.contains('is-collapsed') || !this.hasBodyTarget) return;
        event.preventDefault();
        const startY = event.clientY;
        const startHeight = this.bodyTarget.getBoundingClientRect().height;
        this.element.classList.add('is-resizing');
        const move = (e) => this._setHeight(startHeight + e.clientY - startY);
        const stop = () => {
            window.removeEventListener('pointermove', move);
            window.removeEventListener('pointerup', stop);
            window.removeEventListener('pointercancel', stop);
            this.element.classList.remove('is-resizing');
            this._rememberHeight();
        };
        window.addEventListener('pointermove', move);
        window.addEventListener('pointerup', stop);
        window.addEventListener('pointercancel', stop);
    }

    // Keyboard fallback for the handle (↑ shrinks, ↓ grows — same directions as drag).
    resizeKey(event) {
        const step = event.key === 'ArrowUp' ? -KEY_STEP : event.key === 'ArrowDown' ? KEY_STEP : null;
        if (step === null || this.element.classList.contains('is-collapsed') || !this.hasBodyTarget) return;
        event.preventDefault();
        this._setHeight(this.bodyTarget.getBoundingClientRect().height + step);
        this._rememberHeight();
    }

    _setHeight(height) {
        this._height = this._clampHeight(height);
        this.bodyTarget.style.height = `${this._height}px`;
        this._updateOverflow();
    }

    _rememberHeight() {
        try {
            sessionStorage.setItem(HEIGHT_KEY, String(this._height));
        } catch {
            /* non-fatal */
        }
    }

    // The map must stay usable — the box's bottom edge never crosses the middle
    // of the map wrapper (the box sits offset below the site header, so a plain
    // "height ≤ half" would visually end way past the middle).
    _clampHeight(height) {
        let max = 400;
        const wrapper = this.element.closest('.line-map-wrapper');
        if (wrapper && this.hasBodyTarget) {
            const w = wrapper.getBoundingClientRect();
            max = Math.round(w.top + w.height / 2 - this.bodyTarget.getBoundingClientRect().top);
        }
        return Math.min(Math.max(Math.round(height), MIN_HEIGHT), Math.max(max, MIN_HEIGHT));
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

    render(lines) {
        if (this.hasCountTarget) this.countTarget.textContent = `– ${lines.length}`;
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
        this._updateOverflow();
    }

    // "More below" gradient: on while part of the list is cut off, off once the
    // user scrolls to the end (or the box is collapsed).
    _updateOverflow() {
        if (!this.hasBodyTarget) return;
        const body = this.bodyTarget;
        const more = !this.element.classList.contains('is-collapsed')
            && body.scrollHeight - body.scrollTop - body.clientHeight > 6;
        this.element.classList.toggle('has-overflow', more);
    }
}
