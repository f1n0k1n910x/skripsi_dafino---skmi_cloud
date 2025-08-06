<?php
include 'config.php';
include 'functions.php'; // For generateUniqueFileName

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentFolderId = isset($_POST['current_folder_id']) && $_POST['current_folder_id'] !== '' ? (int)$_POST['current_folder_id'] : NULL;
    $currentFolderPath = isset($_POST['current_folder_path']) ? $_POST['current_folder_path'] : '';

    session_start(); // Pastikan session sudah dimulai untuk mendapatkan user_id
    $userId = $_SESSION['user_id'] ?? null;
    if (!$userId) {
        echo json_encode(['success' => false, 'message' => 'User not logged in.']);
        exit();
    }

    // Check if files were uploaded
    if (isset($_FILES['fileToUpload']) && is_array($_FILES['fileToUpload']['name'])) {
        $uploadedFilesCount = count($_FILES['fileToUpload']['name']);
        $successCount = 0;
        $errorMessages = [];

        // Loop through each uploaded file
        for ($i = 0; $i < $uploadedFilesCount; $i++) {
            $file = [
                'name' => $_FILES['fileToUpload']['name'][$i],
                'type' => $_FILES['fileToUpload']['type'][$i],
                'tmp_name' => $_FILES['fileToUpload']['tmp_name'][$i],
                'error' => $_FILES['fileToUpload']['error'][$i],
                'size' => $_FILES['fileToUpload']['size'][$i],
            ];

            // --- START OPTIMIZATION FOR UPLOAD ---

            // 1. Handle PHP upload errors more specifically
            switch ($file['error']) {
                case UPLOAD_ERR_OK:
                    // File uploaded successfully
                    break;
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    $errorMessages[] = 'Ukuran file "' . htmlspecialchars($file['name']) . '" melebihi batas maksimum yang diizinkan oleh server.';
                    continue 2; // Skip to next file
                case UPLOAD_ERR_PARTIAL:
                    $errorMessages[] = 'File "' . htmlspecialchars($file['name']) . '" hanya terunggah sebagian. Silakan coba lagi.';
                    continue 2;
                case UPLOAD_ERR_NO_FILE:
                    $errorMessages[] = 'Tidak ada file yang diunggah untuk entri ini.';
                    continue 2;
                case UPLOAD_ERR_NO_TMP_DIR:
                    $errorMessages[] = 'Direktori sementara untuk upload tidak ditemukan untuk file "' . htmlspecialchars($file['name']) . '".';
                    continue 2;
                case UPLOAD_ERR_CANT_WRITE:
                    $errorMessages[] = 'Gagal menulis file "' . htmlspecialchars($file['name']) . '" ke disk.';
                    continue 2;
                case UPLOAD_ERR_EXTENSION:
                    $errorMessages[] = 'Ekstensi PHP menghentikan upload file "' . htmlspecialchars($file['name']) . '".';
                    continue 2;
                default:
                    $errorMessages[] = 'Terjadi kesalahan upload yang tidak diketahui untuk file "' . htmlspecialchars($file['name']) . '": ' . $file['error'];
                    continue 2;
            }

            // 2. Define allowed extensions (optional but good practice for security)
            // Given your extensive list, we'll allow almost anything.
            // If you want to restrict, uncomment and modify this array.
            /*
            $allowedExtensions = [
                'doc', 'docx', 'pdf', 'ppt', 'pptx', 'xls', 'xlsx', 'txt', 'odt', 'odp', 'rtf', 'md', 'log', 'csv', 'tex', // Dokumen
                'mp3', 'wav', 'aac', 'ogg', 'flac', 'm4a', 'alac', 'wma', 'opus', 'amr', 'mid', // Musik
                'mp4', 'mkv', 'avi', 'mov', 'wmv', 'flv', 'webm', '3gp', 'm4v', 'mpg', 'mpeg', 'ts', 'ogv', // Video
                'html', 'htm', 'css', 'js', 'php', 'py', 'java', 'json', 'xml', 'ts', 'tsx', 'jsx', 'vue', 'cpp', 'c', 'cs', 'rb', 'go', 'swift', 'sql', 'sh', 'bat', 'ini', 'yml', 'yaml', 'pl', 'r', // Kode
                'zip', 'rar', '7z', 'tar', 'gz', 'bz2', 'xz', 'iso', 'cab', 'arj', // Arsip
                'exe', 'msi', 'apk', 'ipa', 'jar', 'appimage', 'dmg', 'bin', // Instalasi
                'torrent', 'nzb', 'ed2k', 'part', '!ut', // Peer-to-Peer
                'jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg', 'tiff' // Gambar
            ];
            $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            if (!in_array($fileExtension, $allowedExtensions)) {
                $errorMessages[] = 'Jenis file "' . htmlspecialchars($file['name']) . '" tidak diizinkan.';
                continue 2; // Skip to next file
            }
            */

            // 3. Validate file size (redundant if php.ini is set, but good for client-side feedback)
            // This check is less critical if php.ini is configured correctly, as PHP will stop it first.
            // $maxFileSize = 2 * 1024 * 1024 * 1024; // Example: 2GB in bytes
            // if ($file['size'] > $maxFileSize) {
            //     $errorMessages[] = 'Ukuran file "' . htmlspecialchars($file['name']) . '" terlalu besar. Maksimum ' . formatBytes($maxFileSize) . '.';
            //     continue 2; // Skip to next file
            // }

            // --- END OPTIMIZATION FOR UPLOAD ---

            $uploadDirBase = 'uploads';
            $targetDir = $uploadDirBase;

            // Create base upload directory if it doesn't exist
            if (!is_dir($targetDir)) {
                if (!mkdir($targetDir, 0777, true)) {
                    $errorMessages[] = 'Gagal membuat direktori dasar upload.';
                    continue; // Continue to next file, but this is a critical error
                }
            }

            // Append current folder path if available
            if (!empty($currentFolderPath)) {
                $targetDir .= '/' . $currentFolderPath;
                if (!is_dir($targetDir)) {
                    // This shouldn't happen if folders are managed correctly, but good for robustness
                    if (!mkdir($targetDir, 0777, true)) {
                        $errorMessages[] = 'Gagal membuat direktori folder saat ini untuk file "' . htmlspecialchars($file['name']) . '".';
                        continue;
                    }
                }
            }
            
            $originalFileName = basename($file['name']);
            $uniqueFileName = generateUniqueFileName($originalFileName, $targetDir);
            $targetFilePath = $targetDir . '/' . $uniqueFileName;
            $fileType = pathinfo($uniqueFileName, PATHINFO_EXTENSION);
            $fileSize = $file['size'];

            if (move_uploaded_file($file['tmp_name'], $targetFilePath)) {
                // Insert file info into database
                $stmt = $conn->prepare("INSERT INTO files (file_name, file_path, file_size, file_type, folder_id, user_id) VALUES (?, ?, ?, ?, ?, ?)"); // Added user_id
                $stmt->bind_param("ssisii", $uniqueFileName, $targetFilePath, $fileSize, $fileType, $currentFolderId, $userId); // Added $userId

                if ($stmt->execute()) {
                    $successCount++;
                    // NEW: Log activity for file upload
                    $newFileId = $conn->insert_id;
                    logActivity($conn, $userId, 'upload_file', 'Uploaded file "' . $uniqueFileName . '"', $newFileId, 'file');
                } else {
                    // If database insert fails, try to delete the uploaded file
                    unlink($targetFilePath);
                    $errorMessages[] = 'Gagal menyimpan info file "' . htmlspecialchars($file['name']) . '" ke database: ' . $stmt->error;
                }
                $stmt->close();
            } else {
                $errorMessages[] = 'Gagal memindahkan file "' . htmlspecialchars($file['name']) . '". Pastikan izin direktori sudah benar.';
            }
        } // End of for loop

        if ($successCount > 0 && empty($errorMessages)) {
            echo json_encode(['success' => true, 'message' => $successCount . ' file berhasil diunggah!']);
        } elseif ($successCount > 0 && !empty($errorMessages)) {
            echo json_encode(['success' => true, 'message' => $successCount . ' file berhasil diunggah, namun ada beberapa kesalahan: ' . implode('; ', $errorMessages)]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal mengunggah file: ' . implode('; ', $errorMessages)]);
        }

    } else {
        echo json_encode(['success' => false, 'message' => 'Tidak ada file yang diunggah atau format unggahan tidak valid.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Metode permintaan tidak valid.']);
}

$conn->close();
?>
