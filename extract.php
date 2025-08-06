<?php
include 'config.php';
include 'functions.php'; // Memuat fungsi extractZipFile

session_start();

header('Content-Type: application/json');

// Pastikan pengguna sudah login
if (!isset($_SESSION['user_id'])) {
    echo json_encode(["success" => false, "message" => "Unauthorized access."]);
    exit();
}

// Pastikan request adalah POST dan content type adalah JSON
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["success" => false, "message" => "Invalid request method."]);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

$fileId = $input['file_id'] ?? null;
$filePath = $input['file_path'] ?? null; // Path relatif dari file ZIP

if (!$fileId || !$filePath) {
    echo json_encode(["success" => false, "message" => "Missing file ID or file path."]);
    exit();
}

// Ambil informasi file dari database untuk verifikasi dan mendapatkan folder_id
$stmt = $conn->prepare("SELECT file_name, file_path, file_type, folder_id FROM files WHERE id = ?");
$stmt->bind_param("i", $fileId);
$stmt->execute();
$result = $stmt->get_result();
$file = $result->fetch_assoc();
$stmt->close();

if (!$file) {
    echo json_encode(["success" => false, "message" => "File not found in database."]);
    exit();
}

// Pastikan file adalah ZIP
if (strtolower($file['file_type']) !== 'zip') {
    echo json_encode(["success" => false, "message" => "Only ZIP files can be extracted."]);
    exit();
}

// Pastikan path yang diberikan sesuai dengan yang ada di database
if ($file['file_path'] !== $filePath) {
    echo json_encode(["success" => false, "message" => "File path mismatch."]);
    exit();
}

// Path absolut ke file ZIP
$absoluteZipFilePath = __DIR__ . '/' . $filePath; // Asumsi file_path disimpan relatif dari root aplikasi

// Dapatkan folder path tempat file ZIP berada
$baseUploadDir = 'uploads/'; // Sesuaikan dengan base upload directory Anda
$zipFileFolderId = $file['folder_id']; // ID folder tempat file ZIP berada

// Dapatkan path fisik folder tempat file ZIP berada
$targetFolderPath = __DIR__ . '/' . $baseUploadDir; // Default ke root uploads
if ($zipFileFolderId !== NULL) {
    $folderPathFromDb = getFolderPath($conn, $zipFileFolderId);
    if (!empty($folderPathFromDb)) {
        $targetFolderPath .= $folderPathFromDb . '/';
    }
}

// Panggil fungsi ekstrak
// Fungsi extractZipFile sekarang akan menangani penamaan folder unik secara internal
$extractionResult = extractZipFile($absoluteZipFilePath, $targetFolderPath, $conn, $zipFileFolderId);

// Log aktivitas
logActivity($conn, $_SESSION['user_id'], 'extract_file', "Extracted ZIP file: " . $file['file_name'] . ". Result: " . ($extractionResult['success'] ? "Success" : "Failed"));

echo json_encode($extractionResult);

$conn->close();
?>
