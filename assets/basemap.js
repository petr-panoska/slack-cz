import L from 'leaflet';

// Base + orthophoto (satellite) tile sources, shared by every Leaflet map on the
// site so there's a single source of truth for the URLs and attributions.
export const OSM_URL = 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';
export const OSM_ATTR = '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>';
export const ORTHO_URL = 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}';
export const ORTHO_ATTR = 'Tiles &copy; Esri — Source: Esri, Maxar, Earthstar Geographics, and the GIS User Community';

/**
 * Adds the OSM base layer to `map` plus a single pill button that swaps to Esri
 * orthophoto imagery and back. Used on every read-only map; the highline form has
 * its own buttons but reuses the same tile constants above.
 *
 * @param {L.Map} map
 * @param {{position?: string, maxZoom?: number, ortho?: boolean}} [opts] - `ortho`
 *        starts the map on orthophoto imagery instead of the OSM base layer.
 * @returns {{osmLayer: L.TileLayer, orthoLayer: L.TileLayer}}
 */
export function addBasemapToggle(map, { position = 'topright', maxZoom = 19, ortho = false } = {}) {
    const osmLayer = L.tileLayer(OSM_URL, { maxZoom, attribution: OSM_ATTR });
    const orthoLayer = L.tileLayer(ORTHO_URL, { maxZoom, attribution: ORTHO_ATTR });
    (ortho ? orthoLayer : osmLayer).addTo(map);

    const control = L.control({ position });
    control.onAdd = () => {
        const btn = L.DomUtil.create('button', 'map-ctrl-btn map-basemap-toggle');
        btn.type = 'button';
        let showingOrtho = ortho;

        const render = () => {
            btn.textContent = showingOrtho ? 'Mapa' : 'Ortofoto';
            btn.setAttribute('aria-label', showingOrtho ? 'Přepnout na mapu' : 'Přepnout na ortofoto');
            btn.classList.toggle('is-active', showingOrtho);
        };
        render();

        // Keep map drag/zoom from firing when interacting with the button.
        L.DomEvent.disableClickPropagation(btn);
        L.DomEvent.disableScrollPropagation(btn);
        L.DomEvent.on(btn, 'click', (e) => {
            L.DomEvent.preventDefault(e);
            showingOrtho = !showingOrtho;
            if (showingOrtho) {
                map.removeLayer(osmLayer);
                orthoLayer.addTo(map);
            } else {
                map.removeLayer(orthoLayer);
                osmLayer.addTo(map);
            }
            render();
        });

        return btn;
    };
    control.addTo(map);

    return { osmLayer, orthoLayer };
}
