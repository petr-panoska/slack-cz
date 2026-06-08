import L from 'leaflet';

const requestFs = (el) => el.requestFullscreen || el.webkitRequestFullscreen;
const exitFs = () => document.exitFullscreen || document.webkitExitFullscreen;
const fsElement = () => document.fullscreenElement || document.webkitFullscreenElement;

/**
 * Adds a pill button that toggles native fullscreen for a map. By default the map's
 * own container goes fullscreen; pass `element` to fullscreen a richer wrapper (e.g.
 * /mapa fullscreens the whole wrapper so the crossing feed and time-travel controls
 * come along). Keeps Leaflet sized correctly across the resize and self-cleans when
 * the map is removed (Turbo navigations).
 *
 * @param {L.Map} map
 * @param {{element?: HTMLElement, position?: string}} [opts]
 */
export function addFullscreenToggle(map, { element = null, position = 'topright' } = {}) {
    const target = element || map.getContainer();
    // No Fullscreen API (e.g. iOS Safari on non-video elements) → don't add a dead button.
    if (!requestFs(target)) return;

    // Stable marker class so the :fullscreen CSS only sizes our map targets.
    target.classList.add('map-fs-target');

    let btn = null;
    const isFs = () => fsElement() === target;

    const render = () => {
        if (!btn) return;
        const active = isFs();
        // Icon-only; the label lives in aria-label/title for a11y + hover hint.
        btn.textContent = active ? '✕' : '⛶';
        const label = active ? 'Zavřít celou obrazovku' : 'Celá obrazovka';
        btn.setAttribute('aria-label', label);
        btn.title = label;
        btn.classList.toggle('is-active', active);
    };

    const onChange = () => {
        // Leaflet must recompute its size after the container resizes; a second pass
        // covers browsers that report the change before the layout settles.
        map.invalidateSize();
        setTimeout(() => map.invalidateSize(), 200);
        render();
    };
    document.addEventListener('fullscreenchange', onChange);
    document.addEventListener('webkitfullscreenchange', onChange);
    map.on('unload', () => {
        document.removeEventListener('fullscreenchange', onChange);
        document.removeEventListener('webkitfullscreenchange', onChange);
    });

    const control = L.control({ position });
    control.onAdd = () => {
        btn = L.DomUtil.create('button', 'map-ctrl-btn map-fullscreen-toggle');
        btn.type = 'button';
        render();
        L.DomEvent.disableClickPropagation(btn);
        L.DomEvent.disableScrollPropagation(btn);
        L.DomEvent.on(btn, 'click', (e) => {
            L.DomEvent.preventDefault(e);
            if (isFs()) {
                exitFs().call(document);
            } else {
                requestFs(target).call(target);
            }
        });
        return btn;
    };
    control.addTo(map);
}
