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
// $filePath dari JS sekarang adalah file_path yang tersimpan di DB, yang relatif
$filePathFromDb = $input['file_path'] ?? null; 

if (!$fileId || !$filePathFromDb) {
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
// Ini penting untuk keamanan, mencegah ekstraksi file arbitrer
if ($file['file_path'] !== $filePathFromDb) {
    echo json_encode(["success" => false, "message" => "File path mismatch. Provided: " . $filePathFromDb . ", DB: " . $file['file_path']]);
    exit();
}

// Path absolut ke file ZIP
// Menggunakan __DIR__ untuk mendapatkan direktori skrip saat ini, lalu menggabungkannya dengan $filePathFromDb
$absoluteZipFilePath = __DIR__ . '/' . $filePathFromDb; 

// Dapatkan folder path tempat file ZIP berada
$baseUploadDir = 'uploads/'; // Sesuaikan dengan base upload directory Anda
$zipFileFolderId = $file['folder_id']; // ID folder tempat file ZIP berada

// Dapatkan path fisik folder tujuan ekstraksi. Ini adalah folder tempat file ZIP berada.
// Fungsi getFolderPath mengembalikan path relatif dari 'uploads/', jadi kita perlu menggabungkannya.
$extractToPath = __DIR__ . '/' . $baseUploadDir;
if ($zipFileFolderId !== NULL) {
    $folderPathFromDb = getFolderPath($conn, $zipFileFolderId);
    if (!empty($folderPathFromDb)) {
        $extractToPath .= $folderPathFromDb . '/';
    }
}

// Panggil fungsi ekstrak
// Fungsi extractZipFile sekarang akan menangani penamaan folder unik secara internal
$extractionResult = extractZipFile($absoluteZipFilePath, $extractToPath, $conn, $zipFileFolderId);

// Log aktivitas
logActivity($conn, $_SESSION['user_id'], 'extract_file', "Extracted ZIP file: " . $file['file_name'] . ". Result: " . ($extractionResult['success'] ? "Success" : "Failed"));

echo json_encode($extractionResult);

$conn->close();
?>