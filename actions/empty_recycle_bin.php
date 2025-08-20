<?php
include '../config.php';
include '../functions.php'; // For logActivity

// Tambahkan fungsi ini
function deleteFileFromDisk($filePath) {
    if (file_exists($filePath)) {
        return unlink($filePath);
    }
    return true; // File sudah tidak ada, anggap sudah terhapus
}

// Tambahkan fungsi ini
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
$userId = $_SESSION['user_id'];

$input = json_decode(file_get_contents('php://input'), true);
// No specific items needed, just user_id for emptying their trash

$conn->begin_transaction();
$success = false;
$message = '';

try {
    // Delete all physical files associated with the user's deleted_files
    $stmt_files = $conn->prepare("SELECT file_path FROM deleted_files WHERE user_id = ?");
    $stmt_files->bind_param("i", $userId);
    $stmt_files->execute();
    $result_files = $stmt_files->get_result();
    while ($row = $result_files->fetch_assoc()) {
        // MODIFIED: Menggunakan fungsi deleteFileFromDisk
        deleteFileFromDisk($row['file_path']); // Attempt to delete physical file
    }
    $stmt_files->close();

    // Catatan: Untuk menghapus folder fisik secara rekursif saat mengosongkan recycle bin,
    // tabel `deleted_folders` harus menyimpan jalur fisik folder tersebut (misalnya, `trash_path`).
    // Jika tidak ada kolom seperti itu, folder fisik tidak dapat dihapus dari sini.
    // Asumsi saat ini: `deleted_folders` hanya mencatat entri database, bukan jalur fisik yang perlu dihapus.
    // Jika Anda memiliki kolom `trash_path` di `deleted_folders`, Anda bisa menambahkan kode berikut:
    /*
    $stmt_folders_path = $conn->prepare("SELECT trash_path FROM deleted_folders WHERE user_id = ?");
    $stmt_folders_path->bind_param("i", $userId);
    $stmt_folders_path->execute();
    $result_folders_path = $stmt_folders_path->get_result();
    while ($row = $result_folders_path->fetch_assoc()) {
        if (!empty($row['trash_path']) && is_dir($row['trash_path'])) {
            deleteFolderRecursive($row['trash_path']); // Attempt to delete physical folder
        }
    }
    $stmt_folders_path->close();
    */

    // Delete all entries from deleted_files for the user
    $stmt_delete_files = $conn->prepare("DELETE FROM deleted_files WHERE user_id = ?");
    $stmt_delete_files->bind_param("i", $userId);
    if (!$stmt_delete_files->execute()) {
        throw new Exception("Failed to delete files from deleted_files table: " . $stmt_delete_files->error);
    }
    $deletedFileCount = $stmt_delete_files->affected_rows;
    $stmt_delete_files->close();

    // Delete all entries from deleted_folders for the user
    $stmt_delete_folders = $conn->prepare("DELETE FROM deleted_folders WHERE user_id = ?");
    $stmt_delete_folders->bind_param("i", $userId);
    if (!$stmt_delete_folders->execute()) {
        throw new Exception("Failed to delete folders from deleted_folders table: " . $stmt_delete_folders->error);
    }
    $deletedFolderCount = $stmt_delete_folders->affected_rows;
    $stmt_delete_folders->close();

    $conn->commit();
    $success = true;
    $message = "Recycle Bin emptied successfully. Deleted {$deletedFileCount} files and {$deletedFolderCount} folders permanently.";
    logActivity($conn, $userId, 'empty_recycle_bin', "Emptied recycle bin. Deleted {$deletedFileCount} files and {$deletedFolderCount} folders.");

} catch (Exception $e) {
    $conn->rollback();
    $message = 'Error emptying Recycle Bin: ' . $e->getMessage();
}

echo json_encode(['success' => $success, 'message' => $message]);
$conn->close();
?>
