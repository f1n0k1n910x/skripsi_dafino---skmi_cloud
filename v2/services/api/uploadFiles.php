<?php
// v2/services/api/uploadFiles.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../php-error.log');

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../helpers/utils.php';
require_once __DIR__ . '/../fileService.php';
require_once __DIR__ . '/../folderService.php';


session_start();
$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Please log in.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

try {
    $currentFolderId = isset($_POST['current_folder_id']) && $_POST['current_folder_id'] !== '' 
        ? (int)$_POST['current_folder_id'] 
        : null;

    if ($currentFolderId === 0) {
        $currentFolderId = null; // root upload
    }

    $currentFolderPath = $_POST['current_folder_path'] ?? '';

    if (!isset($_FILES['fileToUpload']) || !is_array($_FILES['fileToUpload']['name'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No files uploaded or invalid format.']);
        exit();
    }

    $uploadedFilesCount = count($_FILES['fileToUpload']['name']);
    $successCount = 0;
    $errorMessages = [];

    for ($i = 0; $i < $uploadedFilesCount; $i++) {
        $file = [
            'name'     => $_FILES['fileToUpload']['name'][$i],
            'type'     => $_FILES['fileToUpload']['type'][$i],
            'tmp_name' => $_FILES['fileToUpload']['tmp_name'][$i],
            'error'    => $_FILES['fileToUpload']['error'][$i],
            'size'     => $_FILES['fileToUpload']['size'][$i],
        ];

        // ✅ Handle PHP upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errorMessages[] = 'Upload error for "' . htmlspecialchars($file['name']) . '" (code ' . $file['error'] . ')';
            continue;
        }

        $uploadDirBase = __DIR__ . '/../../../uploads';
        $targetDir = $uploadDirBase;

        if (!is_dir($targetDir) && !mkdir($targetDir, 0777, true)) {
            $errorMessages[] = 'Failed to create upload base directory.';
            continue;
        }

        if (!empty($currentFolderPath)) {
            $targetDir .= '/' . $currentFolderPath;
            if (!is_dir($targetDir) && !mkdir($targetDir, 0777, true)) {
                $errorMessages[] = 'Failed to create target folder directory.';
                continue;
            }
        }

        $originalFileName = basename($file['name']);
        $uniqueFileName = generateUniqueFileName($originalFileName, $targetDir);
        $targetFilePath = $targetDir . '/' . $uniqueFileName;
        $fileType = pathinfo($uniqueFileName, PATHINFO_EXTENSION);
        $fileSize = $file['size'];

        if (!move_uploaded_file($file['tmp_name'], $targetFilePath)) {
            $errorMessages[] = 'Failed to move uploaded file "' . htmlspecialchars($file['name']) . '".';
            continue;
        }

        // ✅ Validate folder_id if provided
        if ($currentFolderId !== null) {
            $check = $conn->prepare("SELECT id FROM folders WHERE id = ?");
            $check->bind_param("i", $currentFolderId);
            $check->execute();
            $check->store_result();
            if ($check->num_rows === 0) {
                $check->close();
                unlink($targetFilePath);
                $errorMessages[] = 'Folder with ID ' . $currentFolderId . ' not found.';
                continue;
            }
            $check->close();
        }

        // ✅ Insert file record
        $stmt = $conn->prepare(
            "INSERT INTO files (file_name, file_path, file_size, file_type, folder_id, user_id) 
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        if (!$stmt) {
            $errorMessages[] = 'Prepare failed: ' . $conn->error;
            unlink($targetFilePath);
            continue;
        }

        // Normalize target path
        $realPath = realpath($targetFilePath);

        // Find the position of "/uploads" in the full path
        $uploadsPos = strpos($realPath, '/uploads');

        // Extract relative path starting from "/uploads"
        $relativeFilePath = substr($realPath, $uploadsPos);

        $stmt->bind_param(
            "ssisii", 
            $uniqueFileName, 
            $relativeFilePath, // store relative path
            $fileSize, 
            $fileType, 
            $currentFolderId, 
            $userId
        );

        if ($stmt->execute()) {
            $successCount++;
            $newFileId = $conn->insert_id;
            logActivity($conn, $userId, 'upload_file', 'Uploaded file "' . $uniqueFileName . '"', $newFileId, 'file');
        } else {
            unlink($targetFilePath);
            $errorMessages[] = 'DB insert failed for "' . htmlspecialchars($file['name']) . '": ' . $stmt->error;
        }
        $stmt->close();
    }

    if ($successCount > 0 && empty($errorMessages)) {
        echo json_encode(['success' => true, 'message' => "$successCount file(s) uploaded successfully."]);
    } elseif ($successCount > 0) {
        echo json_encode(['success' => true, 'message' => "$successCount file(s) uploaded, with errors: " . implode('; ', $errorMessages)]);
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Upload failed: ' . implode('; ', $errorMessages)]);
    }

} catch (Throwable $e) {
    error_log("Upload API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error.']);
}

$conn->close();
