import { Controller } from '@hotwired/stimulus';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { emojiForUser } from '../user_emoji.js';
import { addBasemapPicker } from '../basemap.js';
import { addFullscreenToggle } from '../map_fullscreen.js';
import { enableCtrlScrollZoom } from '../map_scroll_zoom.js';

// Homepage hero map. Unlike the full /mapa (which shows every highline at once),
// this shows ONE crossing at a time: the sidebar list drives which one is active.
// On select we draw the real line (polyline between the two anchor points), zoom
// to it, and animate the crossing icon walking the final stretch of the line.
//
// Markup contract (templates/pages/index.html.twig):
//   data-controller="hp-map"  + canvas target
//   each sidebar <li> = data-hp-map-target="item" with geometry data-* attrs:
//     data-lat/lng (highline midpoint, fallback), data-p1-lat/lng, data-p2-lat/lng,
//     data-user-id (picks the walker emoji)

const CZECH_CENTER = [49.8, 15.5];

// The walker covers the final WALK_FRACTION of the line ("posledních pár metrů").
// Tunable; later we may swap to a per-crossing-style behaviour (run/crawl/etc.).
const WALK_FRACTION = 0.4;
const WALK_DURATION = 5000; // ms — deliberately slow, the athlete strolls the line
const FLY_DURATION = 1.6;   // s — smooth glide between crossings (no jump/reload)
const DWELL = 7000;         // ms — pause after a crossing finishes before auto-advancing

// Celebration played once the athlete reaches the far anchor — one picked at random.
const CELEBRATIONS = ['bounce', 'flip', 'spin', 'pop', 'wobble'];

export default class extends Controller {
    static values = {
        iconUrl: String,
        iconRetinaUrl: String,
        shadowUrl: String,
    };

    static targets = ['canvas', 'item'];

    connect() {
        L.Icon.Default.mergeOptions({
            iconUrl: this.iconUrlValue,
            iconRetinaUrl: this.iconRetinaUrlValue,
            shadowUrl: this.shadowUrlValue,
        });

        // Start already near the first crossing — if we began at country level and then
        // flew/zoomed to the line, the big z7→z17 zoom would briefly balloon the layers
        // and blur the tiles (an ugly fly-through). So seed the view at the first item.
        let center = CZECH_CENTER;
        let zoom = 7;
        const first = this.itemTargets[0];
        if (first) {
            const lat = parseFloat(first.dataset.lat);
            const lng = parseFloat(first.dataset.lng);
            if (!Number.isNaN(lat) && !Number.isNaN(lng)) {
                center = [lat, lng];
                zoom = 14;
            }
        }

        // scrollWheelZoom off so scrolling the homepage doesn't get hijacked by the map.
        this.map = L.map(this.canvasTarget, { zoomControl: false, scrollWheelZoom: false })
            .setView(center, zoom);
        L.control.zoom({ position: 'bottomright' }).addTo(this.map);
        addBasemapPicker(this.map, { ortho: true });
        addFullscreenToggle(this.map);
        // Plain wheel scrolls the homepage; Ctrl/⌘ + wheel zooms the map.
        enableCtrlScrollZoom(this.map);

        this.activeLayer = L.layerGroup().addTo(this.map);
        this.rafHandle = null;
        // Generation counter: each activate() bumps it so a fly-completion handler
        // from a previous selection never starts a stale walk.
        this.gen = 0;
        this.walkStartedGen = -1;
        this.lastGeoKey = null;
        this.activeIndex = 0;
        this.advanceTimer = null;

        // Map is created inside a flex panel that may not have its final size yet.
        requestAnimationFrame(() => {
            this.map?.invalidateSize();
            // First activation is instant (no fly) — we're already framed on it.
            if (this.itemTargets.length > 0) this.activate(0, { animate: false });
        });
    }

    disconnect() {
        if (this.rafHandle) cancelAnimationFrame(this.rafHandle);
        if (this.advanceTimer) clearTimeout(this.advanceTimer);
        if (this.map) {
            this.map.remove();
            this.map = null;
        }
    }

    select(event) {
        // Let clicks on the highline link navigate normally instead of just activating.
        if (event.target.closest('a')) return;
        const idx = this.itemTargets.indexOf(event.currentTarget);
        if (idx >= 0) this.activate(idx);
    }

    activate(index, { animate = true } = {}) {
        const item = this.itemTargets[index];
        if (!item) return;

        this.itemTargets.forEach((el, i) => el.classList.toggle('is-active', i === index));
        item.scrollIntoView({ block: 'nearest' });
        this.activeIndex = index;

        if (this.rafHandle) {
            cancelAnimationFrame(this.rafHandle);
            this.rafHandle = null;
        }
        if (this.advanceTimer) {
            clearTimeout(this.advanceTimer);
            this.advanceTimer = null;
        }
        const gen = ++this.gen;
        this.activeLayer.clearLayers();

        const d = item.dataset;
        const p1 = coord(d.p1Lat, d.p1Lng);
        const p2 = coord(d.p2Lat, d.p2Lng);
        const mid = coord(d.lat, d.lng);
        const emoji = emojiForUser(parseInt(d.userId, 10) || 0);
        const comment = d.comment || '';

        // Consecutive crossings on the same line share identical geometry. Flying to
        // the same bounds makes the map shiver for nothing — detect it and skip the fly.
        const geoKey = `${d.p1Lat}|${d.p1Lng}|${d.p2Lat}|${d.p2Lng}|${d.lat}|${d.lng}`;
        const sameView = geoKey === this.lastGeoKey;
        this.lastGeoKey = geoKey;

        if (p1 && p2) {
            L.polyline([p1, p2], { color: '#e1005b', weight: 4, opacity: 0.9 }).addTo(this.activeLayer);
            this.endpoint(p1);
            this.endpoint(p2);
            const startWalk = () => this.walk(p1, p2, emoji, comment, gen);
            if (animate && !sameView) {
                // flyToBounds glides (pan + zoom) instead of snapping; walk starts once it settles.
                this.map.flyToBounds([p1, p2], { padding: [60, 60], maxZoom: 17, duration: FLY_DURATION });
                this.afterFly(gen, startWalk);
            } else {
                // Instant (initial load) or same line — frame without a fly, walk right away.
                if (!sameView) this.map.fitBounds([p1, p2], { padding: [60, 60], maxZoom: 17, animate: false });
                startWalk();
            }
        } else if (mid) {
            // No line geometry → no walk; show the comment on the static marker.
            const marker = L.marker(mid, { icon: emojiIcon(emoji) }).addTo(this.activeLayer);
            this.showThought(marker, comment);
            if (animate && !sameView) {
                this.map.flyTo(mid, 15, { duration: FLY_DURATION });
                this.afterFly(gen, () => this.scheduleAdvance(gen));
            } else {
                if (!sameView) this.map.setView(mid, 15, { animate: false });
                this.scheduleAdvance(gen);
            }
        }
    }

    // Runs cb once the fly animation settles (or via a fallback timer if the view
    // didn't change so moveend never fires). Stale generations are ignored.
    afterFly(gen, cb) {
        const run = () => { if (gen === this.gen) cb(); };
        this.map.once('moveend', run);
        setTimeout(run, FLY_DURATION * 1000 + 350);
    }

    // Walks the emoji from a point WALK_FRACTION before the end toward the far anchor.
    walk(p1, p2, emoji, comment, gen) {
        if (gen !== this.gen || this.walkStartedGen === gen) return;
        this.walkStartedGen = gen;

        const start = lerp(p1, p2, 1 - WALK_FRACTION);
        const walker = L.marker(start, { icon: emojiIcon(emoji, comment), interactive: false, zIndexOffset: 1000 })
            .addTo(this.activeLayer);

        const t0 = performance.now();
        const step = (now) => {
            if (gen !== this.gen) return;
            const t = Math.min(1, (now - t0) / WALK_DURATION);
            walker.setLatLng(lerp(start, p2, easeInOut(t)));
            if (t < 1) {
                this.rafHandle = requestAnimationFrame(step);
            } else {
                this.rafHandle = null;
                // Reached the far anchor: flourish + reveal the comment (not during the walk).
                this.celebrate(walker);
                this.showThought(walker, comment);
                this.scheduleAdvance(gen);
            }
        };
        this.rafHandle = requestAnimationFrame(step);
    }

    // Made it across — play a random little flourish on the emoji.
    celebrate(walker) {
        const el = walker.getElement()?.querySelector('.user-emoji');
        if (!el) return;
        const name = CELEBRATIONS[Math.floor(Math.random() * CELEBRATIONS.length)];
        const cls = `hp-cel-${name}`;
        el.classList.add(cls);
        el.addEventListener('animationend', () => el.classList.remove(cls), { once: true });
    }

    // Auto-advance to the next crossing after a dwell, so the homepage runs itself
    // as a showcase. Cleared on any (re)activation, so a manual click just takes over.
    scheduleAdvance(gen) {
        if (this.itemTargets.length < 2) return;
        if (this.advanceTimer) clearTimeout(this.advanceTimer);
        this.advanceTimer = setTimeout(() => {
            if (gen !== this.gen) return;
            this.activate((this.activeIndex + 1) % this.itemTargets.length);
        }, DWELL);
    }

    // Floats a subtle thought bubble above the marker (only once the crossing is done).
    showThought(marker, comment) {
        if (!comment) return;
        requestAnimationFrame(() => {
            const el = marker.getElement();
            if (!el || el.querySelector('.hp-thought')) return;
            const bubble = document.createElement('span');
            bubble.className = 'hp-thought';
            bubble.textContent = comment; // textContent escapes — no manual sanitising needed
            el.appendChild(bubble);
        });
    }

    endpoint(c) {
        L.circleMarker(c, {
            color: '#e1005b',
            fillColor: '#fff',
            fillOpacity: 1,
            radius: 5,
            weight: 3,
        }).addTo(this.activeLayer);
    }
}

// Same look as the athlete markers on /mapa (map_controller.js loadUsers).
function emojiIcon(emoji) {
    return L.divIcon({
        className: 'user-circle-icon',
        html: `<span class="user-emoji">${emoji}</span>`,
        iconSize: [32, 32],
        iconAnchor: [16, 16],
    });
}

function coord(lat, lng) {
    const a = parseFloat(lat);
    const b = parseFloat(lng);
    return Number.isNaN(a) || Number.isNaN(b) ? null : [a, b];
}

function lerp(a, b, t) {
    return [a[0] + (b[0] - a[0]) * t, a[1] + (b[1] - a[1]) * t];
}

function easeInOut(t) {
    return t < 0.5 ? 2 * t * t : 1 - Math.pow(-2 * t + 2, 2) / 2;
}
