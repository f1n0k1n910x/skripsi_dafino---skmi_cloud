<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../helpers/utils.php';

function getFolderById($conn, $id) {
    if (!$id) {
        return ['id' => null, 'folder_name' => 'Root', 'parent_id' => null];
    }
    $stmt = $conn->prepare("SELECT * FROM folders WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function getBreadcrumbs($conn, $folderId) {
    $breadcrumbs = [];
    while ($folderId) {
        $stmt = $conn->prepare("SELECT id, folder_name, parent_id FROM folders WHERE id = ?");
        $stmt->bind_param("i", $folderId);
        $stmt->execute();
        $folder = $stmt->get_result()->fetch_assoc();
        if ($folder) {
            array_unshift($breadcrumbs, $folder);
            $folderId = $folder['parent_id'];
        } else {
            break;
        }
    }
    return $breadcrumbs;
}

function getSubfolders($conn, $parentId) {
    $stmt = $conn->prepare("SELECT id, folder_name FROM folders WHERE parent_id " . 
        ($parentId ? "= ?" : "IS NULL"));
    if ($parentId) {
        $stmt->bind_param("i", $parentId);
    }
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function createFolder($conn, $userId, $folderName, $parentFolderId = null, $parentFolderPath = '') {
    try {
        if (empty($folderName)) {
            return ['success' => false, 'message' => 'Folder name cannot be empty.'];
        }

        $baseDir = realpath(__DIR__ . '/../../uploads');
        $targetDir = $baseDir;

        // Append parent folder path for creating the actual directory on disk
        if (!empty($parentFolderPath)) {
            $targetDir .= '/' . $parentFolderPath;
        }

        // Ensure the parent directory exists
        if (!is_dir($targetDir)) {
            if (!mkdir($targetDir, 0777, true)) {
                throw new Exception("Failed to create parent directory: " . $targetDir);
            }
        }

        $uniqueFolderName = generateUniqueFolderName($folderName, $targetDir);
        $fullFolderPath = $targetDir . '/' . $uniqueFolderName;

        if (!mkdir($fullFolderPath, 0777, true)) {
            throw new Exception("Failed to create physical folder: " . $fullFolderPath);
        }

        // Insert folder info into database
        $stmt = $conn->prepare("INSERT INTO folders (folder_name, parent_id, user_id) VALUES (?, ?, ?)");
        if (!$stmt) {
            throw new Exception("Failed to prepare statement: " . $conn->error);
        }

        $stmt->bind_param("sis", $uniqueFolderName, $parentFolderId, $userId);

        if (!$stmt->execute()) {
            // If database insert fails, delete the created directory
            rmdir($fullFolderPath);
            throw new Exception("Failed to execute statement: " . $stmt->error);
        }

        $newFolderId = $conn->insert_id;
        
        // Log activity for folder creation
        logActivity($conn, $userId, 'create_folder', 'Created folder "' . $uniqueFolderName . '"', $newFolderId, 'folder');
        
        $stmt->close();
        return [
            'success' => true, 
            'message' => 'Folder created successfully!', 
            'folder_id' => $newFolderId
        ];

    } catch (Exception $e) {
        error_log("Error in createFolder: " . $e->getMessage());
        
        // Clean up any created directories on error
        if (isset($fullFolderPath) && is_dir($fullFolderPath)) {
            rmdir($fullFolderPath);
        }
        
        return [
            'success' => false, 
            'message' => 'Failed to create folder: ' . $e->getMessage()
        ];
    }
}

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
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $itemPath = "$dir/$file";
        is_dir($itemPath) ? deleteFolderRecursive($itemPath) : unlink($itemPath);
    }
    return rmdir($dir);
}

function deleteFolderAndMoveFilesToTrash($conn, $userId, $folderId, $baseUploadDir) {
    $filesMoved = [];
    $foldersDeleted = [];

    // 1. Move files to trash
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

        $stmt_insert_deleted = $conn->prepare("INSERT INTO deleted_files (user_id, file_name, file_path, file_size, file_type, folder_id, original_folder_path, deleted_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt_insert_deleted->bind_param("issisis", $userId, $fileName, $filePath, $fileSize, $fileType, $originalFolderId, $originalFolderPath);
        if ($stmt_insert_deleted->execute()) {
            $stmt_delete_original_file = $conn->prepare("DELETE FROM files WHERE id = ?");
            $stmt_delete_original_file->bind_param("i", $file['id']);
            if ($stmt_delete_original_file->execute()) {
                $filesMoved[] = $fileName;
                deleteFileFromDisk($baseUploadDir . $filePath);
            }
            $stmt_delete_original_file->close();
        }
        $stmt_insert_deleted->close();
    }
    $stmt_files->close();

    // 2. Process subfolders recursively
    $stmt_folders = $conn->prepare("SELECT id, folder_name FROM folders WHERE parent_id = ?");
    $stmt_folders->bind_param("i", $folderId);
    $stmt_folders->execute();
    $result_folders = $stmt_folders->get_result();
    while ($subfolder = $result_folders->fetch_assoc()) {
        $recursiveResult = deleteFolderAndMoveFilesToTrash($conn, $userId, $subfolder['id'], $baseUploadDir);
        $filesMoved = array_merge($filesMoved, $recursiveResult['filesMoved']);
        $foldersDeleted = array_merge($foldersDeleted, $recursiveResult['foldersDeleted']);

        $stmt_delete_original_folder = $conn->prepare("DELETE FROM folders WHERE id = ?");
        $stmt_delete_original_folder->bind_param("i", $subfolder['id']);
        if ($stmt_delete_original_folder->execute()) {
            $foldersDeleted[] = $subfolder['folder_name'];
        }
        $stmt_delete_original_folder->close();
    }
    $stmt_folders->close();

    // 3. Delete main folder
    $stmt_main_folder_path = $conn->prepare("SELECT folder_name FROM folders WHERE id = ?");
    $stmt_main_folder_path->bind_param("i", $folderId);
    $stmt_main_folder_path->execute();
    $result_main_folder_path = $stmt_main_folder_path->get_result();
    $mainFolderData = $result_main_folder_path->fetch_assoc();
    $stmt_main_folder_path->close();

    if ($mainFolderData) {
        $mainFolderName = $mainFolderData['folder_name'];
        $mainFolderPath = getFolderPath($conn, $folderId);

        $stmt_delete_main_folder = $conn->prepare("DELETE FROM folders WHERE id = ?");
        $stmt_delete_main_folder->bind_param("i", $folderId);
        if ($stmt_delete_main_folder->execute()) {
            $foldersDeleted[] = $mainFolderName;
            deleteFolderRecursive($baseUploadDir . $mainFolderPath);
        }
        $stmt_delete_main_folder->close();
    }

    return ['filesMoved' => $filesMoved, 'foldersDeleted' => $foldersDeleted];
}

/**
 * Delete selected items (files or folders).
 */
function deleteSelectedItems($conn, $userId, array $itemsToDelete, string $baseUploadDir = 'uploads/') {
    $conn->begin_transaction();
    $successCount = 0;
    $failMessages = [];
    $filesMovedToTrashCount = 0;
    $foldersDeletedCount = 0;

    try {
        foreach ($itemsToDelete as $item) {
            $id = (int)($item['id'] ?? 0);
            $type = $item['type'] ?? '';

            if ($id === 0 || empty($type)) {
                $failMessages[] = "Invalid item ID or type.";
                continue;
            }

            if ($type === 'file') {
                // Fetch file
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
                            deleteFileFromDisk($baseUploadDir . $filePath);
                            $successCount++;
                            $filesMovedToTrashCount++;
                            logActivity($conn, $userId, 'move_to_trash_file', "Moved file to trash: " . $fileName);
                        } else {
                            $failMessages[] = "Failed to delete file $fileName from files table.";
                        }
                        $stmt_delete_original->close();
                    } else {
                        $failMessages[] = "Failed to move file $fileName to deleted_files table.";
                    }
                    $stmt_insert_deleted->close();
                } else {
                    $failMessages[] = "File not found with ID: $id";
                }
            } elseif ($type === 'folder') {
                $stmt = $conn->prepare("SELECT folder_name, parent_id FROM folders WHERE id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $result = $stmt->get_result();
                $folder = $result->fetch_assoc();
                $stmt->close();

                if ($folder) {
                    // Insert into deleted_folders
                    $stmt_insert_deleted_folder = $conn->prepare("
                        INSERT INTO deleted_folders (user_id, id, folder_name, parent_id, deleted_at) 
                        VALUES (?, ?, ?, ?, NOW())
                    ");
                    $stmt_insert_deleted_folder->bind_param(
                        "iisi",
                        $userId,
                        $id,
                        $folder['folder_name'],
                        $folder['parent_id']
                    );
            
                    if ($stmt_insert_deleted_folder->execute()) {
                        $deleteResult = deleteFolderAndMoveFilesToTrash($conn, $userId, $id, $baseUploadDir);
                        $filesMovedToTrashCount += count($deleteResult['filesMoved']);
                        $foldersDeletedCount++;
                        $successCount++;
                        logActivity($conn, $userId, 'delete_folder_permanent', "Deleted folder {$folder['folder_name']} and moved its files to trash.");
                    } else {
                        $failMessages[] = "Failed to move folder {$folder['folder_name']} to deleted_folders table.";
                    }
                    $stmt_insert_deleted_folder->close();
                } else {
                    $failMessages[] = "Folder not found with ID: $id";
                }
            } else {
                $failMessages[] = "Unknown type: $type";
            }
        }

        if (empty($failMessages)) {
            $conn->commit();
            return [
                'success' => true,
                'message' => "Operation completed successfully. {$filesMovedToTrashCount} file(s) moved to Recycle Bin. {$foldersDeletedCount} folder(s) deleted."
            ];
        } else {
            $conn->rollback();
            return [
                'success' => false,
                'message' => "Errors: " . implode(" ", $failMessages)
            ];
        }
    } catch (Exception $e) {
        var_dump($e->getMessage());
        die();
        $conn->rollback();
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}
