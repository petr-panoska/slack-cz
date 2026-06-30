import { Controller } from '@hotwired/stimulus';

const CZECH_MONTHS = [
    'led', 'úno', 'bře', 'dub', 'kvě', 'čvn',
    'čvc', 'srp', 'zář', 'říj', 'lis', 'pro',
];

function formatDate(iso) {
    const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(iso);
    if (!m) return iso;
    const day = parseInt(m[3], 10);
    const monIdx = parseInt(m[2], 10) - 1;
    const year = m[1];
    return `${day}. ${CZECH_MONTHS[monIdx] ?? ''} ${year}`;
}

function escapeHtml(s) {
    return String(s)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function renderStars(rating) {
    if (!rating) return '';
    let s = '';
    for (let i = 1; i <= 5; i++) {
        s += `<span class="crossing-feed-star${i <= rating ? ' on' : ''}">★</span>`;
    }
    return `<span class="crossing-feed-rating" title="${rating}/5">${s}</span>`;
}

function isoDate(d) {
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
}

const COLLAPSED_KEY = 'slack.cz:map:feed-collapsed';
const USERS_HIDDEN_KEY = 'slack.cz:map:users-hidden';
const DEFAULT_COUNT = 10;
const MAX_COUNT = 200;
const DEFAULT_RANGE_DAYS = 30;

export default class extends Controller {
    static values = {
        url: String,
    };
    static targets = [
        'list', 'empty', 'caption',
        'filter', 'count', 'from', 'to', 'modeCount', 'modeRange',
    ];

    connect() {
        // Last window broadcast by the map (time-travel playback transiently overrides
        // the user filter; when it ends we fall back to whatever the user picked here).
        this.mapMode = { mode: 'recent' };

        // Default date range = last 30 days, computed client-side to dodge server TZ skew.
        const today = new Date();
        const past = new Date();
        past.setDate(past.getDate() - DEFAULT_RANGE_DAYS);
        const defFrom = isoDate(past);
        const defTo = isoDate(today);
        this.userFilter = { type: 'count', count: DEFAULT_COUNT, from: defFrom, to: defTo };

        if (this.hasFromTarget && !this.fromTarget.value) this.fromTarget.value = defFrom;
        if (this.hasToTarget && !this.toTarget.value) this.toTarget.value = defTo;
        this._syncDisabled();

        this.fetchTimer = null;
        this.abortController = null;

        if (sessionStorage.getItem(COLLAPSED_KEY) === '1') {
            this.element.classList.add('is-collapsed');
        }

        this.usersVisible = sessionStorage.getItem(USERS_HIDDEN_KEY) !== '1';
        this._applyUsersVisibility();

        this._onModeChange = (e) => this._handleMode(e.detail);
        document.addEventListener('slack:map-mode', this._onModeChange);

        // Render immediately; _lastSig dedupes the map controller's first 'recent' broadcast.
        this._lastSig = null;
        this._apply(true);
    }

    disconnect() {
        document.removeEventListener('slack:map-mode', this._onModeChange);
        if (this.fetchTimer) clearTimeout(this.fetchTimer);
        this.abortController?.abort();
    }

    toggle(event) {
        event?.preventDefault();
        const collapsed = this.element.classList.toggle('is-collapsed');
        try {
            sessionStorage.setItem(COLLAPSED_KEY, collapsed ? '1' : '0');
        } catch {
            /* sessionStorage may be unavailable; non-fatal */
        }
    }

    toggleUsers(event) {
        event?.preventDefault();
        this.usersVisible = !this.usersVisible;
        try {
            sessionStorage.setItem(USERS_HIDDEN_KEY, this.usersVisible ? '0' : '1');
        } catch {
            /* sessionStorage may be unavailable; non-fatal */
        }
        this._applyUsersVisibility();
    }

    _applyUsersVisibility() {
        this.element.classList.toggle('users-hidden', !this.usersVisible);
        const eye = this.element.querySelector('.crossing-feed-eye');
        if (eye) {
            const label = this.usersVisible ? 'Skrýt přechody na mapě' : 'Zobrazit přechody na mapě';
            eye.setAttribute('aria-label', label);
            eye.setAttribute('title', label);
        }
        document.dispatchEvent(new CustomEvent('slack:users-visibility', {
            detail: { visible: this.usersVisible },
        }));
    }

    // ---------- filter panel ----------
    toggleFilter(event) {
        event?.preventDefault();
        const willOpen = this.filterTarget.hidden;
        this.filterTarget.hidden = !willOpen;
        this.element.classList.toggle('filter-open', willOpen);
        if (this.hasCaptionTarget) {
            this.captionTarget.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
        }
    }

    changeMode() {
        this.userFilter.type = this.hasModeRangeTarget && this.modeRangeTarget.checked ? 'range' : 'count';
        this._syncDisabled();
        if (this.userFilter.type === 'count') {
            const n = this._readCount();
            if (n !== null) this.userFilter.count = n;
        } else {
            this._readRange();
        }
        this._apply();
    }

    changeCount() {
        const n = this._readCount();
        if (n === null) return; // mid-edit / empty — wait for a valid value
        this.userFilter.count = n;
        if (this.userFilter.type === 'count') this._apply();
    }

    changeRange() {
        if (!this._readRange()) return; // need both endpoints
        if (this.userFilter.type === 'range') this._apply();
    }

    _readCount() {
        if (!this.hasCountTarget) return null;
        const n = parseInt(this.countTarget.value, 10);
        if (!Number.isFinite(n) || n < 1) return null;
        return Math.min(n, MAX_COUNT);
    }

    _readRange() {
        if (!this.hasFromTarget || !this.hasToTarget) return false;
        let from = this.fromTarget.value;
        let to = this.toTarget.value;
        if (!from || !to) return false;
        if (from > to) [from, to] = [to, from];
        this.userFilter.from = from;
        this.userFilter.to = to;
        return true;
    }

    _syncDisabled() {
        const range = this.userFilter.type === 'range';
        if (this.hasCountTarget) this.countTarget.disabled = range;
        if (this.hasFromTarget) this.fromTarget.disabled = !range;
        if (this.hasToTarget) this.toTarget.disabled = !range;
    }

    // ---------- map bus + fetch orchestration ----------
    _handleMode(detail) {
        this.mapMode = detail;
        this._apply();
    }

    _apply(immediate = false) {
        const detail = this._effectiveDetail();
        // Skip duplicates so playback at 1× (≈30 day-changes/sec) doesn't spam fetches.
        const sig = this._sig(detail);
        if (sig === this._lastSig) return;
        this._lastSig = sig;

        if (this.fetchTimer) clearTimeout(this.fetchTimer);
        const run = () => {
            this.fetchTimer = null;
            // Mirror the filter onto the map's emoji markers. Time-travel is the map's own
            // mode (it animates its markers itself), so we never echo that back to it.
            if (detail.mode !== 'time-travel') {
                document.dispatchEvent(new CustomEvent('slack:feed-filter', { detail: { ...detail } }));
            }
            this._fetchAndRender(detail);
        };
        if (immediate) run();
        else this.fetchTimer = setTimeout(run, 200);
    }

    // Time-travel playback (from the map) wins while active; otherwise the user's filter drives the feed.
    _effectiveDetail() {
        if (this.mapMode.mode === 'time-travel') {
            return { mode: 'time-travel', date: this.mapMode.date, days: this.mapMode.days ?? 7 };
        }
        if (this.userFilter.type === 'range') {
            return { mode: 'range', from: this.userFilter.from, to: this.userFilter.to };
        }
        return { mode: 'count', count: this.userFilter.count };
    }

    _sig(d) {
        if (d.mode === 'time-travel') return `tt:${d.date}:${d.days}`;
        if (d.mode === 'range') return `range:${d.from}:${d.to}`;
        return `count:${d.count}`;
    }

    async _fetchAndRender(detail) {
        const params = new URLSearchParams();
        if (detail.mode === 'time-travel') {
            params.set('date', detail.date);
            params.set('days', String(detail.days ?? 7));
        } else if (detail.mode === 'range') {
            params.set('from', detail.from);
            params.set('to', detail.to);
        } else {
            params.set('limit', String(detail.count));
        }
        const qs = params.toString();
        const url = qs ? `${this.urlValue}?${qs}` : this.urlValue;

        this.abortController?.abort();
        const ac = new AbortController();
        this.abortController = ac;

        try {
            const res = await fetch(url, {
                signal: ac.signal,
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            if (!res.ok) return;
            const data = await res.json();
            if (!Array.isArray(data)) return;
            this._renderAll(data, detail);
        } catch (e) {
            if (e.name !== 'AbortError') console.error(e);
        }
    }

    _renderAll(items, detail) {
        if (this.hasCaptionTarget) {
            let caption;
            if (detail.mode === 'time-travel') caption = `${formatDate(detail.date)} · 7 dní zpět`;
            else if (detail.mode === 'range') caption = `${formatDate(detail.from)} – ${formatDate(detail.to)}`;
            else caption = `Posledních ${items.length} přechodů`;
            this.captionTarget.textContent = caption;
        }

        this.listTarget.innerHTML = '';

        if (items.length === 0) {
            if (this.hasEmptyTarget) {
                this.emptyTarget.hidden = false;
                if (detail.mode === 'time-travel') this.emptyTarget.textContent = 'V tomto týdnu žádný přechod.';
                else if (detail.mode === 'range') this.emptyTarget.textContent = 'V tomto období žádný přechod.';
                else this.emptyTarget.textContent = 'Zatím žádný přechod.';
            }
            return;
        }
        if (this.hasEmptyTarget) this.emptyTarget.hidden = true;

        const frag = document.createDocumentFragment();
        items.forEach((item, i) => {
            const node = this._render(item);
            node.style.setProperty('--feed-stagger', `${Math.min(i, 8) * 35}ms`);
            frag.appendChild(node);
        });
        this.listTarget.appendChild(frag);
    }

    _render(item) {
        const li = document.createElement('li');
        li.className = 'crossing-feed-item';

        const userHref = `/denik/${item.userId}`;
        const lineHref = `/lajna/${encodeURIComponent(item.lineSlug)}`;
        const dateStr = escapeHtml(formatDate(item.crossedAt));
        const styleHtml = item.styleLabel
            ? `<span class="crossing-feed-style">${escapeHtml(item.styleLabel)}</span>`
            : '';
        const ratingHtml = renderStars(item.rating);
        const commentHtml = item.comment
            ? `<p class="crossing-feed-comment">${escapeHtml(item.comment)}</p>`
            : '';

        li.innerHTML = `
            <header class="crossing-feed-head">
                <time class="crossing-feed-date" datetime="${escapeHtml(item.crossedAt)}">${dateStr}</time>
                ${styleHtml}
                ${ratingHtml}
            </header>
            <div class="crossing-feed-body">
                <a class="crossing-feed-user" href="${userHref}">${escapeHtml(item.userDisplayName)}</a>
                <span class="crossing-feed-on">na</span>
                <a class="crossing-feed-line" href="${lineHref}">${escapeHtml(item.lineName)}</a>
            </div>
            ${commentHtml}
        `;
        return li;
    }
}
