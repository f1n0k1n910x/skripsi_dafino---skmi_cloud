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

header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access. Please log in.']);
    exit;
}
$userId = $_SESSION['user_id'];

$input = json_decode(file_get_contents('php://input'), true);
$itemsToDelete = $input['items'] ?? [];

if (empty($itemsToDelete)) {
    echo json_encode(['success' => false, 'message' => 'No items selected for permanent deletion.']);
    exit;
}

$conn->begin_transaction();
$successCount = 0;
$failMessages = [];

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
            $stmt = $conn->prepare("SELECT file_name, file_path FROM deleted_files WHERE id = ? AND user_id = ?");
            $stmt->bind_param("ii", $id, $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            $file = $result->fetch_assoc();
            $stmt->close();

            if ($file) {
                // Delete physical file (if it still exists)
                // MODIFIED: Menggunakan fungsi deleteFileFromDisk
                if (deleteFileFromDisk($file['file_path'])) {
                    // Delete from deleted_files table
                    $stmt_delete = $conn->prepare("DELETE FROM deleted_files WHERE id = ? AND user_id = ?");
                    $stmt_delete->bind_param("ii", $id, $userId);
                    if ($stmt_delete->execute()) {
                        $successCount++;
                        logActivity($conn, $userId, 'delete_forever_file', "Permanently deleted file: " . $file['file_name']);
                    } else {
                        $failMessages[] = "Failed to delete file from database: " . $stmt_delete->error;
                    }
                    $stmt_delete->close();
                } else {
                    $failMessages[] = "Failed to delete physical file: " . htmlspecialchars($file['file_name']);
                    continue; // Lanjutkan ke item berikutnya jika penghapusan fisik gagal
                }
            } else {
                $failMessages[] = "File not found in recycle bin with ID: " . $id;
            }
        } elseif ($type === 'folder') {
            // Get folder details from deleted_folders
            $stmt = $conn->prepare("SELECT folder_name FROM deleted_folders WHERE id = ? AND user_id = ?");
            $stmt->bind_param("ii", $id, $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            $folder = $result->fetch_assoc();
            $stmt->close();

            if ($folder) {
                // Recursively delete all associated files and subfolders from 'deleted_files' and 'deleted_folders'
                // This requires a recursive function to find all children of this deleted folder in the trash.
                // For simplicity, we'll just delete the top-level folder entry.
                // A more robust solution would involve a recursive DB deletion.

                // Delete from deleted_folders table
                $stmt_delete = $conn->prepare("DELETE FROM deleted_folders WHERE id = ? AND user_id = ?");
                $stmt_delete->bind_param("ii", $id, $userId);
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

    if (empty($failMessages)) {
        $conn->commit();
        echo json_encode(['success' => true, 'message' => "Successfully permanently deleted {$successCount} item(s)."]);
    } else {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => "Permanent deletion completed with errors. " . implode(" ", $failMessages)]);
    }

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Error during permanent deletion: ' . $e->getMessage()]);
}

$conn->close();
?>
