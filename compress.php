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
        // Ensure the folder path is correctly constructed, including the baseUploadDir
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
    $zipOpenResult = $zip->open($destination, ZipArchive::CREATE | ZipArchive::OVERWRITE);

    if ($zipOpenResult !== TRUE) {
        // Improved error reporting for ZipArchive::open
        $errorMessage = "Failed to open ZIP file: " . $destination . ". ";
        switch ($zipOpenResult) {
            case ZipArchive::ER_EXISTS: $errorMessage .= "File already exists."; break;
            case ZipArchive::ER_INVAL: $errorMessage .= "Invalid argument."; break;
            case ZipArchive::ER_MEMORY: $errorMessage .= "Memory allocation failure."; break;
            case ZipArchive::ER_NOENT: $errorMessage .= "No such file or directory."; break;
            case ZipArchive::ER_NOZIP: $errorMessage .= "Not a zip archive."; break;
            case ZipArchive::ER_OPEN: $errorMessage .= "Can't open file."; break;
            case ZipArchive::ER_READ: $errorMessage .= "Read error."; break;
            case ZipArchive::ER_SEEK: $errorMessage .= "Seek error."; break;
            default: $errorMessage .= "Unknown error code: " . $zipOpenResult; break;
        }
        error_log("ZIP Error: " . $errorMessage); // Log the detailed error
        return ['success' => false, 'message' => $errorMessage];
    }

    foreach ($filesToArchive as $itemPath) {
        // Get the base name of the item (file or folder) to be added to the archive
        $baseItemName = basename($itemPath);

        if (is_file($itemPath)) {
            if (!$zip->addFile($itemPath, $baseItemName)) {
                error_log("ZIP Error: Failed to add file '{$itemPath}' to archive.");
                // Optionally, you could continue or return false here depending on desired behavior
            }
        } elseif (is_dir($itemPath)) {
            // Add folder and its contents recursively
            // Ensure the directory itself is added first
            if (!$zip->addEmptyDir($baseItemName)) {
                error_log("ZIP Error: Failed to add empty directory '{$baseItemName}' to archive.");
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($itemPath, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );
            foreach ($iterator as $file) {
                // Get the relative path of the current file/directory within the itemPath
                // This ensures the internal structure of the folder is preserved in the ZIP
                $relativePath = substr($file->getPathname(), strlen($itemPath) + 1);
                if ($file->isDir()) {
                    if (!$zip->addEmptyDir($baseItemName . '/' . $relativePath)) {
                        error_log("ZIP Error: Failed to add empty directory '{$baseItemName}/{$relativePath}' to archive.");
                    }
                } else {
                    if (!$zip->addFile($file->getPathname(), $baseItemName . '/' . $relativePath)) {
                        error_log("ZIP Error: Failed to add file '{$file->getPathname()}' to archive as '{$baseItemName}/{$relativePath}'.");
                    }
                }
            }
        }
    }

    // Close the zip archive and check for errors during close
    if (!$zip->close()) {
        $errorMessage = "Failed to close ZIP file: " . $destination . ". Possible write errors or corrupted archive.";
        error_log("ZIP Error: " . $errorMessage);
        return ['success' => false, 'message' => $errorMessage];
    }

    return file_exists($destination) ? ['success' => true, 'message' => "Successfully created ZIP: " . basename($destination)] : ['success' => false, 'message' => "Failed to create ZIP. File not found after close."];
}

// The compressShell function and its related shell commands are removed as per the request.

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Set higher execution time and memory limit for potentially large archives
    set_time_limit(300); // 5 minutes
    ini_set('memory_limit', '512M'); // 512 MB

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
            error_log("Item not found or path invalid: ID " . $item['id'] . ", Type " . $item['type'] . ", Path: " . $fullPath);
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
    }
    // Ensure the directory exists and is writable
    if (!is_dir($archiveOutputDir)) {
        if (!mkdir($archiveOutputDir, 0777, true)) { // Use 0777 for maximum compatibility, adjust as needed
            error_log("Failed to create archive output directory: " . $archiveOutputDir);
            echo json_encode(['success' => false, 'message' => 'Failed to create output directory for archive. Check server permissions.']);
            exit;
        }
    } elseif (!is_writable($archiveOutputDir)) {
        error_log("Archive output directory is not writable: " . $archiveOutputDir);
        echo json_encode(['success' => false, 'message' => 'Output directory for archive is not writable. Check server permissions.']);
        exit;
    }


    // Generate a unique name for the archive file
    $archiveNameBase = 'archive_' . date('Ymd_His');
    $archiveExtension = 'zip'; // Always zip
    $outputFile = $archiveOutputDir . $archiveNameBase . '.' . $archiveExtension;

    // Ensure the output file path is relative to the application root for DB storage
    // Use realpath to get the absolute path for str_replace to work correctly
    $appRoot = realpath(__DIR__);
    $outputFileRelative = str_replace($appRoot . DIRECTORY_SEPARATOR, '', realpath($outputFile)); // realpath might return false if file doesn't exist yet

    // If realpath($outputFile) returns false (file doesn't exist yet), construct relative path manually
    if ($outputFileRelative === false) {
        $outputFileRelative = str_replace($appRoot . DIRECTORY_SEPARATOR, '', $outputFile);
    }


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
        $stmt->bind_param("issss", $currentFolderId, $archiveFileName, $outputFileRelative, $archiveFileSize, $archiveFileType); // Use $outputFileRelative
        if ($stmt->execute()) {
            $result['message'] .= " and successfully added to database.";
        } else {
            $result['message'] .= " but failed to add to database: " . $stmt->error;
            $result['success'] = false; // Mark as failure if DB insertion fails
            error_log("Database insertion failed for archive: " . $stmt->error);
        }
        $stmt->close();
    }

    echo json_encode($result);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
?>