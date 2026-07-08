import { Controller } from '@hotwired/stimulus';
import { isMobile } from '../breakpoints.js';

const COLLAPSED_KEY = 'slack.cz:map:panel-collapsed';
const HEIGHT_KEY = 'slack.cz:map:panel-height';
const TAB_KEY = 'slack.cz:map:panel-tab';

// Fixed default body heights (≈ 5 line rows). The height must NEVER derive
// from the content: the panel height resizes the map, that changes what's in
// the viewport, that would change the content height again — an endless
// feedback loop. The user adjusts the height by dragging the bottom-edge handle.
const DEFAULT_HEIGHT_DESKTOP = 280;
const DEFAULT_HEIGHT_MOBILE = 190;
const MIN_HEIGHT = 60;
const KEY_STEP = 40;

// Tabbed panel over the map (Lajny / Přechody). Collapsed = a single bar with
// the active tab; expanded = both tab headers + the active pane. Pane content
// is rendered by the line-feed / crossing-feed controllers living on the same
// element — they dispatch `rendered` (gradient refresh) and `collapse`
// (mobile row click), wired via data-action on the root.
export default class extends Controller {
    static targets = ['body', 'linesTab', 'crossingsTab', 'linesPane', 'crossingsPane', 'toggle'];

    connect() {
        // On mobile the panel starts collapsed (bar only); the session
        // remembers the collapsed state, the dragged height and the active tab.
        let collapsed = null;
        let height = NaN;
        let tab = null;
        try {
            collapsed = sessionStorage.getItem(COLLAPSED_KEY);
            height = parseInt(sessionStorage.getItem(HEIGHT_KEY), 10);
            tab = sessionStorage.getItem(TAB_KEY);
        } catch {
            /* sessionStorage may be unavailable; non-fatal */
        }
        const mobile = isMobile();
        this._height = Number.isFinite(height) && height > 0
            ? height
            : (mobile ? DEFAULT_HEIGHT_MOBILE : DEFAULT_HEIGHT_DESKTOP);
        this._select(tab === 'crossings' ? 'crossings' : 'lines', { remember: false });
        this.setCollapsed(collapsed === '1' || (collapsed === null && mobile), { remember: false });

        // The "more below" gradient goes out once the pane is scrolled to its
        // end — and needs a re-check after the expand transition lands.
        this._onScroll = () => this._updateOverflow();
        if (this.hasBodyTarget) {
            this.bodyTarget.addEventListener('scroll', this._onScroll, { passive: true });
            this.bodyTarget.addEventListener('transitionend', this._onScroll);
        }

        // Viewport resize/rotation can shrink the half-of-map limit below the
        // current height — re-clamp (the stored height stays the user's).
        this._onResize = () => {
            if (!this._collapsed() && this.hasBodyTarget) this._setHeight(this._height);
        };
        window.addEventListener('resize', this._onResize);
    }

    disconnect() {
        if (this.hasBodyTarget) {
            this.bodyTarget.removeEventListener('scroll', this._onScroll);
            this.bodyTarget.removeEventListener('transitionend', this._onScroll);
        }
        window.removeEventListener('resize', this._onResize);
    }

    // Tab click: collapsed → expand (on that tab); other tab → switch; the
    // active tab again → collapse (mirrors the old header toggle).
    selectTab(event) {
        const tab = event.params.tab;
        if (this._collapsed()) {
            this._select(tab);
            this.setCollapsed(false);
        } else if (tab !== this._active) {
            this._select(tab);
        } else {
            this.setCollapsed(true);
        }
        // A tap leaves the button focused, which reads as "still active" on
        // the collapsed outline button. Keyboard clicks (detail 0) keep focus.
        if (event.detail) event.currentTarget.blur();
    }

    toggle(event) {
        event?.preventDefault();
        this.setCollapsed(!this._collapsed());
    }

    // line-feed dispatches `collapse` after a row click on mobile — the panel
    // covers a big chunk of the map and would hide the opened popup.
    collapse() {
        this.setCollapsed(true);
    }

    // line-feed / crossing-feed dispatch `rendered` after (re)filling a pane.
    refreshOverflow() {
        this._updateOverflow();
    }

    setCollapsed(collapsed, { remember = true } = {}) {
        this.element.classList.toggle('map-panel--collapsed', collapsed);
        if (this.hasToggleTarget) {
            this.toggleTarget.setAttribute('aria-expanded', String(!collapsed));
            const label = collapsed ? 'Rozbalit panel' : 'Sbalit panel';
            this.toggleTarget.setAttribute('aria-label', label);
            this.toggleTarget.title = label;
        }
        if (this.hasBodyTarget) {
            // Collapse animates the body height to 0 — the body stays rendered,
            // display:none would kill the transition. Expand goes back to the
            // fixed user height.
            this.bodyTarget.style.height = collapsed ? '0px' : `${this._clampHeight(this._height)}px`;
        }
        this._applyTabState();
        this._updateOverflow();
        if (!remember) return;
        try {
            sessionStorage.setItem(COLLAPSED_KEY, collapsed ? '1' : '0');
        } catch {
            /* non-fatal */
        }
    }

    // Bottom-edge handle drag = resize the panel. Pointer events cover mouse
    // and touch (the handle has touch-action: none).
    resizeStart(event) {
        if (this._collapsed() || !this.hasBodyTarget) return;
        event.preventDefault();
        const startY = event.clientY;
        const startHeight = this.bodyTarget.getBoundingClientRect().height;
        this.element.classList.add('map-panel--resizing');
        const move = (e) => this._setHeight(startHeight + e.clientY - startY);
        const stop = () => {
            window.removeEventListener('pointermove', move);
            window.removeEventListener('pointerup', stop);
            window.removeEventListener('pointercancel', stop);
            this.element.classList.remove('map-panel--resizing');
            this._rememberHeight();
        };
        window.addEventListener('pointermove', move);
        window.addEventListener('pointerup', stop);
        window.addEventListener('pointercancel', stop);
    }

    // Keyboard fallback for the handle (↑ shrinks, ↓ grows — same directions as drag).
    resizeKey(event) {
        const step = event.key === 'ArrowUp' ? -KEY_STEP : event.key === 'ArrowDown' ? KEY_STEP : null;
        if (step === null || this._collapsed() || !this.hasBodyTarget) return;
        event.preventDefault();
        this._setHeight(this.bodyTarget.getBoundingClientRect().height + step);
        this._rememberHeight();
    }

    _collapsed() {
        return this.element.classList.contains('map-panel--collapsed');
    }

    _select(tab, { remember = true } = {}) {
        this._active = tab;
        const lines = tab === 'lines';
        if (this.hasLinesPaneTarget) this.linesPaneTarget.hidden = !lines;
        if (this.hasCrossingsPaneTarget) this.crossingsPaneTarget.hidden = lines;
        this._applyTabState();
        this._updateOverflow();
        if (!remember) return;
        try {
            sessionStorage.setItem(TAB_KEY, tab);
        } catch {
            /* non-fatal */
        }
    }

    // Bootstrap `.active` (filled button) = the tab is selected AND the panel
    // is expanded; collapsed panel shows both tabs as plain outline buttons.
    _applyTabState() {
        const expanded = !this._collapsed();
        const lines = expanded && this._active === 'lines';
        const crossings = expanded && this._active === 'crossings';
        if (this.hasLinesTabTarget) {
            this.linesTabTarget.classList.toggle('active', lines);
            this.linesTabTarget.setAttribute('aria-selected', String(lines));
        }
        if (this.hasCrossingsTabTarget) {
            this.crossingsTabTarget.classList.toggle('active', crossings);
            this.crossingsTabTarget.setAttribute('aria-selected', String(crossings));
        }
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

    // The map must stay usable — the panel's bottom edge never crosses the
    // middle of the map wrapper (the panel sits offset below the site header,
    // so a plain "height ≤ half" would visually end way past the middle).
    _clampHeight(height) {
        let max = 400;
        const wrapper = this.element.closest('.line-map-wrapper');
        if (wrapper && this.hasBodyTarget) {
            const w = wrapper.getBoundingClientRect();
            max = Math.round(w.top + w.height / 2 - this.bodyTarget.getBoundingClientRect().top);
        }
        return Math.min(Math.max(Math.round(height), MIN_HEIGHT), Math.max(max, MIN_HEIGHT));
    }

    // "More below" gradient: on while part of the active pane is cut off, off
    // once the user scrolls to the end (or the panel is collapsed).
    _updateOverflow() {
        if (!this.hasBodyTarget) return;
        const body = this.bodyTarget;
        const more = !this._collapsed()
            && body.scrollHeight - body.scrollTop - body.clientHeight > 6;
        this.element.classList.toggle('map-panel--overflow', more);
    }
}
