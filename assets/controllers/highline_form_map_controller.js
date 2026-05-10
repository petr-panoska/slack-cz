import { Controller } from '@hotwired/stimulus';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

/**
 * Two-endpoint GPS picker for the highline form.
 *
 * Behaviour:
 * - Click on map cycles between bod 1 / bod 2 (alternating). First click sets bod 1,
 *   second sets bod 2, third resets bod 1, …
 * - Both markers are draggable.
 * - Polyline is drawn between the two endpoints whenever both are set.
 * - Live haversine length is rendered into the data-target="distance" element.
 * - Edits to any of the four input fields reposition the corresponding marker.
 *
 * Wires by ID, not by Stimulus targets — the inputs are rendered by Symfony Form and
 * live outside the controller's element scope.
 */
export default class extends Controller {
    static values = {
        p1LatInput: String,
        p1LngInput: String,
        p2LatInput: String,
        p2LngInput: String,
        initLat: Number,
        initLng: Number,
        iconUrl: String,
        iconRetinaUrl: String,
        shadowUrl: String,
    };

    static targets = ['canvas', 'distance'];

    connect() {
        L.Icon.Default.mergeOptions({
            iconUrl: this.iconUrlValue,
            iconRetinaUrl: this.iconRetinaUrlValue,
            shadowUrl: this.shadowUrlValue,
        });

        this.inputs = {
            p1Lat: document.getElementById(this.p1LatInputValue),
            p1Lng: document.getElementById(this.p1LngInputValue),
            p2Lat: document.getElementById(this.p2LatInputValue),
            p2Lng: document.getElementById(this.p2LngInputValue),
        };

        this.points = [readPair(this.inputs.p1Lat, this.inputs.p1Lng), readPair(this.inputs.p2Lat, this.inputs.p2Lng)];

        const center = computeCenter(this.points, [this.initLatValue, this.initLngValue]);
        const zoom = (this.points[0] || this.points[1]) ? 15 : 7;

        this.map = L.map(this.canvasTarget).setView(center, zoom);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        }).addTo(this.map);

        this.markers = [null, null];
        this.polyline = null;
        this.nextSlot = this.points[0] && !this.points[1] ? 1 : 0;

        if (this.points[0]) this.placeMarker(0, this.points[0], false);
        if (this.points[1]) this.placeMarker(1, this.points[1], false);
        this.refreshLine();

        this.map.on('click', (event) => {
            const { lat, lng } = event.latlng;
            const slot = this.nextSlot;
            this.placeMarker(slot, [lat, lng], true);
            this.nextSlot = slot === 0 ? 1 : 0;
        });

        this.inputListeners = [];
        Object.entries(this.inputs).forEach(([key, el]) => {
            const handler = () => this.syncFromInputs();
            el.addEventListener('input', handler);
            this.inputListeners.push([el, handler]);
        });
    }

    disconnect() {
        if (this.inputListeners) {
            this.inputListeners.forEach(([el, handler]) => el.removeEventListener('input', handler));
        }
        if (this.map) {
            this.map.remove();
            this.map = null;
        }
    }

    placeMarker(slot, coord, writeBack) {
        if (!this.markers[slot]) {
            const marker = L.marker(coord, {
                draggable: true,
                title: slot === 0 ? 'Bod 1' : 'Bod 2',
            }).addTo(this.map);
            marker.bindTooltip(slot === 0 ? '1' : '2', { permanent: true, direction: 'top', offset: [0, -36] });
            marker.on('dragend', () => {
                const { lat, lng } = marker.getLatLng();
                this.points[slot] = [lat, lng];
                this.writeInputs(slot, lat, lng);
                this.refreshLine();
            });
            this.markers[slot] = marker;
        } else {
            this.markers[slot].setLatLng(coord);
        }
        this.points[slot] = coord;
        if (writeBack) {
            this.writeInputs(slot, coord[0], coord[1]);
        }
        this.refreshLine();
    }

    writeInputs(slot, lat, lng) {
        const latInput = slot === 0 ? this.inputs.p1Lat : this.inputs.p2Lat;
        const lngInput = slot === 0 ? this.inputs.p1Lng : this.inputs.p2Lng;
        latInput.value = round(lat);
        lngInput.value = round(lng);
        latInput.dispatchEvent(new Event('change', { bubbles: true }));
        lngInput.dispatchEvent(new Event('change', { bubbles: true }));
    }

    syncFromInputs() {
        const p1 = readPair(this.inputs.p1Lat, this.inputs.p1Lng);
        const p2 = readPair(this.inputs.p2Lat, this.inputs.p2Lng);
        if (p1) this.placeMarker(0, p1, false);
        if (p2) this.placeMarker(1, p2, false);
        this.refreshLine();
    }

    refreshLine() {
        const [a, b] = this.points;
        if (this.polyline) {
            this.map.removeLayer(this.polyline);
            this.polyline = null;
        }
        if (a && b) {
            this.polyline = L.polyline([a, b], { color: '#e1005b', weight: 4, opacity: 0.9 }).addTo(this.map);
            const meters = haversineMeters(a[0], a[1], b[0], b[1]);
            if (this.hasDistanceTarget) {
                this.distanceTarget.textContent = `${Math.round(meters)} m`;
                this.distanceTarget.classList.remove('is-empty');
            }
        } else if (this.hasDistanceTarget) {
            this.distanceTarget.textContent = 'Nastav oba body';
            this.distanceTarget.classList.add('is-empty');
        }
    }
}

function readPair(latInput, lngInput) {
    const lat = parseFloat(latInput.value);
    const lng = parseFloat(lngInput.value);
    return Number.isFinite(lat) && Number.isFinite(lng) ? [lat, lng] : null;
}

function computeCenter(points, fallback) {
    if (points[0] && points[1]) {
        return [(points[0][0] + points[1][0]) / 2, (points[0][1] + points[1][1]) / 2];
    }
    return points[0] || points[1] || fallback;
}

function haversineMeters(lat1, lng1, lat2, lng2) {
    const R = 6_371_000;
    const toRad = (deg) => (deg * Math.PI) / 180;
    const dPhi = toRad(lat2 - lat1);
    const dLambda = toRad(lng2 - lng1);
    const a = Math.sin(dPhi / 2) ** 2 + Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLambda / 2) ** 2;
    return 2 * R * Math.asin(Math.min(1, Math.sqrt(a)));
}

function round(n) {
    return Number(n).toFixed(7);
}
