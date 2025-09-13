<?php
include 'config.php';
include 'functions.php'; // Include functions.php for getFolderPath and logActivity

header('Content-Type: application/json');

// Fungsi untuk menghapus file fisik dari disk
function deleteFileFromDisk($filePath) {
    // Ensure relative paths like "uploads/..." resolve correctly
    $absolutePath = realpath(__DIR__ . '/../' . $filePath); // Perbaikan: __DIR__
    if ($absolutePath && file_exists($absolutePath)) {
        return unlink($absolutePath);
    }
    return true; // File sudah tidak ada, anggap sudah terhapus
}

// Fungsi rekursif untuk menghapus folder fisik beserta isinya (tetap ada untuk delete_forever)
// Fungsi ini hanya akan dipanggil jika folder benar-benar dihapus permanen,
// atau setelah semua isinya dipindahkan ke recycle bin dan entri DB dihapus.
function deleteFolderRecursive($dir) {
    if (!is_dir($dir)) {
        return false;
    }
    $files = array_diff(scandir($dir), array('.', '..'));
    foreach ($files as $file) {
        $itemPath = "$dir/$file";
        (is_dir($itemPath)) ? deleteFolderRecursive($itemPath) : unlink($itemPath);
    }
    return rmdir($dir);
}

// Fungsi baru: Menghapus folder secara logis (memindahkan file ke Recycle Bin)
// dan menghapus entri folder dari tabel 'folders'.
function deleteFolderAndMoveFilesToTrash($conn, $userId, $folderId, $baseUploadDir) {
    $filesMoved = [];
    $foldersProcessed = []; // Mengganti foldersDeleted menjadi foldersProcessed
    $errors = [];

    // 1. Ambil semua file di dalam folder ini dan subfolder-nya
    // Menggunakan rekursif untuk mendapatkan semua file di dalam hierarki folder
    $allFiles = [];
    function getAllFilesRecursive($conn, $currentFolderId, &$filesArray) { // Removed $currentUserId
        // Ambil file di folder saat ini
        $stmt_files = $conn->prepare("SELECT id, file_name, file_path, file_size, file_type, folder_id FROM files WHERE folder_id = ?"); // Removed user_id filter
        $stmt_files->bind_param("i", $currentFolderId);
        $stmt_files->execute();
        $result_files = $stmt_files->get_result();
        while ($file = $result_files->fetch_assoc()) {
            $filesArray[] = $file;
        }
        $stmt_files->close();

        // Ambil subfolder dan panggil rekursif
        $stmt_subfolders = $conn->prepare("SELECT id FROM folders WHERE parent_id = ?"); // Removed user_id filter
        $stmt_subfolders->bind_param("i", $currentFolderId);
        $stmt_subfolders->execute();
        $result_subfolders = $stmt_subfolders->get_result();
        while ($subfolder = $result_subfolders->fetch_assoc()) {
            getAllFilesRecursive($conn, $subfolder['id'], $filesArray); // Removed $currentUserId
        }
        $stmt_subfolders->close();
    }
    getAllFilesRecursive($conn, $folderId, $allFiles); // Removed $userId

    foreach ($allFiles as $file) {
        $fileName = $file['file_name'];
        $filePath = $file['file_path'];
        $fileSize = $file['file_size'];
        $fileType = $file['file_type'];
        $originalFolderId = $file['folder_id'];

        $originalFolderPath = getFolderPath($conn, $originalFolderId);

        // Pindahkan ke deleted_files table
        $stmt_insert_deleted = $conn->prepare("INSERT INTO deleted_files (user_id, file_name, file_path, file_size, file_type, folder_id, original_folder_path, deleted_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt_insert_deleted->bind_param("issisis", $userId, $fileName, $filePath, $fileSize, $fileType, $originalFolderId, $originalFolderPath);
        if ($stmt_insert_deleted->execute()) {
            // Hapus dari tabel files asli
            $stmt_delete_original_file = $conn->prepare("DELETE FROM files WHERE id = ?");
            $stmt_delete_original_file->bind_param("i", $file['id']);
            if ($stmt_delete_original_file->execute()) {
                $filesMoved[] = $fileName;
                // Hapus file fisik
                deleteFileFromDisk($baseUploadDir . $filePath);
            } else {
                $errors[] = "Failed to delete file '" . htmlspecialchars($fileName) . "' from original files table: " . $stmt_delete_original_file->error;
            }
            $stmt_delete_original_file->close();
        } else {
            $errors[] = "Failed to move file '" . htmlspecialchars($fileName) . "' to deleted_files table: " . $stmt_insert_deleted->error;
        }
        $stmt_insert_deleted->close();
    }

    // 2. Ambil semua subfolder (termasuk folder utama) dan hapus entri dari DB
    // Menggunakan rekursif untuk mendapatkan semua folder di dalam hierarki folder
    $allFoldersToDelete = [];
    function getAllSubfoldersRecursive($conn, $currentFolderId, &$foldersArray) { // Removed $currentUserId
        $stmt_subfolders = $conn->prepare("SELECT id, folder_name FROM folders WHERE parent_id = ?"); // Removed user_id filter
        $stmt_subfolders->bind_param("i", $currentFolderId);
        $stmt_subfolders->execute();
        $result_subfolders = $stmt_subfolders->get_result();
        while ($subfolder = $result_subfolders->fetch_assoc()) {
            getAllSubfoldersRecursive($conn, $subfolder['id'], $foldersArray); // Removed $currentUserId
            $foldersArray[] = $subfolder; // Tambahkan subfolder setelah memproses isinya
        }
        $stmt_subfolders->close();
    }
    // Dapatkan semua subfolder terlebih dahulu, lalu tambahkan folder utama
    getAllSubfoldersRecursive($conn, $folderId, $allFoldersToDelete); // Removed $userId

    // Tambahkan folder utama ke daftar untuk dihapus terakhir
    $stmt_main_folder = $conn->prepare("SELECT id, folder_name FROM folders WHERE id = ?"); // Removed user_id filter
    $stmt_main_folder->bind_param("i", $folderId);
    $stmt_main_folder->execute();
    $result_main_folder = $stmt_main_folder->get_result();
    $mainFolder = $result_main_folder->fetch_assoc();
    $stmt_main_folder->close();
    if ($mainFolder) {
        $allFoldersToDelete[] = $mainFolder;
    }

    // Hapus folder dari DB (dari yang paling dalam ke luar)
    foreach (array_reverse($allFoldersToDelete) as $folder) { // Hapus dari yang paling dalam
        $folderName = $folder['folder_name'];
        $folderPath = getFolderPath($conn, $folder['id']); // Dapatkan path fisik folder

        // Pindahkan ke deleted_folders table
        $stmt_insert_deleted_folder = $conn->prepare("INSERT INTO deleted_folders (user_id, folder_name, deleted_at) VALUES (?, ?, NOW())");
        $stmt_insert_deleted_folder->bind_param("is", $userId, $folderName);
        if ($stmt_insert_deleted_folder->execute()) {
            $stmt_delete_original_folder = $conn->prepare("DELETE FROM folders WHERE id = ?");
            $stmt_delete_original_folder->bind_param("i", $folder['id']);
            if ($stmt_delete_original_folder->execute()) {
                $foldersProcessed[] = $folderName;
                // Hapus folder fisik setelah semua isinya dipindahkan/dihapus
                deleteFolderRecursive($baseUploadDir . $folderPath);
            } else {
                $errors[] = "Failed to delete folder '" . htmlspecialchars($folderName) . "' from folders table: " . $stmt_delete_original_folder->error;
            }
            $stmt_delete_original_folder->close();
        } else {
            $errors[] = "Failed to move folder '" . htmlspecialchars($folderName) . "' to deleted_folders table: " . $stmt_insert_deleted_folder->error;
        }
        $stmt_insert_deleted_folder->close();
    }

    return ['filesMoved' => $filesMoved, 'foldersProcessed' => $foldersProcessed, 'errors' => $errors];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    session_start();
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized access. Please log in.']);
        exit;
    }
    $userId = $_SESSION['user_id']; // Keep userId for logging and inserting into deleted_files/folders

    $input = json_decode(file_get_contents('php://input'), true);

    $baseUploadDir = 'uploads/';

    // Cek apakah input berupa array items (multiple delete) atau single id/type
    if (isset($input['items']) && is_array($input['items'])) {
        // Multiple delete
        $items = $input['items'];

        $conn->begin_transaction();
        $allErrors = [];
        $allMessages = [];
        $successCount = 0;

        foreach ($items as $item) {
            $id = isset($item['id']) ? (int)$item['id'] : 0;
            $type = isset($item['type']) ? $item['type'] : '';

            if ($id === 0 || empty($type)) {
                $allErrors[] = "Invalid item ID or type.";
                continue;
            }

            try {
                if ($type === 'file') {
                    $stmt = $conn->prepare("SELECT id, file_name, file_path, file_size, file_type, folder_id FROM files WHERE id = ?"); // Removed user_id filter
                    $stmt->bind_param("i", $id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $file = $result->fetch_assoc();
                    $stmt->close();

                    if ($file) {
                        $fileName = $file['file_name'];
                        $filePath = $file['file_path'];
                        $fileSize = $file['file_size'];
                        $fileType = $file['file_type'];
                        $folderId = $file['folder_id'];

                        // Get original folder path for restoration
                        $originalFolderPath = getFolderPath($conn, $folderId);

                        // Move to deleted_files table
                        $stmt_insert_deleted = $conn->prepare("INSERT INTO deleted_files (user_id, file_name, file_path, file_size, file_type, folder_id, original_folder_path, deleted_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                        $stmt_insert_deleted->bind_param("issisis", $userId, $fileName, $filePath, $fileSize, $fileType, $folderId, $originalFolderPath);

                        if ($stmt_insert_deleted->execute()) {
                            // Delete from original files table
                            $stmt_delete_original = $conn->prepare("DELETE FROM files WHERE id = ?"); // Removed user_id filter
                            $stmt_delete_original->bind_param("i", $id);
                            if ($stmt_delete_original->execute()) {
                                // Delete physical file
                                deleteFileFromDisk($baseUploadDir . $filePath);
                                $successCount++;
                                $allMessages[] = "File '" . htmlspecialchars($fileName) . "' moved to Recycle Bin.";
                                logActivity($conn, $userId, 'move_to_trash_file', "Moved file to trash: " . $fileName);
                            } else {
                                $allErrors[] = "Failed to delete file '" . htmlspecialchars($fileName) . "' from original files table: " . $stmt_delete_original->error;
                            }
                            $stmt_delete_original->close();
                        } else {
                            $allErrors[] = "Failed to move file '" . htmlspecialchars($fileName) . "' to deleted_files table: " . $stmt_insert_deleted->error;
                        }
                        $stmt_insert_deleted->close();

                    } else {
                        $allErrors[] = "File not found with ID: " . $id;
                    }
                } elseif ($type === 'folder') {
                    $stmt = $conn->prepare("SELECT folder_name FROM folders WHERE id = ?"); // Removed user_id filter
                    $stmt->bind_param("i", $id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $folder = $result->fetch_assoc();
                    $stmt->close();

                    if ($folder) {
                        $folderName = $folder['folder_name'];

                        // Call function to delete folder and move files to Recycle Bin
                        $deleteResult = deleteFolderAndMoveFilesToTrash($conn, $userId, $id, $baseUploadDir);

                        $filesMovedCount = count($deleteResult['filesMoved']);
                        $foldersProcessedCount = count($deleteResult['foldersProcessed']);
                        $errors = $deleteResult['errors'];

                        if (!empty($errors)) {
                            $allErrors = array_merge($allErrors, $errors);
                        }

                        $successCount++;
                        $allMessages[] = "Folder '" . htmlspecialchars($folderName) . "' and its contents moved to Recycle Bin. " .
                                       "{$filesMovedCount} file(s) moved. " .
                                       "{$foldersProcessedCount} folder(s) processed.";
                        logActivity($conn, $userId, 'move_to_trash_folder', "Moved folder to trash: " . $folderName . " and its contents.");

                    } else {
                        $allErrors[] = "Folder not found with ID: " . $id;
                    }
                } else {
                    $allErrors[] = "Unknown item type: " . htmlspecialchars($type);
                }
            } catch (Exception $e) {
                $allErrors[] = "Error processing item ID $id: " . $e->getMessage();
            }
        }

        if (empty($allErrors)) {
            $conn->commit();
            echo json_encode(['success' => true, 'message' => implode(" ", $allMessages)]);
        } else {
            $conn->rollback();
            $finalMessage = "Operation completed with errors. " . implode(" ", $allErrors);
            echo json_encode(['success' => false, 'message' => $finalMessage, 'errors' => $allErrors]);
        }

    } else {
        // Single delete (backward compatibility) - This block is less likely to be used with multiple selection
        // but kept for robustness. It will behave the same as the multiple delete for a single item.
        $id = (int)($input['id'] ?? 0);
        $type = $input['type'] ?? '';

        if ($id === 0 || empty($type)) {
            echo json_encode(['success' => false, 'message' => 'Invalid item ID or type.']);
            exit;
        }

        $conn->begin_transaction();
        $success = false;
        $message = '';
        $errors = [];

        try {
            if ($type === 'file') {
                $stmt = $conn->prepare("SELECT id, file_name, file_path, file_size, file_type, folder_id FROM files WHERE id = ?"); // Removed user_id filter
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $result = $stmt->get_result();
                $file = $result->fetch_assoc();
                $stmt->close();

                if ($file) {
                    $fileName = $file['file_name'];
                    $filePath = $file['file_path'];
                    $fileSize = $file['file_size'];
                    $fileType = $file['file_type'];
                    $folderId = $file['folder_id'];

                    // Get original folder path for restoration
                    $originalFolderPath = getFolderPath($conn, $folderId);

                    // Move to deleted_files table
                    $stmt_insert_deleted = $conn->prepare("INSERT INTO deleted_files (user_id, file_name, file_path, file_size, file_type, folder_id, original_folder_path, deleted_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                    $stmt_insert_deleted->bind_param("issisis", $userId, $fileName, $filePath, $fileSize, $fileType, $folderId, $originalFolderPath);

                    if ($stmt_insert_deleted->execute()) {
                        // Delete from original files table
                        $stmt_delete_original = $conn->prepare("DELETE FROM files WHERE id = ?"); // Removed user_id filter
                        $stmt_delete_original->bind_param("i", $id);
                        if ($stmt_delete_original->execute()) {
                            // Delete physical file
                            deleteFileFromDisk($baseUploadDir . $filePath);
                            $success = true;
                            $message = "File '" . htmlspecialchars($fileName) . "' moved to Recycle Bin.";
                            logActivity($conn, $userId, 'move_to_trash_file', "Moved file to trash: " . $fileName);
                        } else {
                            $errors[] = "Failed to delete file '" . htmlspecialchars($fileName) . "' from original files table: " . $stmt_delete_original->error;
                        }
                        $stmt_delete_original->close();
                    } else {
                        $errors[] = "Failed to move file '" . htmlspecialchars($fileName) . "' to deleted_files table: " . $stmt_insert_deleted->error;
                    }
                    $stmt_insert_deleted->close();

                } else {
                    $errors[] = "File not found with ID: " . $id;
                }
            } elseif ($type === 'folder') {
                $stmt = $conn->prepare("SELECT folder_name FROM folders WHERE id = ?"); // Removed user_id filter
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $result = $stmt->get_result();
                $folder = $result->fetch_assoc();
                $stmt->close();

                if ($folder) {
                    $folderName = $folder['folder_name'];

                    // Call function to delete folder and move files to Recycle Bin
                    $deleteResult = deleteFolderAndMoveFilesToTrash($conn, $userId, $id, $baseUploadDir);

                    $filesMovedCount = count($deleteResult['filesMoved']);
                    $foldersProcessedCount = count($deleteResult['foldersProcessed']);
                    $errors = $deleteResult['errors'];

                    if (empty($errors)) {
                        $success = true;
                        $message = "Folder '" . htmlspecialchars($folderName) . "' and its contents moved to Recycle Bin. " .
                                   "{$filesMovedCount} file(s) moved. " .
                                   "{$foldersProcessedCount} folder(s) processed.";
                        logActivity($conn, $userId, 'move_to_trash_folder', "Moved folder to trash: " . $folderName . " and its contents.");
                    } else {
                        $errors = array_merge($errors, $deleteResult['errors']);
                        $message = "Errors occurred during folder deletion.";
                    }
                } else {
                    $errors[] = "Folder not found with ID: " . $id;
                }
            } else {
                $errors[] = "Unknown item type: " . htmlspecialchars($type);
            }

            if ($success && empty($errors)) {
                $conn->commit();
                echo json_encode(['success' => true, 'message' => $message]);
            } else {
                $conn->rollback();
                $finalMessage = "Operation failed. " . implode(" ", $errors);
                echo json_encode(['success' => false, 'message' => $finalMessage, 'errors' => $errors]);
            }

        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
    }
} else {
        echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}

$conn->close();
?>