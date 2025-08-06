<?php
include 'config.php'; // Pastikan ini mengarah ke file config.php Anda

if (isset($_GET['c'])) {
    $code = $_GET['c'];

    $stmt = $conn->prepare("SELECT original_file FROM shared_links WHERE short_code = ?");
    $stmt->bind_param("s", $code);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $original_file_path = $row['original_file'];

        // Ambil ID file dari database berdasarkan file_path
        // Ini penting agar view.php bisa memprosesnya dengan ID
        $stmtFile = $conn->prepare("SELECT id FROM files WHERE file_path = ?");
        $stmtFile->bind_param("s", $original_file_path);
        $stmtFile->execute();
        $resultFile = $stmtFile->get_result();
        $fileData = $resultFile->fetch_assoc();
        $stmtFile->close();

        if ($fileData) {
            $fileId = $fileData['id'];
            // Redirect ke view.php dengan file_id dan parameter 'shared=true'
            header("Location: view.php?file_id=$fileId&shared=true");
            exit();
        } else {
            echo "File asli tidak ditemukan di sistem.";
        }
    } else {
        echo "Link tidak valid.";
    }
} else {
    echo "Kode tidak ditemukan.";
}
?>