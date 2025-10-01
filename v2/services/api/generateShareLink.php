<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include '../../../config.php';
require_once __DIR__ . '/../../helpers/utils.php';

// Fungsi untuk menghasilkan kode pendek acak
function generateShortCode($length = 6) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}

// Pastikan request adalah POST dan ada parameter file_id
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['file_id'])) {
    $fileId = (int)$_POST['file_id'];

    // Ambil path file asli dari database
    $stmt = $conn->prepare("SELECT file_path FROM files WHERE id = ?");
    $stmt->bind_param("i", $fileId);
    $stmt->execute();
    $result = $stmt->get_result();
    $fileData = $result->fetch_assoc();
    $stmt->close();

    if ($fileData) {
        $original_file_path = $fileData['file_path'];

        // Cek apakah shortlink untuk file ini sudah ada
        $stmt = $conn->prepare("SELECT short_code, original_file FROM shared_links WHERE original_file = ?");
        $stmt->bind_param("s", $original_file_path);
        $stmt->execute();
        $result = $stmt->get_result();
        $existingShortLink = $result->fetch_assoc();
        $stmt->close();

        if ($existingShortLink) {
            // Jika sudah ada, kembalikan shortlink yang sudah ada
            $short_code = $existingShortLink['short_code'];
        } else {
            // Jika belum ada, generate shortcode baru
            $short_code = generateShortCode();

            // Pastikan shortcode unik
            $stmt = $conn->prepare("SELECT id FROM shared_links WHERE short_code = ?");
            $stmt->bind_param("s", $short_code);
            $stmt->execute();
            $stmt->store_result();

            while ($stmt->num_rows > 0) {
                $short_code = generateShortCode();
                $stmt->bind_param("s", $short_code);
                $stmt->execute();
                $stmt->store_result();
            }
            $stmt->close();

            // Simpan shortlink baru ke database
            $stmt = $conn->prepare("INSERT INTO shared_links (original_file, short_code) VALUES (?, ?)");
            $stmt->bind_param("ss", $original_file_path, $short_code);
            $stmt->execute();
            $stmt->close();
        }

        // Base URL untuk shortlink (ganti dengan domain Anda)
        // Untuk localhost, mungkin http://localhost/Dafino_Cloud_Storage
        // $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
        $baseUrl = getBaseUrl();
        // Jika aplikasi Anda di subdirektori, tambahkan path subdirektori di sini
        // Contoh: $baseUrl .= '/Dafino_Cloud_Storage';
        $shortlink = $baseUrl . '/v2/pdf-viewer.php?url=' . $baseUrl . '/' . $original_file_path;

        if (!str_contains($original_file_path, "pdf"))
            $shortlink = $baseUrl . '/s.php?c=' . $short_code;

        echo json_encode(['success' => true, 'shortlink' => $shortlink]);
    } else {
        echo json_encode(['success' => false, 'message' => 'File tidak ditemukan.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Parameter file_id tidak valid.']);
}
?>
