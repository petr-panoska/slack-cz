import L from 'leaflet';
import MapController, { escapeHtml } from './map_controller.js';

const DATE_FORMATTER = new Intl.DateTimeFormat('cs-CZ', {
    day: 'numeric',
    month: 'numeric',
    year: 'numeric',
});

export default class extends MapController {
    static values = {
        ...MapController.values,
        crossingsUrl: String,
    };

    async afterMapReady() {
        if (!this.hasCrossingsUrlValue) return;

        const response = await fetch(this.crossingsUrlValue, { headers: { Accept: 'application/json' } });
        if (!response.ok) {
            console.error('Failed to load recent crossings', response.status);
            return;
        }

        const crossings = await response.json();
        const pulseIcon = L.divIcon({
            className: 'crossing-pulse',
            html: '<span class="crossing-pulse-dot"></span><span class="crossing-pulse-ring"></span>',
            iconSize: [22, 22],
            iconAnchor: [11, 11],
        });

        for (const c of crossings) {
            const lat = parseFloat(c.latitude);
            const lng = parseFloat(c.longitude);
            if (Number.isNaN(lat) || Number.isNaN(lng)) continue;

            L.marker([lat, lng], { icon: pulseIcon, zIndexOffset: 1000 })
                .bindPopup(this.crossingPopupHtml(c))
                .addTo(this.map);
        }
    }

    crossingPopupHtml(c) {
        const date = DATE_FORMATTER.format(new Date(c.crossedAt));
        const stars = c.rating
            ? '★'.repeat(c.rating) + '☆'.repeat(5 - c.rating)
            : '';
        return `
            <div class="crossing-popup">
                <strong>${escapeHtml(c.userDisplayName)}</strong>
                <div class="crossing-popup-line">${escapeHtml(c.highlineName)}</div>
                <div class="muted">${escapeHtml(date)}${stars ? ` &middot; <span class="crossing-popup-stars">${stars}</span>` : ''}</div>
            </div>
        `;
    }
}
