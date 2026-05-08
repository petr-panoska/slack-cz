import { Controller } from '@hotwired/stimulus';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const TYPE_LABELS = {
    unsorted: 'Nezařazeno',
    highline: 'Highline',
    top_highline: 'Top Highline',
    midline: 'Midline',
    urban_line: 'Urban Line',
};

const STYLE_LABELS = {
    os_fm: 'OS FM',
    os_fm_split: 'OS, FM',
    os: 'OS',
    fm: 'FM',
    ow: 'OW',
    af: 'AF',
    swami: 'swami',
    solo: 'solo',
    kotnik: 'kotník',
};

const CZECH_CENTER = [49.8, 15.5];

// Survives Turbo navigations (map → highline detail → back) so the user
// returns to the same pan/zoom they left.
const VIEW_STORAGE_KEY = 'slack.cz:mapa:view';

function readSavedView() {
    try {
        const raw = sessionStorage.getItem(VIEW_STORAGE_KEY);
        if (!raw) return null;
        const v = JSON.parse(raw);
        if (typeof v?.lat !== 'number' || typeof v?.lng !== 'number' || typeof v?.zoom !== 'number') {
            return null;
        }
        return v;
    } catch {
        return null;
    }
}

function writeSavedView(map) {
    try {
        const c = map.getCenter();
        sessionStorage.setItem(VIEW_STORAGE_KEY, JSON.stringify({
            lat: c.lat,
            lng: c.lng,
            zoom: map.getZoom(),
        }));
    } catch {
        /* sessionStorage may be unavailable (private mode quirks); ignore. */
    }
}

const USER_EMOJIS = [
    '🦊','🐻','🐼','🐨','🐯','🦁','🐮','🐷','🐸','🐵',
    '🐔','🐧','🦅','🦉','🐺','🦄','🐝','🦋','🐢','🐠',
    '🐬','🦈','🦒','🦓','🦌','🐕','🐈','🐇','🦔','🦝',
    '🌲','🌵','🌴','🌸','🌻','🍄','🌈','🍀','⭐','⚡',
    '🔥','💎','🎯','🚀','🏔','🪨','🌟','🎈','🎨','🎭',
];

const DATE_FORMATTER = new Intl.DateTimeFormat('cs-CZ', {
    day: 'numeric',
    month: 'numeric',
    year: 'numeric',
});

const DATE_FORMATTER_LONG = new Intl.DateTimeFormat('cs-CZ', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
});

// At 1× speed: 30 days of virtual time pass per real second.
// 16 years of history → ~3.25 minutes at 1×.
const TIMELINE_DAYS_PER_SECOND = 30;

export default class extends Controller {
    static values = {
        dataUrl: String,
        usersUrl: String,
        timelineUrl: String,
        iconUrl: String,
        iconRetinaUrl: String,
        shadowUrl: String,
    };

    static targets = [
        'canvas',
        'ttToggle',
        'ttPanel',
        'ttPlay',
        'ttDate',
        'ttSlider',
        'ttCounter',
        'ttSpeed',
    ];

    async connect() {
        L.Icon.Default.mergeOptions({
            iconUrl: this.iconUrlValue,
            iconRetinaUrl: this.iconRetinaUrlValue,
            shadowUrl: this.shadowUrlValue,
        });

        const saved = readSavedView();
        this.viewRestored = saved !== null;
        this.map = L.map(this.canvasTarget).setView(
            saved ? [saved.lat, saved.lng] : CZECH_CENTER,
            saved ? saved.zoom : 7,
        );

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        }).addTo(this.map);

        this.map.on('moveend zoomend', () => writeSavedView(this.map));

        // Static mode = highlines + recent users emoji
        this.staticLayer = L.layerGroup().addTo(this.map);
        // Timeline mode = highlines fading in chronologically + crossing pulses
        this.timelineLayer = L.layerGroup();

        this.timeTravel = false;
        this.playing = false;
        this.speedMultiplier = 1;

        await this.loadHighlines();
        await this.loadUsers();
    }

    disconnect() {
        if (this.rafHandle) {
            cancelAnimationFrame(this.rafHandle);
            this.rafHandle = null;
        }
        if (this.map) {
            this.map.remove();
            this.map = null;
        }
    }

    // ---------- Static markers (default mode) ----------

    async loadHighlines() {
        const response = await fetch(this.dataUrlValue, { headers: { Accept: 'application/json' } });
        if (!response.ok) {
            console.error('Failed to load highlines', response.status);
            return;
        }

        const highlines = await response.json();
        const markers = [];
        for (const h of highlines) {
            const lat = parseFloat(h.latitude);
            const lng = parseFloat(h.longitude);
            if (Number.isNaN(lat) || Number.isNaN(lng)) continue;

            const marker = L.marker([lat, lng]).bindPopup(this.popupHtml(h));
            marker.addTo(this.staticLayer);
            markers.push(marker);
        }

        if (markers.length > 0 && !this.viewRestored) {
            const group = L.featureGroup(markers);
            this.map.fitBounds(group.getBounds().pad(0.1));
        }
    }

    async loadUsers() {
        if (!this.hasUsersUrlValue) return;

        const response = await fetch(this.usersUrlValue, { headers: { Accept: 'application/json' } });
        if (!response.ok) {
            console.error('Failed to load users', response.status);
            return;
        }

        const users = await response.json();

        const groups = new Map();
        for (const u of users) {
            const key = `${u.latitude}|${u.longitude}`;
            if (!groups.has(key)) groups.set(key, []);
            groups.get(key).push(u);
        }

        for (const [key, list] of groups) {
            const [lat, lng] = key.split('|').map(parseFloat);
            if (Number.isNaN(lat) || Number.isNaN(lng)) continue;

            list.forEach((u, i) => {
                const offset = fanOffset(i, list.length);
                const emoji = emojiForUser(u.userId);
                const icon = L.divIcon({
                    className: 'user-circle-icon',
                    html: `<span class="user-emoji">${emoji}</span>`,
                    iconSize: [32, 32],
                    iconAnchor: [16 - offset.x, 16 - offset.y],
                });
                L.marker([lat, lng], { icon, zIndexOffset: 800 })
                    .bindPopup(this.userPopupHtml(u))
                    .addTo(this.staticLayer);
            });
        }
    }

    // ---------- Time travel ----------

    async toggleTimeTravel(event) {
        event?.preventDefault();
        if (this.timeTravel) {
            this.exitTimeTravel();
        } else {
            await this.enterTimeTravel();
        }
    }

    async enterTimeTravel() {
        if (!this.hasTimelineUrlValue) return;

        if (!this.timelineLoaded) {
            const ok = await this.fetchTimelineData();
            if (!ok) return;
        }

        this.timeTravel = true;
        this.staticLayer.removeFrom(this.map);
        this.timelineLayer.addTo(this.map);
        this.element.classList.add('time-travel-active');
        if (this.hasTtToggleTarget) this.ttToggleTarget.textContent = 'Návrat do dneška';

        // Reset playback state
        this.virtualTime = this.timelineStart;
        this.timelineHighlineMarkers = new Map();
        this.timelineCounters = { highlines: 0, crossings: 0 };
        this.eventCursor = { highline: 0, crossing: 0 };
        this.playing = false;
        this.lastFrameTime = 0;

        this.updateTimelineUI();
    }

    exitTimeTravel() {
        this.timeTravel = false;
        this.playing = false;
        if (this.rafHandle) {
            cancelAnimationFrame(this.rafHandle);
            this.rafHandle = null;
        }

        this.timelineLayer.clearLayers();
        this.timelineLayer.removeFrom(this.map);
        this.staticLayer.addTo(this.map);

        this.element.classList.remove('time-travel-active');
        if (this.hasTtToggleTarget) this.ttToggleTarget.textContent = 'Přehrát historii';
    }

    async fetchTimelineData() {
        const response = await fetch(this.timelineUrlValue, { headers: { Accept: 'application/json' } });
        if (!response.ok) {
            console.error('Failed to load timeline data', response.status);
            return false;
        }
        const data = await response.json();
        this.timelineHighlines = data.highlines;
        this.timelineCrossings = data.crossings;

        this.timelineHighlinesById = new Map();
        for (const h of this.timelineHighlines) {
            this.timelineHighlinesById.set(h.id, h);
        }

        const firstDate = this.timelineHighlines[0]?.appearanceDate;
        this.timelineStart = firstDate
            ? new Date(firstDate).getTime()
            : new Date('2010-01-01').getTime();
        this.timelineEnd = Date.now();

        this.timelineLoaded = true;
        return true;
    }

    togglePlay(event) {
        event?.preventDefault();
        if (!this.timeTravel) return;

        // Reached the end? rewind to start.
        if (this.virtualTime >= this.timelineEnd) {
            this.seekToTime(this.timelineStart);
        }

        this.playing = !this.playing;
        if (this.playing) {
            this.lastFrameTime = performance.now();
            this.rafHandle = requestAnimationFrame((t) => this.tick(t));
        }
        this.updateTimelineUI();
    }

    seek(event) {
        if (!this.timeTravel) return;
        const ratio = parseFloat(event.target.value) / 1000;
        const target = this.timelineStart + ratio * (this.timelineEnd - this.timelineStart);
        this.seekToTime(target);
    }

    seekToTime(target) {
        // Wipe everything and re-process from the very beginning, no animations.
        this.timelineLayer.clearLayers();
        this.timelineHighlineMarkers = new Map();
        this.timelineCounters = { highlines: 0, crossings: 0 };
        this.eventCursor = { highline: 0, crossing: 0 };

        this.virtualTime = this.timelineStart;
        this.processEventsTo(target, true);
        this.virtualTime = target;

        this.updateTimelineUI();
    }

    setSpeed(event) {
        const v = parseFloat(event.target.value);
        if (Number.isFinite(v) && v > 0) this.speedMultiplier = v;
    }

    tick(now) {
        if (!this.playing || !this.timeTravel) return;

        // Cap delta to avoid huge jumps if tab was throttled.
        const delta = Math.min(0.1, (now - this.lastFrameTime) / 1000);
        this.lastFrameTime = now;

        const advanceMs = TIMELINE_DAYS_PER_SECOND * this.speedMultiplier * 86400 * 1000 * delta;
        const newTime = this.virtualTime + advanceMs;

        this.processEventsTo(newTime, false);
        this.virtualTime = newTime;

        if (this.virtualTime >= this.timelineEnd) {
            this.virtualTime = this.timelineEnd;
            this.playing = false;
        }

        this.updateTimelineUI();

        if (this.playing) {
            this.rafHandle = requestAnimationFrame((t) => this.tick(t));
        }
    }

    processEventsTo(targetTime, instant) {
        // Fire highlines first so subsequent crossings can anchor to them.
        while (this.eventCursor.highline < this.timelineHighlines.length) {
            const h = this.timelineHighlines[this.eventCursor.highline];
            if (new Date(h.appearanceDate).getTime() > targetTime) break;
            this.appearHighline(h, instant);
            this.eventCursor.highline++;
        }

        while (this.eventCursor.crossing < this.timelineCrossings.length) {
            const c = this.timelineCrossings[this.eventCursor.crossing];
            if (new Date(c.crossedAt).getTime() > targetTime) break;
            this.pulseCrossing(c, instant);
            this.eventCursor.crossing++;
        }
    }

    appearHighline(h, instant) {
        this.timelineCounters.highlines++;

        const lat = parseFloat(h.latitude);
        const lng = parseFloat(h.longitude);
        if (Number.isNaN(lat) || Number.isNaN(lng)) return;

        const marker = L.marker([lat, lng]).bindPopup(this.popupHtml(h));
        marker.addTo(this.timelineLayer);
        this.timelineHighlineMarkers.set(h.id, marker);

        if (!instant) this.bloomAt(lat, lng);
    }

    bloomAt(lat, lng) {
        const icon = L.divIcon({
            className: 'highline-bloom-icon',
            html: '<span class="highline-bloom"></span>',
            iconSize: [80, 80],
            iconAnchor: [40, 40],
        });
        const m = L.marker([lat, lng], { icon, interactive: false, zIndexOffset: 600 });
        m.addTo(this.timelineLayer);
        setTimeout(() => this.timelineLayer.removeLayer(m), 2200);
    }

    pulseCrossing(c, instant) {
        this.timelineCounters.crossings++;
        if (instant) return;

        const h = this.timelineHighlinesById.get(c.highlineId);
        if (!h) return;
        const lat = parseFloat(h.latitude);
        const lng = parseFloat(h.longitude);
        if (Number.isNaN(lat) || Number.isNaN(lng)) return;

        const emoji = emojiForUser(c.userId);
        const icon = L.divIcon({
            className: 'crossing-pulse-icon',
            html: `<span class="crossing-pulse-ring"></span><span class="crossing-pulse-emoji">${emoji}</span>`,
            iconSize: [44, 44],
            iconAnchor: [22, 22],
        });
        const m = L.marker([lat, lng], { icon, interactive: false, zIndexOffset: 1100 });
        m.addTo(this.timelineLayer);
        setTimeout(() => this.timelineLayer.removeLayer(m), 1700);
    }

    updateTimelineUI() {
        if (!this.timeTravel) return;

        if (this.hasTtDateTarget) {
            this.ttDateTarget.textContent = DATE_FORMATTER_LONG.format(new Date(this.virtualTime));
        }
        if (this.hasTtSliderTarget) {
            const ratio = (this.virtualTime - this.timelineStart) / (this.timelineEnd - this.timelineStart);
            this.ttSliderTarget.value = String(Math.round(ratio * 1000));
        }
        if (this.hasTtCounterTarget) {
            const c = this.timelineCounters;
            this.ttCounterTarget.textContent = `Highlines: ${c.highlines} · Přechody: ${c.crossings}`;
        }
        if (this.hasTtPlayTarget) {
            this.ttPlayTarget.textContent = this.playing ? '⏸' : '▶';
            this.ttPlayTarget.setAttribute('aria-label', this.playing ? 'Pauza' : 'Přehrát');
        }
    }

    // ---------- Popups ----------

    popupHtml(h) {
        const typeLabel = TYPE_LABELS[h.type] ?? h.type;
        const place = [h.area, h.region].filter(Boolean).join(', ');
        const title = h.slug
            ? `<a href="/highline/${encodeURIComponent(h.slug)}">${escapeHtml(h.name)}</a>`
            : `<strong>${escapeHtml(h.name)}</strong>`;
        return `
            <div class="highline-popup">
                <strong>${title}</strong>
                <div>${escapeHtml(typeLabel)}</div>
                <div>${h.length} m &middot; ${h.height} m vysoko</div>
                ${place ? `<div class="muted">${escapeHtml(place)}</div>` : ''}
            </div>
        `;
    }

    userPopupHtml(u) {
        const date = DATE_FORMATTER.format(new Date(u.crossedAt));
        const stars = u.rating
            ? '★'.repeat(u.rating) + '☆'.repeat(5 - u.rating)
            : '';
        const lineLink = u.highlineSlug
            ? `<a href="/highline/${encodeURIComponent(u.highlineSlug)}">${escapeHtml(u.highlineName)}</a>`
            : escapeHtml(u.highlineName);
        return `
            <div class="user-popup">
                <strong>${escapeHtml(u.userDisplayName)}</strong>
                <div class="user-popup-line">${lineLink}</div>
                <div class="muted">${escapeHtml(date)}${stars ? ` &middot; <span class="user-popup-stars">${stars}</span>` : ''}</div>
            </div>
        `;
    }
}

function fanOffset(i, total) {
    if (total <= 1) return { x: 0, y: 0 };
    const radius = 22;
    const angle = (i / total) * Math.PI * 2 - Math.PI / 2;
    return { x: Math.cos(angle) * radius, y: Math.sin(angle) * radius };
}

function emojiForUser(userId) {
    return USER_EMOJIS[userId % USER_EMOJIS.length];
}

export function escapeHtml(value) {
    if (value == null) return '';
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}
