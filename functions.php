<?php

// Fungsi untuk mendapatkan kelas ikon Font Awesome berdasarkan ekstensi
if (!function_exists('getFontAwesomeIconClass')) {
    function getFontAwesomeIconClass($fileName) {
        $ext = pathinfo($fileName, PATHINFO_EXTENSION);
        $ext = strtolower($ext);

        $iconClasses = [
            // Documents
            'pdf' => 'fa-file-pdf',
            'doc' => 'fa-file-word',
            'docx' => 'fa-file-word',
            'xls' => 'fa-file-excel',
            'xlsx' => 'fa-file-excel',
            'ppt' => 'fa-file-powerpoint',
            'pptx' => 'fa-file-powerpoint',
            'txt' => 'fa-file-alt',
            'rtf' => 'fa-file-alt',
            'md' => 'fa-file-alt',
            'csv' => 'fa-file-csv',
            'odt' => 'fa-file-alt',
            'odp' => 'fa-file-powerpoint',
            'log' => 'fa-file-alt',
            'tex' => 'fa-file-alt',

            // Images
            'jpg' => 'fa-file-image',
            'jpeg' => 'fa-file-image',
            'png' => 'fa-file-image',
            'gif' => 'fa-file-image',
            'bmp' => 'fa-file-image',
            'webp' => 'fa-file-image',
            'svg' => 'fa-file-image',
            'tiff' => 'fa-file-image',

            // Audio
            'mp3' => 'fa-file-audio',
            'wav' => 'fa-file-audio',
            'ogg' => 'fa-file-audio',
            'flac' => 'fa-file-audio',
            'aac' => 'fa-file-audio',
            'm4a' => 'fa-file-audio',
            'alac' => 'fa-file-audio',
            'wma' => 'fa-file-audio',
            'opus' => 'fa-file-audio',
            'amr' => 'fa-file-audio',
            'mid' => 'fa-file-audio',

            // Video
            'mp4' => 'fa-file-video',
            'avi' => 'fa-file-video',
            'mov' => 'fa-file-video',
            'wmv' => 'fa-file-video',
            'flv' => 'fa-file-video',
            'webm' => 'fa-file-video',
            '3gp' => 'fa-file-video',
            'm4v' => 'fa-file-video',
            'mpg' => 'fa-file-video',
            'mpeg' => 'fa-file-video',
            'ts' => 'fa-file-video',
            'ogv' => 'fa-file-video',

            // Archives
            'zip' => 'fa-file-archive',
            'rar' => 'fa-file-archive',
            '7z' => 'fa-file-archive',
            'tar' => 'fa-file-archive',
            'gz' => 'fa-file-archive',
            'bz2' => 'fa-file-archive',
            'xz' => 'fa-file-archive',
            'iso' => 'fa-file-archive',
            'cab' => 'fa-file-archive',
            'arj' => 'fa-file-archive',

            // Code
            'html' => 'fa-file-code',
            'htm' => 'fa-file-code',
            'css' => 'fa-file-code',
            'js' => 'fa-file-code',
            'php' => 'fa-file-code',
            'py' => 'fa-file-code',
            'java' => 'fa-file-code',
            'json' => 'fa-file-code',
            'xml' => 'fa-file-code',
            'ts' => 'fa-file-code',
            'tsx' => 'fa-file-code',
            'jsx' => 'fa-file-code',
            'vue' => 'fa-file-code',
            'cpp' => 'fa-file-code',
            'c' => 'fa-file-code',
            'cs' => 'fa-file-code',
            'rb' => 'fa-file-code',
            'go' => 'fa-file-code',
            'swift' => 'fa-file-code',
            'sql' => 'fa-database',
            'sh' => 'fa-file-code',
            'bat' => 'fa-file-code',
            'ini' => 'fa-file-code',
            'yml' => 'fa-file-code',
            'yaml' => 'fa-file-code',
            'pl' => 'fa-file-code',
            'r' => 'fa-file-code',

            // Installation
            'exe' => 'fa-box',
            'msi' => 'fa-box',
            'apk' => 'fa-box',
            'ipa' => 'fa-box',
            'jar' => 'fa-box',
            'appimage' => 'fa-box',
            'dmg' => 'fa-box',
            'bin' => 'fa-box',

            // P2P
            'torrent' => 'fa-magnet',
            'nzb' => 'fa-magnet',
            'ed2k' => 'fa-magnet',
            'part' => 'fa-magnet',
            '!ut' => 'fa-magnet',
            
            // CAD
            'dwg' => 'file-color-cad',
            'dxf' => 'file-color-cad',
            'dgn' => 'file-color-cad',
            'iges' => 'file-color-cad',
            'igs' => 'file-color-cad',
            'step' => 'file-color-cad',
            'stp' => 'file-color-cad',
            'stl' => 'file-color-cad',
            '3ds' => 'file-color-cad',
            'obj' => 'file-color-cad',
            'sldprt' => 'file-color-cad',
            'sldasm' => 'file-color-cad',
            'ipt' => 'file-color-cad',
            'iam' => 'file-color-cad',
            'catpart' => 'file-color-cad',
            'catproduct' => 'file-color-cad',
            'prt' => 'file-color-cad',
            'asm' => 'file-color-cad',
            'fcstd' => 'file-color-cad',
            'skp' => 'file-color-cad',
            'x_t' => 'file-color-cad',
            'x_b' => 'file-color-cad',

            // Default
            'default' => 'fa-file'
        ];

        return $iconClasses[$ext] ?? $iconClasses['default'];
    }
}

// Fungsi untuk mendapatkan kelas warna kustom berdasarkan ekstensi
if (!function_exists('getFileColorClassPhp')) {
    function getFileColorClassPhp($fileName) {
        $ext = pathinfo($fileName, PATHINFO_EXTENSION);
        $ext = strtolower($ext);

        $colorClasses = [
            // Documents
            'pdf' => 'file-color-pdf',
            'doc' => 'file-color-doc',
            'docx' => 'file-color-doc',
            'xls' => 'file-color-xls',
            'xlsx' => 'file-color-xls',
            'ppt' => 'file-color-ppt',
            'pptx' => 'file-color-ppt',
            'txt' => 'file-color-txt',
            'rtf' => 'file-color-txt',
            'md' => 'file-color-txt',
            'csv' => 'file-color-csv',
            'odt' => 'file-color-doc',
            'odp' => 'file-color-ppt',
            'log' => 'file-color-txt',
            'tex' => 'file-color-txt',

            // Images
            'jpg' => 'file-color-image',
            'jpeg' => 'file-color-image',
            'png' => 'file-color-image',
            'gif' => 'file-color-image',
            'bmp' => 'file-color-image',
            'webp' => 'file-color-image',
            'svg' => 'file-color-image',
            'tiff' => 'file-color-image',

            // Audio
            'mp3' => 'file-color-audio',
            'wav' => 'file-color-audio',
            'ogg' => 'file-color-audio',
            'flac' => 'file-color-audio',
            'aac' => 'file-color-audio',
            'm4a' => 'file-color-audio',
            'alac' => 'file-color-audio',
            'wma' => 'file-color-audio',
            'opus' => 'file-color-audio',
            'amr' => 'file-color-audio',
            'mid' => 'file-color-audio',

            // Video
            'mp4' => 'file-color-video',
            'avi' => 'file-color-video',
            'mov' => 'file-color-video',
            'wmv' => 'file-color-video',
            'flv' => 'file-color-video',
            'webm' => 'file-color-video',
            '3gp' => 'file-color-video',
            'm4v' => 'file-color-video',
            'mpg' => 'file-color-video',
            'mpeg' => 'file-color-video',
            'ts' => 'file-color-video',
            'ogv' => 'file-color-video',

            // Archives
            'zip' => 'file-color-archive',
            'rar' => 'file-color-archive',
            '7z' => 'file-color-archive',
            'tar' => 'file-color-archive',
            'gz' => 'file-color-archive',
            'bz2' => 'file-color-archive',
            'xz' => 'file-color-archive',
            'iso' => 'file-color-archive',
            'cab' => 'file-color-archive',
            'arj' => 'file-color-archive',

            // Code
            'html' => 'file-color-code',
            'htm' => 'file-color-code',
            'css' => 'file-color-code',
            'js' => 'file-color-code',
            'php' => 'file-color-code',
            'py' => 'file-color-code',
            'java' => 'file-color-code',
            'json' => 'file-color-code',
            'xml' => 'file-color-code',
            'ts' => 'file-color-code',
            'tsx' => 'file-color-code',
            'jsx' => 'file-color-code',
            'vue' => 'file-color-code',
            'cpp' => 'file-color-code',
            'c' => 'file-color-code',
            'cs' => 'file-color-code',
            'rb' => 'file-color-code',
            'go' => 'file-color-code',
            'swift' => 'file-color-code',
            'sql' => 'file-color-code', // Menggunakan warna code untuk SQL
            'sh' => 'file-color-code',
            'bat' => 'file-color-code',
            'ini' => 'file-color-code',
            'yml' => 'file-color-code',
            'yaml' => 'file-color-code',
            'pl' => 'file-color-code',
            'r' => 'file-color-code',

            // Installation
            'exe' => 'file-color-exe',
            'msi' => 'file-color-exe',
            'apk' => 'file-color-exe',
            'ipa' => 'file-color-exe',
            'jar' => 'file-color-exe',
            'appimage' => 'file-color-exe',
            'dmg' => 'file-color-exe',
            'bin' => 'file-color-exe',

            // P2P
            'torrent' => 'file-color-default', // Tidak ada warna spesifik, pakai default
            'nzb' => 'file-color-default',
            'ed2k' => 'file-color-default',
            'part' => 'file-color-default',
            '!ut' => 'file-color-default',
            
            // CAD Files (NEW)
            'dwg' => 'file-color-cad',
            'dxf' => 'file-color-cad',
            'dgn' => 'file-color-cad',
            'iges' => 'file-color-cad',
            'igs' => 'file-color-cad',
            'step' => 'file-color-cad',
            'stp' => 'file-color-cad',
            'stl' => 'file-color-cad',
            '3ds' => 'file-color-cad',
            'obj' => 'file-color-cad',
            'sldprt' => 'file-color-cad',
            'sldasm' => 'file-color-cad',
            'ipt' => 'file-color-cad',
            'iam' => 'file-color-cad',
            'catpart' => 'file-color-cad',
            'catproduct' => 'file-color-cad',
            'prt' => 'file-color-cad',
            'asm' => 'file-color-cad',
            'fcstd' => 'file-color-cad',
            'skp' => 'file-color-cad',
            'x_t' => 'file-color-cad',
            'x_b' => 'file-color-cad',

            // Default
            'default' => 'file-color-default'
        ];

        return $colorClasses[$ext] ?? $colorClasses['default'];
    }
}

// Fungsi untuk memformat ukuran byte menjadi format yang mudah dibaca (KB, MB, GB)
if (!function_exists('formatBytes')) {
    function formatBytes($bytes, $precision = 2) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}


/**
 * Fungsi rekursif untuk mendapatkan path lengkap sebuah folder.
 * Digunakan untuk membangun path fisik folder di server.
 *
 * @param mysqli $conn Koneksi database.
 * @param int $folderId ID folder yang ingin dicari path-nya.
 * @return string Path folder relatif terhadap 'uploads/' (misal: 'folder_induk/subfolder').
 */
if (!function_exists('getFolderPath')) {
    function getFolderPath($conn, $folderId) {
        if ($folderId === NULL) {
            return ''; // Root folder
        }

        $path = [];
        $currentId = $folderId;

        while ($currentId !== NULL) {
            $stmt = $conn->prepare("SELECT folder_name, parent_id FROM folders WHERE id = ?");
            $stmt->bind_param("i", $currentId);
            $stmt->execute();
            $result = $stmt->get_result();
            $folder = $result->fetch_assoc();
            $stmt->close();

            if ($folder) {
                array_unshift($path, $folder['folder_name']);
                $currentId = $folder['parent_id'];
            } else {
                break; // Folder not found, stop
            }
        }
        return implode('/', $path);
    }
}

/**
 * Fungsi untuk menghapus file fisik dari disk.
 *
 * @param string $filePath Path absolut atau relatif ke file yang akan dihapus.
 * @return bool True jika berhasil dihapus atau file tidak ada, false jika gagal.
 */
if (!function_exists('deleteFileFromDisk')) {
    function deleteFileFromDisk($filePath) {
        if (file_exists($filePath)) {
            return unlink($filePath);
        }
        return true; // File sudah tidak ada, anggap sudah terhapus
    }
}

/**
 * Fungsi rekursif untuk menghapus folder fisik beserta isinya.
 *
 * @param string $dir Path direktori yang akan dihapus.
 * @return bool True jika berhasil dihapus, false jika gagal.
 */
if (!function_exists('deleteFolderRecursive')) {
    function deleteFolderRecursive($dir) {
        if (!is_dir($dir)) {
            return false;
        }
        $files = array_diff(scandir($dir), array('.', '..'));
        foreach ($files as $file) {
            $itemPath = "$dir/$file";
            // Pastikan itemPath adalah path yang valid sebelum mencoba menghapus
            if (is_file($itemPath)) {
                unlink($itemPath);
            } elseif (is_dir($itemPath)) {
                deleteFolderRecursive($itemPath);
            }
        }
        // Coba hapus direktori setelah isinya kosong
        return rmdir($dir);
    }
}

/**
 * Fungsi rekursif untuk menghapus file dan subfolder dari database
 * yang berada di dalam folder tertentu.
 *
 * @param mysqli $conn Koneksi database.
 * @param int $folderId ID folder yang isinya akan dihapus dari DB.
 */
// Fungsi rekursif untuk menghapus file dan subfolder dari database
// yang berada di dalam folder tertentu (digunakan untuk penghapusan permanen)
if (!function_exists('deleteFolderContentsFromDB')) {
    function deleteFolderContentsFromDB($conn, $folderId, $isDeletedTable = false) {
        $tablePrefix = $isDeletedTable ? 'deleted_' : '';

        // Hapus file di dalam folder ini
        $stmt_files = $conn->prepare("DELETE FROM {$tablePrefix}files WHERE folder_id = ?");
        if ($stmt_files) {
            $stmt_files->bind_param("i", $folderId);
            $stmt_files->execute();
            $stmt_files->close();
        } else {
            error_log("Error preparing delete files statement in deleteFolderContentsFromDB: " . $conn->error);
        }

        // Dapatkan subfolder
        $stmt_subfolders = $conn->prepare("SELECT id FROM {$tablePrefix}folders WHERE parent_id = ?");
        if ($stmt_subfolders) {
            $stmt_subfolders->bind_param("i", $folderId);
            $stmt_subfolders->execute();
            $result_subfolders = $stmt_subfolders->get_result();
            while ($row = $result_subfolders->fetch_assoc()) {
                deleteFolderContentsFromDB($conn, $row['id'], $isDeletedTable); // Rekursif
            }
            $stmt_subfolders->close();
        } else {
            error_log("Error preparing select subfolders statement in deleteFolderContentsFromDB: " . $conn->error);
        }
    }
}

/**
 * Fungsi baru: Menghapus folder secara fisik dan memindahkan file-filenya ke Recycle Bin.
 * Ini akan menghapus entri folder dari DB dan fisik, tetapi memindahkan file ke deleted_files.
 *
 * @param mysqli $conn Koneksi database.
 * @param int $userId ID pengguna yang melakukan operasi.
 * @param int $folderId ID folder yang akan dihapus.
 * @param string $baseUploadDir Direktori dasar tempat file diunggah (misalnya, 'uploads/').
 * @return array Hasil operasi (filesMoved: array of file names, foldersDeleted: array of folder names).
 */
if (!function_exists('deleteFolderAndMoveFilesToTrash')) {
    function deleteFolderAndMoveFilesToTrash($conn, $userId, $folderId, $baseUploadDir) {
        $filesMoved = [];
        $foldersDeleted = [];

        // 1. Ambil semua file di dalam folder ini dan subfolder-nya
        // Gunakan RecursiveIteratorIterator untuk mendapatkan semua file di dalam folder fisik
        $folderPathPhysical = $baseUploadDir . getFolderPath($conn, $folderId);

        if (is_dir($folderPathPhysical)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($folderPathPhysical, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $item) {
                if ($item->isFile()) {
                    $filePathRelative = str_replace($baseUploadDir, '', $item->getPathname());
                    
                    // Cari file di DB berdasarkan file_path
                    $stmt_find_file_db = $conn->prepare("SELECT id, file_name, file_size, file_type, folder_id FROM files WHERE file_path = ?");
                    $stmt_find_file_db->bind_param("s", $filePathRelative);
                    $stmt_find_file_db->execute();
                    $result_find_file_db = $stmt_find_file_db->get_result();
                    $file = $result_find_file_db->fetch_assoc();
                    $stmt_find_file_db->close();

                    if ($file) {
                        $fileName = $file['file_name'];
                        $fileSize = $file['file_size'];
                        $fileType = $file['file_type'];
                        $originalFolderId = $file['folder_id'];
                        $originalFolderPath = getFolderPath($conn, $originalFolderId);

                        // Pindahkan ke deleted_files table
                        $stmt_insert_deleted = $conn->prepare("INSERT INTO deleted_files (user_id, file_name, file_path, file_size, file_type, folder_id, original_folder_path, deleted_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                        $stmt_insert_deleted->bind_param("issisis", $userId, $fileName, $filePathRelative, $fileSize, $fileType, $originalFolderId, $originalFolderPath);
                        
                        if ($stmt_insert_deleted->execute()) {
                            // Hapus dari tabel files asli
                            $stmt_delete_original_file = $conn->prepare("DELETE FROM files WHERE id = ?");
                            $stmt_delete_original_file->bind_param("i", $file['id']);
                            if ($stmt_delete_original_file->execute()) {
                                $filesMoved[] = $fileName;
                                // File fisik akan dihapus bersama folder induknya nanti
                            } else {
                                error_log("Failed to delete file from original files table: " . $stmt_delete_original_file->error);
                            }
                            $stmt_delete_original_file->close();
                        } else {
                            error_log("Failed to move file to deleted_files table: " . $stmt_insert_deleted->error);
                        }
                        $stmt_insert_deleted->close();
                    }
                }
            }
        }

        // 2. Hapus entri folder dari database secara rekursif
        // Ini akan menghapus folder utama dan semua subfolder dari tabel 'folders'
        // dan juga file-file yang mungkin belum terdeteksi oleh iterasi fisik di atas
        // (misal: file yang ada di DB tapi tidak ada fisiknya, atau sebaliknya)
        // Kita akan menggunakan pendekatan yang lebih aman: hapus dari DB, lalu hapus fisik.

        // Dapatkan semua subfolder dari folder yang akan dihapus (termasuk dirinya sendiri)
        $foldersToDeleteFromDB = [];
        $queue = [$folderId];

        while (!empty($queue)) {
            $currentFolderId = array_shift($queue);
            $foldersToDeleteFromDB[] = $currentFolderId;

            $stmt_sub = $conn->prepare("SELECT id FROM folders WHERE parent_id = ?");
            $stmt_sub->bind_param("i", $currentFolderId);
            $stmt_sub->execute();
            $result_sub = $stmt_sub->get_result();
            while ($row_sub = $result_sub->fetch_assoc()) {
                $queue[] = $row_sub['id'];
            }
            $stmt_sub->close();
        }

        // Hapus dari DB dari yang paling dalam ke luar
        $foldersToDeleteFromDB = array_reverse($foldersToDeleteFromDB);

        foreach ($foldersToDeleteFromDB as $fId) {
            $stmt_get_folder_name = $conn->prepare("SELECT folder_name FROM folders WHERE id = ?");
            $stmt_get_folder_name->bind_param("i", $fId);
            $stmt_get_folder_name->execute();
            $result_get_folder_name = $stmt_get_folder_name->get_result();
            $folderNameRow = $result_get_folder_name->fetch_assoc();
            $stmt_get_folder_name->close();

            if ($folderNameRow) {
                $foldersDeleted[] = $folderNameRow['folder_name'];
            }

            $stmt_delete_folder_db = $conn->prepare("DELETE FROM folders WHERE id = ?");
            $stmt_delete_folder_db->bind_param("i", $fId);
            if (!$stmt_delete_folder_db->execute()) {
                error_log("Error deleting folder from DB (ID: {$fId}): " . $conn->error);
            }
            $stmt_delete_folder_db->close();
        }

        // 3. Hapus folder fisik secara rekursif
        // Ini akan menghapus folder utama dan semua isinya (file yang sudah dipindahkan ke trash secara DB,
        // dan subfolder yang sudah kosong di DB)
        if (is_dir($folderPathPhysical)) {
            deleteFolderRecursive($folderPathPhysical);
        }

        return ['filesMoved' => $filesMoved, 'foldersDeleted' => $foldersDeleted];
    }
}


// NEW: Fungsi untuk membersihkan Recycle Bin secara otomatis (setelah 30 hari)
if (!function_exists('cleanRecycleBinAutomatically')) {
    function cleanRecycleBinAutomatically($conn) {
        $thirtyDaysAgo = date('Y-m-d H:i:s', strtotime('-30 days'));
        $baseUploadDir = 'uploads/'; // Sesuaikan dengan direktori upload Anda

        // Hapus file dari deleted_files yang lebih dari 30 hari
        $stmt_files = $conn->prepare("SELECT id, file_path FROM deleted_files WHERE deleted_at <= ?");
        $stmt_files->bind_param("s", $thirtyDaysAgo);
        $stmt_files->execute();
        $result_files = $stmt_files->get_result();
        $filesToDelete = [];
        while ($row = $result_files->fetch_assoc()) {
            $filesToDelete[] = $row;
        }
        $stmt_files->close();

        foreach ($filesToDelete as $file) {
            if (file_exists($file['file_path'])) {
                unlink($file['file_path']); // Hapus file fisik
            }
            $stmt_delete_db = $conn->prepare("DELETE FROM deleted_files WHERE id = ?");
            $stmt_delete_db->bind_param("i", $file['id']);
            $stmt_delete_db->execute();
            $stmt_delete_db->close();
            // Log aktivitas jika diperlukan, tapi ini otomatis jadi mungkin tidak perlu log per item
        }

        // Hapus folder dari deleted_folders yang lebih dari 30 hari
        // Ini lebih kompleks karena perlu menghapus sub-itemnya juga.
        // Untuk kesederhanaan, kita akan menghapus entri folder dan mengandalkan
        // penghapusan file fisik yang sudah dilakukan di atas.
        // Jika ada folder kosong yang tersisa di sistem file, itu perlu penanganan terpisah.
        $stmt_folders = $conn->prepare("SELECT id FROM deleted_folders WHERE deleted_at <= ?");
        $stmt_folders->bind_param("s", $thirtyDaysAgo);
        $stmt_folders->execute();
        $result_folders = $stmt_folders->get_result();
        $foldersToDelete = [];
        while ($row = $result_folders->fetch_assoc()) {
            $foldersToDelete[] = $row['id'];
        }
        $stmt_folders->close();

        foreach ($foldersToDelete as $folderId) {
            // Hapus entri folder dari database
            $stmt_delete_db = $conn->prepare("DELETE FROM deleted_folders WHERE id = ?");
            $stmt_delete_db->bind_param("i", $folderId);
            $stmt_delete_db->execute();
            $stmt_delete_db->close();
        }
        error_log("Recycle Bin cleanup completed. Items older than 30 days removed.");
    }
}

/**
 * Fungsi untuk menghasilkan nama file unik jika sudah ada file dengan nama yang sama.
 *
 * @param string $originalName Nama file asli.
 * @param string $directory Direktori tempat file akan disimpan.
 * @return string Nama file unik.
 */
if (!function_exists('generateUniqueFileName')) {
    function generateUniqueFileName($originalName, $directory) {
        $info = pathinfo($originalName);
        $name = $info['filename'];
        $ext = isset($info['extension']) ? '.' . $info['extension'] : '';
        $counter = 1;
        $newName = $originalName;

        while (file_exists($directory . '/' . $newName)) {
            $newName = $name . '(' . $counter . ')' . $ext;
            $counter++;
        }
        return $newName;
    }
}

/**
 * Fungsi untuk menghasilkan nama folder unik jika sudah ada folder dengan nama yang sama.
 *
 * @param string $originalName Nama folder asli.
 * @param string $directory Direktori tempat folder akan disimpan.
 * @return string Nama folder unik.
 */
if (!function_exists('generateUniqueFolderName')) {
    function generateUniqueFolderName($originalName, $directory) {
        $counter = 1;
        $newName = $originalName;

        // Cek apakah direktori sudah ada
        while (is_dir($directory . '/' . $newName)) {
            $newName = $originalName . '(' . $counter . ')';
            $counter++;
        }
        return $newName;
    }
}

/**
 * Menghitung ukuran total sebuah folder secara rekursif.
 *
 * @param string $dir Path ke folder yang akan dihitung ukurannya.
 * @return int Ukuran folder dalam byte.
 */
if (!function_exists('getFolderSize')) {
    function getFolderSize($dir) {
        $size = 0;
        if (!is_dir($dir)) {
            return $size;
        }

        // Menggunakan RecursiveIteratorIterator untuk menelusuri folder secara rekursif
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }
        return $size;
    }
}


// Pastikan fungsi logActivity hanya dideklarasikan sekali
if (!function_exists('logActivity')) {
    /**
     * Mencatat aktivitas pengguna ke tabel 'activities'.
     *
     * @param mysqli $conn Koneksi database.
     * @param int $userId ID pengguna yang melakukan aktivitas.
     * @param string $activityType Tipe aktivitas (e.g., 'upload_file', 'delete_file', 'rename_file', 'create_folder', 'download', 'login', 'share_link', 'archive').
     * @param string $description Deskripsi detail aktivitas.
     */
    function logActivity($conn, $userId, $activityType, $description) {
        // Pastikan koneksi dan user ID valid
        if (!$conn || !is_numeric($userId) || $userId <= 0) {
            error_log("Invalid parameters for logActivity: conn=" . ($conn ? "true" : "false") . ", userId=" . $userId);
            return;
        }

        $stmt = $conn->prepare("INSERT INTO activities (user_id, activity_type, description, timestamp) VALUES (?, ?, ?, NOW())");
        if ($stmt) {
            $stmt->bind_param("iss", $userId, $activityType, $description);
            if (!$stmt->execute()) {
                error_log("Error executing activity log statement: " . $stmt->error);
            }
            $stmt->close();
        } else {
            error_log("Error preparing activity log statement: " . $conn->error);
        }
    }
}

// NEW: Function to get time elapsed string
if (!function_exists('time_elapsed_string')) {
    function time_elapsed_string($datetime, $full = false) {
        $now = new DateTime;
        $ago = new DateTime($datetime);
        $diff = $now->diff($ago);

        $diff->w = floor($diff->d / 7);
        $diff->d -= $diff->w * 7;

        $string = array(
            'y' => 'year',
            'm' => 'month',
            'w' => 'week',
            'd' => 'day',
            'h' => 'hour',
            'i' => 'minute',
            's' => 'second',
        );
        foreach ($string as $k => &$v) {
            if ($diff->$k) {
                $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
            } else {
                unset($string[$k]);
            }
        }

        if (!$full) $string = array_slice($string, 0, 1);
        return $string ? implode(', ', $string) . ' ago' : 'just now';
    }
}

/**
 * Fungsi untuk memeriksa apakah penyimpanan sudah penuh.
 *
 * @param mysqli $conn Koneksi database.
 * @param int $totalStorageBytes Total kapasitas penyimpanan dalam byte.
 * @return bool True jika penyimpanan penuh, false jika tidak.
 */
if (!function_exists('isStorageFull')) {
    function isStorageFull($conn, $totalStorageBytes) {
        $usedStorageBytes = 0;
        $stmt = $conn->prepare("SELECT SUM(file_size) as total_size FROM files");
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        if ($row['total_size']) {
            $usedStorageBytes = $row['total_size'];
        }
        $stmt->close();
        return $usedStorageBytes >= $totalStorageBytes;
    }
}

// Fungsi untuk mengekstrak file ZIP
if (!function_exists('extractZipFile')) {
    /**
     * Mengekstrak file ZIP ke direktori tujuan.
     *
     * @param string $zipFilePath Path absolut ke file ZIP.
     * @param string $extractTo Path absolut ke direktori tujuan ekstraksi.
     * @param mysqli $conn Koneksi database untuk menyimpan informasi file yang diekstrak.
     * @param int $folderId ID folder di database tempat file ZIP berada (untuk menentukan parent_id file yang diekstrak).
     * @return array Hasil ekstraksi (success, message).
     */
    function extractZipFile($zipFilePath, $extractTo, $conn, $folderId)
    {
        if (!file_exists($zipFilePath)) {
            return ["success" => false, "message" => "❌ ZIP file not found."]; // File ZIP tidak ditemukan.
        }

        $zip = new ZipArchive();
        
        // Dapatkan nama folder yang diusulkan dari nama file ZIP
        $originalFolderName = pathinfo($zipFilePath, PATHINFO_FILENAME);
        
        // Gunakan generateUniqueFolderName untuk mendapatkan nama folder unik
        // $extractTo sudah berisi path dasar (misal: uploads/current_folder/)
        // Kita perlu memastikan $extractTo berakhir dengan slash untuk generateUniqueFolderName
        $extractTo = rtrim($extractTo, '/') . '/';
        $uniqueFolderName = generateUniqueFolderName($originalFolderName, $extractTo);
        $finalExtractToDirectory = $extractTo . $uniqueFolderName . '/';

        // Pastikan direktori tujuan ada
        if (!is_dir($finalExtractToDirectory)) {
            if (!mkdir($finalExtractToDirectory, 0777, true)) {
                return ["success" => false, "message" => "❌ Failed to create extraction directory: " . htmlspecialchars($finalExtractToDirectory)]; // Gagal membuat direktori ekstrak.
            }
        }

        if ($zip->open($zipFilePath) === true) {
            // Cek isi ZIP sebelum ekstraksi
            if ($zip->numFiles == 0) {
                $zip->close();
                return ["success" => false, "message" => "❌ Empty or corrupted ZIP file."]; // File ZIP kosong atau rusak.
            }

            // Ekstrak semua file ke direktori unik yang baru dibuat
            if ($zip->extractTo($finalExtractToDirectory)) {
                $zip->close();

                // Verifikasi apakah ada file yang diekstrak
                $extractedFiles = array_diff(scandir($finalExtractToDirectory), ['.', '..']);
                if (empty($extractedFiles)) {
                    // Jika direktori kosong setelah ekstraksi, mungkin ada masalah
                    // Hapus folder kosong yang baru dibuat
                    rmdir($finalExtractToDirectory);
                    return ["success" => false, "message" => "❌ Extraction failed. No files found after extraction."]; // Gagal ekstrak. Tidak ada file yang ditemukan setelah ekstraksi.
                }

                // --- Simpan informasi file yang diekstrak ke database ---
                // Buat entri folder baru di database untuk folder hasil ekstraksi
                $newFolderId = null;
                $stmt_insert_folder = $conn->prepare("INSERT INTO folders (folder_name, parent_id, created_at, updated_at) VALUES (?, ?, NOW(), NOW())");
                $stmt_insert_folder->bind_param("si", $uniqueFolderName, $folderId); // $folderId adalah parent_id untuk folder baru ini
                if ($stmt_insert_folder->execute()) {
                    $newFolderId = $conn->insert_id;
                } else {
                    error_log("Error inserting new folder for extracted files: " . $conn->error);
                    // Jika gagal membuat entri folder di DB, kita tidak bisa melanjutkan penyimpanan file ke DB dengan benar
                    // Namun, file fisik sudah diekstrak, jadi kita bisa mengembalikan sukses parsial atau error
                    return ["success" => false, "message" => "✅ ZIP extracted successfully to: <b>" . htmlspecialchars($finalExtractToDirectory) . "</b>, but failed to record folder in database."];
                }
                $stmt_insert_folder->close();

                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($finalExtractToDirectory, RecursiveDirectoryIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::SELF_FIRST
                );

                foreach ($iterator as $item) {
                    // Path relatif dari folder hasil ekstraksi (misal: subfolder/file.txt)
                    $relativePath = str_replace($finalExtractToDirectory, '', $item->getPathname());
                    $relativePath = ltrim($relativePath, '/\\'); // Hapus slash di awal

                    // Tentukan parent_id untuk item ini
                    $currentParentId = $newFolderId; // Default ke folder hasil ekstraksi yang baru dibuat
                    
                    if ($item->isDir() && $relativePath !== '') {
                        // Jika ini subfolder, cari atau buat entri foldernya di DB
                        $subfolderPathParts = explode('/', $relativePath);
                        $currentDbParentId = $newFolderId; // Mulai dari folder hasil ekstraksi

                        foreach ($subfolderPathParts as $part) {
                            // Cek apakah folder sudah ada di DB di bawah parent_id saat ini
                            $stmt_find_subfolder = $conn->prepare("SELECT id FROM folders WHERE folder_name = ? AND parent_id = ?");
                            $stmt_find_subfolder->bind_param("si", $part, $currentDbParentId);
                            $stmt_find_subfolder->execute();
                            $result_find_subfolder = $stmt_find_subfolder->get_result();
                            if ($row_subfolder = $result_find_subfolder->fetch_assoc()) {
                                $currentDbParentId = $row_subfolder['id'];
                            } else {
                                // Buat folder baru di DB jika belum ada
                                $stmt_insert_subfolder = $conn->prepare("INSERT INTO folders (folder_name, parent_id, created_at, updated_at) VALUES (?, ?, NOW(), NOW())");
                                $stmt_insert_subfolder->bind_param("si", $part, $currentDbParentId);
                                if ($stmt_insert_subfolder->execute()) {
                                    $currentDbParentId = $conn->insert_id;
                                } else {
                                    error_log("Error inserting subfolder: " . $conn->error);
                                    break; // Hentikan jika gagal membuat subfolder
                                }
                                $stmt_insert_subfolder->close();
                            }
                            $stmt_find_subfolder->close();
                        }
                        $currentParentId = $currentDbParentId;
                    }

                    if ($item->isFile()) {
                        $file_name = $item->getFilename();
                        $file_size = $item->getSize();
                        $file_type = pathinfo($file_name, PATHINFO_EXTENSION);
                        // Path relatif dari root aplikasi (misal: uploads/folder_induk/nama_zip(1)/subfolder/file.txt)
                        $file_path_db = str_replace(__DIR__ . '/', '', $item->getPathname()); 

                        // Cek apakah file sudah ada di database untuk folder ini
                        $stmt_check_file = $conn->prepare("SELECT id FROM files WHERE file_name = ? AND folder_id = ?");
                        $stmt_check_file->bind_param("si", $file_name, $currentParentId);
                        $stmt_check_file->execute();
                        $result_check_file = $stmt_check_file->get_result();
                        if ($result_check_file->num_rows > 0) {
                            // File sudah ada, mungkin perlu update atau skip
                            $stmt_check_file->close();
                            continue;
                        }
                        $stmt_check_file->close();

                        $stmt_insert_file = $conn->prepare("INSERT INTO files (file_name, file_path, file_size, file_type, folder_id, uploaded_at) VALUES (?, ?, ?, ?, ?, NOW())");
                        $stmt_insert_file->bind_param("ssisi", $file_name, $file_path_db, $file_size, $file_type, $currentParentId);
                        if (!$stmt_insert_file->execute()) {
                            error_log("Error inserting extracted file into DB: " . $conn->error);
                        }
                        $stmt_insert_file->close();
                    }
                }
                // --- Akhir penyimpanan informasi file ke database ---

                return ["success" => true, "message" => "✅ ZIP extracted successfully to: <b>" . htmlspecialchars($finalExtractToDirectory) . "</b><br>📁 Number of files: " . count($extractedFiles)]; // ZIP berhasil diekstrak.
            } else {
                $zip->close();
                // Jika ekstraksi fisik gagal, hapus folder yang mungkin sudah dibuat
                if (is_dir($finalExtractToDirectory)) {
                    rmdir($finalExtractToDirectory);
                }
                return ["success" => false, "message" => "❌ Failed to extract ZIP contents."]; // Gagal mengekstrak isi ZIP.
            }
        } else {
            return ["success" => false, "message" => "❌ Failed to open ZIP file. It might be corrupted."]; // Gagal membuka file ZIP. Mungkin file rusak.
        }
    }
}

?>
