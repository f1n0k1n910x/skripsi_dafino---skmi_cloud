<?php
include 'config.php';
include 'functions.php';

session_start();

// Cek apakah request berasal dari tautan berbagi (dari s.php)
$is_shared_link = isset($_GET['shared']) && $_GET['shared'] === 'true';

// Jika bukan dari tautan berbagi DAN pengguna belum login, redirect ke login.php
if (!$is_shared_link && !isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'] ?? null; // user_id bisa null jika akses dari shared link dan belum login

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
    } else {
        $data['fileContent'] = null;
    }

    return $data;
}

// Handle AJAX request for file data
if (isset($_GET['action']) && $_GET['action'] === 'get_file_data') {
    header('Content-Type: application/json');
    $fileId = isset($_GET['file_id']) ? (int)$_GET['file_id'] : 0;
    echo json_encode(getFileData($conn, $fileId, $user_id)); // Pass $user_id to getFileData
    $conn->close();
    exit();
}

// Initial data load for the first page render
$fileId = isset($_GET['file_id']) ? (int)$_GET['file_id'] : 0;
$initial_data = getFileData($conn, $fileId, $user_id); // Pass $user_id

if (isset($initial_data['error'])) {
    // If there's an error, redirect based on shared link status
    if ($is_shared_link) {
        // For shared link, just show an error message
        echo "Error: " . htmlspecialchars($initial_data['message']);
        exit();
    } else {
        // For non-shared link, redirect to index with error
        header('Location: index.php?error=' . urlencode($initial_data['message']));
        exit();
    }
}

// Extract data for HTML rendering
$user = $initial_data['user'];
$file = $initial_data['file'];
$fileName = $initial_data['fileName'];
$filePath = $initial_data['filePath'];
$fileType = $initial_data['fileType'];
$fullFilePath = $initial_data['fullFilePath'];
$fileSize = $initial_data['fileSize'];
$uploadedAt = $initial_data['uploadedAt'];
$fileCategory = $initial_data['fileCategory'];
$breadcrumbs = $initial_data['breadcrumbs'];
$currentFolderId = $initial_data['currentFolderId'];
$fileContent = $initial_data['fileContent']; // Content for code files

// Fungsi untuk membaca file teks (dari kode yang Anda berikan)
function readTextFile($path) {
    $content = file_get_contents($path);
    return htmlspecialchars($content); // Hindari XSS
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>File Preview : <?php echo htmlspecialchars($fileName); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <?php if ($fileCategory === 'code'): ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/vs2015.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/languages/php.min.js"></script>
    <script>hljs.highlightAll();</script>
    <?php endif; ?>
    <style>
        :root {
            --metro-blue: #0078D7;
            --metro-dark-blue: #0056b3;
            --metro-light-gray: #E1E1E1;
            --metro-medium-gray: #C8C8C8;
            --metro-dark-gray: #666666;
            --metro-text-color: #333333;
            --metro-bg-color: #F0F0F0;
            --metro-success: #4CAF50;
            --metro-error: #E81123;
            --metro-warning: #FF8C00;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            background-color: var(--metro-bg-color);
            color: var(--metro-text-color);
            padding: 0;
            height: 100vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .header-sticky {
            position: sticky;
            top: 0;
            z-index: 1000;
            background-color: #fff;
            /* Removed box-shadow */
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: transform 0.3s ease-in-out;
        }

        .header-sticky h1 {
            margin: 0;
            font-size: 1.8em;
            font-weight: 300;
            color: var(--metro-text-color);
            display: flex;
            align-items: center;
        }

        .header-sticky h1 i {
            margin-right: 15px;
            color: var(--metro-blue);
        }

        .profile-container {
            display: flex;
            align-items: center;
        }

        .profile-container .profile-image {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            margin-left: 15px;
            border: 2px solid var(--metro-light-gray);
            transition: border-color 0.3s ease-in-out;
        }

        .profile-container .profile-image:hover {
            border-color: var(--metro-blue);
        }

        .profile-container .username {
            font-weight: 600;
            color: var(--metro-text-color);
            font-size: 1em;
            text-decoration: none;
            transition: color 0.3s ease-in-out;
        }

        .profile-container .username:hover {
            color: var(--metro-blue);
        }

        .main-container {
            display: flex;
            flex-grow: 1;
            overflow: hidden;
            padding: 20px;
        }

        .preview-pane {
            flex: 3;
            background-color: #fff;
            margin-right: 20px;
            border-radius: 8px;
            /* Removed box-shadow */
            padding: 20px;
            overflow-y: auto;
            position: relative;
            animation: fadeIn 0.5s ease-out;
        }

        .file-info-pane {
            flex: 1;
            background-color: #fff;
            border-radius: 8px;
            /* Removed box-shadow */
            padding: 20px;
            animation: slideInRight 0.5s ease-out;
        }

        .file-info-pane h3 {
            margin-top: 0;
            font-weight: 600;
            font-size: 1.2em;
            color: var(--metro-text-color);
            border-bottom: 2px solid var(--metro-light-gray);
            padding-bottom: 10px;
            margin-bottom: 15px;
        }

        .file-info-item {
            margin-bottom: 15px;
            line-height: 1.5;
        }

        .file-info-item strong {
            display: block;
            font-size: 0.9em;
            color: var(--metro-dark-gray);
            margin-bottom: 5px;
        }

        .file-info-item span {
            display: block;
            font-size: 1em;
            color: var(--metro-text-color);
            word-wrap: break-word;
        }

        .back-button {
            display: inline-flex;
            align-items: center;
            background: transparent;
            border: none;
            cursor: pointer;
            color: var(--metro-text-color);
            text-decoration: none;
            font-size: 1.1em;
            font-weight: 400;
            margin-bottom: 20px;
            transition: color 0.2s ease-in-out, transform 0.2s ease-in-out;
        }

        .back-button:hover {
            color: var(--metro-blue);
            transform: translateX(-5px);
        }

        .back-button i {
            margin-right: 8px;
            font-size: 1.2em;
        }

        .preview-content {
            text-align: center;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 20px;
            min-height: 500px;
            position: relative; /* Added for zoom controls positioning */
        }

        .preview-content img {
            max-width: 100%;
            max-height: 70vh;
            object-fit: contain;
            border-radius: 5px;
            /* Removed box-shadow */
            transform-origin: center center; /* Added for zoom */
            transition: transform 0.1s ease-out; /* Added for smooth zoom */
        }

        .preview-content video, .preview-content audio {
            width: 100%;
            max-width: 800px;
            border-radius: 5px;
            /* Removed box-shadow */
            transform-origin: center center; /* Added for zoom */
            transition: transform 0.1s ease-out; /* Added for smooth zoom */
        }

        .preview-content pre {
            background-color: var(--metro-bg-color);
            padding: 0px;
            border-radius: 5px;
            text-align: left;
            white-space: pre-wrap;
            word-break: break-all;
            width: 100%;
            box-sizing: border-box;
            max-height: 70vh;
            overflow-y: auto;
            /* Removed box-shadow */
            transform-origin: top left; /* Added for zoom */
            transition: transform 0.1s ease-out; /* Added for smooth zoom */
        }

        .preview-content pre code {
            font-family: 'Consolas', 'Courier New', Courier, monospace;
            font-size: 0.9em;
        }

        .general-file-info {
            background-color: var(--metro-light-gray);
            color: var(--metro-dark-gray);
            padding: 30px;
            border-radius: 5px;
            text-align: center;
            animation: fadeIn 0.5s ease-out;
        }

        .general-file-info .icon {
            font-size: 48px;
            color: var(--metro-blue);
            margin-bottom: 20px;
        }

        .general-file-info p {
            font-size: 1.1em;
            font-weight: 400;
        }

        .download-button {
            display: inline-flex;
            align-items: center;
            padding: 12px 24px;
            background-color: var(--metro-blue);
            color: #FFFFFF;
            text-decoration: none;
            border-radius: 5px;
            font-weight: 600;
            transition: background-color 0.2s ease-out, transform 0.2s ease-out;
            margin-top: 20px;
            /* Removed box-shadow */
        }

        .download-button:hover {
            background-color: var(--metro-dark-blue);
            transform: translateY(-2px);
            /* Removed box-shadow */
        }

        .download-button i {
            margin-right: 10px;
        }

        .pdf-viewer {
            width: 100%;
            height: 70vh;
            border: none;
            transform-origin: center center; /* Added for zoom */
            transition: transform 0.1s ease-out; /* Added for smooth zoom */
        }

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideInRight {
            from { transform: translateX(20px); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        .breadcrumbs {
            margin-bottom: 20px;
            font-size: 0.9em;
        }
        .breadcrumbs a {
            color: var(--metro-dark-gray);
            text-decoration: none;
            transition: color 0.2s ease-in-out;
        }
        .breadcrumbs a:hover {
            color: var(--metro-blue);
            text-decoration: underline;
        }
        .breadcrumbs span {
            color: var(--metro-dark-gray);
            margin: 0 5px;
        }

        /* Zoom Controls */
        .zoom-controls {
            position: absolute;
            top: 20px;
            right: 20px;
            display: flex;
            gap: 10px;
            z-index: 10;
        }

        .zoom-button {
            background-color: var(--metro-blue);
            color: #fff;
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            font-size: 1.2em;
            cursor: pointer;
            display: flex;
            justify-content: center;
            align-items: center;
            /* Removed box-shadow */
            transition: background-color 0.2s ease-out, transform 0.2s ease-out;
        }

        .zoom-button:hover {
            background-color: var(--metro-dark-blue);
            transform: scale(1.05);
        }

        /* Styles from your provided code for text viewer */
        .viewer-container {
            padding: 1rem;
            background: #fff;
            border: 1px solid #ccc;
            overflow: auto;
            max-height: 70vh; /* Adjusted to fit preview-content height */
            width: 100%; /* Ensure it takes full width */
            box-sizing: border-box; /* Include padding and border in width */
        }
        .viewer-container pre {
            white-space: pre-wrap;
            word-wrap: break-word;
            background: #eef;
            padding: 1rem;
            text-align: left; /* Align text to left */
        }
    </style>
</head>
<body>

<header class="header-sticky">
    <h1><i class="fas fa-file-alt"></i>File Preview</h1>
    <div class="profile-container">
        <?php if ($user_id !== null): ?>
            <a href="profile.php" class="username" id="headerUsername">
                <?php echo htmlspecialchars($user['full_name'] ?? $user['username'] ?? 'User'); ?>
            </a>
            <a href="profile.php">
                <img src="<?php echo htmlspecialchars($user['profile_picture'] ?? 'img/default_avatar.png'); ?>" alt="Profile Picture" class="profile-image" id="headerProfilePicture">
            </a>
        <?php else: ?>
            <span class="username" id="headerUsername">Guest</span>
            <img src="img/default_avatar.png" alt="Profile Picture" class="profile-image" id="headerProfilePicture">
        <?php endif; ?>
    </div>
</header>

<div class="main-container">
    <div class="preview-pane">
        <div class="breadcrumbs" id="breadcrumbsContainer">
            <a href="index.php"><i class="fas fa-home"></i> Home</a>
            <?php
            // Tampilkan breadcrumbs hanya jika pengguna login
            if ($user_id !== null) {
                foreach ($breadcrumbs as $crumb): ?>
                    <span>/</span><a href="index.php?folder=<?php echo htmlspecialchars($crumb['id']); ?>"><?php echo htmlspecialchars($crumb['name']); ?></a>
                <?php endforeach;
            }
            ?>
            <span>/</span><span id="currentFileNameBreadcrumb"><?php echo htmlspecialchars($fileName); ?></span>
        </div>
        <a href="index.php<?php echo ($user_id !== null && $currentFolderId) ? '?folder=' . htmlspecialchars($currentFolderId) : ''; ?>" class="back-button" id="backButton">
            <i class="fas fa-arrow-left"></i> Back
        </a>
        <div class="preview-content" id="previewContent">
            <?php
            // NEW: Add 'cad' to zoomable categories
            $zoomableCategories = ['image', 'document', 'video', 'code', 'cad'];
            $isZoomable = in_array($fileCategory, $zoomableCategories);

            if ($isZoomable && $fileCategory !== 'archive') {
                echo '<div class="zoom-controls">';
                echo '<button class="zoom-button" id="zoomOutBtn"><i class="fas fa-minus"></i></button>';
                echo '<button class="zoom-button" id="zoomInBtn"><i class="fas fa-plus"></i></button>';
                echo '</div>';
            }
            ?>

            <?php if ($fileCategory === 'image'): ?>
                <img id="previewElement" src="<?php echo htmlspecialchars($filePath); ?>" alt="<?php echo htmlspecialchars($fileName); ?>">
            <?php elseif ($fileCategory === 'audio'): ?>
                <audio id="previewElement" controls autoplay>
                    <source src="<?php echo htmlspecialchars($filePath); ?>" type="audio/<?php echo htmlspecialchars($fileType); ?>">
                    Your browser does not support the audio element.
                </audio>
            <?php elseif ($fileCategory === 'video'): ?>
                <video id="previewElement" controls autoplay>
                    <source src="<?php echo htmlspecialchars($filePath); ?>" type="video/<?php echo htmlspecialchars($fileType); ?>">
                    Your browser does not support the video tag.
                </video>
            <?php elseif ($fileCategory === 'document' && $fileType === 'pdf'): ?>
                <iframe id="previewElement" src="<?php echo htmlspecialchars($filePath); ?>" class="pdf-viewer"></iframe>
            <?php elseif ($fileCategory === 'code'): ?>
                <pre id="previewElement"><code class="language-<?php echo htmlspecialchars($fileType); ?>"><?php echo htmlspecialchars($fileContent); ?></code></pre>
            <?php elseif ($fileCategory === 'archive'): ?>
                <div class="general-file-info">
                    <i class="fas fa-archive icon"></i>
                    <p>Previewing the contents of the archive file <strong><?php echo strtoupper($fileType); ?></strong> is not supported.</p>
                    <p>Please download the file to view its contents.</p>
                    <a href="download.php?file=<?php echo urlencode($filePath); ?>&new_filename=<?php echo urlencode($fileName); ?>" download="<?php echo htmlspecialchars($fileName); ?>" class="download-button" id="downloadButton1">
                        <i class="fas fa-download"></i> Download File
                    </a>
                </div>
            <?php
            // START: Kode pratinjau tambahan dari Anda
            // Ini akan menangani pratinjau untuk file teks dan memberikan pesan untuk dokumen kantor
            // jika kategori file bukan 'code' atau 'document' (selain PDF)
            elseif (in_array($fileType, ['txt', 'md', 'log', 'csv', 'tex'])): ?>
                <div class="viewer-container">
                    <pre id="previewElement"><?= readTextFile($fullFilePath) ?></pre>
                </div>
            <?php elseif (in_array($fileType, ['doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'odt', 'odp', 'rtf'])): ?>
                <div class="general-file-info">
                    <i class="fas fa-file-alt icon"></i>
                    <p>⚠️ Pratinjau lokal untuk file <strong><?= strtoupper($fileType) ?></strong> tidak didukung secara langsung.</p>
                    <p>Silakan <a href="<?= htmlspecialchars($filePath) ?>" target="_blank" download>download file</a> untuk melihat isinya.</p>
                    <a href="download.php?file=<?php echo urlencode($filePath); ?>&new_filename=<?php echo urlencode($fileName); ?>" download="<?php echo htmlspecialchars($fileName); ?>" class="download-button" id="downloadButton3">
                        <i class="fas fa-download"></i> Download File
                    </a>
                </div>
            <?php
            // NEW: CAD File Preview (display icon and download message)
            elseif ($fileCategory === 'cad'): ?>
                <div class="general-file-info">
                    <i class="fas fa-cube icon"></i> <!-- Using fa-cube icon for CAD -->
                    <p>Preview for CAD file type <strong><?php echo strtoupper($fileType); ?></strong> is not supported directly in the browser.</p>
                    <p>Please download the file to open it with appropriate software.</p>
                    <a href="download.php?file=<?php echo urlencode($filePath); ?>&new_filename=<?php echo urlencode($fileName); ?>" download="<?php echo htmlspecialchars($fileName); ?>" class="download-button" id="downloadButtonCAD">
                        <i class="fas fa-download"></i> Download File
                    </a>
                </div>
            <?php
            // END: Kode pratinjau tambahan dari Anda
            else: ?>
                <div class="general-file-info">
                    <i class="fas fa-exclamation-circle icon"></i>
                    <p>Preview for file type <strong><?php echo strtoupper($fileType); ?></strong> is not supported.</p>
                    <p>Please download the file to open it.</p>
                    <a href="download.php?file=<?php echo urlencode($filePath); ?>&new_filename=<?php echo urlencode($fileName); ?>" download="<?php echo htmlspecialchars($fileName); ?>" class="download-button" id="downloadButton2">
                        <i class="fas fa-download"></i> Download File
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="file-info-pane">
        <h3>File Details</h3>
        <div class="file-info-item">
            <strong>File Name</strong>
            <span id="detailFileName"><?php echo htmlspecialchars($fileName); ?></span>
        </div>
        <div class="file-info-item">
            <strong>File Size</strong>
            <span id="detailFileSize"><?php echo htmlspecialchars(formatBytes($fileSize)); ?></span>
        </div>
        <div class="file-info-item">
            <strong>File Type</strong>
            <span id="detailFileType"><?php echo htmlspecialchars(strtoupper($fileType)); ?></span>
        </div>
        <div class="file-info-item">
            <strong>Uploaded At</strong>
            <span id="detailUploadedAt"><?php echo htmlspecialchars($uploadedAt); ?></span>
        </div>
        <a href="download.php?file=<?php echo urlencode($filePath); ?>&new_filename=<?php echo urlencode($fileName); ?>" download="<?php echo htmlspecialchars($fileName); ?>" class="download-button" style="width: 100%; box-sizing: border-box;" id="detailDownloadButton">
            <i class="fas fa-download"></i> Download
        </a>
    </div>
</div>

<script>
    // Helper function to format bytes (replicate from PHP's formatBytes)
    function formatBytes(bytes, precision = 2) {
        const units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
        bytes = Math.max(bytes, 0);
        const pow = Math.floor((bytes ? Math.log(bytes) : 0) / Math.log(1024));
        const p = Math.min(pow, units.length - 1);
        bytes /= (1 << (10 * p));
        return bytes.toFixed(precision) + ' ' + units[p];
    }

    // Helper function for HTML escaping
    function htmlspecialchars(str) {
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return str.replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    // Global variables for zoom
    let currentZoom = 1.0;
    const zoomStep = 0.1;
    const minZoom = 0.5;
    const maxZoom = 3.0;

    // Function to apply zoom
    function applyZoom() {
        const previewElement = document.getElementById('previewElement');
        if (previewElement) {
            if (previewElement.tagName === 'IMG' || previewElement.tagName === 'VIDEO' || previewElement.tagName === 'AUDIO' || previewElement.tagName === 'IFRAME') {
                previewElement.style.transform = `scale(${currentZoom})`;
            } else if (previewElement.tagName === 'PRE') {
                // For PRE tags, adjust font size for zoom
                const codeElement = previewElement.querySelector('code');
                if (codeElement) {
                    codeElement.style.fontSize = `${0.9 * currentZoom}em`;
                } else {
                    // If it's a plain PRE tag (like for your text files)
                    previewElement.style.fontSize = `${1.0 * currentZoom}em`; // Assuming base font size is 1em
                }
            }
        }
    }

    // Function to update the UI with new file data
    function updateFileUI(data) {
        // Update Header
        // Periksa apakah user_id ada untuk menentukan tampilan header
        const isUserLoggedIn = <?php echo json_encode($user_id !== null); ?>;
        const headerUsername = document.getElementById('headerUsername');
        const headerProfilePicture = document.getElementById('headerProfilePicture');
        const profileContainer = headerUsername.closest('.profile-container');

        if (isUserLoggedIn) {
            headerUsername.textContent = data.user.full_name || data.user.username || 'User';
            headerProfilePicture.src = data.user.profile_picture || 'img/default_avatar.png';
            if (!headerUsername.href) { // Ensure href exists for logged-in users
                headerUsername.outerHTML = `<a href="profile.php" class="username" id="headerUsername">${headerUsername.textContent}</a>`;
                headerProfilePicture.closest('a').href = 'profile.php';
            }
        } else {
            headerUsername.textContent = 'Guest';
            headerProfilePicture.src = 'img/default_avatar.png';
            // Remove href for guest users if it was set
            if (headerUsername.href) {
                headerUsername.outerHTML = `<span class="username" id="headerUsername">${headerUsername.textContent}</span>`;
                headerProfilePicture.closest('a').outerHTML = `<img src="img/default_avatar.png" alt="Profile Picture" class="profile-image" id="headerProfilePicture">`;
            }
        }


        // Update Breadcrumbs
        const breadcrumbsContainer = document.getElementById('breadcrumbsContainer');
        breadcrumbsContainer.innerHTML = '<a href="index.php"><i class="fas fa-home"></i> Home</a>';
        // Tampilkan breadcrumbs hanya jika pengguna login
        if (isUserLoggedIn) {
            data.breadcrumbs.forEach(crumb => {
                breadcrumbsContainer.innerHTML += `<span>/</span><a href="index.php?folder=${htmlspecialchars(crumb.id)}">${htmlspecialchars(crumb.name)}</a>`;
            });
        }
        breadcrumbsContainer.innerHTML += `<span>/</span><span id="currentFileNameBreadcrumb">${htmlspecialchars(data.fileName)}</span>`;

        // Update Back Button
        const backButton = document.getElementById('backButton');
        // Tombol back selalu ke index.php, dan biarkan index.php yang menangani redirect login
        if (isUserLoggedIn && data.currentFolderId) {
            backButton.href = `index.php?folder=${htmlspecialchars(data.currentFolderId)}`;
        } else {
            backButton.href = `index.php`; // Always redirect to index.php for non-logged in or root
        }


        // Update Preview Content
        const previewContentDiv = document.getElementById('previewContent');
        let previewHtml = '';
        // NEW: Add 'cad' to zoomable categories for JS
        const zoomableCategories = ['image', 'document', 'video', 'code', 'cad'];
        // Tambahkan tipe file teks Anda ke daftar yang dapat di-zoom jika diinginkan
        const zoomableFileTypes = ['txt', 'md', 'log', 'csv', 'tex'];
        const isZoomable = zoomableCategories.includes(data.fileCategory) || zoomableFileTypes.includes(data.fileType);


        if (isZoomable && data.fileCategory !== 'archive') {
            previewHtml += `
                <div class="zoom-controls">
                    <button class="zoom-button" id="zoomOutBtn"><i class="fas fa-minus"></i></button>
                    <button class="zoom-button" id="zoomInBtn"><i class="fas fa-plus"></i></button>
                </div>
            `;
        }

        if (data.fileCategory === 'image') {
            previewHtml += `<img id="previewElement" src="${htmlspecialchars(data.filePath)}" alt="${htmlspecialchars(data.fileName)}">`;
        } else if (data.fileCategory === 'audio') {
            previewHtml += `
                <audio id="previewElement" controls autoplay>
                    <source src="${htmlspecialchars(data.filePath)}" type="audio/${htmlspecialchars(data.fileType)}">
                    Your browser does not support the audio element.
                </audio>
            `;
        } else if (data.fileCategory === 'video') {
            previewHtml += `
                <video id="previewElement" controls autoplay>
                    <source src="${htmlspecialchars(data.filePath)}" type="video/${htmlspecialchars(data.fileType)}">
                    Your browser does not support the video tag.
                </video>
            `;
        } else if (data.fileCategory === 'document' && data.fileType === 'pdf') {
            previewHtml += `<iframe id="previewElement" src="${htmlspecialchars(data.filePath)}" class="pdf-viewer"></iframe>`;
        } else if (data.fileCategory === 'code') {
            previewHtml += `<pre id="previewElement"><code class="language-${htmlspecialchars(data.fileType)}">${htmlspecialchars(data.fileContent)}</code></pre>`;
            // Re-run highlight.js if it's a code file
            if (typeof hljs !== 'undefined') {
                setTimeout(() => { // Give DOM a moment to update
                    hljs.highlightAll();
                }, 0);
            }
        } else if (data.fileCategory === 'archive') {
            previewHtml += `
                <div class="general-file-info">
                    <i class="fas fa-archive icon"></i>
                    <p>Previewing the contents of the archive file <strong>${data.fileType.toUpperCase()}</strong> is not supported.</p>
                    <p>Please download the file to view its contents.</p>
                    <a href="download.php?file=${encodeURIComponent(data.filePath)}&new_filename=${encodeURIComponent(data.fileName)}" download="${htmlspecialchars(data.fileName)}" class="download-button" id="downloadButton1">
                        <i class="fas fa-download"></i> Download File
                    </a>
                </div>
            `;
        }
        // START: Logika pratinjau tambahan dari Anda untuk AJAX
        else if (['txt', 'md', 'log', 'csv', 'tex'].includes(data.fileType)) {
            // Untuk file teks, kita perlu mengambil kontennya lagi karena getFileData hanya mengambilnya untuk 'code'
            // Atau, jika Anda ingin getFileData selalu mengambil konten untuk semua file teks, Anda bisa memodifikasi getFileData.
            // Untuk saat ini, kita asumsikan data.fileContent mungkin kosong untuk non-code text files, jadi kita akan menampilkannya apa adanya.
            // Jika Anda ingin membaca ulang file di sisi klien, Anda perlu endpoint terpisah atau memodifikasi getFileData.
            // Untuk demonstrasi, kita akan menampilkan data.fileContent jika ada, atau pesan default.
            previewHtml += `
                <div class="viewer-container">
                    <pre id="previewElement">${htmlspecialchars(data.fileContent || 'Tidak dapat memuat konten teks.')}</pre>
                </div>
            `;
        } else if (['doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'odt', 'odp', 'rtf'].includes(data.fileType)) {
            previewHtml += `
                <div class="general-file-info">
                    <i class="fas fa-file-alt icon"></i>
                    <p>⚠️ Pratinjau lokal untuk file <strong>${data.fileType.toUpperCase()}</strong> tidak didukung secara langsung.</p>
                    <p>Silakan <a href="${htmlspecialchars(data.filePath)}" target="_blank" download>download file</a> untuk melihat isinya.</p>
                    <a href="download.php?file=${encodeURIComponent(data.filePath)}&new_filename=${encodeURIComponent(data.fileName)}" download="${htmlspecialchars(data.fileName)}" class="download-button" id="downloadButton3">
                        <i class="fas fa-download"></i> Download File
                    </a>
                </div>
            `;
        }
        // NEW: CAD File Preview (display icon and download message) for AJAX
        else if (data.fileCategory === 'cad') {
            previewHtml += `
                <div class="general-file-info">
                    <i class="fas fa-cube icon"></i>
                    <p>Preview for CAD file type <strong>${data.fileType.toUpperCase()}</strong> is not supported directly in the browser.</p>
                    <p>Please download the file to open it with appropriate software.</p>
                    <a href="download.php?file=${encodeURIComponent(data.filePath)}&new_filename=${encodeURIComponent(data.fileName)}" download="${htmlspecialchars(data.fileName)}" class="download-button" id="downloadButtonCAD">
                        <i class="fas fa-download"></i> Download File
                    </a>
                </div>
            `;
        }
        // END: Logika pratinjau tambahan dari Anda untuk AJAX
        else {
            previewHtml += `
                <div class="general-file-info">
                    <i class="fas fa-exclamation-circle icon"></i>
                    <p>Preview for file type <strong>${data.fileType.toUpperCase()}</strong> is not supported.</p>
                    <p>Please download the file to open it.</p>
                    <a href="download.php?file=${encodeURIComponent(data.filePath)}&new_filename=${encodeURIComponent(data.fileName)}" download="${htmlspecialchars(data.fileName)}" class="download-button" id="downloadButton2">
                        <i class="fas fa-download"></i> Download File
                    </a>
                </div>
            `;
        }
        previewContentDiv.innerHTML = previewHtml;

        // Re-attach zoom event listeners if zoom controls are present
        if (isZoomable && data.fileCategory !== 'archive') {
            document.getElementById('zoomInBtn').addEventListener('click', function() {
                if (currentZoom < maxZoom) {
                    currentZoom += zoomStep;
                    applyZoom();
                }
            });
            document.getElementById('zoomOutBtn').addEventListener('click', function() {
                if (currentZoom > minZoom) {
                    currentZoom -= zoomStep;
                    applyZoom();
                }
            });
        }

        // Update File Details Pane
        document.getElementById('detailFileName').textContent = htmlspecialchars(data.fileName);
        document.getElementById('detailFileSize').textContent = formatBytes(data.fileSize);
        document.getElementById('detailFileType').textContent = htmlspecialchars(data.fileType.toUpperCase());
        document.getElementById('detailUploadedAt').textContent = htmlspecialchars(data.uploadedAt);
        document.getElementById('detailDownloadButton').href = `download.php?file=${encodeURIComponent(data.filePath)}&new_filename=${encodeURIComponent(data.fileName)}`;
        document.getElementById('detailDownloadButton').download = htmlspecialchars(data.fileName);

        // Reset zoom level
        currentZoom = 1.0;
        applyZoom();
    }

    // Function to fetch file data via AJAX
    async function fetchFileData(fileId) {
        try {
            // Include 'shared' parameter in AJAX request if it was present initially
            const urlParams = new URLSearchParams(window.location.search);
            const isShared = urlParams.get('shared') === 'true' ? '&shared=true' : '';

            const response = await fetch(`view.php?action=get_file_data&file_id=${fileId}${isShared}`);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            const data = await response.json();
            if (data.error) {
                alert(data.message); // Simple alert for error
                // If there's an error, decide where to redirect
                if (isShared) {
                    // For shared link, just show error and stay (or redirect to a generic error page)
                    // For now, staying on the page with an alert.
                } else {
                    window.location.href = 'index.php'; // Redirect to index if file not found for logged-in user
                }
                return;
            }
            updateFileUI(data);
        } catch (error) {
            console.error("Could not fetch file data:", error);
            alert('Failed to load file data. Please try again.');
            // If critical error, redirect based on shared status
            const urlParams = new URLSearchParams(window.location.search);
            const isShared = urlParams.get('shared') === 'true';
            if (!isShared) {
                window.location.href = 'index.php'; // Redirect on critical error for logged-in users
            }
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        // Initial UI update with data from PHP (server-side rendered)
        // The PHP code already populates the initial HTML, so we just need to ensure
        // event listeners for zoom are attached if applicable.
        const initialFileCategory = "<?php echo $fileCategory; ?>";
        const initialFileType = "<?php echo $fileType; ?>"; // Ambil tipe file awal
        // NEW: Add 'cad' to zoomable categories for initial load
        const zoomableCategories = ['image', 'document', 'video', 'code', 'cad'];
        const zoomableFileTypes = ['txt', 'md', 'log', 'csv', 'tex']; // Tipe file teks yang bisa di-zoom
        const isZoomableInitial = zoomableCategories.includes(initialFileCategory) || zoomableFileTypes.includes(initialFileType);


        if (isZoomableInitial && initialFileCategory !== 'archive') {
            document.getElementById('zoomInBtn').addEventListener('click', function() {
                if (currentZoom < maxZoom) {
                    currentZoom += zoomStep;
                    applyZoom();
                }
            });
            document.getElementById('zoomOutBtn').addEventListener('click', function() {
                if (currentZoom > minZoom) {
                    currentZoom -= zoomStep;
                    applyZoom();
                }
            });
        }

        // You can call fetchFileData(fileId) here if you want to always load via AJAX
        // even on first page load, but it's generally better to server-render initial state.
        // This setup allows for dynamic loading if the file_id changes (e.g., via history API)
        // or if you want to refresh the view.
    });

    // Example of how to trigger a new file load (e.g., if file_id changes in URL hash)
    // This is more advanced and would require modifying how file_id is passed.
    // For now, the page is designed to load one file per page load.
    // If you want to change files without full page reload, you'd need to
    // modify the URL (e.g., using pushState) and then call fetchFileData.
</script>

</body>
</html>
<?php $conn->close(); ?>
