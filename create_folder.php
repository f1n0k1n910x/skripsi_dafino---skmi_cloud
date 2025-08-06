<?php
include 'config.php';
include 'functions.php'; // For generateUniqueFolderName

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $folderName = isset($_POST['folderName']) ? trim($_POST['folderName']) : '';
    $parentFolderId = isset($_POST['parent_folder_id']) && $_POST['parent_folder_id'] !== '' ? (int)$_POST['parent_folder_id'] : NULL;
    $parentFolderPath = isset($_POST['parent_folder_path']) ? $_POST['parent_folder_path'] : '';

    if (empty($folderName)) {
        echo json_encode(['success' => false, 'message' => 'Folder name cannot be empty.']);
        exit;
    }

    $baseDir = 'uploads'; // Base directory for all cloud storage files/folders
    $targetDir = $baseDir;

    // Append parent folder path for creating the actual directory on disk
    if (!empty($parentFolderPath)) {
        $targetDir .= '/' . $parentFolderPath;
    }

    // Ensure the parent directory exists
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    $uniqueFolderName = generateUniqueFolderName($folderName, $targetDir);
    $fullFolderPath = $targetDir . '/' . $uniqueFolderName;

    if (mkdir($fullFolderPath, 0777, true)) { // Create the physical directory
        // Insert folder info into database, created_at and updated_at will be set by MySQL's DEFAULT CURRENT_TIMESTAMP
        $stmt = $conn->prepare("INSERT INTO folders (folder_name, parent_id) VALUES (?, ?)");
        $stmt->bind_param("si", $uniqueFolderName, $parentFolderId);

        if ($stmt->execute()) {
            // NEW: Log activity for folder creation
            session_start(); // Pastikan session sudah dimulai untuk mendapatkan user_id
            if (isset($_SESSION['user_id'])) {
                $newFolderId = $conn->insert_id; // Get the ID of the newly created folder
                logActivity($conn, $_SESSION['user_id'], 'create_folder', 'Created folder "' . $uniqueFolderName . '"', $newFolderId, 'folder');
            }
            echo json_encode(['success' => true, 'message' => 'Folder created successfully!']);
        } else {
            // If database insert fails, try to delete the created directory
            rmdir($fullFolderPath); // Only if empty, otherwise manual cleanup needed
            echo json_encode(['success' => false, 'message' => 'Failed to save folder info to database.']);
        }
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to create physical folder.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}

$conn->close();
?>
