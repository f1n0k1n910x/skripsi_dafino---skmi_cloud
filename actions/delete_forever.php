<?php
include '../config.php';
include '../functions.php'; // For logActivity

// Tambahkan fungsi ini
function deleteFileFromDisk($filePath) {
    // Ensure relative paths like "uploads/..." resolve correctly
    $absolutePath = realpath(__DIR__ . '/../' . $filePath);
    if ($absolutePath && file_exists($absolutePath)) {
        return unlink($absolutePath);
    }
    return true; // Already gone
}

// Fungsi rekursif untuk menghapus folder fisik beserta isinya
// Diambil dari kode yang sudah diberikan (delete.php)
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

header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access. Please log in.']);
    exit;
}
$userId = $_SESSION['user_id']; // Tetap ambil userId untuk logging

$input = json_decode(file_get_contents('php://input'), true);
$itemsToDelete = $input['items'] ?? [];

if (empty($itemsToDelete)) {
    echo json_encode(['success' => false, 'message' => 'No items selected for permanent deletion.']);
    exit;
}

$conn->begin_transaction();
$successCount = 0;
$failMessages = [];
$baseUploadDir = '../uploads/'; // Sesuaikan dengan direktori upload Anda

try {
    foreach ($itemsToDelete as $item) {
        $id = (int)($item['id'] ?? 0);
        $type = $item['type'] ?? '';

        if ($id === 0 || empty($type)) {
            $failMessages[] = "Invalid item ID or type for one of the selected items.";
            continue;
        }

        if ($type === 'file') {
            // Get file path from deleted_files
            // MODIFIED: Removed user_id filter from SELECT query
            $stmt = $conn->prepare("SELECT file_name, file_path FROM deleted_files WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $file = $result->fetch_assoc();
            $stmt->close();

            if ($file) {
                // Delete physical file (if it still exists)
                // MODIFIED: Menggunakan fungsi deleteFileFromDisk
                if (deleteFileFromDisk($file['file_path'])) {
                    // Delete from deleted_files table
                    // MODIFIED: Removed user_id filter from DELETE query
                    $stmt_delete = $conn->prepare("DELETE FROM deleted_files WHERE id = ?");
                    $stmt_delete->bind_param("i", $id);
                    if ($stmt_delete->execute()) {
                        $successCount++;
                        logActivity($conn, $userId, 'delete_forever_file', "Permanently deleted file: " . $file['file_name']);
                    } else {
                        $failMessages[] = "Failed to delete file from database: " . $stmt_delete->error;
                    }
                    $stmt_delete->close();
                } else {
                    $failMessages[] = "Failed to delete physical file: " . htmlspecialchars($file['file_name']);
                    // Lanjutkan ke item berikutnya jika penghapusan fisik gagal, tetapi jangan rollback transaksi
                    // Ini penting agar item lain yang berhasil dihapus tidak ikut dibatalkan.
                }
            } else {
                $failMessages[] = "File not found in recycle bin with ID: " . $id;
            }
        } elseif ($type === 'folder') {
            // Get folder details from deleted_folders
            // MODIFIED: Removed user_id filter from SELECT query
            // Tambahkan kolom trash_path untuk menghapus folder fisik
            $stmt = $conn->prepare("SELECT folder_name, trash_path FROM deleted_folders WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $folder = $result->fetch_assoc();
            $stmt->close();

            if ($folder) {
                $folderName = $folder['folder_name'];
                $trashPath = $folder['trash_path']; // Ambil jalur fisik folder dari database

                // Hapus folder fisik jika ada
                if (!empty($trashPath) && is_dir($trashPath)) {
                    if (!deleteFolderRecursive($trashPath)) {
                        $failMessages[] = "Failed to delete physical folder and its contents: " . htmlspecialchars($folderName);
                        // Lanjutkan ke item berikutnya jika penghapusan fisik gagal
                        continue;
                    }
                }

                // Delete from deleted_folders table
                // MODIFIED: Removed user_id filter from DELETE query
                $stmt_delete = $conn->prepare("DELETE FROM deleted_folders WHERE id = ?");
                $stmt_delete->bind_param("i", $id);
                if ($stmt_delete->execute()) {
                    $successCount++;
                    logActivity($conn, $userId, 'delete_forever_folder', "Permanently deleted folder: " . $folder['folder_name']);
                } else {
                    $failMessages[] = "Failed to delete folder from database: " . $stmt_delete->error;
                }
                $stmt_delete->close();
            } else {
                $failMessages[] = "Folder not found in recycle bin with ID: " . $id;
            }
        }
    }

    // Commit transaction only if there are no critical errors that prevent all operations
    // If some items failed but others succeeded, we still commit the successful ones.
    $conn->commit();

    if (empty($failMessages)) {
        echo json_encode(['success' => true, 'message' => "Successfully permanently deleted {$successCount} item(s)."]);
    } else {
        // If there are fail messages, it means some items were not found or failed to delete physically/from DB.
        // We still report success for the items that *were* deleted, but include the warnings.
        echo json_encode(['success' => true, 'message' => "Permanent deletion completed with some issues. Successfully deleted {$successCount} item(s). Errors: " . implode(" ", $failMessages)]);
    }

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Error during permanent deletion: ' . $e->getMessage()]);
}

$conn->close();
?>