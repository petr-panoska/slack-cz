import { Controller } from '@hotwired/stimulus';

// Module-scope cache: 254 highlines max — fetched once per tab, reused across
// every search-open. Survives Turbo navigations naturally (module is not reset).
let cachedHighlines = null;
let inFlight = null;

async function getHighlines(url) {
    if (cachedHighlines) return cachedHighlines;
    if (inFlight) return inFlight;
    inFlight = fetch(url, { headers: { Accept: 'application/json' } })
        .then((r) => (r.ok ? r.json() : []))
        .then((list) => {
            cachedHighlines = Array.isArray(list) ? list : [];
            inFlight = null;
            return cachedHighlines;
        })
        .catch(() => {
            inFlight = null;
            return [];
        });
    return inFlight;
}

// Diacritics-insensitive: "cimburi" matches "Cimbuři".
function fold(s) {
    return (s ?? '')
        .toString()
        .normalize('NFD')
        .replace(/\p{Diacritic}/gu, '')
        .toLowerCase();
}

const MAX_RESULTS = 12;

export default class extends Controller {
    static targets = ['input', 'results'];
    static values = { dataUrl: String };

    connect() {
        this.onDocClick = (e) => {
            if (this.isOpen() && !this.element.contains(e.target)) this.close();
        };
        this.onKeydown = (e) => {
            if (e.key === 'Escape' && this.isOpen()) {
                this.close();
                this.element.querySelector('.site-search-btn')?.focus();
            }
        };
        document.addEventListener('click', this.onDocClick);
        document.addEventListener('keydown', this.onKeydown);
    }

    disconnect() {
        document.removeEventListener('click', this.onDocClick);
        document.removeEventListener('keydown', this.onKeydown);
    }

    isOpen() {
        return this.element.classList.contains('is-open');
    }

    toggle(event) {
        event?.preventDefault();
        event?.stopPropagation();
        if (this.isOpen()) this.close();
        else this.open();
    }

    open() {
        this.element.classList.add('is-open');
        // Pre-warm cache so first keystroke is instant.
        getHighlines(this.dataUrlValue).then(() => {
            if (this.isOpen()) this.render();
        });
        // focus after the panel transition begins
        requestAnimationFrame(() => {
            this.inputTarget.focus();
            this.inputTarget.select();
        });
    }

    close() {
        this.element.classList.remove('is-open');
    }

    async input() {
        const list = await getHighlines(this.dataUrlValue);
        this.render(list);
    }

    render(list) {
        const data = list ?? cachedHighlines ?? [];
        const q = fold(this.inputTarget.value).trim();
        const ul = this.resultsTarget;

        if (!q) {
            ul.innerHTML = '';
            ul.classList.remove('has-results');
            return;
        }

        const matches = data
            .filter((h) => fold(h.name).includes(q))
            .slice(0, MAX_RESULTS);

        ul.classList.add('has-results');

        if (matches.length === 0) {
            ul.innerHTML = '<li class="site-search-empty">Nic nenalezeno</li>';
            return;
        }

        ul.innerHTML = matches
            .map((h) => {
                const meta = [h.area, h.region].filter(Boolean).join(' · ');
                return `
                    <li>
                        <a href="/highline/${encodeURIComponent(h.slug)}" data-action="click->search#close">
                            <span class="site-search-name">${escapeHtml(h.name)}</span>
                            ${meta ? `<span class="site-search-meta">${escapeHtml(meta)}</span>` : ''}
                        </a>
                    </li>
                `;
            })
            .join('');
    }
}

function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}
