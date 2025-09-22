<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require '../../../config.php';

require '../../helpers/utils.php';

require '../../services/authService.php';
require '../../services/folderService.php';
require '../../services/fileService.php';

session_start();
checkAuth(); // Redirects if not logged in

// Cek apakah request berasal dari tautan berbagi (dari s.php)
$is_shared_link = isset($_GET['shared']) && $_GET['shared'] === 'true';

// Jika bukan dari tautan berbagi DAN pengguna belum login, redirect ke login.php
if (!$is_shared_link && !isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'] ?? null; // user_id bisa null jika akses dari shared link dan belum login

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
$fileContent = $initial_data['fileContent']; // Content for code files and now archive files

// Fungsi untuk membaca file teks (dari kode yang Anda berikan)
function readTextFile($path) {
    $content = file_get_contents($path);
    return htmlspecialchars($content); // Hindari XSS
}

// NEW: Fungsi untuk membaca isi arsip (ZIP dan TAR)
function readArchiveContent($filePath, $fileType) {
    $output = '';
    $output .= "<div class='archive-viewer'>";
    $output .= "<h3>Contents of: " . htmlspecialchars(basename($filePath)) . "</h3>";
    $output .= "<div class='archive-table-container'>"; // Added for responsive table
    $output .= "<table class='archive-table'>";
    $output .= "<thead><tr><th>Name</th><th>Size</th><th>Modified</th></tr></thead>";
    $output .= "<tbody>";

    if ($fileType === 'zip') {
        if (class_exists('ZipArchive')) {
            $zip = new ZipArchive;
            if ($zip->open($filePath) === TRUE) {
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $stat = $zip->statIndex($i);
                    $output .= "<tr>";
                    $output .= "<td data-label='Name'>" . htmlspecialchars($stat['name']) . "</td>";
                    $output .= "<td data-label='Size'>" . formatBytes($stat['size']) . "</td>";
                    $output .= "<td data-label='Modified'>" . date("Y-m-d H:i:s", $stat['mtime']) . "</td>";
                    $output .= "</tr>";
                }
                $zip->close();
            } else {
                $output .= "<tr><td colspan='3'>Failed to open ZIP file.</td></tr>";
            }
        } else {
            $output .= "<tr><td colspan='3'>PHP ZipArchive extension is not enabled.</td></tr>";
        }
    } elseif ($fileType === 'tar') {
        if (class_exists('PharData')) {
            try {
                $tar = new PharData($filePath);
                foreach (new RecursiveIteratorIterator($tar) as $file) {
                    // Get relative path within the archive
                    $relativePath = str_replace($tar->getPathname() . '/', '', $file->getPathname());
                    $output .= "<tr>";
                    $output .= "<td data-label='Name'>" . htmlspecialchars($relativePath) . "</td>";
                    $output .= "<td data-label='Size'>" . formatBytes($file->getSize()) . "</td>";
                    $output .= "<td data-label='Modified'>" . date("Y-m-d H:i:s", $file->getMTime()) . "</td>";
                    $output .= "</tr>";
                }
            } catch (Exception $e) {
                $output .= "<tr><td colspan='3'>Failed to open TAR file: " . htmlspecialchars($e->getMessage()) . "</td></tr>";
            }
        } else {
            $output .= "<tr><td colspan='3'>PHP Phar extension is not enabled or PharData class not found.</td></tr>";
        }
    } else {
        $output .= "<tr><td colspan='3'>Unsupported archive format. Only .zip and .tar are supported for preview.</td></tr>";
    }

    $output .= "</tbody>";
    $output .= "</table>";
    $output .= "</div>"; // Close archive-table-container
    $output .= "</div>";
    return $output;
}

?>

<?php include '../partials/file-view-header.php'; ?>

<header class="header-main">
    <h1><i class="fas fa-file-alt"></i> <span data-lang-key="filePreview">File Preview</span></h1>
    <div class="profile-container">
        <?php if ($user_id !== null): ?>
            <a href="profile.php" class="username" id="headerUsername">
                <?php echo htmlspecialchars($user['full_name'] ?? $user['username'] ?? 'User'); ?>
            </a>
            <a href="profile.php">
                <img src="<?php echo getBaseUrl() . '/' . htmlspecialchars($user['profile_picture'] ?? 'img/default_avatar.png'); ?>" alt="Profile Picture" class="profile-image" id="headerProfilePicture">
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
            <a href="<?php echo $baseV2Url;?>"><i class="fas fa-home"></i> Home</a>
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
        <a href="<?php echo $baseV2Url;?> <?php echo ($user_id !== null && $currentFolderId) ? '?folder=' . htmlspecialchars($currentFolderId) : ''; ?>" class="back-button" id="backButton">
            <i class="fas fa-arrow-left"></i> Back
        </a>
        <div class="preview-content" id="previewContent" alt="aa">
            <?php
            // NEW: Add 'cad' to zoomable categories
            $zoomableCategories = ['image', 'document', 'video', 'code', 'cad'];
            // Archive files are not directly zoomable in the same way, so exclude them
            $isZoomable = in_array($fileCategory, $zoomableCategories) && $fileCategory !== 'archive';

            if ($isZoomable) { // Only show zoom controls if the file type is zoomable
                echo '<div class="zoom-controls">';
                echo '<button class="zoom-button" id="zoomOutBtn"><i class="fas fa-minus"></i></button>';
                echo '<button class="zoom-button" id="zoomInBtn"><i class="fas fa-plus"></i></button>';
                echo '</div>';
            }
            ?>

            <?php if ($fileCategory === 'image'): ?>
                <img id="previewElement" src="<?php echo getBaseUrl() . '/uploads/' . htmlspecialchars($filePath); ?>" alt="<?php echo htmlspecialchars($fileName); ?>">
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
                <?php if ($fileType === 'zip' || $fileType === 'tar'): ?>
                    <div id="previewElement" class="archive-preview-container">
                        <?php echo $fileContent; // $fileContent now contains the HTML table ?>
                    </div>
                <?php else: ?>
                    <div class="general-file-info">
                        <i class="fas fa-archive icon"></i>
                        <p>Previewing the contents of the archive file <strong><?php echo strtoupper($fileType); ?></strong> is not supported.</p>
                        <p>Please download the file to view its contents.</p>
                        <a href="download.php?file=<?php echo urlencode($filePath); ?>&new_filename=<?php echo urlencode($fileName); ?>" download="<?php echo htmlspecialchars($fileName); ?>" class="download-button" id="downloadButton1">
                            <i class="fas fa-download"></i> Download File
                        </a>
                    </div>
                <?php endif; ?>
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

<script src="../../js/translations.js"></script>

<script>

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
            // Check if the element is an image, video, audio, or iframe (PDF viewer)
            if (previewElement.tagName === 'IMG' || previewElement.tagName === 'VIDEO' || previewElement.tagName === 'AUDIO' || previewElement.tagName === 'IFRAME') {
                previewElement.style.transform = `scale(${currentZoom})`;
            } else if (previewElement.tagName === 'PRE') {
                // For PRE tags (code/text files), adjust font size for zoom
                const codeElement = previewElement.querySelector('code');
                if (codeElement) {
                    codeElement.style.fontSize = `${0.9 * currentZoom}em`;
                } else {
                    // If it's a plain PRE tag (like for your text files)
                    previewElement.style.fontSize = `${1.0 * currentZoom}em`; // Assuming base font size is 1em
                }
            }
            // Archive viewer is a div, not directly zoomable by scale, but its content could be
            // For now, we don't apply direct scale to archive-viewer.
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
            backButton.href = <?php echo $baseV2Url;?>; // Always redirect to index.php for non-logged in or root
        }


        // Update Preview Content
        const previewContentDiv = document.getElementById('previewContent');
        let previewHtml = '';
        // NEW: Add 'cad' to zoomable categories for JS
        const zoomableCategories = ['image', 'document', 'video', 'code', 'cad'];
        // Tambahkan tipe file teks Anda ke daftar yang dapat di-zoom jika diinginkan
        const zoomableFileTypes = ['txt', 'md', 'log', 'csv', 'tex'];
        // Archive files are not directly zoomable in the same way, so exclude them
        const isZoomable = (zoomableCategories.includes(data.fileCategory) || zoomableFileTypes.includes(data.fileType)) && data.fileCategory !== 'archive';


        if (isZoomable) { // Only show zoom controls if the file type is zoomable
            previewHtml += `
                <div class="zoom-controls">
                    <button class="zoom-button" id="zoomOutBtn"><i class="fas fa-minus"></i></button>
                    <button class="zoom-button" id="zoomInBtn"><i class="fas fa-plus"></i></button>
                </div>
            `;
        }

        if (data.fileCategory === 'image') {
            previewHtml += `<img id="previewElement" src="/uploads/${htmlspecialchars(data.filePath)}" alt="${htmlspecialchars(data.fileName)}">`;
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
            if (data.fileType === 'zip' || data.fileType === 'tar') {
                // For archive files, the fileContent now contains the HTML table
                previewHtml += `
                    <div id="previewElement" class="archive-preview-container">
                        ${data.fileContent}
                    </div>
                `;
            } else {
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
        }
        // START: Logika pratinjau tambahan dari Anda untuk AJAX
        else if (['txt', 'md', 'log', 'csv', 'tex'].includes(data.fileType)) {
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
        if (isZoomable) { // Only attach if zoomable
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

    // Device detection & body class toggling
    function setDeviceClass() {
        const ua = navigator.userAgent || '';
        const isIPad = /iPad/.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
        const w = window.innerWidth;
        document.body.classList.remove('mobile', 'tablet-portrait', 'tablet-landscape', 'desktop', 'device-ipad'); // Clear all

        if (isIPad) {
            document.body.classList.add('device-ipad');
            // For iPad, also apply tablet-landscape or tablet-portrait based on orientation
            if (window.matchMedia("(orientation: portrait)").matches) {
                document.body.classList.add('tablet-portrait');
            } else {
                document.body.classList.add('tablet-landscape');
            }
        } else if (w <= 767) {
            document.body.classList.add('mobile');
        } else if (w >= 768 && w <= 1024) {
            if (window.matchMedia("(orientation: portrait)").matches) {
                document.body.classList.add('tablet-portrait');
            } else {
                document.body.classList.add('tablet-landscape');
            }
        } else {
            document.body.classList.add('desktop');
        }
    }

    // Debounce function to limit how often setDeviceClass is called on resize
    let resizeTimer;
    window.addEventListener('resize', () => {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(setDeviceClass, 150);
    });
    window.addEventListener('orientationchange', setDeviceClass); // Listen for orientation changes

    document.addEventListener('DOMContentLoaded', function() {
        setDeviceClass(); // Initial call to set device class on load

        // Initial UI update with data from PHP (server-side rendered)
        // The PHP code already populates the initial HTML, so we just need to ensure
        // event listeners for zoom are attached if applicable.
        const initialFileCategory = "<?php echo $fileCategory; ?>";
        const initialFileType = "<?php echo $fileType; ?>"; // Ambil tipe file awal
        // NEW: Add 'cad' to zoomable categories for initial load
        const zoomableCategories = ['image', 'document', 'video', 'code', 'cad'];
        const zoomableFileTypes = ['txt', 'md', 'log', 'csv', 'tex']; // Tipe file teks yang bisa di-zoom
        // Archive files are not directly zoomable in the same way, so exclude them
        const isZoomableInitial = (zoomableCategories.includes(initialFileCategory) || zoomableFileTypes.includes(initialFileType)) && initialFileCategory !== 'archive';


        if (isZoomableInitial) { // Only attach if zoomable
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
