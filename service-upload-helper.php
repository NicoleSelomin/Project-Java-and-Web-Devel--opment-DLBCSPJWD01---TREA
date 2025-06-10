<?php
/**
 * ---------------------------------------------------------------------
 * service-upload-helper.php
 * ---------------------------------------------------------------------
 * Utility functions for service application processing.
 * - Get service info by slug
 * - Create structured upload folders for owner applications
 * - Save single or multiple uploaded files with MIME validation
 * ---------------------------------------------------------------------
 */

/**
 * Fetch service information (service_id, service_name) using the slug.
 * Exits with error if not found.
 *
 * @param string $slug
 * @param PDO $pdo
 * @return array
 */
function getServiceInfo($slug, $pdo) {
    $stmt = $pdo->prepare("SELECT service_id, service_name FROM services WHERE slug = ?");
    $stmt->execute([$slug]);
    $service = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$service) {
        echo "Service not found.";
        exit();
    }
    $service['safe_name'] = preg_replace('/\s+/', '_', strtolower($service['service_name']));
    return $service;
}

/**
 * Create a structured folder for a service application.
 * Returns the created folder path.
 *
 * @param int $owner_id
 * @param string $owner_name
 * @param int $service_id
 * @param string $service_slug
 * @param int $request_id
 * @return string
 */
function createApplicationFolder($owner_id, $owner_name, $service_id, $service_slug, $request_id) {
    // Sanitize folder name
    $safe_owner_name = preg_replace('/[^a-zA-Z0-9]/', '_', strtolower($owner_name));
    $base = "uploads/owner/{$owner_id}_{$safe_owner_name}/applications/{$service_id}_{$service_slug}/request_{$request_id}/";
    if (!is_dir($base)) {
        mkdir($base, 0777, true);
    }
    return $base;
}

/**
 * Save a single uploaded file with MIME type validation.
 * Returns saved path or null.
 *
 * @param array $file $_FILES item (one file)
 * @param string $targetPath
 * @return string|null
 */
function saveFile($file, $targetPath) {
    if (isset($file) && $file['error'] === UPLOAD_ERR_OK) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        $allowedTypes = ['application/pdf', 'image/jpeg', 'image/png'];
        if (!in_array($mime, $allowedTypes)) {
            echo "Invalid file type: $mime";
            exit();
        }
        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            return $targetPath;
        }
    }
    return null;
}

/**
 * Save multiple uploaded files (e.g., supporting documents) to the base path.
 * Returns array of saved file paths.
 *
 * @param array $filesArray $_FILES[item] (multiple files)
 * @param string $base Base upload directory
 * @param string $prefix File name prefix
 * @return array
 */
function saveMultipleFiles($filesArray, $base, $prefix = 'doc') {
    $saved = [];
    if (!isset($filesArray['name']) || !is_array($filesArray['name'])) {
        return $saved;
    }
    foreach ($filesArray['name'] as $i => $name) {
        if ($filesArray['error'][$i] === UPLOAD_ERR_OK) {
            $ext = pathinfo($name, PATHINFO_EXTENSION);
            $tmp = [
                'name'     => $filesArray['name'][$i],
                'tmp_name' => $filesArray['tmp_name'][$i],
                'error'    => $filesArray['error'][$i],
            ];
            $path = $base . "{$prefix}_{$i}." . $ext;
            $savedPath = saveFile($tmp, $path);
            if ($savedPath) $saved[] = $savedPath;
        }
    }
    return $saved;
}
?>
