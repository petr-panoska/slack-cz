import { Controller } from '@hotwired/stimulus';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

export default class extends Controller {
    static values = {
        lat: Number,
        lng: Number,
        name: String,
        p1Lat: Number,
        p1Lng: Number,
        p2Lat: Number,
        p2Lng: Number,
        parkingLat: Number,
        parkingLng: Number,
        iconUrl: String,
        iconRetinaUrl: String,
        shadowUrl: String,
    };

    static targets = ['canvas'];

    connect() {
        L.Icon.Default.mergeOptions({
            iconUrl: this.iconUrlValue,
            iconRetinaUrl: this.iconRetinaUrlValue,
            shadowUrl: this.shadowUrlValue,
        });

        const center = [this.latValue, this.lngValue];
        this.map = L.map(this.canvasTarget).setView(center, 15);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        }).addTo(this.map);

        const hasPolyline =
            this.hasP1LatValue && this.hasP1LngValue &&
            this.hasP2LatValue && this.hasP2LngValue;

        const bounds = [];

        if (hasPolyline) {
            const p1 = [this.p1LatValue, this.p1LngValue];
            const p2 = [this.p2LatValue, this.p2LngValue];

            L.polyline([p1, p2], {
                color: '#e1005b',
                weight: 4,
                opacity: 0.9,
            }).addTo(this.map);

            this.endpointMarker(p1, 1);
            this.endpointMarker(p2, 2);

            bounds.push(p1, p2);
        } else {
            L.marker(center).bindPopup(escapeHtml(this.nameValue || '')).addTo(this.map);
            bounds.push(center);
        }

        if (this.hasParkingLatValue && this.hasParkingLngValue) {
            const parking = [this.parkingLatValue, this.parkingLngValue];
            this.parkingMarker(parking);
            bounds.push(parking);
        }

        if (bounds.length > 1) {
            this.map.fitBounds(bounds, { padding: [50, 50], maxZoom: 17 });
        }
    }

    disconnect() {
        if (this.map) {
            this.map.remove();
            this.map = null;
        }
    }

    endpointMarker(coord, number) {
        const icon = L.divIcon({
            className: 'hl-point-marker',
            html: `<span>${number}</span>`,
            iconSize: [28, 28],
            iconAnchor: [14, 14],
        });
        L.marker(coord, { icon, title: `Bod ${number}` }).addTo(this.map);
    }

    parkingMarker(coord) {
        const icon = L.divIcon({
            className: 'hl-parking-marker',
            html: '<span class="hl-parking-marker-glyph">P</span>',
            iconSize: [26, 26],
            iconAnchor: [13, 13],
        });
        L.marker(coord, { icon }).bindTooltip('Parkování', { direction: 'top' }).addTo(this.map);
    }
}

function escapeHtml(value) {
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}
