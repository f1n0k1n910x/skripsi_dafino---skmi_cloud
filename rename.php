<?php
include 'config.php';
include 'functions.php'; // For generateUniqueFileName

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $itemId = (int)$_POST['itemId'];
    $itemType = $_POST['itemType']; // 'file' or 'folder'
    $newName = trim($_POST['newName']);

    if (empty($newName)) {
        echo json_encode(['success' => false, 'message' => 'New name cannot be empty.']);
        exit;
    }

    session_start(); // Pastikan session sudah dimulai untuk mendapatkan user_id
    $userId = $_SESSION['user_id'] ?? null;
    if (!$userId) {
        echo json_encode(['success' => false, 'message' => 'User not logged in.']);
        exit();
    }

    // Start transaction for atomicity
    $conn->begin_transaction();
    $allSuccess = true;
    $message = '';
    $oldName = ''; // To store the old name for logging

    try {
        if ($itemType === 'file') {
            $stmt = $conn->prepare("SELECT file_name, file_path, folder_id FROM files WHERE id = ?");
            $stmt->bind_param("i", $itemId);
            $stmt->execute();
            $result = $stmt->get_result();
            $file = $result->fetch_assoc();
            $stmt->close();

            if ($file) {
                $oldFilePath = $file['file_path'];
                $oldFileName = $file['file_name'];
                $oldName = $oldFileName; // Store old name for logging
                $fileExtension = pathinfo($oldFileName, PATHINFO_EXTENSION);
                $newFileNameWithExt = $newName;

                // Add extension back if user didn't provide it, or ensure it's correct
                if (!empty($fileExtension) && strtolower(pathinfo($newName, PATHINFO_EXTENSION)) !== strtolower($fileExtension)) {
                     $newFileNameWithExt = $newName . '.' . $fileExtension;
                } else if (empty($fileExtension) && pathinfo($newName, PATHINFO_EXTENSION)) {
                    // If original had no extension but new name has one, use new name as is.
                } else if (!empty($fileExtension) && empty(pathinfo($newName, PATHINFO_EXTENSION))) {
                     $newFileNameWithExt = $newName . '.' . $fileExtension;
                }

                // Determine the directory of the file
                $currentDir = dirname($oldFilePath);
                $newFullFilePath = $currentDir . '/' . $newFileNameWithExt;

                // Check if a file with the new name already exists in the same directory (case-insensitive for some OS)
                if (file_exists($newFullFilePath) && strtolower($newFullFilePath) !== strtolower($oldFilePath)) {
                    throw new Exception('A file with that name already exists in this directory.');
                }

                // Rename on disk
                if (!rename($oldFilePath, $newFullFilePath)) {
                    throw new Exception('Failed to rename physical file.');
                }

                // Update database
                $stmt = $conn->prepare("UPDATE files SET file_name = ?, file_path = ? WHERE id = ?");
                $stmt->bind_param("ssi", $newFileNameWithExt, $newFullFilePath, $itemId);
                if (!$stmt->execute()) {
                    throw new Exception('Failed to update file info in database.');
                }
                $stmt->close();
                $message = 'File renamed successfully!';
                // NEW: Log activity for file rename
                logActivity($conn, $userId, 'rename_file', 'Renamed file "' . $oldName . '" to "' . $newFileNameWithExt . '"', $itemId, 'file');

            } else {
                throw new Exception('File not found.');
            }
        } elseif ($itemType === 'folder') {
            $stmt = $conn->prepare("SELECT folder_name, parent_id FROM folders WHERE id = ?");
            $stmt->bind_param("i", $itemId);
            $stmt->execute();
            $result = $stmt->get_result();
            $folder = $result->fetch_assoc();
            $stmt->close();

            if ($folder) {
                $oldFolderName = $folder['folder_name'];
                $oldName = $oldFolderName; // Store old name for logging
                $parentFolderId = $folder['parent_id'];

                // --- RECONSTRUCTING THE OLD PHYSICAL PATH ---
                // This is the critical part. We need the *actual* physical path.
                // A more robust solution would store full_path in DB, but given constraints,
                // we'll reconstruct it carefully.
                $oldFullFolderPath = 'uploads';
                if ($parentFolderId !== NULL) {
                    $pathComponents = [];
                    $tempFolderId = $parentFolderId;
                    while($tempFolderId !== NULL) {
                        $s = $conn->prepare("SELECT folder_name, parent_id FROM folders WHERE id = ?");
                        $s->bind_param("i", $tempFolderId);
                        $s->execute();
                        $r = $s->get_result();
                        $f = $r->fetch_assoc();
                        if ($f) {
                            array_unshift($pathComponents, $f['folder_name']);
                            $tempFolderId = $f['parent_id'];
                        } else {
                            // Parent folder not found, something is inconsistent.
                            // Fallback to root 'uploads' or throw error.
                            // For now, break and use what we have.
                            break;
                        }
                    }
                    $oldFullFolderPath .= '/' . implode('/', $pathComponents);
                }
                $oldFullFolderPath .= '/' . $oldFolderName;

                $newFullFolderPath = dirname($oldFullFolderPath) . '/' . $newName;

                // Check if a folder with the new name already exists in the same parent
                if (file_exists($newFullFolderPath) && strtolower($newFullFolderPath) !== strtolower($oldFullFolderPath)) {
                    throw new Exception('A folder with that name already exists in this directory.');
                }

                // Rename on disk
                if (!rename($oldFullFolderPath, $newFullFolderPath)) {
                    throw new Exception('Failed to rename physical folder. Check permissions or if folder is in use.');
                }

                // Update database. updated_at will be automatically updated by MySQL trigger.
                $stmt = $conn->prepare("UPDATE folders SET folder_name = ? WHERE id = ?");
                $stmt->bind_param("si", $newName, $itemId);
                if (!$stmt->execute()) {
                    // If DB update fails, attempt to revert physical rename
                    rename($newFullFolderPath, $oldFullFolderPath);
                    throw new Exception('Failed to update folder info in database.');
                }
                $stmt->close();

                // If folder renamed, update all file_path for contained files and subfolders
                // This is crucial for nested files and folders!
                // Update files
                $stmtUpdateFiles = $conn->prepare("
                    UPDATE files
                    SET file_path = REPLACE(file_path, ?, ?)
                    WHERE file_path LIKE ?
                ");
                // Ensure the old path prefix ends with a slash for accurate replacement
                $oldPathPrefixForReplace = rtrim($oldFullFolderPath, '/') . '/';
                $newPathPrefixForReplace = rtrim($newFullFolderPath, '/') . '/';
                $likePattern = $oldPathPrefixForReplace . '%'; // Match files inside this folder

                $stmtUpdateFiles->bind_param("sss", $oldPathPrefixForReplace, $newPathPrefixForReplace, $likePattern);
                if (!$stmtUpdateFiles->execute()) {
                    // Log error but don't fail overall transaction if physical rename succeeded
                    error_log("Failed to update file paths after folder rename for folder ID: $itemId: " . $conn->error);
                }
                $stmtUpdateFiles->close();

                // Update subfolders (their parent_id doesn't change, but their physical path does)
                // This is more complex. If you store full_path in folders table, you'd update it here.
                // Without full_path in folders table, this is implicitly handled by the reconstruction logic
                // when accessing subfolders, but it's good to be aware.
                // For now, the current reconstruction logic in index.php and rename.php will handle it.

                $message = 'Folder renamed successfully!';
                // NEW: Log activity for folder rename
                logActivity($conn, $userId, 'rename_folder', 'Renamed folder "' . $oldName . '" to "' . $newName . '"', $itemId, 'folder');

            } else {
                throw new Exception('Folder not found.');
            }
        } else {
            throw new Exception('Invalid item type.');
        }

        $conn->commit(); // Commit transaction if all operations successful
        echo json_encode(['success' => true, 'message' => $message]);

    } catch (Exception $e) {
        $conn->rollback(); // Rollback on error
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }

} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}

$conn->close();
?>
