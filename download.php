<?php
include 'config.php'; // Pastikan file config.php ada dan berisi koneksi database

if (isset($_GET['file'])) {
    $file_path = $_GET['file']; // Dapatkan path file asli
    
    // Keamanan dasar: Cegah akses folder luar
    // Pastikan path file yang diminta benar-benar ada di dalam folder 'uploads/'
    // Menggunakan realpath() untuk mengatasi serangan path traversal
    $allowed_dir = realpath(__DIR__ . '/uploads/'); // Ganti jika folder uploads ada di tempat lain
    $requested_file_path = realpath($file_path);

    if (strpos($requested_file_path, $allowed_dir) === false) {
        die("Akses tidak diizinkan. File di luar direktori yang diizinkan.");
    }

    // Ambil nama file baru jika disediakan
    $new_filename = isset($_GET['new_filename']) ? basename($_GET['new_filename']) : basename($file_path);

    // Pastikan file ada
    if (file_exists($requested_file_path)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream'); // Tipe umum untuk unduhan
        header('Content-Disposition: attachment; filename="' . $new_filename . '"'); // Gunakan nama file baru
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($requested_file_path));
        
        // Bersihkan output buffer jika ada
        if (ob_get_level()) {
            ob_end_clean();
        }
        
        readfile($requested_file_path);
        exit;
    } else {
        echo "File tidak ditemukan: " . htmlspecialchars($file_path);
        exit;
    }
} else {
    echo "Akses tidak valid. Parameter 'file' tidak disediakan.";
    exit;
}
?>