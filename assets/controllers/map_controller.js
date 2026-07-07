import { Controller } from '@hotwired/stimulus';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
// Attaches L.markerClusterGroup; the base CSS carries only the cluster
// zoom animations — the cluster icon itself is ours (.map-cluster).
import 'leaflet.markercluster';
import 'leaflet.markercluster/dist/MarkerCluster.min.css';
import { emojiForUser } from '../user_emoji.js';
import { addBasemapPicker } from '../basemap.js';
import { addFullscreenToggle } from '../map_fullscreen.js';
import { addLocateControl } from '../map_locate.js';

// Mirrors App\Enum\LineType (legacy "Top Highline"/"Urban Line" were collapsed
// into Highline at import — they don't exist as live values anymore).
const TYPE_LABELS = {
    highline: 'Highline',
    midline: 'Midline',
    longline: 'Longline',
    waterline: 'Waterline',
    unsorted: 'Nezařazeno',
};

// One colour per type for markers + lines + legend. White-ringed so they read on
// any basemap. Unsorted (the import default, still the majority) stays a neutral grey.
const TYPE_COLORS = {
    highline: '#e1005b',
    midline: '#f57c00',
    longline: '#2e7d32',
    waterline: '#0277bd',
    unsorted: '#78909c',
};

// Canonical legend order; the legend itself only renders the types actually on the map.
const LEGEND_ORDER = ['highline', 'midline', 'longline', 'waterline', 'unsorted'];

// Exported for the sidebar line list (line_feed_controller) so the type dots
// there match the map markers.
export function typeColor(type) {
    return TYPE_COLORS[type] ?? TYPE_COLORS.unsorted;
}

function lineIcon(type) {
    return L.divIcon({
        className: 'line-marker',
        html: `<span class="line-dot" style="background:${typeColor(type)}"></span>`,
        iconSize: [18, 18],
        iconAnchor: [9, 9],
    });
}

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

// Below this zoom the lines would be sub-pixel noise over a country-wide view, so
// we only draw the polyline between a highline's two anchors once zoomed in close.
const LINE_MIN_ZOOM = 14;

// When re-fitting the map to the filtered crossings, don't zoom in tighter than this
// (a single crossing would otherwise slam to max zoom).
const FIT_MAX_ZOOM = 13;

// Survives Turbo navigations (map → highline detail → back) so the user
// returns to the same pan/zoom they left.
const VIEW_STORAGE_KEY = 'slack.cz:map:view';
// Owned by crossing_feed_controller (eye toggle); read here on boot so we don't
// race with the feed controller's connect order.
const USERS_HIDDEN_KEY = 'slack.cz:map:users-hidden';

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
        this.map = L.map(this.canvasTarget, { zoomControl: false }).setView(
            saved ? [saved.lat, saved.lng] : CZECH_CENTER,
            saved ? saved.zoom : 7,
        );
        L.control.zoom({ position: 'bottomright' }).addTo(this.map);

        addBasemapPicker(this.map);
        // Fullscreen the whole wrapper, not just the canvas, so the crossing feed and
        // time-travel controls stay on screen.
        addFullscreenToggle(this.map, { element: this.element });
        addLocateControl(this.map);

        this.map.on('moveend zoomend', () => {
            writeSavedView(this.map);
            this._publishViewportLines();
        });
        this.map.on('zoomend', () => this._updateLinesVisibility());

        // The canvas shares the page with the lines box (no overlap), so its size
        // changes when the box expands/collapses (animated) — keep Leaflet's
        // internal size in sync per frame and republish the viewport lines once
        // the resize settles. `pan: false` keeps invalidateSize from firing
        // moveend, which would masquerade as a user interaction.
        if ('ResizeObserver' in window) {
            this._canvasResize = new ResizeObserver(() => {
                if (!this.map) return;
                this.map.invalidateSize({ animate: false, pan: false });
                clearTimeout(this._resizePublish);
                this._resizePublish = setTimeout(() => this._publishViewportLines('resize'), 120);
            });
            this._canvasResize.observe(this.canvasTarget);
        }

        // Static mode = highline markers, clustered in dense areas (Tisá, Ostrov).
        // Clustering stops at LINE_MIN_ZOOM — where the actual lines draw, every
        // marker stands on its own anchor. Recent users emoji live in usersLayer
        // (toggleable via the sidebar eye) and stay unclustered.
        this.staticLayer = L.markerClusterGroup({
            maxClusterRadius: 50,
            disableClusteringAtZoom: LINE_MIN_ZOOM,
            showCoverageOnHover: false,
            // Click = zoom towards the cluster, nothing else. Spiderfy (the
            // default at the last clustered zoom) fans markers out on legs to
            // fake positions — nonsense for real-world anchors.
            spiderfyOnMaxZoom: false,
            iconCreateFunction: (cluster) => L.divIcon({
                className: 'map-cluster',
                html: `<span class="map-cluster__count">${cluster.getChildCount()}</span>`,
                iconSize: [34, 34],
                iconAnchor: [17, 17],
            }),
        }).addTo(this.map);
        // Polyline per highline (the line between its two anchors); only shown once
        // zoomed in past LINE_MIN_ZOOM — see _updateLinesVisibility.
        this.linesLayer = L.layerGroup();
        this.usersLayer = L.layerGroup();
        // Timeline mode = highlines fading in chronologically + crossing pulses
        this.timelineLayer = L.layerGroup();

        this.timeTravel = false;
        this.playing = false;
        this.speedMultiplier = 1;
        this._lastEmittedDate = null;

        // Markers are hidden by default — '0' means the user explicitly showed them.
        try {
            this.usersVisible = sessionStorage.getItem(USERS_HIDDEN_KEY) === '0';
        } catch {
            this.usersVisible = false;
        }
        if (this.usersVisible) this.usersLayer.addTo(this.map);

        this._onUsersVisibility = (e) => this._setUsersVisible(e.detail?.visible ?? true);
        document.addEventListener('slack:users-visibility', this._onUsersVisibility);

        // The sidebar feed owns the crossing filter (last N / date range); it broadcasts
        // changes here so the emoji markers stay in sync with the list. Default mirrors the
        // sidebar's default (last RECENT_LIMIT) so a missed boot-time broadcast is harmless.
        this.feedFilter = { mode: 'count', count: 10 };
        this._feedSig = 'count:10';
        this._onFeedFilter = (e) => this._handleFeedFilter(e.detail);
        document.addEventListener('slack:feed-filter', this._onFeedFilter);

        // Sidebar line list: it asks for a (re)publish when it connects (covers the
        // boot race where lines finished loading before the list controller existed)
        // and sends back clicks so we can focus the map on the picked line.
        this._onLinesRequest = () => this._publishViewportLines();
        this._onLineFocus = (e) => this._focusLine(e.detail?.id);
        document.addEventListener('slack:lines-request', this._onLinesRequest);
        document.addEventListener('slack:line-focus', this._onLineFocus);

        await this.loadLines();
        await this.loadUsers();

        this._publishMode();
    }

    disconnect() {
        if (this.rafHandle) {
            cancelAnimationFrame(this.rafHandle);
            this.rafHandle = null;
        }
        this._canvasResize?.disconnect();
        clearTimeout(this._resizePublish);
        if (this._onUsersVisibility) {
            document.removeEventListener('slack:users-visibility', this._onUsersVisibility);
        }
        if (this._onFeedFilter) {
            document.removeEventListener('slack:feed-filter', this._onFeedFilter);
        }
        if (this._onLinesRequest) {
            document.removeEventListener('slack:lines-request', this._onLinesRequest);
        }
        if (this._onLineFocus) {
            document.removeEventListener('slack:line-focus', this._onLineFocus);
        }
        if (this.map) {
            this.map.remove();
            this.map = null;
        }
    }

    // ---------- Static markers (default mode) ----------

    async loadLines() {
        const response = await fetch(this.dataUrlValue, { headers: { Accept: 'application/json' } });
        if (!response.ok) {
            console.error('Failed to load highlines', response.status);
            return;
        }

        const highlines = await response.json();
        const markers = [];
        const presentTypes = new Set();
        // Feeds the sidebar line list (viewport filter + click-to-focus).
        this.lineIndex = [];
        for (const h of highlines) {
            // A line is anchored by point1; the marker sits exactly on it. point2 is
            // optional (legacy lines may have only the first anchor) — with both we
            // also draw the actual line.
            const p1 = [parseFloat(h.point1Latitude), parseFloat(h.point1Longitude)];
            if (p1.some(Number.isNaN)) continue;
            const p2 = [parseFloat(h.point2Latitude), parseFloat(h.point2Longitude)];
            const hasLine = !p2.some(Number.isNaN);
            presentTypes.add(h.type);

            const marker = L.marker(p1, { icon: lineIcon(h.type) }).bindPopup(this.popupHtml(h));
            marker.addTo(this.staticLayer);
            markers.push(marker);
            this.lineIndex.push({
                id: h.id,
                name: h.name,
                slug: h.slug,
                type: h.type,
                length: h.length,
                height: h.height,
                area: h.area,
                region: h.region,
                lat: p1[0],
                lng: p1[1],
                marker,
            });

            // The line rides in its own layer so we can hide it again at low zoom
            // (lines are meaningless from a country-wide view).
            if (hasLine) {
                L.polyline([p1, p2], { color: typeColor(h.type), weight: 3, opacity: 0.85 })
                    .bindPopup(this.popupHtml(h))
                    .addTo(this.linesLayer);
            }
        }

        if (markers.length > 0 && !this.viewRestored) {
            const group = L.featureGroup(markers);
            this.map.fitBounds(group.getBounds().pad(0.1));
        }

        this._addLegend(presentTypes);
        this._updateLinesVisibility();
        this._publishViewportLines();
    }

    // Broadcast the lines inside the current viewport (alphabetical) for the sidebar
    // list. Cheap enough to run on every moveend/zoomend — max ~254 lines.
    // `reason` tells the list what triggered the update: 'interaction' (user moved
    // the map) may re-fit the box height, 'resize' (the box itself resized the map)
    // must not — that distinction is what breaks the height↔viewport feedback loop.
    _publishViewportLines(reason = 'interaction') {
        if (!this.map || !this.lineIndex) return;
        const bounds = this.map.getBounds();
        const lines = this.lineIndex
            .filter((r) => bounds.contains([r.lat, r.lng]))
            .map(({ marker, ...data }) => data)
            .sort((a, b) => a.name.localeCompare(b.name, 'cs'));
        document.dispatchEvent(new CustomEvent('slack:viewport-lines', { detail: { lines, reason } }));
    }

    // Sidebar list click → center the picked line and open its popup. In time-travel
    // the static markers are off the map, so a popup would have nothing to anchor to.
    _focusLine(id) {
        if (!this.map || this.timeTravel) return;
        const rec = this.lineIndex?.find((r) => r.id === id);
        if (!rec) return;
        this.map.setView([rec.lat, rec.lng], Math.max(this.map.getZoom(), LINE_MIN_ZOOM));
        // Right after setView the marker may still sit inside a cluster — let
        // the cluster group unfold it first, then open the popup.
        this.staticLayer.zoomToShowLayer(rec.marker, () => rec.marker.openPopup());
    }

    // Type legend as a collapsible top-right control: a "?" pill that toggles a panel
    // listing the types actually present (in canonical LEGEND_ORDER). Top-right so it
    // sits with the other map buttons instead of covering the bottom-left crossing feed,
    // and it always starts collapsed so the legend stays hidden until asked for.
    _addLegend(presentTypes) {
        if (this._legend) {
            this._legend.remove();
            this._legend = null;
        }
        const rows = LEGEND_ORDER.filter((t) => presentTypes.has(t));
        if (rows.length === 0) return;

        const control = L.control({ position: 'topright' });
        control.onAdd = () => {
            const root = L.DomUtil.create('div', 'map-legend-ctrl');

            const btn = L.DomUtil.create('button', 'map-ctrl-btn map-legend-toggle', root);
            btn.type = 'button';
            btn.textContent = '?';
            btn.setAttribute('aria-haspopup', 'true');
            btn.setAttribute('aria-label', 'Legenda typů');
            btn.title = 'Legenda typů';

            const panel = L.DomUtil.create('div', 'map-legend', root);
            panel.innerHTML = rows.map((t) => (
                `<span class="map-legend-row">`
                + `<span class="map-legend-dot" style="background:${typeColor(t)}"></span>`
                + `${escapeHtml(TYPE_LABELS[t] ?? t)}</span>`
            )).join('');

            const setOpen = (open) => {
                panel.hidden = !open;
                btn.classList.toggle('is-active', open);
                btn.setAttribute('aria-expanded', String(open));
            };
            setOpen(false);

            L.DomEvent.on(btn, 'click', (e) => {
                L.DomEvent.preventDefault(e);
                setOpen(panel.hidden);
            });

            // Don't let map drag/zoom/click fire through the control; close on map click.
            L.DomEvent.disableClickPropagation(root);
            L.DomEvent.disableScrollPropagation(root);
            control._collapse = () => setOpen(false);
            this.map.on('click', control._collapse);

            return root;
        };
        // Drop the map-click listener when the control is removed (legend re-renders).
        control.onRemove = () => {
            if (control._collapse) this.map.off('click', control._collapse);
        };
        control.addTo(this.map);
        this._legend = control;
    }

    async loadUsers({ fit = false } = {}) {
        if (!this.hasUsersUrlValue) return;

        // Guard against out-of-order responses if the filter changes mid-flight.
        const token = (this._usersToken = (this._usersToken ?? 0) + 1);
        const response = await fetch(this._feedFilterUrl(), { headers: { Accept: 'application/json' } });
        if (token !== this._usersToken) return;
        if (!response.ok) {
            console.error('Failed to load users', response.status);
            return;
        }

        const users = await response.json();
        if (token !== this._usersToken) return;

        // Rebuild from scratch — the filter (last N / date range) may have changed.
        this.usersLayer.clearLayers();

        const groups = new Map();
        for (const u of users) {
            const key = `${u.latitude}|${u.longitude}`;
            if (!groups.has(key)) groups.set(key, []);
            groups.get(key).push(u);
        }

        const points = [];
        for (const [key, list] of groups) {
            const [lat, lng] = key.split('|').map(parseFloat);
            if (Number.isNaN(lat) || Number.isNaN(lng)) continue;
            points.push([lat, lng]);

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
                    .addTo(this.usersLayer);
            });
        }

        // On a filter change, frame the resulting crossings. Skip when there's nothing to
        // show or the markers are hidden (zooming to invisible points would be confusing).
        if (fit && this.usersVisible && !this.timeTravel && points.length > 0) {
            this.map.fitBounds(L.latLngBounds(points).pad(0.2), { maxZoom: FIT_MAX_ZOOM });
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
        this.usersLayer.removeFrom(this.map);
        this.linesLayer.removeFrom(this.map);
        this.timelineLayer.addTo(this.map);
        this.element.classList.add('time-travel-active');
        if (this.hasTtToggleTarget) this.ttToggleTarget.textContent = 'Návrat do dneška';

        // Reset playback state
        this.virtualTime = this.timelineStart;
        this.timelineLineMarkers = new Map();
        this.timelineCounters = { lines: 0, crossings: 0 };
        this.eventCursor = { line: 0, crossing: 0 };
        this.playing = false;
        this.lastFrameTime = 0;

        this.updateTimelineUI();
        this._publishMode();
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
        this._updateLinesVisibility();
        if (this.usersVisible) this.usersLayer.addTo(this.map);

        this.element.classList.remove('time-travel-active');
        if (this.hasTtToggleTarget) this.ttToggleTarget.textContent = 'Přehrát historii';
        this._publishMode();
    }

    async fetchTimelineData() {
        const response = await fetch(this.timelineUrlValue, { headers: { Accept: 'application/json' } });
        if (!response.ok) {
            console.error('Failed to load timeline data', response.status);
            return false;
        }
        const data = await response.json();
        this.timelineLines = data.lines;
        this.timelineCrossings = data.crossings;

        this.timelineLinesById = new Map();
        for (const h of this.timelineLines) {
            this.timelineLinesById.set(h.id, h);
        }

        const firstDate = this.timelineLines[0]?.appearanceDate;
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
        this.timelineLineMarkers = new Map();
        this.timelineCounters = { lines: 0, crossings: 0 };
        this.eventCursor = { line: 0, crossing: 0 };

        this.virtualTime = this.timelineStart;
        this.processEventsTo(target, true);
        this.virtualTime = target;

        this.updateTimelineUI();
        this._publishMode();
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
        this._publishMode();

        if (this.playing) {
            this.rafHandle = requestAnimationFrame((t) => this.tick(t));
        }
    }

    processEventsTo(targetTime, instant) {
        // Fire highlines first so subsequent crossings can anchor to them.
        while (this.eventCursor.line < this.timelineLines.length) {
            const h = this.timelineLines[this.eventCursor.line];
            if (new Date(h.appearanceDate).getTime() > targetTime) break;
            this.appearLine(h, instant);
            this.eventCursor.line++;
        }

        while (this.eventCursor.crossing < this.timelineCrossings.length) {
            const c = this.timelineCrossings[this.eventCursor.crossing];
            if (new Date(c.crossedAt).getTime() > targetTime) break;
            this.pulseCrossing(c, instant);
            this.eventCursor.crossing++;
        }
    }

    appearLine(h, instant) {
        this.timelineCounters.lines++;

        const lat = parseFloat(h.latitude);
        const lng = parseFloat(h.longitude);
        if (Number.isNaN(lat) || Number.isNaN(lng)) return;

        const marker = L.marker([lat, lng], { icon: lineIcon(h.type) }).bindPopup(this.popupHtml(h));
        marker.addTo(this.timelineLayer);
        this.timelineLineMarkers.set(h.id, marker);

        if (!instant) this.bloomAt(lat, lng);
    }

    bloomAt(lat, lng) {
        const icon = L.divIcon({
            className: 'line-bloom-icon',
            html: '<span class="line-bloom"></span>',
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

        const h = this.timelineLinesById.get(c.lineId);
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
            this.ttCounterTarget.textContent = `Highlines: ${c.lines} · Přechody: ${c.crossings}`;
        }
        if (this.hasTtPlayTarget) {
            this.ttPlayTarget.textContent = this.playing ? '⏸' : '▶';
            this.ttPlayTarget.setAttribute('aria-label', this.playing ? 'Pauza' : 'Přehrát');
        }
    }

    // ---------- Popups ----------

    popupHtml(h) {
        // "Nezařazeno" is the default/placeholder type on most lines — it carries no
        // info, so skip the type line entirely when that's all we have.
        const typeLabel = h.type === 'unsorted' ? '' : (TYPE_LABELS[h.type] ?? h.type);
        const place = [h.area, h.region].filter(Boolean).join(', ');
        const title = h.slug
            ? `<a href="/lajna/${encodeURIComponent(h.slug)}">${escapeHtml(h.name)}</a>`
            : `<strong>${escapeHtml(h.name)}</strong>`;
        return `
            <div class="line-popup">
                <strong>${title}</strong>
                ${typeLabel ? `<div>${escapeHtml(typeLabel)}</div>` : ''}
                <div>${h.length} m &middot; ${h.height} m vysoko</div>
                ${place ? `<div class="muted">${escapeHtml(place)}</div>` : ''}
            </div>
        `;
    }

    // Lines only make sense up close — show them past LINE_MIN_ZOOM, and never in
    // time-travel mode (the static layers are off the map then anyway).
    _updateLinesVisibility() {
        if (!this.map || !this.linesLayer) return;
        const show = !this.timeTravel && this.map.getZoom() >= LINE_MIN_ZOOM;
        if (show) {
            this.linesLayer.addTo(this.map);
        } else {
            this.linesLayer.removeFrom(this.map);
        }
    }

    _setUsersVisible(visible) {
        this.usersVisible = !!visible;
        // In time-travel mode users layer isn't on the map anyway — apply on next exit.
        if (this.timeTravel || !this.map) return;
        if (this.usersVisible) this.usersLayer.addTo(this.map);
        else this.usersLayer.removeFrom(this.map);
    }

    // ---------- Cross-controller bus ----------

    // The sidebar feed drives the crossing filter; mirror it onto the emoji markers.
    _handleFeedFilter(detail) {
        if (!detail || detail.mode === 'time-travel') return;
        const sig = detail.mode === 'range'
            ? `range:${detail.from}:${detail.to}`
            : `count:${detail.count}`;
        if (sig === this._feedSig) return;
        this._feedSig = sig;
        this.feedFilter = detail;
        // In time-travel the users layer is off the map; the sidebar re-broadcasts when
        // playback ends, so we reload then instead of now. Re-fit so the filtered crossings
        // are actually in view.
        if (!this.timeTravel) this.loadUsers({ fit: true });
    }

    _feedFilterUrl() {
        const params = new URLSearchParams();
        const f = this.feedFilter;
        if (f?.mode === 'range') {
            params.set('from', f.from);
            params.set('to', f.to);
        } else {
            params.set('limit', String(f?.count ?? 10));
        }
        const qs = params.toString();
        return qs ? `${this.usersUrlValue}?${qs}` : this.usersUrlValue;
    }

    // Tells the news-bar feed which window of crossings to show.
    _publishMode() {
        if (this.timeTravel) {
            const date = new Date(this.virtualTime).toISOString().slice(0, 10);
            if (date === this._lastEmittedDate) return;
            this._lastEmittedDate = date;
            document.dispatchEvent(new CustomEvent('slack:map-mode', {
                detail: { mode: 'time-travel', date, days: 7 },
            }));
        } else {
            this._lastEmittedDate = null;
            document.dispatchEvent(new CustomEvent('slack:map-mode', {
                detail: { mode: 'recent' },
            }));
        }
    }

    userPopupHtml(u) {
        const date = DATE_FORMATTER.format(new Date(u.crossedAt));
        const stars = u.rating
            ? '★'.repeat(u.rating) + '☆'.repeat(5 - u.rating)
            : '';
        const lineLink = u.lineSlug
            ? `<a href="/lajna/${encodeURIComponent(u.lineSlug)}">${escapeHtml(u.lineName)}</a>`
            : escapeHtml(u.lineName);
        const userLink = u.userId
            ? `<a href="/denik/${u.userId}">${escapeHtml(u.userDisplayName)}</a>`
            : escapeHtml(u.userDisplayName);
        return `
            <div class="user-popup">
                <strong>${userLink}</strong>
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

export function escapeHtml(value) {
    if (value == null) return '';
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}
