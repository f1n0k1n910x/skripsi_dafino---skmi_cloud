<?php
include 'config.php';
include 'functions.php'; // Include functions.php file

session_start();
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Base directory for scanning
$baseDir = realpath(__DIR__); 
$quarantineDir = $baseDir . '/quarantine'; // Quarantine folder
$historyLog = $baseDir . '/history.log';  // History log file

// "Unimportant" file/folder extensions and names
$badExt = [
    // Documents
    'doc','docx','pdf','ppt','pptx','xls','xlsx','txt','odt','odp','rtf','md','log','csv','tex',
    // Music
    'mp3','wav','aac','ogg','flac','m4a','alac','wma','opus','amr','mid',
    // Videos
    'mp4','mkv','avi','mov','wmv','flv','webm','3gp','m4v','mpg','mpeg','ts','ogv',
    // Code / Scripts
    'html','htm','css','js','php','py','java','json','xml','ts','tsx','jsx','vue',
    'cpp','c','cs','rb','go','swift','sql','sh','bat','ini','yml','yaml','pl','r',
    // Archives / Compressed
    'zip','rar','7z','tar','gz','bz2','xz','iso','cab','arj',
    // Installation / Executables
    'exe','msi','apk','ipa','jar','appimage','dmg','bin',
    // Peer-to-Peer
    'torrent','nzb','ed2k','part','!ut',
    // Images
    'jpg','jpeg','png','gif','bmp','webp','svg','tiff',
    // CAD
    'dwg','dxf','dgn','iges','igs','step','stp','stl','3ds','obj',
    'sldprt','sldasm','ipt','iam','catpart','catproduct','prt','asm',
    'fcstd','skp','x_t','x_b'
];
$badFolders = ['__MACOSX', '.DS_Store', 'temp', 'backup'];

// Ignored files/folders (including this script itself and the quarantine folder)
$selfFile = realpath(__FILE__);
$ignored = [$selfFile, $historyLog, $quarantineDir];

// Create quarantine folder if it doesn't exist
if (!is_dir($quarantineDir)) {
    mkdir($quarantineDir, 0750, true);
}

// Function to save history log
function saveHistory($logFile, $action, $source, $destination = '') {
    $line = date('Y-m-d H:i:s') . " | $action | $source";
    if ($destination) $line .= " -> $destination";
    $line .= PHP_EOL;
    file_put_contents($logFile, $line, FILE_APPEND);
}

// Function to recursively delete a directory
function deleteDir($dir) {
    if (!is_dir($dir)) return;
    $items = array_diff(scandir($dir), ['.', '..']);
    foreach ($items as $item) {
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) {
            deleteDir($path);
        } else {
            @unlink($path);
        }
    }
    @rmdir($dir);
}

// Function to move a file to quarantine
function quarantineFile($filePath, $quarantineDir, $historyLog) {
    $baseName = basename($filePath);
    $newName = $quarantineDir . '/' . date('Ymd_His') . '_' . $baseName;
    if (@rename($filePath, $newName)) {
        saveHistory($historyLog, "QUARANTINE FILE", $filePath, $newName);
        return $newName;
    }
    return false;
}

// Function to automatically clean quarantine contents
function cleanQuarantine($quarantineDir, $badExt, $historyLog) {
    $items = glob($quarantineDir . '/*');
    foreach ($items as $item) {
        if (is_file($item)) {
            $ext = strtolower(pathinfo($item, PATHINFO_EXTENSION));
            if (in_array($ext, $badExt)) {
                if (@unlink($item)) {
                    saveHistory($historyLog, "DELETED FILE FROM QUARANTINE", $item);
                }
            }
        } elseif (is_dir($item)) {
            $filesInside = glob($item . '/*');
            if (count($filesInside) === 0) {
                if (@rmdir($item)) {
                    saveHistory($historyLog, "DELETED EMPTY FOLDER FROM QUARANTINE", $item);
                }
            }
        }
    }
}

// Main folder scanning function
function scanFolder($startDir, $quarantineDir, $historyLog, $badExt, $badFolders, $ignored) {
    $rii = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($startDir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($rii as $file) {
        $path = $file->getPathname();

        // Ignore specific files/folders
        foreach ($ignored as $ignorePath) {
            if (strpos($path, $ignorePath) === 0) continue 2;
        }

        $baseName = basename($path);

        // Handle bad folders
        foreach ($badFolders as $badFolder) {
            if (stripos($baseName, $badFolder) !== false) {
                // Delete folder if found
                if ($file->isDir()) {
                    deleteDir($path);
                    saveHistory($historyLog, "DELETED BAD FOLDER", $path);
                    continue 2;
                }
                // Delete file if name matches
                elseif ($file->isFile()) {
                    @unlink($path);
                    saveHistory($historyLog, "DELETED BAD FILE (NAME MATCH)", $path);
                    continue 2;
                }
            }
        }

        if ($file->isFile()) {
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            // If file extension is unimportant OR contains PHP code (malicious)
            if (in_array($ext, $badExt) || preg_match('/<\?php/i', @file_get_contents($path))) {
                $quarantined = quarantineFile($path, $quarantineDir, $historyLog);
            }
        } elseif ($file->isDir()) {
            // Delete empty folders
            $files = glob($path . '/*');
            if (count($files) === 0) {
                @rmdir($path);
                saveHistory($historyLog, "DELETED EMPTY FOLDER", $path);
            }
        }
    }

    // Clean quarantine immediately after scan
    cleanQuarantine($quarantineDir, $badExt, $historyLog);
}

// If called with AJAX to load history
if (isset($_GET['action']) && $_GET['action'] === 'get_history') {
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $filterDate = isset($_GET['date']) ? $_GET['date'] : '';
    $lines = file_exists($historyLog) ? file($historyLog, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
    $lines = array_reverse($lines); // newest on top

    if ($filterDate) {
        $lines = array_filter($lines, fn($line) => strpos($line, $filterDate) === 0);
    }

    $perPage = 10;
    $totalPages = max(1, ceil(count($lines) / $perPage));
    $start = ($page - 1) * $perPage;
    $pageLines = array_slice($lines, $start, $perPage);

    header('Content-Type: application/json');
    echo json_encode([
        'data' => $pageLines,
        'totalPages' => $totalPages,
    ]);
    exit;
}

// If scan button is triggered
if (isset($_GET['action']) && $_GET['action'] === 'scan') {
    scanFolder($baseDir, $quarantineDir, $historyLog, $badExt, $badFolders, $ignored);
    echo "✅ Scan and cleanup completed.";
    exit;
}

// Fetch storage information for sidebar
$totalStorageGB = 500; 
$totalStorageBytes = $totalStorageGB * 1024 * 1024 * 1024; // Convert GB to Bytes

$usedStorageBytes = 0;
// Calculate used storage from files table (sum of file_size)
$stmt = $conn->prepare("SELECT SUM(file_size) as total_size FROM files");
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
if ($row['total_size']) {
    $usedStorageBytes = $row['total_size'];
}
$stmt->close(); // Close the statement

$usedStorageGB = $usedStorageBytes / (1024 * 1024 * 1024); // Convert bytes to GB

$usedPercentage = ($totalStorageBytes > 0) ? ($usedStorageBytes / $totalStorageBytes) * 100 : 0;
if ($usedPercentage > 100) $usedPercentage = 100; // Cap at 100%
$freeStorageGB = $totalStorageGB - $usedStorageGB;

// Check if storage is full
$isStorageFull = isStorageFull($conn, $totalStorageBytes);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Protection - SKMI Cloud Storage</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="css/internal.css"> <!-- Import CSS -->
    <style>
        /* Metro Design (Modern UI) & Windows 7 Animations - Copied from index.php */
        :root {
            --metro-blue: #0078D7; /* Windows 10/Metro accent blue */
            --metro-dark-blue: #0056b3;
            --metro-light-gray: #E1E1E1;
            --metro-medium-gray: #C8C8C8;
            --metro-dark-gray: #666666;
            --metro-text-color: #333333;
            --metro-bg-color: #F0F0F0;
            --metro-sidebar-bg: #2D2D30; /* Darker sidebar for contrast */
            --metro-sidebar-text: #F0F0F0;
            --metro-success: #4CAF50;
            --metro-error: #E81123; /* Windows 10 error red */
            --metro-warning: #FF8C00; /* Windows 10 warning orange */
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            display: flex;
            height: 100vh;
            background-color: var(--metro-bg-color);
            color: var(--metro-text-color);
            overflow: hidden; /* Prevent body scroll, main-content handles it */
        }

        /* Base Sidebar (for Desktop/Tablet Landscape) */
        .sidebar {
            width: 250px; /* Wider sidebar for Metro feel */
            background-color: var(--metro-sidebar-bg);
            color: var(--metro-sidebar-text);
            display: flex;
            flex-direction: column;
            padding: 20px 0;
            transition: width 0.3s ease-in-out, transform 0.3s ease-in-out;
            flex-shrink: 0; /* Prevent shrinking */
        }

        .sidebar-header {
            padding: 0 20px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            justify-content: center; /* Center logo */
        }

        .sidebar-header img {
            width: 150px; /* Larger logo */
            height: auto;
            display: block;
        }

        .sidebar-header h2 {
            margin: 0;
            font-size: 1.8em;
            color: var(--metro-sidebar-text);
            font-weight: 300; /* Lighter font weight */
        }

        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 0;
            flex-grow: 1;
        }

        .sidebar-menu li {
            margin-bottom: 5px; /* Closer spacing */
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 15px 20px; /* More padding */
            color: var(--metro-sidebar-text);
            text-decoration: none;
            font-size: 1.1em;
            transition: background-color 0.2s ease-out, color 0.2s ease-out;
            border-left: 5px solid transparent; /* For active state */
        }

        .sidebar-menu a i {
            margin-right: 15px;
            font-size: 1.4em;
            width: 25px; /* Fixed width for icons */
            text-align: center;
        }

        .sidebar-menu a:hover {
            background-color: rgba(255,255,255,0.1); /* Subtle hover */
            color: #FFFFFF;
        }

        .sidebar-menu a.active {
            background-color: var(--metro-blue); /* Metro accent color */
            border-left: 5px solid var(--metro-blue);
            color: #FFFFFF;
            font-weight: 600;
        }

        /* Storage Info */
        .storage-info {
            padding: 20px;
            border-top: 1px solid rgba(255,255,255,0.1);
            text-align: center;
            font-size: 0.9em;
        }

        .storage-info h4 {
            margin-top: 0;
            margin-bottom: 15px;
            color: var(--metro-sidebar-text);
            font-weight: 400;
        }

        .progress-bar-container {
            width: 100%;
            background-color: rgba(255,255,255,0.2);
            border-radius: 5px;
            height: 8px;
            margin-bottom: 10px;
            overflow: hidden;
            position: relative; /* Added for text overlay */
        }

        .progress-bar {
            height: 100%;
            background-color: var(--metro-success); /* Green for progress */
            border-radius: 5px;
            transition: width 0.5s ease-in-out;
            position: relative;
            overflow: hidden;
        }

        /* Progress bar text overlay */
        .progress-bar-text {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: #fff; /* White text for contrast */
            font-size: 0.7em; /* Smaller font size */
            font-weight: bold;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.5); /* Add shadow for readability */
            white-space: nowrap; /* Prevent text from wrapping */
        }

        .storage-text {
            font-size: 0.9em;
            color: var(--metro-light-gray);
        }

        /* Main Content */
        .main-content {
            flex-grow: 1;
            padding: 30px;
            display: flex;
            flex-direction: column;
            overflow-y: auto; /* Enable scrolling for content */
            background-color: #FFFFFF; /* White background for content area */
            border-radius: 8px;
            margin: 0; /* MODIFIED: Full width */
        }

        /* Header Main */
        .header-main {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--metro-light-gray);
            background-color: #FFFFFF; /* White header */
            padding: 15px 30px; /* Add padding for header */
            margin: -30px -30px 25px -30px; /* Adjust margin to cover full width */
            border-radius: 0; /* No rounded top corners for full width */
        }

        .header-main h1 {
            margin: 0;
            color: var(--metro-text-color);
            font-size: 2.5em;
            font-weight: 300;
        }

        /* Custom styles for Protection page content */
        .protection-container {
            padding: 20px;
            background-color: #f9f9f9;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .protection-container h2 {
            margin-top: 0;
            color: var(--metro-text-color);
            font-size: 2em;
            font-weight: 400;
            border-bottom: 1px solid var(--metro-light-gray);
            padding-bottom: 10px;
            margin-bottom: 20px;
        }

        .protection-container button {
            background-color: var(--metro-blue);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 1em;
            transition: background-color 0.2s ease-out, transform 0.1s ease-out;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin-bottom: 15px;
        }

        .protection-container button:hover {
            background-color: var(--metro-dark-blue);
            transform: translateY(-1px);
        }

        .protection-container button:active {
            transform: translateY(0);
        }

        .filter-section {
            margin-top: 20px;
            margin-bottom: 20px;
            padding: 15px;
            background-color: #e9e9e9;
            border-radius: 5px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .filter-section label {
            font-weight: 600;
            color: var(--metro-text-color);
        }

        .filter-section input[type="date"] {
            padding: 8px;
            border: 1px solid var(--metro-medium-gray);
            border-radius: 3px;
            font-size: 0.95em;
            color: var(--metro-text-color);
            background-color: #FFFFFF;
            transition: border-color 0.2s ease-out, box-shadow 0.2s ease-out;
        }

        .filter-section input[type="date"]:focus {
            border-color: var(--metro-blue);
            box-shadow: 0 0 0 2px rgba(0,120,215,0.3);
            outline: none;
        }

        #history-table-container {
            max-height: 500px; /* Limit height for scrollability */
            overflow-y: auto;
            border: 1px solid var(--metro-light-gray);
            border-radius: 5px;
        }

        #history-table {
            width: 100%;
            border-collapse: collapse;
        }

        #history-table th, #history-table td {
            text-align: left;
            padding: 12px 15px;
            border-bottom: 1px solid var(--metro-light-gray);
            font-size: 0.9em;
        }

        #history-table th {
            background-color: var(--metro-bg-color);
            color: var(--metro-dark-gray);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.8em;
            position: sticky;
            top: 0;
            z-index: 1;
        }

        #history-table tbody tr:hover {
            background-color: var(--metro-light-gray);
        }

        .pagination {
            margin-top: 20px;
            text-align: center;
        }

        .pagination button {
            background-color: var(--metro-medium-gray);
            color: var(--metro-text-color);
            border: 1px solid var(--metro-dark-gray);
            padding: 8px 12px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 0.9em;
            margin: 0 3px;
            transition: background-color 0.2s ease-out;
        }

        .pagination button:hover:not(:disabled) {
            background-color: var(--metro-blue);
            color: white;
            border-color: var(--metro-blue);
        }

        .pagination button:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        /* Custom Notification Styles - Copied from index.php */
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 25px;
            border-radius: 5px;
            color: white;
            font-weight: bold;
            z-index: 1001;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            opacity: 0;
            transform: translateY(-20px);
            transition: opacity 0.3s ease-out, transform 0.3s ease-out;
        }

        .notification.show {
            opacity: 1;
            transform: translateY(0);
        }

        .notification.success {
            background-color: var(--metro-success);
        }

        .notification.error {
            background-color: var(--metro-error);
        }

        .notification.info {
            background-color: var(--metro-blue);
        }

        /* Custom Scrollbar for Webkit browsers (Chrome, Safari) - Copied from index.php */
        ::-webkit-scrollbar {
            width: 8px; /* Width of the scrollbar */
            height: 8px; /* Height of horizontal scrollbar */
        }

        ::-webkit-scrollbar-track {
            background: var(--metro-light-gray); /* Color of the track */
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb {
            background: var(--metro-medium-gray); /* Color of the scroll thumb */
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--metro-dark-gray); /* Color of the scroll thumb on hover */
        }

        /* Overlay for mobile sidebar - Copied from index.php */
        .overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 99;
        }
        .overlay.show {
            display: block;
        }
    </style>
</head>
<body>
    <div class="sidebar mobile-hidden">
        <div class="sidebar-header">
            <img src="img/logo.png" alt="Dafino Logo">
        </div>
        <ul class="sidebar-menu">
            <li><a href="index.php"><i class="fas fa-folder"></i> My Drive</a></li>
            <li><a href="priority_files.php"><i class="fas fa-star"></i> Priority File</a></li>
            <li><a href="summary.php"><i class="fas fa-chart-line"></i> Summary</a></li>
            <li><a href="members.php"><i class="fas fa-users"></i> Members</a></li>
            <li><a href="protection.php" class="active"><i class="fas fa-shield-alt"></i> Protection</a></li> <!-- NEW: Protection Link -->
            <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
        <div class="storage-info">
            <h4>Storage</h4>
            <div class="progress-bar-container">
                <div class="progress-bar" style="width: <?php echo round($usedPercentage, 2); ?>%;">
                    <span class="progress-bar-text"><?php echo round($usedPercentage, 2); ?>%</span>
                </div>
            </div>
            <p class="storage-text"><?php echo formatBytes($usedStorageBytes); ?> of <?php echo formatBytes($totalStorageBytes); ?> used</p>
            <?php if ($isStorageFull): ?>
                <p class="storage-text" style="color: var(--metro-error); font-weight: bold;">Storage Full! Cannot upload more files.</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="main-content">
        <div class="header-main">
            <button class="sidebar-toggle-btn" id="sidebarToggleBtn"><i class="fas fa-bars"></i></button>
            <h1>Protection</h1>
        </div>

        <div class="protection-container">
            <h2>Scan & History</h2>
            <button onclick="startScan()"><i class="fas fa-play-circle"></i> Start Scan & Clean Now</button>

            <div class="filter-section">
                <label for="filterDate"><i class="fas fa-calendar-alt"></i> Filter by Date:</label>
                <input type="date" id="filterDate" />
                <button onclick="loadHistory(1)"><i class="fas fa-filter"></i> Apply Filter</button>
            </div>

            <h3>Scan History</h3>
            <div id="history-table-container">
                <table id="history-table">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Log Entry</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- History logs will be loaded here by JavaScript -->
                    </tbody>
                </table>
            </div>
            <div class="pagination"></div>
        </div>
    </div>

    <div id="customNotification" class="notification"></div>
    <div class="overlay" id="mobileOverlay"></div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const customNotification = document.getElementById('customNotification');
            const sidebar = document.querySelector('.sidebar');
            const sidebarToggleBtn = document.getElementById('sidebarToggleBtn');
            const mobileOverlay = document.getElementById('mobileOverlay');

            let currentPage = 1;

            // Function to show custom notification
            function showNotification(message, type) {
                customNotification.innerHTML = message;
                customNotification.className = 'notification show ' + type;
                setTimeout(() => {
                    customNotification.classList.remove('show');
                }, 3000);
            }

            // Function to start scan
            window.startScan = function() {
                showNotification('Scan and cleanup process started...', 'info');
                fetch('protection.php?action=scan')
                    .then(res => res.text())
                    .then(msg => {
                        showNotification(msg, 'success');
                        loadHistory(1); // Reload history after scan
                    })
                    .catch(error => {
                        console.error('Error during scan:', error);
                        showNotification('An error occurred during the scan.', 'error');
                    });
            }

            // Function to load history
            window.loadHistory = function(page = 1) {
                currentPage = page;
                const date = document.getElementById('filterDate').value;
                fetch(`protection.php?action=get_history&page=${page}&date=${date}`)
                    .then(res => res.json())
                    .then(data => {
                        const historyTableBody = document.querySelector('#history-table tbody');
                        historyTableBody.innerHTML = ''; // Clear existing rows

                        if (data.data.length === 0) {
                            historyTableBody.innerHTML = '<tr><td colspan="2" style="text-align: center;">No history logs found.</td></tr>';
                        } else {
                            data.data.forEach((line, i) => {
                                const row = historyTableBody.insertRow();
                                const cellNo = row.insertCell();
                                const cellLog = row.insertCell();
                                cellNo.textContent = (page - 1) * 10 + i + 1;
                                cellLog.textContent = line;
                            });
                        }

                        // Pagination buttons
                        const paginationContainer = document.querySelector('.pagination');
                        paginationContainer.innerHTML = '';
                        for (let i = 1; i <= data.totalPages; i++) {
                            const btn = document.createElement('button');
                            btn.textContent = i;
                            btn.disabled = (i === page);
                            btn.onclick = () => loadHistory(i);
                            paginationContainer.appendChild(btn);
                        }
                    })
                    .catch(error => {
                        console.error('Error loading history:', error);
                        showNotification('Failed to load history logs.', 'error');
                    });
            }

            // Initial load of history
            loadHistory(1);

            // Sidebar toggle for mobile/tablet
            sidebarToggleBtn.addEventListener('click', () => {
                sidebar.classList.toggle('show-mobile-sidebar');
                mobileOverlay.classList.toggle('show');
            });

            mobileOverlay.addEventListener('click', () => {
                sidebar.classList.remove('show-mobile-sidebar');
                mobileOverlay.classList.remove('show');
            });

            // Device detection & body class toggling (copied from index.php)
            function setDeviceClass() {
                const ua = navigator.userAgent || '';
                const isIPad = /iPad/.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
                const w = window.innerWidth;
                document.body.classList.remove('mobile', 'tablet-portrait', 'tablet-landscape', 'desktop'); // Clear all
                // For this page, we are intentionally NOT adding mobile/tablet classes for now
                if (w > 1024) { // Only apply desktop styles
                    document.body.classList.add('desktop');
                }
                // If you later decide to add responsive styles, uncomment and adjust these:
                /*
                if (w <= 767) {
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
                */
            }
            window.addEventListener('resize', setDeviceClass);
            window.addEventListener('orientationchange', setDeviceClass);
            setDeviceClass(); // init
        });
    </script>
</body>
</html>
