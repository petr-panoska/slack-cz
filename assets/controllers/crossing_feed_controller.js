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

const COLLAPSED_KEY = 'slack.cz:mapa:feed-collapsed';

export default class extends Controller {
    static values = {
        url: String,
    };
    static targets = ['list', 'empty', 'caption'];

    connect() {
        this.currentMode = { mode: 'recent' };
        this.fetchTimer = null;
        this.abortController = null;

        if (sessionStorage.getItem(COLLAPSED_KEY) === '1') {
            this.element.classList.add('is-collapsed');
        }

        this._onModeChange = (e) => this._handleMode(e.detail);
        document.addEventListener('slack:map-mode', this._onModeChange);

        // Initial render before the map controller publishes its mode (covers slow boot).
        // Setting _lastSig also dedupes the map controller's first 'recent' broadcast.
        this._lastSig = 'recent';
        this._fetchAndRender(this.currentMode);
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

    _handleMode(detail) {
        // Skip duplicates so playback at 1× (≈30 day-changes/sec) doesn't spam fetches.
        const sig = detail.mode === 'time-travel'
            ? `tt:${detail.date}:${detail.days}`
            : 'recent';
        if (sig === this._lastSig) return;
        this._lastSig = sig;
        this.currentMode = detail;

        if (this.fetchTimer) clearTimeout(this.fetchTimer);
        this.fetchTimer = setTimeout(() => {
            this.fetchTimer = null;
            this._fetchAndRender(detail);
        }, 200);
    }

    async _fetchAndRender(detail) {
        let url = this.urlValue;
        if (detail.mode === 'time-travel') {
            const params = new URLSearchParams({
                date: detail.date,
                days: String(detail.days ?? 7),
            });
            url = `${this.urlValue}?${params.toString()}`;
        }

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
            this.captionTarget.textContent = detail.mode === 'time-travel'
                ? `${formatDate(detail.date)} · 7 dní zpět`
                : `Posledních ${items.length} přechodů`;
        }

        this.listTarget.innerHTML = '';

        if (items.length === 0) {
            if (this.hasEmptyTarget) {
                this.emptyTarget.hidden = false;
                this.emptyTarget.textContent = detail.mode === 'time-travel'
                    ? 'V tomto týdnu žádný přechod.'
                    : 'Zatím žádný přechod.';
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
        const lineHref = `/highline/${encodeURIComponent(item.highlineSlug)}`;
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
                <a class="crossing-feed-line" href="${lineHref}">${escapeHtml(item.highlineName)}</a>
            </div>
            ${commentHtml}
        `;
        return li;
    }
}
