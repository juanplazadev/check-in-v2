import 'leaflet/dist/leaflet.css';

import L from 'leaflet';
import markerIcon2x from 'leaflet/dist/images/marker-icon-2x.png';
import markerIcon from 'leaflet/dist/images/marker-icon.png';
import markerShadow from 'leaflet/dist/images/marker-shadow.png';
import { Circle, MapContainer, Marker, TileLayer } from 'react-leaflet';

const METERS_PER_MILE = 1609.344;

// Leaflet guesses its marker image paths from its stylesheet, which Vite rewrites; point it at
// the bundled files instead. An empty imagePath stops Leaflet from prefixing its guessed
// directory onto these already absolute URLs.
const markerIconOptions = {
    iconUrl: markerIcon,
    iconRetinaUrl: markerIcon2x,
    shadowUrl: markerShadow,
};

L.Icon.Default.imagePath = '';
L.Icon.Default.mergeOptions(markerIconOptions);

/**
 * The map itself. Leaflet reads `window` on import, so this module is only ever loaded in the
 * browser, through LocationMap.
 */
export default function LeafletMap({
    latitude,
    longitude,
    radiusMiles,
}: {
    latitude: number;
    longitude: number;
    radiusMiles: number;
}) {
    const center: [number, number] = [latitude, longitude];
    const radius = radiusMiles * METERS_PER_MILE;

    return (
        <MapContainer
            // Fit the radius circle on first render, with a little room around it.
            bounds={L.latLng(center).toBounds(radius * 2.2)}
            scrollWheelZoom={false}
            className="z-0 size-full"
        >
            <TileLayer
                attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
                url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
            />
            <Circle
                center={center}
                radius={radius}
                pathOptions={{
                    color: '#37b44a',
                    fillColor: '#37b44a',
                    fillOpacity: 0.12,
                    weight: 2,
                }}
            />
            <Marker position={center} />
        </MapContainer>
    );
}
