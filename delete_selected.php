<?php
include 'config.php';
include 'functions.php'; // Include functions.php for getFolderPath and logActivity

header('Content-Type: application/json');

// These functions are now only used by delete_forever.php, not by this file directly.
// They are kept here for completeness if this file were to perform direct physical deletion.
function deleteFileFromDisk($filePath) {
    if (file_exists($filePath)) {
        return unlink($filePath);
    }
    return true;
}

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

// Fungsi baru: Menghapus folder secara fisik dan memindahkan file-filenya ke Recycle Bin
// Fungsi ini diduplikasi dari delete.php agar mandiri, atau bisa dipindahkan ke functions.php
function deleteFolderAndMoveFilesToTrash($conn, $userId, $folderId, $baseUploadDir) {
    $filesMoved = [];
    $foldersDeleted = [];

    // 1. Ambil semua file di dalam folder ini dan subfolder-nya
    $stmt_files = $conn->prepare("SELECT id, file_name, file_path, file_size, file_type, folder_id FROM files WHERE folder_id = ?");
    $stmt_files->bind_param("i", $folderId);
    $stmt_files->execute();
    $result_files = $stmt_files->get_result();
    while ($file = $result_files->fetch_assoc()) {
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
            }
            $stmt_delete_original_file->close();
        }
        $stmt_insert_deleted->close();
    }
    $stmt_files->close();

    // 2. Ambil semua subfolder di dalam folder ini dan rekursif panggil fungsi ini
    $stmt_folders = $conn->prepare("SELECT id, folder_name FROM folders WHERE parent_id = ?");
    $stmt_folders->bind_param("i", $folderId);
    $stmt_folders->execute();
    $result_folders = $stmt_folders->get_result();
    while ($subfolder = $result_folders->fetch_assoc()) {
        $subfolderName = $subfolder['folder_name'];
        $subfolderId = $subfolder['id'];

        // Rekursif panggil untuk subfolder
        $recursiveResult = deleteFolderAndMoveFilesToTrash($conn, $userId, $subfolderId, $baseUploadDir);
        $filesMoved = array_merge($filesMoved, $recursiveResult['filesMoved']);
        $foldersDeleted = array_merge($foldersDeleted, $recursiveResult['foldersDeleted']);

        // Setelah semua isi subfolder diproses, hapus entri subfolder dari DB
        $stmt_delete_original_folder = $conn->prepare("DELETE FROM folders WHERE id = ?");
        $stmt_delete_original_folder->bind_param("i", $subfolderId);
        if ($stmt_delete_original_folder->execute()) {
            $foldersDeleted[] = $subfolderName;
        }
        $stmt_delete_original_folder->close();
    }
    $stmt_folders->close();

    // 3. Hapus folder utama dari DB (jika belum terhapus oleh cascade)
    $stmt_main_folder_path = $conn->prepare("SELECT folder_name, parent_id FROM folders WHERE id = ?");
    $stmt_main_folder_path->bind_param("i", $folderId);
    $stmt_main_folder_path->execute();
    $result_main_folder_path = $stmt_main_folder_path->get_result();
    $mainFolderData = $result_main_folder_path->fetch_assoc();
    $stmt_main_folder_path->close();

    if ($mainFolderData) {
        $mainFolderName = $mainFolderData['folder_name'];
        $mainFolderPath = getFolderPath($conn, $folderId); // Dapatkan path fisik folder

        $stmt_delete_main_folder = $conn->prepare("DELETE FROM folders WHERE id = ?");
        $stmt_delete_main_folder->bind_param("i", $folderId);
        if ($stmt_delete_main_folder->execute()) {
            $foldersDeleted[] = $mainFolderName;
            // Hapus folder fisik setelah semua isinya dipindahkan/dihapus
            deleteFolderRecursive($baseUploadDir . $mainFolderPath);
        }
        $stmt_delete_main_folder->close();
    }

    return ['filesMoved' => $filesMoved, 'foldersDeleted' => $foldersDeleted];
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    session_start();
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized access. Please log in.']);
        exit;
    }
    $userId = $_SESSION['user_id'];

    $input = json_decode(file_get_contents('php://input'), true);
    $itemsToDelete = $input['items'] ?? [];

    if (empty($itemsToDelete)) {
        echo json_encode(['success' => false, 'message' => 'No items selected.']);
        exit;
    }

    $conn->begin_transaction();
    $successCount = 0;
    $failMessages = [];
    $filesMovedToTrashCount = 0;
    $foldersPermanentlyDeletedCount = 0;
    $baseUploadDir = 'uploads/';

    try {
        foreach ($itemsToDelete as $item) {
            $id = (int)($item['id'] ?? 0);
            $type = $item['type'] ?? '';

            if ($id === 0 || empty($type)) {
                $failMessages[] = "Invalid item ID or type for one of the selected items.";
                continue;
            }

            if ($type === 'file') {
                $stmt = $conn->prepare("SELECT file_name, file_path, file_size, file_type, folder_id FROM files WHERE id = ?");
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

                    $originalFolderPath = getFolderPath($conn, $folderId);

                    $stmt_insert_deleted = $conn->prepare("INSERT INTO deleted_files (user_id, file_name, file_path, file_size, file_type, folder_id, original_folder_path, deleted_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                    $stmt_insert_deleted->bind_param("issisis", $userId, $fileName, $filePath, $fileSize, $fileType, $folderId, $originalFolderPath);
                    
                    if ($stmt_insert_deleted->execute()) {
                        $stmt_delete_original = $conn->prepare("DELETE FROM files WHERE id = ?");
                        $stmt_delete_original->bind_param("i", $id);
                        if ($stmt_delete_original->execute()) {
                            // Hapus file fisik
                            deleteFileFromDisk($baseUploadDir . $filePath);
                            $successCount++;
                            $filesMovedToTrashCount++;
                            logActivity($conn, $userId, 'move_to_trash_file', "Moved file to trash: " . $fileName);
                        } else {
                            $failMessages[] = "Failed to delete file '" . htmlspecialchars($fileName) . "' from original files table: " . $stmt_delete_original->error;
                        }
                        $stmt_delete_original->close();
                    } else {
                        $failMessages[] = "Failed to move file '" . htmlspecialchars($fileName) . "' to deleted_files table: " . $stmt_insert_deleted->error;
                    }
                    $stmt_insert_deleted->close();

                } else {
                    $failMessages[] = "File not found with ID: " . $id;
                }
            } elseif ($type === 'folder') {
                $stmt = $conn->prepare("SELECT folder_name FROM folders WHERE id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $result = $stmt->get_result();
                $folder = $result->fetch_assoc();
                $stmt->close();

                if ($folder) {
                    $folderName = $folder['folder_name'];
                    
                    // Panggil fungsi baru untuk menghapus folder dan memindahkan file ke Recycle Bin
                    $deleteResult = deleteFolderAndMoveFilesToTrash($conn, $userId, $id, $baseUploadDir);
                    
                    $filesMovedToTrashCount += count($deleteResult['filesMoved']);
                    $foldersPermanentlyDeletedCount++; // Hitung folder utama yang dihapus
                    $successCount++;
                    logActivity($conn, $userId, 'delete_folder_permanent', "Permanently deleted folder: " . $folderName . " and moved its files to trash.");

                } else {
                    $failMessages[] = "Folder not found with ID: " . $id;
                }
            } else {
                $failMessages[] = "Unknown item type: " . htmlspecialchars($type);
            }
        }

        if (empty($failMessages)) {
            $conn->commit();
            $message = "Operation completed successfully. ";
            if ($filesMovedToTrashCount > 0) {
                $message .= "{$filesMovedToTrashCount} file(s) moved to Recycle Bin. ";
            }
            if ($foldersPermanentlyDeletedCount > 0) {
                $message .= "{$foldersPermanentlyDeletedCount} folder(s) permanently deleted.";
            }
            echo json_encode(['success' => true, 'message' => $message]);
        } else {
            $conn->rollback();
            $message = "Operation completed with errors. ";
            if ($filesMovedToTrashCount > 0) {
                $message .= "{$filesMovedToTrashCount} file(s) moved to Recycle Bin. ";
            }
            if ($foldersPermanentlyDeletedCount > 0) {
                $message .= "{$foldersPermanentlyDeletedCount} folder(s) permanently deleted. ";
            }
            $message .= "Errors: " . implode(" ", $failMessages);
            echo json_encode(['success' => false, 'message' => $message]);
        }

    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }

} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}

$conn->close();
?>
