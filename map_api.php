<?php
/**
 * Happiva - Map API Configuration
 * OpenStreetMap (Free) + Mapbox (Optional)
 */

// Free Map API using OpenStreetMap
// No API key required for basic usage

/**
 * Get coordinates for an address using Nominatim (OpenStreetMap)
 */
function getCoordinates($address) {
    // Helper function to fetch coordinates
    $fetchCoordinates = function($query) {
        $q = urlencode(trim($query));
        $url = "https://nominatim.openstreetmap.org/search?format=json&q={$q}&limit=1";
        
        $context = stream_context_create([
            'http' => [
                'header' => "User-Agent: Happiva/1.1\r\n"
            ]
        ]);
        
        $response = @file_get_contents($url, false, $context);
        
        if ($response) {
            $data = json_decode($response, true);
            if (!empty($data)) {
                return [
                    'success' => true,
                    'lat' => $data[0]['lat'],
                    'lon' => $data[0]['lon'],
                    'display_name' => $data[0]['display_name']
                ];
            }
        }
        return false;
    };

    // 1. Try full address
    $result = $fetchCoordinates($address);
    if ($result) {
        return $result;
    }

    // 2. Fallbacks if comma-separated
    if (strpos($address, ',') !== false) {
        $parts = explode(',', $address);
        
        // Remove the first part (often a venue name confusing the API)
        array_shift($parts);
        $result = $fetchCoordinates(implode(',', $parts));
        if ($result) {
            return $result;
        }
        
        // Remove everything except the last two parts (typically City, State)
        if (count($parts) >= 2) {
            $cityState = trim($parts[count($parts)-2]) . ', ' . trim($parts[count($parts)-1]);
            $result = $fetchCoordinates($cityState);
            if ($result) {
                return $result;
            }
        }
    }
    
    return [
        'success' => false,
        'error' => 'Location not found'
    ];
}

/**
 * Get static map image URL
 */
function getStaticMapUrl($lat, $lon, $width = 400, $height = 300, $zoom = 14) {
    // Using OpenStreetMap static map (free)
    return "https://staticmap.openstreetmap.de/staticmap.php?center={$lat},{$lon}&zoom={$zoom}&size={$width}x{$height}&markers={$lat},{$lon},red-pushpin";
}

/**
 * Get embeddable map iframe URL
 */
function getMapEmbedUrl($lat, $lon, $zoom = 14) {
    return "https://www.google.com/maps?q={$lat},{$lon}&z={$zoom}&output=embed";
}

/**
 * Get directions URL
 */
function getDirectionsUrl($fromLat, $fromLon, $toLat, $toLon) {
    return "https://www.google.com/maps/dir/?api=1&origin={$fromLat},{$fromLon}&destination={$toLat},{$toLon}";
}

// Mapbox Configuration (Optional - for more features)
// Get your key from: https://account.mapbox.com/
define('MAPBOX_ACCESS_TOKEN', 'pk.your_mapbox_token_here');

/**
 * Get Mapbox static map URL (better quality)
 */
function getMapboxStaticMapUrl($lat, $lon, $width = 400, $height = 300, $zoom = 14) {
    $token = MAPBOX_ACCESS_TOKEN;
    if (empty($token) || $token === 'pk.your_mapbox_token_here') {
        return getStaticMapUrl($lat, $lon, $width, $height, $zoom);
    }
    
    return "https://api.mapbox.com/styles/v1/mapbox/streets-v11/static/pin-s-marker/{$lon},{$lat},{$zoom}/{$width}x{$height}@2x?access_token={$token}";
}

/**
 * Get coordinates using Mapbox Geocoding API
 */
function getCoordinatesMapbox($address) {
    $token = MAPBOX_ACCESS_TOKEN;
    if (empty($token) || $token === 'pk.your_mapbox_token_here') {
        return getCoordinates($address);
    }
    
    $address = urlencode($address);
    $url = "https://api.mapbox.com/geocoding/v5/mapbox.places/{$address}.json?access_token={$token}";
    
    $response = @file_get_contents($url);
    
    if ($response) {
        $data = json_decode($response, true);
        if (!empty($data['features'])) {
            $feature = $data['features'][0];
            return [
                'success' => true,
                'lat' => $feature['center'][1],
                'lon' => $feature['center'][0],
                'display_name' => $feature['place_name']
            ];
        }
    }
    
    return getCoordinates($address);
}

// Handle API requests
if (isset($_GET['api']) && $_GET['api'] === 'map') {
    header('Content-Type: application/json');
    
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'geocode':
            $address = $_GET['address'] ?? '';
            echo json_encode(getCoordinates($address));
            break;
            
        case 'static_map':
            $lat = $_GET['lat'] ?? '';
            $lon = $_GET['lon'] ?? '';
            $width = $_GET['width'] ?? 400;
            $height = $_GET['height'] ?? 300;
            echo json_encode([
                'success' => true,
                'map_url' => getStaticMapUrl($lat, $lon, $width, $height)
            ]);
            break;
            
        case 'directions':
            $fromLat = $_GET['from_lat'] ?? '';
            $fromLon = $_GET['from_lon'] ?? '';
            $toLat = $_GET['to_lat'] ?? '';
            $toLon = $_GET['to_lon'] ?? '';
            echo json_encode([
                'success' => true,
                'directions_url' => getDirectionsUrl($fromLat, $fromLon, $toLat, $toLon)
            ]);
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
    exit;
}
