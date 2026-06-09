import L from 'leaflet';

// Base + orthophoto (satellite) tile sources, shared by every Leaflet map on the
// site so there's a single source of truth for the URLs and attributions.
export const OSM_URL = 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';
export const OSM_ATTR = '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>';
export const ORTHO_URL = 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}';
export const ORTHO_ATTR = 'Tiles &copy; Esri — Source: Esri, Maxar, Earthstar Geographics, and the GIS User Community';

// Transparent label/road tiles laid over the ortho imagery for the "hybrid" view
// (orthophoto + place names + roads). Same Esri family as ORTHO_URL above.
export const HYBRID_REF_URLS = [
    'https://server.arcgisonline.com/ArcGIS/rest/services/Reference/World_Boundaries_and_Places/MapServer/tile/{z}/{y}/{x}',
    'https://server.arcgisonline.com/ArcGIS/rest/services/Reference/World_Transportation/MapServer/tile/{z}/{y}/{x}',
];

// Ordered list of selectable basemaps, shared by the picker control and the form.
export const BASEMAPS = [
    { id: 'osm', label: 'Mapa' },
    { id: 'ortho', label: 'Ortofoto' },
    { id: 'hybrid', label: 'Hybrid' },
];

/**
 * Builds the three selectable base layers for one map. `hybrid` is a layer group:
 * the ortho imagery with the transparent reference tiles stacked on top (zIndex
 * keeps labels/roads above the imagery).
 *
 * @param {{maxZoom?: number}} [opts]
 * @returns {{osm: L.TileLayer, ortho: L.TileLayer, hybrid: L.LayerGroup}}
 */
export function createBasemapLayers({ maxZoom = 19 } = {}) {
    const osm = L.tileLayer(OSM_URL, { maxZoom, attribution: OSM_ATTR });
    const ortho = L.tileLayer(ORTHO_URL, { maxZoom, attribution: ORTHO_ATTR });

    const hybrid = L.layerGroup([
        L.tileLayer(ORTHO_URL, { maxZoom, attribution: ORTHO_ATTR, zIndex: 1 }),
        ...HYBRID_REF_URLS.map((url) => L.tileLayer(url, { maxZoom, zIndex: 2 })),
    ]);

    return { osm, ortho, hybrid };
}

/** Shows the layer with id `id` (one of BASEMAPS) and removes the other two. */
export function applyBasemap(map, layers, id) {
    BASEMAPS.forEach(({ id: key }) => {
        if (key === id) {
            layers[key].addTo(map);
        } else if (map.hasLayer(layers[key])) {
            map.removeLayer(layers[key]);
        }
    });
}

/**
 * Adds the three base layers to `map` plus an expandable picker control: a pill
 * button showing the current layer that opens a small list (Mapa / Ortofoto /
 * Hybrid). Used on every read-only map and on the highline form.
 *
 * @param {L.Map} map
 * @param {{position?: string, maxZoom?: number, initial?: string, ortho?: boolean}} [opts]
 *        `initial` picks the starting layer id; `ortho: true` is shorthand for
 *        `initial: 'ortho'`.
 * @returns {{osm: L.TileLayer, ortho: L.TileLayer, hybrid: L.LayerGroup}}
 */
export function addBasemapPicker(map, { position = 'topright', maxZoom = 19, initial, ortho = false } = {}) {
    const layers = createBasemapLayers({ maxZoom });
    let current = initial || (ortho ? 'ortho' : 'osm');
    applyBasemap(map, layers, current);

    const labelFor = (id) => BASEMAPS.find((b) => b.id === id).label;

    const control = L.control({ position });
    control.onAdd = () => {
        const root = L.DomUtil.create('div', 'map-layers');
        // Align/flip the popout so it always opens onto the map, not off its edge.
        if (position.includes('left')) root.classList.add('map-layers--left');
        if (position.includes('bottom')) root.classList.add('map-layers--up');
        const btn = L.DomUtil.create('button', 'map-ctrl-btn map-layers-toggle', root);
        btn.type = 'button';
        btn.setAttribute('aria-haspopup', 'true');

        const panel = L.DomUtil.create('div', 'map-layers-panel', root);
        panel.hidden = true;

        const collapse = () => {
            panel.hidden = true;
            root.classList.remove('is-open');
            btn.setAttribute('aria-expanded', 'false');
        };
        const expand = () => {
            panel.hidden = false;
            root.classList.add('is-open');
            btn.setAttribute('aria-expanded', 'true');
        };

        const renderToggle = () => {
            btn.textContent = `${labelFor(current)} ▾`;
            btn.setAttribute('aria-label', `Vrstva mapy: ${labelFor(current)}`);
        };

        BASEMAPS.forEach(({ id, label }) => {
            const opt = L.DomUtil.create('button', 'map-layers-option', panel);
            opt.type = 'button';
            opt.textContent = label;
            opt.classList.toggle('is-active', id === current);
            L.DomEvent.on(opt, 'click', (e) => {
                L.DomEvent.preventDefault(e);
                current = id;
                applyBasemap(map, layers, current);
                panel.querySelectorAll('.map-layers-option').forEach((o) => o.classList.toggle('is-active', o === opt));
                renderToggle();
                collapse();
            });
        });

        renderToggle();
        collapse();

        L.DomEvent.on(btn, 'click', (e) => {
            L.DomEvent.preventDefault(e);
            panel.hidden ? expand() : collapse();
        });

        // Don't let map drag/zoom/click fire through the control; close on map click.
        L.DomEvent.disableClickPropagation(root);
        L.DomEvent.disableScrollPropagation(root);
        map.on('click', collapse);

        return root;
    };
    control.addTo(map);

    return layers;
}
