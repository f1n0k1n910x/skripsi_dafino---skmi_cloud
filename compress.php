<?php
include 'config.php'; // Ensure this points to your database configuration file
include 'functions.php'; // Include functions.php for logActivity

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit();
}

// Function to get the full path of a file/folder by ID
function getFullPath($conn, $id, $type) {
    $baseUploadDir = 'uploads/'; // Adjust to your upload directory
    if ($type === 'folder') {
        $folderPath = getFolderPath($conn, $id); // This function is already in functions.php
        return $baseUploadDir . $folderPath;
    } elseif ($type === 'file') {
        $stmt = $conn->prepare("SELECT file_path FROM files WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $file = $result->fetch_assoc();
        $stmt->close();
        return $file ? $file['file_path'] : null;
    }
    return null;
}

// Function to compress to ZIP using ZipArchive (PHP Native)
function compressToZip($filesToArchive, $destination) {
    $zip = new ZipArchive();
    if ($zip->open($destination, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
        return ['success' => false, 'message' => "Failed to open ZIP file: " . $destination];
    }

    foreach ($filesToArchive as $itemPath) {
        // Get the base name of the item (file or folder) to be added to the archive
        $baseItemName = basename($itemPath);

        if (is_file($itemPath)) {
            $zip->addFile($itemPath, $baseItemName);
        } elseif (is_dir($itemPath)) {
            // Add folder and its contents recursively
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($itemPath, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );
            foreach ($iterator as $file) {
                // Get the relative path of the current file/directory within the itemPath
                $relativePath = substr($file->getPathname(), strlen($itemPath) + 1);
                if ($file->isDir()) {
                    $zip->addEmptyDir($baseItemName . '/' . $relativePath);
                } else {
                    $zip->addFile($file->getPathname(), $baseItemName . '/' . $relativePath);
                }
            }
        }
    }

    $zip->close();
    return file_exists($destination) ? ['success' => true, 'message' => "Successfully created ZIP: " . basename($destination)] : ['success' => false, 'message' => "Failed to create ZIP."];
}

// The compressShell function and its related shell commands are removed as per the request.

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    $selectedItems = $data['items'] ?? [];
    $format = $data['format'] ?? 'zip'; // Default to zip
    $currentFolderId = $data['current_folder_id']; // Get current folder ID from request

    // Ensure only 'zip' format is processed
    if ($format !== 'zip') {
        echo json_encode(['success' => false, 'message' => 'Only ZIP format is supported for archiving.']);
        exit;
    }

    if (empty($selectedItems)) {
        echo json_encode(['success' => false, 'message' => 'No items selected for archiving.']);
        exit;
    }

    $filesToArchive = [];
    $itemNames = [];
    foreach ($selectedItems as $item) {
        $fullPath = getFullPath($conn, $item['id'], $item['type']);
        if ($fullPath && file_exists($fullPath)) {
            $filesToArchive[] = $fullPath;
            $itemNames[] = basename($fullPath);
        } else {
            // Log or handle cases where file/folder is not found
            error_log("Item not found or path invalid: ID " . $item['id'] . ", Type " . $item['type']);
        }
    }

    if (empty($filesToArchive)) {
        echo json_encode(['success' => false, 'message' => 'Selected items not found on server or are invalid.']);
        exit;
    }

    // Determine the output directory for the archive
    $archiveOutputDir = 'uploads/';
    if ($currentFolderId) {
        $folderPath = getFolderPath($conn, $currentFolderId);
        if ($folderPath) {
            $archiveOutputDir .= $folderPath . '/';
        }
        // Ensure the directory exists
        if (!is_dir($archiveOutputDir)) {
            mkdir($archiveOutputDir, 0777, true);
        }
    }

    // Generate a unique name for the archive file
    $archiveNameBase = 'archive_' . date('Ymd_His');
    $archiveExtension = 'zip'; // Always zip
    $outputFile = $archiveOutputDir . $archiveNameBase . '.' . $archiveExtension;

    $result = compressToZip($filesToArchive, $outputFile); // Always call compressToZip

    if ($result['success']) {
        // Log activity
        $userId = $_SESSION['user_id'];
        $description = "Archived " . implode(', ', $itemNames) . " to " . basename($outputFile) . " (Format: $format)";
        logActivity($conn, $userId, 'archive', $description);

        // Add the archived file to the database
        $archiveFileName = basename($outputFile);
        $archiveFileSize = filesize($outputFile);
        $archiveFileType = $archiveExtension; // Store the full extension as type

        $stmt = $conn->prepare("INSERT INTO files (folder_id, file_name, file_path, file_size, file_type, uploaded_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("issss", $currentFolderId, $archiveFileName, $outputFile, $archiveFileSize, $archiveFileType);
        if ($stmt->execute()) {
            $result['message'] .= " and successfully added to database.";
        } else {
            $result['message'] .= " but failed to add to database: " . $stmt->error;
            $result['success'] = false; // Mark as failure if DB insertion fails
        }
        $stmt->close();
    }

    echo json_encode($result);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
?>
