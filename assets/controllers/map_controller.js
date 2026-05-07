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

// Curated, widely-supported emoji set used as deterministic per-user "avatar".
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

export default class extends Controller {
    static values = {
        dataUrl: String,
        usersUrl: String,
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

        await this.loadHighlines();
        await this.loadUsers();
    }

    async loadHighlines() {
        const response = await fetch(this.dataUrlValue, { headers: { Accept: 'application/json' } });
        if (!response.ok) {
            console.error('Failed to load highlines', response.status);
            return;
        }

        const highlines = await response.json();
        this.markers = [];
        this.markersByHighlineId = new Map();
        for (const h of highlines) {
            const lat = parseFloat(h.latitude);
            const lng = parseFloat(h.longitude);
            if (Number.isNaN(lat) || Number.isNaN(lng)) continue;

            const marker = L.marker([lat, lng]).bindPopup(this.popupHtml(h));
            marker.addTo(this.map);
            this.markers.push(marker);
            this.markersByHighlineId.set(h.id, marker);
        }

        if (this.markers.length > 0) {
            const group = L.featureGroup(this.markers);
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

        // Group by lat/lng so users at the same spot can be fanned out around the point.
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
                    .addTo(this.map);
            });
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

    userPopupHtml(u) {
        const date = DATE_FORMATTER.format(new Date(u.crossedAt));
        const stars = u.rating
            ? '★'.repeat(u.rating) + '☆'.repeat(5 - u.rating)
            : '';
        return `
            <div class="user-popup">
                <strong>${escapeHtml(u.userDisplayName)}</strong>
                <div class="user-popup-line">${escapeHtml(u.highlineName)}</div>
                <div class="muted">${escapeHtml(date)}${stars ? ` &middot; <span class="user-popup-stars">${stars}</span>` : ''}</div>
            </div>
        `;
    }
}

function fanOffset(i, total) {
    if (total <= 1) return { x: 0, y: 0 };
    const radius = 22;
    const angle = (i / total) * Math.PI * 2 - Math.PI / 2; // start from top, clockwise
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
