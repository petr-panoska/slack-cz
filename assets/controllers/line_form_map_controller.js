import { Controller } from '@hotwired/stimulus';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { addBasemapPicker } from '../basemap.js';
import { enableCtrlScrollZoom } from '../map_scroll_zoom.js';

/**
 * Two-endpoint + parking GPS picker for the highline form.
 *
 * Editing is button-driven (same pattern as parking): "Přidat bod 1/2" arms a mode,
 * the next map click drops that marker, and the button flips to "Smazat …". Markers
 * stay draggable and the four lat/lng inputs stay two-way bound. The shared basemap
 * picker (Mapa / Ortofoto / Hybrid) is added as a map control.
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
        parkingLatInput: String,
        parkingLngInput: String,
        initLat: Number,
        initLng: Number,
        lengthOutput: String,
    };

    static targets = ['canvas', 'distance', 'p1Toggle', 'p2Toggle', 'parkingToggle', 'fullscreenToggle'];

    connect() {
        this.inputs = {
            p1Lat: document.getElementById(this.p1LatInputValue),
            p1Lng: document.getElementById(this.p1LngInputValue),
            p2Lat: document.getElementById(this.p2LatInputValue),
            p2Lng: document.getElementById(this.p2LngInputValue),
            parkingLat: this.hasParkingLatInputValue ? document.getElementById(this.parkingLatInputValue) : null,
            parkingLng: this.hasParkingLngInputValue ? document.getElementById(this.parkingLngInputValue) : null,
        };
        // Read-only length readout lives in a form-section below the map, outside this
        // controller's own element — same by-ID wiring as the lat/lng inputs above.
        this.lengthOutputEl = this.hasLengthOutputValue ? document.getElementById(this.lengthOutputValue) : null;

        this.points = [readPair(this.inputs.p1Lat, this.inputs.p1Lng), readPair(this.inputs.p2Lat, this.inputs.p2Lng)];
        this.parking = readPair(this.inputs.parkingLat, this.inputs.parkingLng);

        this.map = L.map(this.canvasTarget);
        this.fitToKnownPoints();
        // top-left (under the zoom control); the top-right corner holds the point buttons.
        addBasemapPicker(this.map, { position: 'topleft' });
        // Plain wheel scrolls the form page; Ctrl/⌘ + wheel zooms the map.
        enableCtrlScrollZoom(this.map);

        this.markers = [null, null];
        this.parkingMarkerRef = null;
        this.polyline = null;
        this.activeMode = null;

        if (this.points[0]) this.placeMarker(0, this.points[0], false);
        if (this.points[1]) this.placeMarker(1, this.points[1], false);
        if (this.parking) this.placeParkingMarker(this.parking, false);
        this.refreshLine();
        this.refreshToggles();

        this.map.on('click', (event) => {
            if (!this.activeMode) return;
            const { lat, lng } = event.latlng;
            if (this.activeMode === 'parking') {
                this.placeParkingMarker([lat, lng], true);
            } else {
                this.placeMarker(this.activeMode === 'p1' ? 0 : 1, [lat, lng], true);
            }
            this.activeMode = null;
            this.refreshToggles();
        });

        this.inputListeners = [];
        Object.entries(this.inputs).forEach(([, el]) => {
            if (!el) return;
            const handler = () => this.syncFromInputs();
            el.addEventListener('input', handler);
            this.inputListeners.push([el, handler]);
        });

        // Leaflet must recompute its size after the container resizes (in/out of fullscreen).
        // The 200ms delay waits out the fullscreen transition so the container has its final
        // size before we measure/fit against it.
        this.fullscreenHandler = () => {
            if (this.map) {
                this.map.invalidateSize();
                setTimeout(() => {
                    if (!this.map) return;
                    this.map.invalidateSize();
                    // Leaving fullscreen: the view was framed for the fullscreen aspect ratio —
                    // re-fit to the small inline canvas so every placed point stays visible.
                    if (!document.fullscreenElement && !document.webkitFullscreenElement) {
                        this.fitToKnownPoints();
                    }
                }, 200);
            }
            this.refreshFullscreenToggle();
        };
        document.addEventListener('fullscreenchange', this.fullscreenHandler);
        this.refreshFullscreenToggle();
    }

    disconnect() {
        if (this.inputListeners) {
            this.inputListeners.forEach(([el, handler]) => el.removeEventListener('input', handler));
        }
        if (this.fullscreenHandler) {
            document.removeEventListener('fullscreenchange', this.fullscreenHandler);
        }
        if (this.map) {
            this.map.remove();
            this.map = null;
        }
    }

    // Frames the view around whatever is already placed (both line anchors AND parking,
    // if set) — not just p1/p2 — so nothing ends up off-screen. Used on initial load and
    // again when leaving fullscreen (see fullscreenHandler).
    fitToKnownPoints() {
        const known = [this.points[0], this.points[1], this.parking].filter(Boolean);
        if (known.length >= 2) {
            this.map.fitBounds(known, { padding: [40, 40], maxZoom: 17 });
        } else if (known.length === 1) {
            this.map.setView(known[0], 15);
        } else {
            this.map.setView([this.initLatValue, this.initLngValue], 7);
        }
    }

    toggleFullscreen(event) {
        event.preventDefault();
        if (document.fullscreenElement) {
            document.exitFullscreen();
        } else if (this.element.requestFullscreen) {
            this.element.requestFullscreen();
        }
    }

    refreshFullscreenToggle() {
        if (!this.hasFullscreenToggleTarget) return;
        const active = document.fullscreenElement === this.element;
        this.fullscreenToggleTarget.textContent = active ? '✕ Zavřít' : '⛶ Celá obrazovka';
        this.fullscreenToggleTarget.classList.toggle('is-active', active);
    }

    /** Arm / disarm an editing mode, or delete an already-placed marker. */
    toggle(event) {
        event.preventDefault();
        const mode = event.params.mode;
        const isSet = mode === 'parking' ? !!this.parking : !!this.points[mode === 'p1' ? 0 : 1];
        if (isSet) {
            this.removeMarker(mode);
            this.activeMode = null;
        } else {
            this.activeMode = this.activeMode === mode ? null : mode;
        }
        this.refreshToggles();
    }

    removeMarker(mode) {
        if (mode === 'parking') {
            if (this.parkingMarkerRef) {
                this.map.removeLayer(this.parkingMarkerRef);
                this.parkingMarkerRef = null;
            }
            this.parking = null;
            if (this.inputs.parkingLat) this.inputs.parkingLat.value = '';
            if (this.inputs.parkingLng) this.inputs.parkingLng.value = '';
            return;
        }
        const slot = mode === 'p1' ? 0 : 1;
        if (this.markers[slot]) {
            this.map.removeLayer(this.markers[slot]);
            this.markers[slot] = null;
        }
        this.points[slot] = null;
        const latInput = slot === 0 ? this.inputs.p1Lat : this.inputs.p2Lat;
        const lngInput = slot === 0 ? this.inputs.p1Lng : this.inputs.p2Lng;
        if (latInput) latInput.value = '';
        if (lngInput) lngInput.value = '';
        this.refreshLine();
    }

    placeMarker(slot, coord, writeBack) {
        if (!this.markers[slot]) {
            const icon = L.divIcon({
                className: 'hl-point-marker',
                html: `<span>${slot === 0 ? 1 : 2}</span>`,
                iconSize: [28, 28],
                iconAnchor: [14, 14],
            });
            const marker = L.marker(coord, { icon, draggable: true, title: slot === 0 ? 'Bod 1' : 'Bod 2' }).addTo(this.map);
            // Live redraw while dragging, not just on drop — polyline + length track the
            // marker in real time. The (change-triggering) form inputs only need the final
            // position, so writeInputs stays on dragend.
            marker.on('drag', () => {
                const { lat, lng } = marker.getLatLng();
                this.points[slot] = [lat, lng];
                this.refreshLine();
            });
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

    placeParkingMarker(coord, writeBack) {
        if (!this.parkingMarkerRef) {
            const icon = L.divIcon({
                className: 'hl-parking-marker',
                html: '<span class="hl-parking-marker-glyph">P</span>',
                iconSize: [26, 26],
                iconAnchor: [13, 13],
            });
            const marker = L.marker(coord, { icon, draggable: true, title: 'Parkování' }).addTo(this.map);
            marker.on('dragend', () => {
                const { lat, lng } = marker.getLatLng();
                this.parking = [lat, lng];
                this.writeParkingInputs(lat, lng);
            });
            this.parkingMarkerRef = marker;
        } else {
            this.parkingMarkerRef.setLatLng(coord);
        }
        this.parking = coord;
        if (writeBack) this.writeParkingInputs(coord[0], coord[1]);
    }

    writeInputs(slot, lat, lng) {
        const latInput = slot === 0 ? this.inputs.p1Lat : this.inputs.p2Lat;
        const lngInput = slot === 0 ? this.inputs.p1Lng : this.inputs.p2Lng;
        latInput.value = round(lat);
        lngInput.value = round(lng);
        latInput.dispatchEvent(new Event('change', { bubbles: true }));
        lngInput.dispatchEvent(new Event('change', { bubbles: true }));
    }

    writeParkingInputs(lat, lng) {
        if (!this.inputs.parkingLat || !this.inputs.parkingLng) return;
        this.inputs.parkingLat.value = round(lat);
        this.inputs.parkingLng.value = round(lng);
        this.inputs.parkingLat.dispatchEvent(new Event('change', { bubbles: true }));
        this.inputs.parkingLng.dispatchEvent(new Event('change', { bubbles: true }));
    }

    syncFromInputs() {
        const p1 = readPair(this.inputs.p1Lat, this.inputs.p1Lng);
        const p2 = readPair(this.inputs.p2Lat, this.inputs.p2Lng);
        if (p1) this.placeMarker(0, p1, false);
        if (p2) this.placeMarker(1, p2, false);
        const parking = readPair(this.inputs.parkingLat, this.inputs.parkingLng);
        if (parking) {
            this.placeParkingMarker(parking, false);
        } else if (this.parkingMarkerRef) {
            this.map.removeLayer(this.parkingMarkerRef);
            this.parkingMarkerRef = null;
            this.parking = null;
        }
        this.refreshLine();
        this.refreshToggles();
    }

    refreshToggles() {
        this.refreshToggle(this.hasP1ToggleTarget ? this.p1ToggleTarget : null, 'p1', this.points[0], 'bod 1');
        this.refreshToggle(this.hasP2ToggleTarget ? this.p2ToggleTarget : null, 'p2', this.points[1], 'bod 2');
        this.refreshToggle(this.hasParkingToggleTarget ? this.parkingToggleTarget : null, 'parking', this.parking, 'parkování');
    }

    refreshToggle(btn, mode, value, label) {
        if (!btn) return;
        if (value) {
            btn.textContent = `Smazat ${label}`;
            btn.classList.add('is-active');
        } else if (this.activeMode === mode) {
            btn.textContent = 'Klikni do mapy…';
            btn.classList.add('is-active');
        } else {
            btn.textContent = `Přidat ${label}`;
            btn.classList.remove('is-active');
        }
    }

    refreshLine() {
        const [a, b] = this.points;
        if (a && b) {
            // setLatLngs on the existing layer (not remove+recreate) — this now also runs on
            // every 'drag' tick, not just discrete clicks/drops.
            if (this.polyline) {
                this.polyline.setLatLngs([a, b]);
            } else {
                this.polyline = L.polyline([a, b], { color: '#e1005b', weight: 4, opacity: 0.9 }).addTo(this.map);
            }
            const meters = haversineMeters(a[0], a[1], b[0], b[1]);
            const label = `${Math.round(meters)} m`;
            if (this.hasDistanceTarget) {
                this.distanceTarget.textContent = label;
                this.distanceTarget.classList.remove('is-empty');
            }
            if (this.lengthOutputEl) this.lengthOutputEl.textContent = label;
        } else {
            if (this.polyline) {
                this.map.removeLayer(this.polyline);
                this.polyline = null;
            }
            if (this.hasDistanceTarget) {
                this.distanceTarget.textContent = 'Nastav oba body';
                this.distanceTarget.classList.add('is-empty');
            }
            if (this.lengthOutputEl) this.lengthOutputEl.textContent = 'Nastav oba body';
        }
    }
}

function readPair(latInput, lngInput) {
    if (!latInput || !lngInput) return null;
    const lat = parseFloat(latInput.value);
    const lng = parseFloat(lngInput.value);
    return Number.isFinite(lat) && Number.isFinite(lng) ? [lat, lng] : null;
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
