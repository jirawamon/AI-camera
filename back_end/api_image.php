<?php
/**
 * Image API endpoint - serves event images on demand (lazy loading)
 * Usage: api_image.php?id=123
 * 
 * This avoids loading heavy base64 image data in the main page query.
 * Images are served individually with proper caching headers.
 */

require_once __DIR__ . '/config.php';

// Validate ID
$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    exit;
}

// Set cache headers - images don't change, cache for 1 day
header('Cache-Control: public, max-age=86400, immutable');
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 86400) . ' GMT');

// Check If-None-Match for 304 response
$etag = '"img-' . $id . '"';
header('ETag: ' . $etag);

if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
    http_response_code(304);
    exit;
}

try {
    $db = getDB();
    
    // Only fetch image_data for this single row
    $stmt = $db->prepare("SELECT image_data FROM webhook_events WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $imageData = $stmt->fetchColumn();
    
    if (!$imageData) {
        // Return a 1x1 transparent pixel as fallback
        header('Content-Type: image/png');
        echo base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPj/HwADBwIAMCbHYQAAAABJRU5ErkJggg==');
        exit;
    }
    
    // Detect if already base64 or raw
    if (preg_match('/^data:image\/(\w+);base64,/', $imageData, $matches)) {
        // Strip data URI prefix
        $mimeType = $matches[1] === 'jpg' ? 'jpeg' : $matches[1];
        $imageData = substr($imageData, strlen($matches[0]));
        header('Content-Type: image/' . $mimeType);
        echo base64_decode($imageData);
    } elseif (preg_match('/^[a-zA-Z0-9+\/=\s]+$/', substr($imageData, 0, 100))) {
        // Pure base64 string (no data: prefix)
        header('Content-Type: image/jpeg');
        echo base64_decode($imageData);
    } else {
        // Raw binary data
        header('Content-Type: image/jpeg');
        echo $imageData;
    }
    
} catch (Exception $e) {
    http_response_code(500);
    exit;
}
