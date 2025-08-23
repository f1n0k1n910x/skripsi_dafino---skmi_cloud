<?php
$baseUrl = '/skripsi_dafino---skmi_cloud/';
$baseV2Url = '/skripsi_dafino---skmi_cloud/v2';

$filterExtensions = [];

$docExt = ['doc','docx','pdf','ppt','pptx','xls','xlsx','txt','odt','odp','rtf','md','log','csv','tex'];
$musicExt = ['mp3','wav','aac','ogg','flac','m4a','alac','wma','opus','amr','mid'];
$videoExt = ['mp4','mkv','avi','mov','wmv','flv','webm','3gp','m4v','mpg','mpeg','ts','ogv'];
$codeExt = ['html','htm','css','js','php','py','java','json','xml','ts','tsx','jsx','vue','cpp','c','cs','rb','go','swift','sql','sh','bat','ini','yml','yaml','md','pl','r'];
$archiveExt = ['zip','rar','7z','tar','gz','bz2','xz','iso','cab','arj'];
$instExt = ['exe','msi','apk','ipa','sh','bat','jar','appimage','dmg','bin'];
$ptpExt = ['torrent','nzb','ed2k','part','!ut'];
$imageExt = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg', 'tiff'];
$cadExt = ['dwg', 'dxf', 'dgn', 'iges', 'igs', 'step', 'stp', 'stl', '3ds', 'obj', 'sldprt', 'sldasm', 'ipt', 'iam', 'catpart', 'catproduct', 'prt', 'asm', 'fcstd', 'skp', 'x_t', 'x_b'];


function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];

    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);

    $bytes /= (1 << (10 * $pow));

    return round($bytes, $precision) . ' ' . $units[$pow];
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

function getFileColorClassPhp($filename) {
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    
    $colorMap = [
        // Images - blue
        'jpg' => 'blue', 'jpeg' => 'blue', 'png' => 'blue',
        'gif' => 'blue', 'bmp' => 'blue', 'webp' => 'blue', 'svg' => 'blue',
        
        // Documents - red
        'pdf' => 'red', 'doc' => 'blue', 'docx' => 'blue',
        'txt' => 'gray', 'rtf' => 'gray', 'odt' => 'blue',
        
        // Archives - orange
        'zip' => 'orange', 'rar' => 'orange', '7z' => 'orange',
        'tar' => 'orange', 'gz' => 'orange',
        
        // Code - green
        'php' => 'green', 'js' => 'green', 'css' => 'green',
        'html' => 'green', 'xml' => 'green', 'json' => 'green',
        'py' => 'green', 'java' => 'green', 'cpp' => 'green',
        'c' => 'green', 'sql' => 'green',
        
        // Audio - purple
        'mp3' => 'purple', 'wav' => 'purple', 'ogg' => 'purple',
        'flac' => 'purple', 'm4a' => 'purple',
        
        // Video - pink
        'mp4' => 'pink', 'avi' => 'pink', 'mov' => 'pink',
        'wmv' => 'pink', 'flv' => 'pink', 'webm' => 'pink',
        
        // CAD files - teal
        'dwg' => 'teal', 'dxf' => 'teal', 'stl' => 'teal',
        'step' => 'teal', 'stp' => 'teal',
        
        // Presentations & Spreadsheets - specific colors
        'ppt' => 'orange', 'pptx' => 'orange',
        'xls' => 'green', 'xlsx' => 'green',
        
        // Installers - dark gray
        'exe' => 'dark-gray', 'msi' => 'dark-gray', 'dmg' => 'dark-gray', 'pkg' => 'dark-gray'
    ];
    
    return $colorMap[$extension] ?? 'default';
}

// Function to fetch file data
function getFileData($conn, $fileId, $user_id) {
    $data = [];

    // Fetch user information to display in the header
    // Hanya fetch jika user_id ada (yaitu, jika pengguna sudah login)
    if ($user_id !== null) {
        $stmt = $conn->prepare("SELECT username, full_name, profile_picture FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $user_result = $stmt->get_result();
        $data['user'] = $user_result->fetch_assoc();
        $stmt->close();
    } else {
        // Jika tidak ada user_id (shared link, belum login), set default user info
        $data['user'] = [
            'username' => 'Guest',
            'full_name' => 'Guest User',
            'profile_picture' => 'img/default_avatar.png'
        ];
    }


    $file = null;
    if ($fileId > 0) {
        $stmt = $conn->prepare("SELECT file_name, file_path, file_size, file_type, uploaded_at, folder_id FROM files WHERE id = ?");
        $stmt->bind_param("i", $fileId);
        $stmt->execute();
        $result = $stmt->get_result();
        $file = $result->fetch_assoc();
        $stmt->close();
    }

    if (!$file) {
        return ['error' => true, 'message' => 'File not found.'];
    }

    $data['file'] = $file;
    $data['fileName'] = $file['file_name'];
    $data['filePath'] = $file['file_path'];
    $data['fileType'] = strtolower($file['file_type']);
    $data['fullFilePath'] = __DIR__ . '/' . $file['file_path'];
    $data['fileSize'] = $file['file_size'];
    $data['uploadedAt'] = $file['uploaded_at'];

    $documentExtensions = ['doc', 'docx', 'pdf', 'ppt', 'pptx', 'xls', 'xlsx', 'txt', 'odt', 'odp', 'rtf', 'md', 'log', 'csv', 'tex'];
    $audioExtensions = ['mp3', 'wav', 'aac', 'ogg', 'flac', 'm4a', 'alac', 'wma', 'opus', 'amr', 'mid'];
    $videoExtensions = ['mp4', 'mkv', 'avi', 'mov', 'wmv', 'flv', 'webm', '3gp', 'm4v', 'mpg', 'mpeg', 'ts', 'ogv'];
    $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg', 'tiff'];
    $codeExtensions = ['html', 'htm', 'css', 'js', 'php', 'py', 'java', 'json', 'xml', 'ts', 'tsx', 'jsx', 'vue', 'cpp', 'c', 'cs', 'rb', 'go', 'swift', 'sql', 'sh', 'bat', 'ini', 'yml', 'yaml', 'pl', 'r'];
    $archiveExtensions = ['zip', 'rar', '7z', 'tar', 'gz', 'bz2', 'xz', 'iso', 'cab', 'arj'];
    // NEW: CAD Extensions
    $cadExtensions = ['dwg', 'dxf', 'dgn', 'iges', 'igs', 'step', 'stp', 'stl', '3ds', 'obj', 'sldprt', 'sldasm', 'ipt', 'iam', 'catpart', 'catproduct', 'prt', 'asm', 'fcstd', 'skp', 'x_t', 'x_b'];


    $fileCategory = 'other';
    if (in_array($data['fileType'], $documentExtensions)) {
        $fileCategory = 'document';
    } elseif (in_array($data['fileType'], $audioExtensions)) {
        $fileCategory = 'audio';
    } elseif (in_array($data['fileType'], $videoExtensions)) {
        $fileCategory = 'video';
    } elseif (in_array($data['fileType'], $imageExtensions)) {
        $fileCategory = 'image';
    } elseif (in_array($data['fileType'], $codeExtensions)) {
        $fileCategory = 'code';
    } elseif (in_array($data['fileType'], $archiveExtensions)) {
        $fileCategory = 'archive';
    } elseif (in_array($data['fileType'], $cadExtensions)) { // NEW: CAD Category
        $fileCategory = 'cad';
    }
    $data['fileCategory'] = $fileCategory;

    // For breadcrumbs
    // Breadcrumbs hanya akan ditampilkan jika pengguna sudah login
    $currentFolderId = $file['folder_id'];
    $breadcrumbs = [];
    if ($currentFolderId && $user_id !== null) { // Hanya tampilkan breadcrumbs jika pengguna login
        $path = [];
        $tempFolderId = $currentFolderId;
        while ($tempFolderId !== NULL) {
            $stmt = $conn->prepare("SELECT id, folder_name, parent_id FROM folders WHERE id = ?");
            $stmt->bind_param("i", $tempFolderId);
            $stmt->execute();
            $result = $stmt->get_result();
            $folder = $result->fetch_assoc();
            if ($folder) {
                array_unshift($path, ['id' => $folder['id'], 'name' => $folder['folder_name']]);
                $tempFolderId = $folder['parent_id'];
            } else {
                $currentFolderId = NULL; // Break if folder not found (e.g., deleted)
                break;
            }
        }
        $breadcrumbs = $path;
    }
    $data['breadcrumbs'] = $breadcrumbs;
    $data['currentFolderId'] = $currentFolderId;

    // For code files, read content
    if ($fileCategory === 'code' && file_exists($data['fullFilePath'])) {
        $data['fileContent'] = file_get_contents($data['fullFilePath']);
    } elseif ($fileCategory === 'archive' && ($data['fileType'] === 'zip' || $data['fileType'] === 'tar')) {
        // For archive files, generate the HTML content for the table
        $data['fileContent'] = readArchiveContent($data['fullFilePath'], $data['fileType']);
    } else {
        $data['fileContent'] = null;
    }

    return $data;
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

if (!function_exists('fetchFolders')) {
    function fetchFolders(
        $conn,
        int $currentFolderId = null,
        string $searchQuery = '',
        array $filterExtensions = [],
        string $baseUploadDir = '',
        string $sizeFilter = ''
    ): array {
        $sqlFolders = "SELECT id, folder_name, created_at, updated_at 
                       FROM folders 
                       WHERE parent_id <=> ?";
        $folderParams = [$currentFolderId];
        $folderTypes = "i";
    
        // Apply search filter
        if (!empty($searchQuery)) {
            $sqlFolders .= " AND folder_name LIKE ?";
            $searchTerm = '%' . $searchQuery . '%';
            $folderParams[] = $searchTerm;
            $folderTypes .= "s";
        }
    
        // Default sorting (alphabetical)
        $sqlFolders .= " ORDER BY folder_name ASC";
    
        $stmt = $conn->prepare($sqlFolders);
        $stmt->bind_param($folderTypes, ...$folderParams);
        $stmt->execute();
        $result = $stmt->get_result();
    
        $folders = [];
        while ($row = $result->fetch_assoc()) {
            // Apply file type filter to folders
            if (empty($filterExtensions) || folderContainsFilteredFiles($conn, $row['id'], $filterExtensions, realpath(__DIR__ . '/../../uploads/'))) {
                $folders[] = $row;
            }
        }
        $stmt->close();
    
        // Calculate folder sizes
        foreach ($folders as &$folder) {
            $folderPath = realpath(__DIR__ . '/../../uploads') . "/" . getFolderPath($conn, $folder['id']);
            $folder['calculated_size'] = getFolderSize($folderPath);
        }
        unset($folder);
    
        // Apply size sorting if requested
        if ($sizeFilter === 'asc') {
            usort($folders, fn($a, $b) => $a['calculated_size'] <=> $b['calculated_size']);
        } elseif ($sizeFilter === 'desc') {
            usort($folders, fn($a, $b) => $b['calculated_size'] <=> $a['calculated_size']);
        }
    
        return $folders;
    }    
}

if (!function_exists('fetchFiles')) {
    function fetchFiles(
        mysqli $conn,
        int $currentFolderId = null,
        string $searchQuery = '',
        array $filterExtensions = [],
        string $sizeFilter = ''
    ): array {
        $sqlFiles = "SELECT id, file_name, file_path, file_size, file_type, uploaded_at 
                     FROM files 
                     WHERE folder_id <=> ?";
        $params = [$currentFolderId];
        $types = "i";
    
        // Apply search filter
        if (!empty($searchQuery)) {
            $sqlFiles .= " AND file_name LIKE ?";
            $searchTerm = '%' . $searchQuery . '%';
            $params[] = $searchTerm;
            $types .= "s";
        }
    
        // Apply extension filter
        if (!empty($filterExtensions)) {
            $placeholders = implode(',', array_fill(0, count($filterExtensions), '?'));
            $sqlFiles .= " AND file_type IN ($placeholders)";
            foreach ($filterExtensions as $ext) {
                $params[] = $ext;
                $types .= "s";
            }
        }
    
        // Sorting
        if ($sizeFilter === 'asc') {
            $sqlFiles .= " ORDER BY file_size ASC";
        } elseif ($sizeFilter === 'desc') {
            $sqlFiles .= " ORDER BY file_size DESC";
        } else {
            $sqlFiles .= " ORDER BY file_name ASC"; // default alphabetical
        }
    
        $stmt = $conn->prepare($sqlFiles);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
    
        $files = [];
        while ($row = $result->fetch_assoc()) {
            $files[] = $row;
        }
        $stmt->close();
    
        return $files;
    }  
}

if (!function_exists('buildFolderBreadcrumbs')) {
    function buildFolderBreadcrumbs(
        mysqli $conn,
        ?int $currentFolderId
    ): array {
        $path = [];
        $currentFolderName = 'Root';
        $currentFolderPath = '';
    
        if ($currentFolderId) {
            $tempFolderId = $currentFolderId;
    
            while ($tempFolderId !== null) {
                $stmt = $conn->prepare("SELECT id, folder_name, parent_id FROM folders WHERE id = ?");
                $stmt->bind_param("i", $tempFolderId);
                $stmt->execute();
                $result = $stmt->get_result();
                $folder = $result->fetch_assoc();
                $stmt->close();
    
                if ($folder) {
                    array_unshift($path, [
                        'id' => $folder['id'],
                        'name' => $folder['folder_name']
                    ]);
                    $tempFolderId = $folder['parent_id'];
                } else {
                    // Folder not found, fallback to root
                    $path = [];
                    $currentFolderId = null;
                    $currentFolderName = 'Root';
                    $currentFolderPath = '';
                    break;
                }
            }
    
            if (!empty($path)) {
                $currentFolderName = end($path)['name'];
                $currentFolderPathArray = array_map(fn($f) => $f['name'], $path);
                $currentFolderPath = implode('/', $currentFolderPathArray);
            }
        }
    
        return [
            'breadcrumbs' => $path,             // array of ['id' => ..., 'name' => ...]
            'currentFolderId' => $currentFolderId,
            'currentFolderName' => $currentFolderName,
            'currentFolderPath' => $currentFolderPath
        ];
    }
}

if (!function_exists('getDeletedFiles')) {
    function getDeletedFiles($conn, $userId, $searchQuery = '', $filterExtensions = [], $releaseFilter = 'all', $sortOrder = 'asc') {
        $deletedItems = [];
    
        // Base query
        $sql = "SELECT id, file_name, file_path, file_size, file_type, deleted_at 
                FROM deleted_files 
                WHERE user_id = ?";
        $params = [$userId];
        $types = "i";
    
        // Search filter
        if (!empty($searchQuery)) {
            $sql .= " AND file_name LIKE ?";
            $params[] = "%" . $searchQuery . "%";
            $types .= "s";
        }
    
        // File type filter
        if (!empty($filterExtensions)) {
            $placeholders = implode(',', array_fill(0, count($filterExtensions), '?'));
            $sql .= " AND file_type IN ($placeholders)";
            foreach ($filterExtensions as $ext) {
                $params[] = $ext;
                $types .= "s";
            }
        }
    
        // Sorting
        if ($releaseFilter === 'newest') {
            $sql .= " ORDER BY deleted_at DESC";
        } elseif ($releaseFilter === 'oldest') {
            $sql .= " ORDER BY deleted_at ASC";
        } else {
            $sql .= ($sortOrder === 'asc')
                ? " ORDER BY file_name ASC"
                : " ORDER BY file_name DESC";
        }
    
        // Prepare & execute
        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            throw new Exception("SQL prepare failed: " . $conn->error);
        }
    
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
    
        // Collect rows
        while ($row = $result->fetch_assoc()) {
            $row['item_type'] = 'file';
            $deletedItems[] = $row;
        }
    
        $stmt->close();
    
        return $deletedItems;
    }    
}

if (!function_exists('getDeletedFolders')) {
    function getDeletedFolders($conn, $userId, $searchQuery = "", $releaseFilter = "all", $sortOrder = "asc") {
        $sql = "SELECT id, folder_name, deleted_at 
                FROM deleted_folders 
                WHERE user_id = ?";
        
        $params = [$userId];
        $types = "i";

        // Apply search
        if (!empty($searchQuery)) {
            $sql .= " AND folder_name LIKE ?";
            $params[] = "%" . $searchQuery . "%";
            $types .= "s";
        }

        // Apply sorting
        if ($releaseFilter === 'newest') {
            $sql .= " ORDER BY deleted_at DESC";
        } elseif ($releaseFilter === 'oldest') {
            $sql .= " ORDER BY deleted_at ASC";
        } else {
            $sql .= " ORDER BY folder_name " . ($sortOrder === 'asc' ? "ASC" : "DESC");
        }

        // Prepare + execute
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();

        $deletedFolders = [];
        while ($row = $result->fetch_assoc()) {
            $row['item_type'] = 'folder';
            $deletedFolders[] = $row;
        }

        $stmt->close();
        return $deletedFolders;
    }
}