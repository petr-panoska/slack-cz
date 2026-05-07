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

const CZECH_CENTER = [49.8, 15.5];

export default class extends Controller {
    static values = {
        dataUrl: String,
        iconUrl: String,
        iconRetinaUrl: String,
        shadowUrl: String,
    };

    async connect() {
        L.Icon.Default.mergeOptions({
            iconUrl: this.iconUrlValue,
            iconRetinaUrl: this.iconRetinaUrlValue,
            shadowUrl: this.shadowUrlValue,
        });

        this.map = L.map(this.element).setView(CZECH_CENTER, 7);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        }).addTo(this.map);

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
            marker.addTo(this.map);
            markers.push(marker);
        }

        if (markers.length > 0) {
            const group = L.featureGroup(markers);
            this.map.fitBounds(group.getBounds().pad(0.1));
        }
    }

    disconnect() {
        if (this.map) {
            this.map.remove();
            this.map = null;
        }
    }

    popupHtml(h) {
        const typeLabel = TYPE_LABELS[h.type] ?? h.type;
        const place = [h.area, h.region].filter(Boolean).join(', ');
        return `
            <div class="highline-popup">
                <strong>${escapeHtml(h.name)}</strong>
                <div>${escapeHtml(typeLabel)}</div>
                <div>${h.length} m &middot; ${h.height} m vysoko</div>
                ${place ? `<div class="muted">${escapeHtml(place)}</div>` : ''}
            </div>
        `;
    }
}

function escapeHtml(value) {
    if (value == null) return '';
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}
