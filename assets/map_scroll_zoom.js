import L from 'leaflet';

// Cooperative gesture handling for maps embedded in a scrollable page (homepage,
// deník, highline detail, highline form). Plain wheel scrolls the PAGE; only
// Ctrl/⌘ + wheel zooms the map — the same convention as Google Maps. Leaflet's
// built-in scrollWheelZoom can't gate on a modifier (it either eats every wheel
// event or none), so we disable it and run a modifier-aware handler that mirrors
// Leaflet's own debounced, zoom-to-cursor math for an identical feel.
//
// Not used on the dedicated /mapa page, where the map IS the content and plain
// wheel-zoom is expected.

const isMac = /Mac|iPhone|iPad|iPod/.test(navigator.platform || navigator.userAgent || '');
const MODIFIER_LABEL = isMac ? '⌘' : 'Ctrl';

/**
 * @param {L.Map} map
 * @param {{hint?: boolean}} [opts] `hint: false` suppresses the "podrž Ctrl" overlay.
 */
export function enableCtrlScrollZoom(map, { hint = true } = {}) {
    // Turn off Leaflet's own handler (a no-op on maps created with scrollWheelZoom: false).
    if (map.scrollWheelZoom) map.scrollWheelZoom.disable();

    const container = map.getContainer();
    const hintEl = hint ? makeHint(container) : null;

    // Accumulate wheel delta and debounce, exactly like Leaflet's ScrollWheelZoom,
    // so a flick of the wheel maps to one smooth zoom step instead of many.
    let delta = 0;
    let lastPoint = null;
    let startTime = 0;
    let timer = null;

    const performZoom = () => {
        timer = null;
        const zoom = map.getZoom();
        const snap = map.options.zoomSnap || 0;
        const d2 = delta / (map.options.wheelPxPerZoomLevel * 4);
        const d3 = (4 * Math.log(2 / (1 + Math.exp(-Math.abs(d2))))) / Math.LN2;
        const d4 = snap ? Math.ceil(d3 / snap) * snap : d3;
        const target = clamp(zoom + (delta > 0 ? d4 : -d4), map.getMinZoom(), map.getMaxZoom());

        delta = 0;
        startTime = 0;
        if (target !== zoom) map.setZoomAround(lastPoint, target);
    };

    let hintTimer = null;
    const flashHint = () => {
        if (!hintEl) return;
        hintEl.classList.add('is-visible');
        clearTimeout(hintTimer);
        hintTimer = setTimeout(() => hintEl.classList.remove('is-visible'), 1100);
    };

    // In fullscreen there's no page to scroll, so the modifier is pointless — plain
    // wheel zooms directly. The Ctrl/⌘ requirement only applies to the inline map.
    const inFullscreen = () => {
        const fsEl = document.fullscreenElement || document.webkitFullscreenElement;
        return !!fsEl && (fsEl === container || fsEl.contains(container));
    };

    const onWheel = (e) => {
        if (!inFullscreen() && !(e.ctrlKey || e.metaKey)) {
            // Inline + plain wheel: let the page scroll, nudge the user toward the modifier.
            flashHint();
            return;
        }
        // Fullscreen, or modifier held → zoom; swallow the browser's own page/ctrl zoom.
        e.preventDefault();

        delta += L.DomEvent.getWheelDelta(e);
        lastPoint = map.mouseEventToContainerPoint(e);
        if (!startTime) startTime = Date.now();
        const left = Math.max((map.options.wheelDebounceTime || 40) - (Date.now() - startTime), 0);
        clearTimeout(timer);
        timer = setTimeout(performZoom, left);
    };

    // Non-passive so preventDefault() actually blocks the browser's page zoom.
    container.addEventListener('wheel', onWheel, { passive: false });
    map.on('unload', () => {
        clearTimeout(timer);
        clearTimeout(hintTimer);
        container.removeEventListener('wheel', onWheel, { passive: false });
        if (hintEl) hintEl.remove();
    });
}

// Centered "podrž Ctrl a otoč kolečkem" overlay; enableCtrlScrollZoom toggles its
// .is-visible class on a plain-wheel scroll and clears it shortly after.
function makeHint(container) {
    const el = L.DomUtil.create('div', 'map-zoom-hint', container);
    el.textContent = `Pro přiblížení podrž ${MODIFIER_LABEL} a otoč kolečkem`;
    return el;
}

function clamp(value, min, max) {
    return Math.min(max, Math.max(min, value));
}
