import L from 'leaflet';

// Material "my location" crosshair, mirrors the icon-only style of the other
// map control pills (layers, fullscreen).
const LOCATE_ICON = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><path d="M12 8c-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4-1.79-4-4-4zm8.94 3A8.994 8.994 0 0 0 13 3.06V1h-2v2.06A8.994 8.994 0 0 0 3.06 11H1v2h2.06A8.994 8.994 0 0 0 11 20.94V23h2v-2.06A8.994 8.994 0 0 0 20.94 13H23v-2h-2.06zM12 19c-3.87 0-7-3.13-7-7s3.13-7 7-7 7 3.13 7 7-3.13 7-7 7z"/></svg>';

const DOT_HTML = '<span class="map-locate-dot"></span>';

/**
 * Adds a toggleable "my location" button to the map's control corner. First
 * click starts a geolocation watch (the browser shows its permission dialog),
 * draws a blue dot + accuracy circle that follow the device, and centers the
 * map on the first fix. Second click stops the watch and removes the layers.
 * Permission denied / no fix → the button flashes an error state and resets.
 *
 * @param {L.Map} map
 * @param {{position?: string}} [opts]
 */
export function addLocateControl(map, { position = 'topright' } = {}) {
    // No API (or insecure context, where the browser hides it) → no dead button.
    if (!('geolocation' in navigator)) return;

    let btn = null;
    let active = false;
    let centered = false;
    let marker = null;
    let circle = null;
    let errorTimer = null;

    const setPressed = (on) => {
        btn.classList.toggle('is-active', on);
        btn.setAttribute('aria-pressed', String(on));
        const label = on ? 'Skrýt moji polohu' : 'Zobrazit moji polohu';
        btn.setAttribute('aria-label', label);
        btn.title = label;
    };

    const stop = () => {
        active = false;
        centered = false;
        map.stopLocate();
        marker?.remove();
        circle?.remove();
        marker = null;
        circle = null;
        btn.classList.remove('is-locating');
        setPressed(false);
    };

    const start = () => {
        active = true;
        setPressed(true);
        btn.classList.add('is-locating');
        map.locate({ watch: true, setView: false, enableHighAccuracy: true });
    };

    map.on('locationfound', (e) => {
        if (!active) return;
        btn.classList.remove('is-locating');
        if (!marker) {
            circle = L.circle(e.latlng, {
                radius: e.accuracy,
                color: '#2a7fff',
                weight: 1,
                opacity: 0.5,
                fillColor: '#2a7fff',
                fillOpacity: 0.12,
                interactive: false,
            }).addTo(map);
            marker = L.marker(e.latlng, {
                icon: L.divIcon({ className: 'map-locate-marker', html: DOT_HTML, iconSize: [18, 18] }),
                interactive: false,
                keyboard: false,
            }).addTo(map);
        } else {
            marker.setLatLng(e.latlng);
            circle.setLatLng(e.latlng).setRadius(e.accuracy);
        }
        // Center once per activation; afterwards the user pans freely.
        if (!centered) {
            centered = true;
            map.setView(e.latlng, Math.max(map.getZoom(), 13));
        }
    });

    map.on('locationerror', (e) => {
        if (!active) return;
        // A running watch emits transient errors (signal lost, timeout) — keep
        // watching and keep the last known dot. Bail out only when permission
        // was denied (code 1) or the very first fix already failed.
        if (e.code !== 1 && marker) return;
        stop();
        // Brief visual feedback on the button (denied permission / no fix).
        btn.classList.add('is-error');
        btn.title = 'Polohu se nepodařilo zjistit';
        clearTimeout(errorTimer);
        errorTimer = setTimeout(() => btn.classList.remove('is-error'), 1600);
    });

    // Turbo navigation removes the map — don't leave the geolocation watch running.
    map.on('unload', () => {
        if (active) map.stopLocate();
        clearTimeout(errorTimer);
    });

    const control = L.control({ position });
    control.onAdd = () => {
        btn = L.DomUtil.create('button', 'map-ctrl-btn map-locate-toggle');
        btn.type = 'button';
        btn.innerHTML = LOCATE_ICON;
        setPressed(false);
        L.DomEvent.disableClickPropagation(btn);
        L.DomEvent.disableScrollPropagation(btn);
        L.DomEvent.on(btn, 'click', (e) => {
            L.DomEvent.preventDefault(e);
            active ? stop() : start();
        });
        return btn;
    };
    control.addTo(map);
}
