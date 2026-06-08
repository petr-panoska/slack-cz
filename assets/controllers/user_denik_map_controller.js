import { Controller } from '@hotwired/stimulus';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { addBasemapToggle } from '../basemap.js';
import { addFullscreenToggle } from '../map_fullscreen.js';

export default class extends Controller {
    static values = {
        highlines: Array,
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

        const points = (this.highlinesValue || [])
            .map(h => [parseFloat(h.latitude), parseFloat(h.longitude), h])
            .filter(([lat, lng]) => Number.isFinite(lat) && Number.isFinite(lng));

        this.map = L.map(this.canvasTarget, {
            scrollWheelZoom: false,
        }).setView([49.8, 15.5], 7);

        addBasemapToggle(this.map);
        addFullscreenToggle(this.map);

        if (points.length === 0) return;

        for (const [lat, lng, h] of points) {
            const marker = L.circleMarker([lat, lng], {
                color: '#e1005b',
                fillColor: '#fff',
                fillOpacity: 1,
                radius: 7,
                weight: 3,
            }).addTo(this.map);

            const label = h.crossings > 1 ? ` (${h.crossings}×)` : '';
            const safeName = escapeHtml(h.name);
            marker.bindPopup(
                `<a href="/highline/${encodeURIComponent(h.slug)}" class="denik-map-popup">${safeName}</a>${label}`,
            );
        }

        if (points.length === 1) {
            this.map.setView([points[0][0], points[0][1]], 13);
        } else {
            const bounds = L.latLngBounds(points.map(([lat, lng]) => [lat, lng]));
            this.map.fitBounds(bounds, { padding: [30, 30], maxZoom: 13 });
        }
    }

    disconnect() {
        if (this.map) {
            this.map.remove();
            this.map = null;
        }
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
