<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Waste Dashboard</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: system-ui, sans-serif; background: #0f172a; color: #e2e8f0; }

        header {
            padding: 1rem 1.5rem;
            background: #1e293b;
            border-bottom: 1px solid #334155;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        header h1 { font-size: 1.25rem; font-weight: 600; }
        header span { font-size: 0.8rem; color: #64748b; }
        #last-updated { margin-left: auto; font-size: 0.75rem; color: #64748b; }

        .grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            grid-template-rows: repeat(2, 1fr);
            gap: 1rem;
            padding: 1rem;
            height: calc(100vh - 60px);
        }

        .card {
            background: #1e293b;
            border-radius: 0.75rem;
            border: 1px solid #334155;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .card-header {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #334155;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .card-header h2 { font-size: 0.9rem; font-weight: 600; }
        .card-header .dot { width: 8px; height: 8px; border-radius: 50%; }
        .map { flex: 1; }

        /* Legend */
        .legend {
            padding: 0.5rem 1rem;
            display: flex;
            gap: 1rem;
            font-size: 0.7rem;
            color: #94a3b8;
            border-top: 1px solid #334155;
            flex-wrap: wrap;
        }
        .legend-item { display: flex; align-items: center; gap: 0.3rem; }
        .legend-dot { width: 10px; height: 10px; border-radius: 50%; }
    </style>
</head>
<body>

<header>
    <h1>🗑️ Smart Waste Dashboard</h1>
    <span id="last-updated">Loading…</span>
</header>

<div class="grid">
    <div class="card">
        <div class="card-header">
            <div class="dot" style="background:#3b82f6"></div>
            <h2>Fill Level</h2>
        </div>
        <div id="map-weight" class="map"></div>
        <div class="legend">
            <div class="legend-item"><div class="legend-dot" style="background:#22c55e"></div> &lt;50%</div>
            <div class="legend-item"><div class="legend-dot" style="background:#eab308"></div> 50–75%</div>
            <div class="legend-item"><div class="legend-dot" style="background:#f97316"></div> 75–90%</div>
            <div class="legend-item"><div class="legend-dot" style="background:#ef4444"></div> &gt;90%</div>
            <div class="legend-item"><div class="legend-dot" style="background:#64748b"></div> Unknown</div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="dot" style="background:#f59e0b"></div>
            <h2>Battery Level</h2>
        </div>
        <div id="map-battery" class="map"></div>
        <div class="legend">
            <div class="legend-item"><div class="legend-dot" style="background:#22c55e"></div> &gt;50%</div>
            <div class="legend-item"><div class="legend-dot" style="background:#eab308"></div> 25–50%</div>
            <div class="legend-item"><div class="legend-dot" style="background:#ef4444"></div> &lt;25%</div>
            <div class="legend-item"><div class="legend-dot" style="background:#64748b"></div> Unknown</div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="dot" style="background:#a855f7"></div>
            <h2>Lid Status</h2>
        </div>
        <div id="map-lid" class="map"></div>
        <div class="legend">
            <div class="legend-item"><div class="legend-dot" style="background:#22c55e"></div> Closed</div>
            <div class="legend-item"><div class="legend-dot" style="background:#ef4444"></div> Open</div>
            <div class="legend-item"><div class="legend-dot" style="background:#64748b"></div> Unknown</div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="dot" style="background:#10b981"></div>
            <h2>Collection Route</h2>
            <span id="route-summary" style="margin-left:auto;font-size:0.75rem;color:#94a3b8"></span>
        </div>
        <div id="map-route" class="map"></div>
        <div class="legend">
            <div class="legend-item"><div class="legend-dot" style="background:#10b981"></div> Depot</div>
            <div class="legend-item"><div class="legend-dot" style="background:#f97316"></div> Stop (≥50%)</div>
            <div class="legend-item" style="display:flex;align-items:center;gap:0.3rem">
                <div style="width:20px;height:3px;background:#ff0000;border-radius:2px"></div> Route
            </div>
        </div>
    </div>
</div>

<script>
const TILE = 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';
const TILE_ATTR = '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>';
const DEFAULT_CENTER = [41.15, -8.61];
const DEFAULT_ZOOM = 13;

function makeMap(id) {
    const map = L.map(id, { zoomControl: true }).setView(DEFAULT_CENTER, DEFAULT_ZOOM);
    L.tileLayer(TILE, { attribution: TILE_ATTR }).addTo(map);
    return map;
}

function circleColor(value, type) {
    if (value === null || value === undefined) return '#64748b';
    if (type === 'weight') {
        if (value >= 90) return '#ef4444';
        if (value >= 75) return '#f97316';
        if (value >= 50) return '#eab308';
        return '#22c55e';
    }
    if (type === 'battery') {
        if (value <= 25) return '#ef4444';
        if (value <= 50) return '#eab308';
        return '#22c55e';
    }
    if (type === 'lid') {
        return value ? '#ef4444' : '#22c55e';
    }
    return '#64748b';
}

function makeMarker(lat, lng, color, popupHtml) {
    return L.circleMarker([lat, lng], {
        radius: 10,
        fillColor: color,
        color: '#fff',
        weight: 2,
        opacity: 1,
        fillOpacity: 0.9,
    }).bindPopup(popupHtml);
}

const maps = {
    weight:  makeMap('map-weight'),
    battery: makeMap('map-battery'),
    lid:     makeMap('map-lid'),
    route:   makeMap('map-route'),
};

const layers = { weight: [], battery: [], lid: [], route: [] };

function clearLayers() {
    for (const key of Object.keys(layers)) {
        layers[key].forEach(m => m.remove());
        layers[key] = [];
    }
}

function renderBins(bins) {
    clearLayers();

    if (!bins.length) return;

    const bounds = bins.map(b => [b.lat, b.lng]);

    bins.forEach(bin => {
        const { bin_id, lat, lng, capacity_percent, battery_level, lid_open } = bin;

        // Weight map
        const wColor = circleColor(capacity_percent, 'weight');
        const wLabel = capacity_percent !== null ? `${capacity_percent.toFixed(1)}%` : 'N/A';
        const wMarker = makeMarker(lat, lng, wColor,
            `<b>${bin_id}</b><br>Fill: ${wLabel}`);
        wMarker.addTo(maps.weight);
        layers.weight.push(wMarker);

        // Battery map
        const bColor = circleColor(battery_level, 'battery');
        const bLabel = battery_level !== null ? `${battery_level.toFixed(1)}%` : 'N/A';
        const bMarker = makeMarker(lat, lng, bColor,
            `<b>${bin_id}</b><br>Battery: ${bLabel}`);
        bMarker.addTo(maps.battery);
        layers.battery.push(bMarker);

        // Lid map
        const lColor = (lid_open === null || lid_open === undefined) ? '#64748b' : circleColor(lid_open, 'lid');
        const lLabel = (lid_open === null || lid_open === undefined) ? 'Unknown' : (lid_open ? '🔓 Open' : '🔒 Closed');
        const lMarker = makeMarker(lat, lng, lColor,
            `<b>${bin_id}</b><br>Lid: ${lLabel}`);
        lMarker.addTo(maps.lid);
        layers.lid.push(lMarker);
    });

    // Fit all maps to bin locations
    if (bounds.length) {
        const leafletBounds = L.latLngBounds(bounds);
        Object.values(maps).forEach(m => m.fitBounds(leafletBounds, { padding: [40, 40] }));
    }
}

async function refresh() {
    try {
        const res = await fetch('/api/bin-states');
        const bins = await res.json();
        renderBins(bins);
        document.getElementById('last-updated').textContent =
            'Updated: ' + new Date().toLocaleTimeString();
    } catch (e) {
        console.error('Failed to fetch bin states', e);
    }
}

let routeRefreshing = false;

async function refreshRoute() {
    if (routeRefreshing) return;
    routeRefreshing = true;
    try {
        const res = await fetch('/api/route');
        const data = await res.json();

        layers.route.forEach(l => l.remove());
        layers.route = [];

        const { depot, route, total_km } = data;
        if (!depot) return;

        // Depot marker
        const depotMarker = L.circleMarker([depot.lat, depot.lng], {
            radius: 12, fillColor: '#10b981', color: '#fff', weight: 2, fillOpacity: 1,
        }).bindPopup('<b>Depot</b>');
        depotMarker.addTo(maps.route);
        layers.route.push(depotMarker);

        if (!route.length) {
            document.getElementById('route-summary').textContent = 'No bins need emptying';
            return;
        }

        // Stop markers with order number
        route.forEach((bin, i) => {
            const icon = L.divIcon({
                className: '',
                html: `<div style="background:#f97316;color:#fff;border:2px solid #fff;border-radius:50%;width:24px;height:24px;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:bold">${i + 1}</div>`,
                iconSize: [24, 24], iconAnchor: [12, 12],
            });
            const m = L.marker([bin.lat, bin.lng], { icon })
                .bindPopup(`<b>${bin.bin_id}</b><br>Stop #${i + 1}<br>Fill: ${bin.capacity_percent?.toFixed(1) ?? '?'}%`);
            m.addTo(maps.route);
            layers.route.push(m);
        });

        // Build OSRM waypoints: depot → stops → depot
        const waypoints = [
            `${depot.lng},${depot.lat}`,
            ...route.map(b => `${b.lng},${b.lat}`),
            `${depot.lng},${depot.lat}`,
        ].join(';');

        try {
            const osrmRes = await fetch(
                `https://router.project-osrm.org/route/v1/driving/${waypoints}?overview=full&geometries=geojson`
            );
            const osrmData = await osrmRes.json();
            const coords = osrmData.routes[0].geometry.coordinates.map(([lng, lat]) => [lat, lng]);
            const line = L.polyline(coords, { color: '#ff0000', weight: 5, opacity: 1 });
            line.addTo(maps.route);
            layers.route.push(line);
            maps.route.fitBounds(line.getBounds(), { padding: [40, 40] });
        } catch {
            // Fallback to straight lines if OSRM fails
            const points = [[depot.lat, depot.lng], ...route.map(b => [b.lat, b.lng]), [depot.lat, depot.lng]];
            const line = L.polyline(points, { color: '#ff0000', weight: 5, opacity: 1 });
            line.addTo(maps.route);
            layers.route.push(line);
            maps.route.fitBounds(line.getBounds(), { padding: [40, 40] });
        }

        document.getElementById('route-summary').textContent =
            `${route.length} stop${route.length !== 1 ? 's' : ''} · ${total_km} km`;
    } catch (e) {
        console.error('Failed to fetch route', e);
    } finally {
        routeRefreshing = false;
    }
}

refresh();
refreshRoute();
setInterval(refresh, 10000);
setInterval(refreshRoute, 30000);
</script>
</body>
</html>
