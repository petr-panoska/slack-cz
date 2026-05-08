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

        if (hasPolyline) {
            const p1 = [this.p1LatValue, this.p1LngValue];
            const p2 = [this.p2LatValue, this.p2LngValue];

            L.polyline([p1, p2], {
                color: '#e1005b',
                weight: 4,
                opacity: 0.9,
            }).addTo(this.map);

            this.endpointMarker(p1, 'Bod 1');
            this.endpointMarker(p2, 'Bod 2');

            this.map.fitBounds([p1, p2], { padding: [50, 50], maxZoom: 17 });
        } else {
            L.marker(center).bindPopup(escapeHtml(this.nameValue || '')).addTo(this.map);
        }
    }

    disconnect() {
        if (this.map) {
            this.map.remove();
            this.map = null;
        }
    }

    endpointMarker(coord, label) {
        L.circleMarker(coord, {
            color: '#e1005b',
            fillColor: '#fff',
            fillOpacity: 1,
            radius: 6,
            weight: 3,
        }).bindTooltip(label, { permanent: false, direction: 'top' }).addTo(this.map);
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
