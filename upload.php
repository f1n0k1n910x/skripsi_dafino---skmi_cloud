<?php
// upload.php

include 'config.php';
include 'functions.php'; // For generateUniqueFileName

// --- Error handling setup ---
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't echo PHP errors (breaks JSON)
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php-error.log'); // log errors instead

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'Metode permintaan tidak valid.']);
        exit();
    }

    // --- Handle folder_id ---
    $currentFolderId = isset($_POST['current_folder_id']) && $_POST['current_folder_id'] !== '' 
        ? (int)$_POST['current_folder_id'] 
        : null;

    // ✅ Treat 0 as NULL (root upload)
    if ($currentFolderId === 0) {
        $currentFolderId = null;
    }

    $currentFolderPath = isset($_POST['current_folder_path']) ? $_POST['current_folder_path'] : '';

    session_start(); // start session for user_id
    $userId = $_SESSION['user_id'] ?? null;
    if (!$userId) {
        echo json_encode(['success' => false, 'message' => 'User not logged in.']);
        exit();
    }

    if (!isset($_FILES['fileToUpload']) || !is_array($_FILES['fileToUpload']['name'])) {
        echo json_encode(['success' => false, 'message' => 'Tidak ada file yang diunggah atau format unggahan tidak valid.']);
        exit();
    }

    $uploadedFilesCount = count($_FILES['fileToUpload']['name']);
    $successCount = 0;
    $errorMessages = [];

    for ($i = 0; $i < $uploadedFilesCount; $i++) {
        $file = [
            'name' => $_FILES['fileToUpload']['name'][$i],
            'type' => $_FILES['fileToUpload']['type'][$i],
            'tmp_name' => $_FILES['fileToUpload']['tmp_name'][$i],
            'error' => $_FILES['fileToUpload']['error'][$i],
            'size' => $_FILES['fileToUpload']['size'][$i],
        ];

        // --- Handle PHP upload errors ---
        switch ($file['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $errorMessages[] = 'Ukuran file "' . htmlspecialchars($file['name']) . '" melebihi batas maksimum.';
                continue 2;
            case UPLOAD_ERR_PARTIAL:
                $errorMessages[] = 'File "' . htmlspecialchars($file['name']) . '" hanya terunggah sebagian.';
                continue 2;
            case UPLOAD_ERR_NO_FILE:
                $errorMessages[] = 'Tidak ada file yang diunggah.';
                continue 2;
            default:
                $errorMessages[] = 'Kesalahan upload "' . htmlspecialchars($file['name']) . '" (kode ' . $file['error'] . ').';
                continue 2;
        }

        $uploadDirBase = 'uploads';
        $targetDir = $uploadDirBase;

        if (!is_dir($targetDir) && !mkdir($targetDir, 0777, true)) {
            $errorMessages[] = 'Gagal membuat direktori dasar upload.';
            continue;
        }

        if (!empty($currentFolderPath)) {
            $targetDir .= '/' . $currentFolderPath;
            if (!is_dir($targetDir) && !mkdir($targetDir, 0777, true)) {
                $errorMessages[] = 'Gagal membuat direktori folder untuk "' . htmlspecialchars($file['name']) . '".';
                continue;
            }
        }

        $originalFileName = basename($file['name']);
        $uniqueFileName = generateUniqueFileName($originalFileName, $targetDir);
        $targetFilePath = $targetDir . '/' . $uniqueFileName;
        $fileType = pathinfo($uniqueFileName, PATHINFO_EXTENSION);
        $fileSize = $file['size'];

        if (move_uploaded_file($file['tmp_name'], $targetFilePath)) {

            // --- Validate folder_id if provided ---
            if ($currentFolderId !== null) {
                $check = $conn->prepare("SELECT id FROM folders WHERE id = ?");
                $check->bind_param("i", $currentFolderId);
                $check->execute();
                $check->store_result();
                if ($check->num_rows === 0) {
                    $check->close();
                    unlink($targetFilePath);
                    $errorMessages[] = 'Folder dengan ID ' . $currentFolderId . ' tidak ditemukan.';
                    continue;
                }
                $check->close();
            }

            // --- Insert file record ---
            if ($currentFolderId === null) {
                $stmt = $conn->prepare(
                    "INSERT INTO files (file_name, file_path, file_size, file_type, folder_id, user_id) 
                     VALUES (?, ?, ?, ?, NULL, ?)"
                );
                if (!$stmt) {
                    $errorMessages[] = 'Prepare failed: ' . $conn->error;
                    unlink($targetFilePath);
                    continue;
                }
                $stmt->bind_param("ssisi", 
                    $uniqueFileName, 
                    $targetFilePath, 
                    $fileSize, 
                    $fileType, 
                    $userId
                );
            } else {
                $stmt = $conn->prepare(
                    "INSERT INTO files (file_name, file_path, file_size, file_type, folder_id, user_id) 
                     VALUES (?, ?, ?, ?, ?, ?)"
                );
                if (!$stmt) {
                    $errorMessages[] = 'Prepare failed: ' . $conn->error;
                    unlink($targetFilePath);
                    continue;
                }
                $stmt->bind_param("ssissi", 
                    $uniqueFileName, 
                    $targetFilePath, 
                    $fileSize, 
                    $fileType, 
                    $currentFolderId, 
                    $userId
                );
            }

            if ($stmt->execute()) {
                $successCount++;
                $newFileId = $conn->insert_id;
                // Optional: only call if you have logActivity defined
                if (function_exists('logActivity')) {
                    logActivity($conn, $userId, 'upload_file', 'Uploaded file "' . $uniqueFileName . '"', $newFileId, 'file');
                }
            } else {
                unlink($targetFilePath);
                $errorMessages[] = 'Gagal menyimpan info file "' . htmlspecialchars($file['name']) . '" ke database: ' . $stmt->error;
            }
            $stmt->close();
        } else {
            $errorMessages[] = 'Gagal memindahkan file "' . htmlspecialchars($file['name']) . '".';
        }
    }

    // --- JSON Response ---
    if ($successCount > 0 && empty($errorMessages)) {
        echo json_encode(['success' => true, 'message' => "$successCount file berhasil diunggah!"]);
    } elseif ($successCount > 0) {
        echo json_encode(['success' => true, 'message' => "$successCount file berhasil diunggah, namun ada kesalahan: " . implode('; ', $errorMessages)]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal mengunggah file: ' . implode('; ', $errorMessages)]);
    }

} catch (Throwable $e) {
    // Catch fatal errors and return JSON
    error_log("Upload error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error. Silakan cek log.']);
}

$conn->close();
