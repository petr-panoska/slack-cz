import L from 'leaflet';

// Material "my location" crosshair, mirrors the icon-only style of the other
// map control pills (layers, fullscreen).
const LOCATE_ICON = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><path d="M12 8c-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4-1.79-4-4-4zm8.94 3A8.994 8.994 0 0 0 13 3.06V1h-2v2.06A8.994 8.994 0 0 0 3.06 11H1v2h2.06A8.994 8.994 0 0 0 11 20.94V23h2v-2.06A8.994 8.994 0 0 0 20.94 13H23v-2h-2.06zM12 19c-3.87 0-7-3.13-7-7s3.13-7 7-7 7 3.13 7 7-3.13 7-7 7z"/></svg>';

const DOT_HTML = '<span class="map-locate-dot"></span>';

// Where to send the user when geolocation permission is denied. The fix lives
// in a different place per platform, and iOS is a two-level trap: Safari has
// its own per-site setting AND a system-wide "Safari Websites" entry under
// Location Services — the system one silently wins, so the message must point
// there. iPadOS masquerades as Macintosh, hence the maxTouchPoints check.
// Chrome/Firefox/Edge on iOS brand their UA (CriOS/FxiOS/EdgiOS) but are
// WebKit underneath with their own app-level location permission.
function deniedHelp() {
    const ua = navigator.userAgent;
    const ios = /iPad|iPhone|iPod/.test(ua) || (ua.includes('Macintosh') && navigator.maxTouchPoints > 1);
    if (ios) {
        const safari = !/CriOS|FxiOS|EdgiOS|OPiOS/.test(ua);
        return {
            text: safari
                ? 'Safari nemá přístup k poloze. Zkontroluj Nastavení → Soukromí a zabezpečení → Polohové služby → Weby v Safari.'
                : 'Prohlížeč nemá přístup k poloze. Povol mu ji v Nastavení → Soukromí a zabezpečení → Polohové služby.',
            href: 'https://support.apple.com/cs-cz/102515',
        };
    }
    if (/Android/.test(ua)) {
        return {
            text: 'Poloha je pro tento web zablokovaná. Povol ji ťuknutím na ikonu vlevo od adresy → Oprávnění → Poloha.',
            href: 'https://support.google.com/chrome/answer/142065?hl=cs',
        };
    }
    return {
        text: 'Poloha je pro tento web zablokovaná — povol ji přes ikonu vlevo v adresním řádku prohlížeče.',
        href: null,
    };
}

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
    // No API or insecure (http) context → geolocation can't work, no dead button.
    if (!('geolocation' in navigator) || !window.isSecureContext) return;

    let btn = null;
    let active = false;
    let centered = false;
    let marker = null;
    let circle = null;
    let toast = null;

    // `title` is useless on touch devices — failures get a visible map toast.
    // It carries a link the user has to be able to read and tap, so it stays
    // until dismissed instead of auto-hiding.
    const showToast = (text, href) => {
        toast?.remove();
        toast = L.DomUtil.create('div', 'map-locate-toast', map.getContainer());
        L.DomEvent.disableClickPropagation(toast);
        const body = L.DomUtil.create('span', '', toast);
        body.textContent = text;
        if (href) {
            const link = L.DomUtil.create('a', '', body);
            link.href = href;
            link.target = '_blank';
            link.rel = 'noopener';
            link.textContent = 'Podrobný návod';
        }
        const close = L.DomUtil.create('button', 'map-locate-toast-close', toast);
        close.type = 'button';
        close.setAttribute('aria-label', 'Zavřít');
        close.innerHTML = '&times;';
        L.DomEvent.on(close, 'click', () => {
            toast?.remove();
            toast = null;
        });
    };

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
        // Long-but-FINITE timeout: the first GPS fix on a phone routinely takes
        // longer than Leaflet's 10s default (the iOS permission dialog alone can
        // eat it), and a watch should simply keep waiting — we pulse until the
        // fix lands. Never pass Infinity here: Safari converts it to a 32-bit
        // int without clamping, ends up with timeout=0 and fails every attempt
        // instantly (Chrome clamps, which hides the bug in testing).
        map.locate({ watch: true, setView: false, enableHighAccuracy: true, timeout: 600000 });
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
        // A running watch emits transient errors (no fix yet, signal lost,
        // timeout) — keep watching, the pulse shows we're still trying. Only
        // denied permission (code 1) is final.
        if (e.code !== 1) return;
        stop();
        btn.classList.add('is-error');
        setTimeout(() => btn.classList.remove('is-error'), 1600);
        const help = deniedHelp();
        showToast(help.text, help.href);
    });

    // Turbo navigation removes the map — don't leave the geolocation watch
    // running. The toast lives inside the map container, so it goes with it.
    map.on('unload', () => {
        if (active) map.stopLocate();
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
