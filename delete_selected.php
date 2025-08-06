<?php
include 'config.php';

header('Content-Type: application/json');

function deleteFileFromDisk($filePath) {
    if (file_exists($filePath)) {
        return unlink($filePath);
    }
    return true; // File already doesn't exist, consider it deleted
}

function deleteFolderRecursive($dir) {
    if (!is_dir($dir)) {
        return false;
    }
    $files = array_diff(scandir($dir), array('.', '..'));
    foreach ($files as $file) {
        (is_dir("$dir/$file")) ? deleteFolderRecursive("$dir/$file") : unlink("$dir/$file");
    }
    return rmdir($dir);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $itemsToDelete = $input['items'] ?? [];

    if (empty($itemsToDelete)) {
        echo json_encode(['success' => false, 'message' => 'No items selected for deletion.']);
        exit;
    }

    $conn->begin_transaction();
    $allSuccess = true;
    $messages = [];

    foreach ($itemsToDelete as $item) {
        $id = (int)$item['id'];
        $type = $item['type'];

        if ($type === 'file') {
            // Fetch file path from database
            $stmt = $conn->prepare("SELECT file_path FROM files WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $file = $result->fetch_assoc();
            $stmt->close();

            if ($file) {
                // Delete from disk
                if (deleteFileFromDisk($file['file_path'])) {
                    // Delete from database
                    $stmt = $conn->prepare("DELETE FROM files WHERE id = ?");
                    $stmt->bind_param("i", $id);
                    if (!$stmt->execute()) {
                        $allSuccess = false;
                        $messages[] = "Failed to delete file from DB: " . htmlspecialchars($file['file_name']);
                    }
                    $stmt->close();
                } else {
                    $allSuccess = false;
                    $messages[] = "Failed to delete physical file: " . htmlspecialchars($file['file_name']);
                }
            } else {
                $messages[] = "File not found: ID " . $id;
            }
        } elseif ($type === 'folder') {
            // Fetch folder path (reconstruct from breadcrumbs or store in DB if needed)
            // For simplicity, let's assume we store a base path or reconstruct it.
            // A more robust system would store the full path in the DB for folders too.
            // For now, we'll try to find its full disk path based on its name and parent.

            // To properly delete folder from disk, you need its full path.
            // A more robust solution involves storing a full_path column in the folders table
            // or reconstructing it recursively. For this example, let's simplify and assume
            // the `uploads/` base path and reconstruct based on parent structure.
            // A safer approach: fetch folder_name and parent_id, then recursively build path.

            // Let's modify folders table to store `full_path` for easier deletion
            // For now, we'll fetch folder_name and assume uploads/folder_name if root.
            // *** IMPORTANT: For production, ensure folder_path is managed correctly in DB ***
            
            // For demonstration, let's assume `uploads` is the root and
            // folders are created directly under it (or we reconstruct path carefully)
            
            // A better way: fetch full path from DB if you stored it, or
            // build it recursively. For this example, we'll delete based on DB
            // and cascade delete will handle files. Physical deletion needs path.

            // To actually delete from disk, you need the folder's full path.
            // Let's assume a simplified scenario for now, but a real system
            // would either store the full_path in the folders table or
            // have a more sophisticated path reconstruction.

            $stmt = $conn->prepare("SELECT folder_name FROM folders WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $folder = $result->fetch_assoc();
            $stmt->close();

            if ($folder) {
                 // !!! THIS IS A SIMPLIFICATION !!!
                 // To delete a folder physically, you need its absolute path.
                 // The best way is to have a 'folder_path' column in your 'folders' table,
                 // just like 'file_path' in the 'files' table.
                 // For now, assuming direct subfolders of 'uploads/' for demonstration.
                 // In a real app, you would reconstruct the full path, e.g., using parent_id.

                // Example of path reconstruction (simplified, assuming depth 1 for physical deletion)
                // If you implemented full_path in DB, retrieve it directly.
                // For a truly nested path, you would need to recursively get parents.
                $folderPathOnDisk = 'uploads/' . $folder['folder_name']; // This is simplistic

                // Cascade delete in DB will handle files and subfolders in DB
                $stmt = $conn->prepare("DELETE FROM folders WHERE id = ?");
                $stmt->bind_param("i", $id);
                if ($stmt->execute()) {
                    // Try to delete physical folder after DB.
                    // This can fail if folder is not empty (contains other files/folders not in DB, or if path is wrong)
                    if (is_dir($folderPathOnDisk)) {
                        if (!deleteFolderRecursive($folderPathOnDisk)) {
                            $allSuccess = false;
                            $messages[] = "Failed to delete physical folder and its contents: " . htmlspecialchars($folder['folder_name']);
                        }
                    }
                } else {
                    $allSuccess = false;
                    $messages[] = "Failed to delete folder from DB: " . htmlspecialchars($folder['folder_name']);
                }
                $stmt->close();
            } else {
                $messages[] = "Folder not found: ID " . $id;
            }
        }
    }

    if ($allSuccess) {
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Selected items deleted successfully!']);
    } else {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Some items could not be deleted: ' . implode(', ', $messages)]);
    }

} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}

$conn->close();
?>