<?php
include 'config.php';
include 'functions.php'; // Include functions.php for getFolderPath and logActivity

header('Content-Type: application/json');

// Fungsi untuk menghapus file fisik dari disk
// Direplikasi dari delete_selected.php untuk menjaga kemandirian
function deleteFileFromDisk($filePath) {
    if (file_exists($filePath)) {
        return unlink($filePath);
    }
    return true; // File sudah tidak ada, anggap sudah terhapus
}

// Fungsi rekursif untuk menghapus folder fisik beserta isinya
// Direplikasi dari delete_selected.php untuk menjaga kemandirian
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Pastikan pengguna sudah login
    session_start();
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized access. Please log in.']);
        exit;
    }
    $userId = $_SESSION['user_id'];

    $input = json_decode(file_get_contents('php://input'), true);
    $id = (int)($input['id'] ?? 0);
    $type = $input['type'] ?? '';

    if ($id === 0 || empty($type)) {
        echo json_encode(['success' => false, 'message' => 'Invalid item ID or type.']);
        exit;
    }

    $conn->begin_transaction();
    $success = false;
    $message = '';
    $baseUploadDir = 'uploads/'; // Sesuaikan dengan direktori upload Anda

    try {
        if ($type === 'file') {
            // Ambil path file dari database
            $stmt = $conn->prepare("SELECT file_name, file_path FROM files WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $file = $result->fetch_assoc();
            $stmt->close();

            if ($file) {
                $fileName = $file['file_name'];
                $filePath = $file['file_path'];

                // Hapus file fisik dari disk
                if (deleteFileFromDisk($filePath)) {
                    // Hapus dari database
                    $stmt = $conn->prepare("DELETE FROM files WHERE id = ?");
                    $stmt->bind_param("i", $id);
                    if ($stmt->execute()) {
                        $success = true;
                        $message = "File '" . htmlspecialchars($fileName) . "' deleted successfully.";
                        logActivity($conn, $userId, 'delete_file', "Deleted file: " . $fileName);
                    } else {
                        throw new Exception("Failed to delete file from database: " . $stmt->error);
                    }
                    $stmt->close();
                } else {
                    throw new Exception("Failed to delete physical file: " . htmlspecialchars($fileName));
                }
            } else {
                throw new Exception("File not found with ID: " . $id);
            }
        } elseif ($type === 'folder') {
            // Ambil nama folder dan parent_id dari database
            $stmt = $conn->prepare("SELECT folder_name, parent_id FROM folders WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $folder = $result->fetch_assoc();
            $stmt->close();

            if ($folder) {
                $folderName = $folder['folder_name'];
                $parent_id = $folder['parent_id'];

                // Rekonstruksi jalur fisik folder menggunakan getFolderPath dari functions.php
                // Ini adalah bagian yang Anda sebutkan. getFolderPath akan membangun jalur lengkap.
                $fullFolderPath = $baseUploadDir . getFolderPath($conn, $id);

                // Hapus folder dari database (ini akan memicu cascade delete untuk file dan subfolder di DB)
                $stmt = $conn->prepare("DELETE FROM folders WHERE id = ?");
                $stmt->bind_param("i", $id);
                if ($stmt->execute()) {
                    // Hapus folder fisik dari disk setelah berhasil dihapus dari DB
                    if (is_dir($fullFolderPath)) {
                        if (deleteFolderRecursive($fullFolderPath)) {
                            $success = true;
                            $message = "Folder '" . htmlspecialchars($folderName) . "' and its contents deleted successfully.";
                            logActivity($conn, $userId, 'delete_folder', "Deleted folder: " . $folderName);
                        } else {
                            throw new Exception("Failed to delete physical folder and its contents: " . htmlspecialchars($folderName));
                        }
                    } else {
                        // Jika folder fisik tidak ada, anggap berhasil dihapus secara fisik
                        $success = true;
                        $message = "Folder '" . htmlspecialchars($folderName) . "' deleted successfully from database (physical folder not found or already deleted).";
                        logActivity($conn, $userId, 'delete_folder', "Deleted folder (physical not found): " . $folderName);
                    }
                } else {
                    throw new Exception("Failed to delete folder from database: " . $stmt->error);
                }
                $stmt->close();
            } else {
                throw new Exception("Folder not found with ID: " . $id);
            }
        } else {
            throw new Exception("Unknown item type: " . htmlspecialchars($type));
        }

        if ($success) {
            $conn->commit();
            echo json_encode(['success' => true, 'message' => $message]);
        } else {
            $conn->rollback();
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
