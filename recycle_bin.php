<?php
include 'config.php';
include 'functions.php'; // Include functions.php file

session_start();
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// --- Tambahkan kode ini ---
// Define $currentUserRole from session
$currentUserRole = isset($_SESSION['role']) ? $_SESSION['role'] : 'guest'; // Default to 'guest' or 'user' if not set
// --- Akhir penambahan kode ---
// Current folder ID, default to NULL for root
$currentFolderId = isset($_GET['folder']) ? (int)$_GET['folder'] : NULL;

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
    

$userId = $_SESSION['user_id'];

// Get search query
$searchQuery = isset($_GET['search']) ? $_GET['search'] : '';

// Get sorting parameters
$releaseFilter = isset($_GET['release']) ? $_GET['release'] : 'newest'; // Default to newest for trash
$sortOrder = isset($_GET['sort']) ? $_GET['sort'] : 'asc'; // 'asc', 'desc'
$fileTypeFilter = isset($_GET['file_type']) ? $_GET['file_type'] : 'all'; // 'all', 'document', 'music', etc.

// Define file categories for filtering (same as index.php)
$docExt = ['doc','docx','pdf','ppt','pptx','xls','xlsx','txt','odt','odp','rtf','md','log','csv','tex'];
$musicExt = ['mp3','wav','aac','ogg','flac','m4a','alac','wma','opus','amr','mid'];
$videoExt = ['mp4','mkv','avi','mov','wmv','flv','webm','3gp','m4v','mpg','mpeg','ts','ogv'];
$codeExt = ['html','htm','css','js','php','py','java','json','xml','ts','tsx','jsx','vue','cpp','c','cs','rb','go','swift','sql','sh','bat','ini','yml','yaml','md','pl','r'];
$archiveExt = ['zip','rar','7z','tar','gz','bz2','xz','iso','cab','arj'];
$instExt = ['exe','msi','apk','ipa','sh','bat','jar','appimage','dmg','bin'];
$ptpExt = ['torrent','nzb','ed2k','part','!ut'];
$imageExt = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg', 'tiff'];
$cadExt = ['dwg', 'dxf', 'dgn', 'iges', 'igs', 'step', 'stp', 'stl', '3ds', 'obj', 'sldprt', 'sldasm', 'ipt', 'iam', 'catpart', 'catproduct', 'prt', 'asm', 'fcstd', 'skp', 'x_t', 'x_b'];

// Map filter types to actual extensions
$filterExtensions = [];
switch ($fileTypeFilter) {
    case 'document': $filterExtensions = $docExt; break;
    case 'music': $filterExtensions = $musicExt; break;
    case 'video': $filterExtensions = $videoExt; break;
    case 'code': $filterExtensions = $codeExt; break;
    case 'archive': $filterExtensions = $archiveExt; break;
    case 'installation': $filterExtensions = $instExt; break;
    case 'p2p': $filterExtensions = $ptpExt; break;
    case 'image': $filterExtensions = $imageExt; break;
    case 'cad': $filterExtensions = $cadExt; break;
    case 'all': default: $filterExtensions = []; break; // No specific filter
}

// Fetch deleted files and folders for the current user
$deletedItems = [];

// SQL for deleted files
$sqlFiles = "SELECT id, file_name, file_path, file_size, file_type, deleted_at FROM deleted_files WHERE user_id = ?";
$paramsFiles = [$userId];
$typesFiles = "i";

if (!empty($searchQuery)) {
    $sqlFiles .= " AND file_name LIKE ?";
    $searchTerm = '%' . $searchQuery . '%';
    $paramsFiles[] = $searchTerm;
    $typesFiles .= "s";
}

if (!empty($filterExtensions)) {
    $placeholders = implode(',', array_fill(0, count($filterExtensions), '?'));
    $sqlFiles .= " AND file_type IN ($placeholders)";
    foreach ($filterExtensions as $ext) {
        $paramsFiles[] = $ext;
        $typesFiles .= "s";
    }
}

// Apply release date filter (using deleted_at for trash)
if ($releaseFilter === 'newest') {
    $sqlFiles .= " ORDER BY deleted_at DESC";
} elseif ($releaseFilter === 'oldest') {
    $sqlFiles .= " ORDER BY deleted_at ASC";
} else {
    // Apply alphabetical sorting if no release filter or 'all'
    if ($sortOrder === 'asc') {
        $sqlFiles .= " ORDER BY file_name ASC";
    } else {
        $sqlFiles .= " ORDER BY file_name DESC";
    }
}

$stmtFiles = $conn->prepare($sqlFiles);
$stmtFiles->bind_param($typesFiles, ...$paramsFiles);
$stmtFiles->execute();
$resultFiles = $stmtFiles->get_result();
while ($row = $resultFiles->fetch_assoc()) {
    $row['item_type'] = 'file';
    $deletedItems[] = $row;
}
$stmtFiles->close();

// SQL for deleted folders
$sqlFolders = "SELECT id, folder_name, deleted_at FROM deleted_folders WHERE user_id = ?";
$paramsFolders = [$userId];
$typesFolders = "i";

if (!empty($searchQuery)) {
    $sqlFolders .= " AND folder_name LIKE ?";
    $searchTerm = '%' . $searchQuery . '%';
    $paramsFolders[] = $searchTerm;
    $typesFolders .= "s";
}

// Apply release date filter (using deleted_at for trash)
if ($releaseFilter === 'newest') {
    $sqlFolders .= " ORDER BY deleted_at DESC";
} elseif ($releaseFilter === 'oldest') {
    $sqlFolders .= " ORDER BY deleted_at ASC";
} else {
    // Apply alphabetical sorting if no release filter or 'all'
    if ($sortOrder === 'asc') {
        $sqlFolders .= " ORDER BY folder_name ASC";
    } else {
        $sqlFolders .= " ORDER BY folder_name DESC";
    }
}

$stmtFolders = $conn->prepare($sqlFolders);
$stmtFolders->bind_param($typesFolders, ...$paramsFolders);
$stmtFolders->execute();
$resultFolders = $stmtFolders->get_result();
while ($row = $resultFolders->fetch_assoc()) {
    $row['item_type'] = 'folder';
    $deletedItems[] = $row;
}
$stmtFolders->close();

// Sort all deleted items by deleted_at if releaseFilter is newest/oldest, otherwise by name
if ($releaseFilter === 'newest') {
    usort($deletedItems, function($a, $b) {
        return strtotime($b['deleted_at']) - strtotime($a['deleted_at']);
    });
} elseif ($releaseFilter === 'oldest') {
    usort($deletedItems, function($a, $b) {
        return strtotime($a['deleted_at']) - strtotime($b['deleted_at']);
    });
} else {
    usort($deletedItems, function($a, $b) use ($sortOrder) {
        $nameA = $a['item_type'] === 'file' ? $a['file_name'] : $a['folder_name'];
        $nameB = $b['item_type'] === 'file' ? $b['file_name'] : $b['folder_name'];
        return ($sortOrder === 'asc') ? strcasecmp($nameA, $nameB) : strcasecmp($nameB, $nameA);
    });
}


// Simulated data for storage (Replace with actual data from your database/system)
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

// Define file categories for thumbnail preview (same as index.php)
// These are already defined above, no need to redefine.
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recycle Bin - SKMI Cloud Storage</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="css/internal.css"> <!-- Import CSS -->
    <style>
        /* Metro Design (Modern UI) & Windows 7 Animations */
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

            /* --- LOKASI EDIT UKURAN FONT SIDEBAR --- */
            --sidebar-font-size-desktop: 0.9em; /* Ukuran font default untuk desktop */
            --sidebar-font-size-tablet-landscape: 1.0em; /* Ukuran font untuk tablet landscape */
            --sidebar-font-size-tablet-portrait: 0.95em; /* Ukuran font untuk tablet portrait */
            --sidebar-font-size-mobile: 0.9em; /* Ukuran font untuk mobile */
            /* --- AKHIR LOKASI EDIT UKURAN FONT SIDEBAR --- */
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
            overflow-y: auto; /* Enable vertical scrolling */
            overflow-x: hidden; /* Hide horizontal scrolling */
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
            font-size: var(--sidebar-font-size-desktop); /* Menggunakan variabel untuk desktop */
            transition: background-color 0.2s ease-out, color 0.2s ease-out;
            border-left: 5px solid transparent; /* For active state */
        }

        .sidebar-menu a i {
            margin-right: 15px;
            font-size: 1.4em;
            width: 25px; /* Fixed width for icons */
            text-align: center;
        }

        /* Perbaikan Animasi Hover dan Active */
        .sidebar-menu a:hover {
            background-color: rgba(255,255,255,0.15); /* Sedikit lebih terang dari sebelumnya */
            color: #FFFFFF;
            transform: translateX(5px); /* Efek geser ke kanan */
            transition: background-color 0.2s ease-out, color 0.2s ease-out, transform 0.2s ease-out;
        }

        .sidebar-menu a.active {
            background-color: var(--metro-blue); /* Metro accent color */
            border-left: 5px solid var(--metro-blue);
            color: #FFFFFF;
            font-weight: 600;
            transform: translateX(0); /* Pastikan tidak ada geseran saat aktif */
        }

        /* Storage Info */
        .storage-info {
            padding: 20px;
            border-top: 1px solid rgba(255,255,255,0.1);
            text-align: center;
            font-size: 0.9em;
            /* Posisi dirapikan seperti priority_files.php */
            margin-top: auto; /* Dorong ke bawah */
            padding-top: 20px;
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
            /* box-shadow: 0 5px 15px rgba(0,0,0,0.1); */ /* Removed shadow */
        }

        /* Header Main - Now always white */
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
            border-radius: 0; /* MODIFIED: No rounded top corners for full width */
            /*box-shadow: 0 2px 5px rgba(0,0,0,0.05); /* Subtle shadow for header */
        }

        .header-main h1 {
            margin: 0;
            color: var(--metro-text-color);
            font-size: 2.5em;
            font-weight: 300;
        }

        .search-bar {
            display: flex;
            align-items: center;
            background-color: var(--metro-light-gray);
            border-radius: 5px;
            padding: 8px 15px;
            box-shadow: inset 0 1px 3px rgba(0,0,0,0.1); /* Subtle inner shadow */
            transition: background-color 0.2s ease-out, box-shadow 0.2s ease-out;
        }

        .search-bar:focus-within {
            background-color: #FFFFFF;
            box-shadow: 0 0 0 2px var(--metro-blue); /* Focus highlight */
        }

        .search-bar input {
            border: none;
            outline: none;
            padding: 5px;
            font-size: 1em;
            width: 280px;
            background: transparent;
            color: var(--metro-text-color);
        }

        .search-bar i {
            color: var(--metro-dark-gray);
            margin-right: 10px;
        }

        /* Toolbar */
        .toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--metro-light-gray);
        }

        .toolbar-left {
            display: flex;
            gap: 10px; /* Space between buttons */
        }

        .toolbar-right {
            display: flex;
            gap: 10px; /* Space between buttons */
        }

        .toolbar-left button,
        .toolbar-right button {
            background-color: var(--metro-blue);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 3px; /* Sharper corners */
            cursor: pointer;
            font-size: 1em;
            transition: background-color 0.2s ease-out, transform 0.1s ease-in-out;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            white-space: nowrap; /* Prevent text wrapping */
        }

        .toolbar-left button:hover,
        .toolbar-right button:hover {
            background-color: var(--metro-dark-blue);
            transform: translateY(-1px); /* Subtle lift */
        }

        .toolbar-left button:active,
        .toolbar-right button:active {
            transform: translateY(0); /* Press effect */
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .toolbar-left button i {
            margin-right: 8px;
        }

        /* Archive button specific style */
        #archiveSelectedBtn {
            background-color: var(--metro-warning); /* Orange for archive */
            color: #FFFFFF;
            font-weight: normal;
        }
        #archiveSelectedBtn:hover {
            background-color: #E67A00; /* Darker orange on hover */
        }

        .view-toggle button {
            background-color: var(--metro-light-gray);
            border: 1px solid var(--metro-medium-gray);
            padding: 8px 12px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 1.1em;
            color: var(--metro-text-color);
            transition: background-color 0.2s ease-out, color 0.2s ease-out;
        }

        .view-toggle button.active {
            background-color: var(--metro-blue);
            color: white;
            border-color: var(--metro-blue);
        }

        /* Breadcrumbs */
        .breadcrumbs {
            margin-bottom: 20px;
            font-size: 0.95em;
            color: var(--metro-dark-gray);
            display: flex;
            align-items: center;
            flex-wrap: wrap;
        }

        .breadcrumbs a {
            color: var(--metro-blue);
            text-decoration: none;
            margin-right: 5px;
            transition: color 0.2s ease-out;
        }

        .breadcrumbs a:hover {
            text-decoration: underline;
            color: var(--metro-dark-blue);
        }

        .breadcrumbs span {
            margin-right: 5px;
            color: var(--metro-medium-gray);
        }

        /* File and Folder Display */
        .file-list-container {
            flex-grow: 1;
            background-color: #FFFFFF;
            border-radius: 8px;
            /* box-shadow: 0 2px 10px rgba(0,0,0,0.05); */ /* Removed as main-content has shadow */
            padding: 0; /* Removed padding as table handles it */
            overflow: auto; /* Allow horizontal scrolling for wide tables */
            -webkit-overflow-scrolling: touch; /* momentum scrolling on iOS */
            touch-action: pan-y; /* allow vertical scrolling by default */
        }

        /* List View */
        .file-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 0;
        }

        .file-table th, .file-table td {
            text-align: left;
            padding: 15px 20px; /* More padding */
            border-bottom: 1px solid var(--metro-light-gray);
            font-size: 0.95em;
        }

        .file-table th {
            background-color: var(--metro-bg-color); /* Light gray header */
            color: var(--metro-dark-gray);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85em;
            position: sticky;
            top: 0;
            z-index: 1;
        }

        .file-table tbody tr:hover {
            background-color: var(--metro-light-gray); /* Subtle hover */
        }

        .file-icon {
            margin-right: 12px;
            font-size: 1.3em; /* Slightly larger icons */
            width: 28px; /* Fixed width for icons */
            text-align: center;
            flex-shrink: 0; /* Prevent icon from shrinking */
        }

        /* Icon colors for list view (Metro-inspired) */
        /* These are now handled by internal.css via file-color-xxx classes */
        /* .file-icon.pdf { color: #E81123; } */
        /* .file-icon.doc { color: #2B579A; } */
        /* .file-icon.xls { color: #107C10; } */
        /* .file-icon.ppt { color: #D24726; } */
        /* .file-icon.jpg, .file-icon.png, .file-icon.gif { color: #8E24AA; } */
        /* .file-icon.zip { color: #F7B500; } */
        /* .file-icon.txt { color: #666666; } */
        /* .file-icon.exe, .file-icon.apk { color: #0078D7; } */
        /* .file-icon.mp3, .file-icon.wav { color: #00B294; } */
        /* .file-icon.mp4, .file-icon.avi { color: #FFB900; } */
        /* .file-icon.html, .file-icon.css, .file-icon.js, .file-icon.php, .file-icon.py, .file-icon.json, .file-icon.sql, .file-icon.java, .file-icon.c { color: #505050; } */
        .file-icon.folder { color: #FFD700; } /* Gold for folders */
        /* .file-icon.default { color: #999999; } */

        .file-name-cell {
            display: flex;
            align-items: center;
            max-width: 350px; /* Increased max-width */
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .file-name-cell a {
            color: var(--metro-text-color);
            text-decoration: none;
            font-weight: 400;
            display: block;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            transition: color 0.2s ease-out;
        }

        .file-name-cell a:hover {
            color: var(--metro-blue);
        }

        .file-checkbox {
            margin-right: 10px;
            transform: scale(1.2); /* Slightly larger checkbox */
            accent-color: var(--metro-blue); /* Metro accent color for checkbox */
        }

        /* Context Menu Styles */
        .context-menu {
            position: fixed;
            z-index: 12000; /* Higher z-index for context menu */
            background: #FFFFFF;
            border: 1px solid var(--metro-medium-gray);
            box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
            border-radius: 5px;
            overflow: hidden;
            min-width: 180px;
            display: none; /* Hidden by default */
            animation: fadeInScale 0.2s ease-out forwards;
            transform-origin: top left;
        }
        .context-menu.visible {
            display: block;
        }

        .context-menu ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .context-menu li {
            color: var(--metro-text-color);
            padding: 10px 15px;
            text-decoration: none;
            display: flex;
            align-items: center;
            font-size: 0.95em;
            transition: background-color 0.2s ease-out, color 0.2s ease-out;
            cursor: pointer;
        }

        .context-menu li i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }

        .context-menu li:hover {
            background-color: var(--metro-blue);
            color: #FFFFFF;
        }

        .context-menu .separator {
            height: 1px;
            background-color: var(--metro-light-gray);
            margin: 5px 0;
        }


        /* Grid View */
        .file-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); /* Adjusted minmax for better preview */
            gap: 25px; /* Increased gap */
            padding: 20px;
            overflow: auto; /* Allow horizontal scrolling for wide tables */
            -webkit-overflow-scrolling: touch; /* momentum scrolling on iOS */
            touch-action: pan-y; /* allow vertical scrolling by default */
        }

        .grid-item {
            background-color: #FFFFFF;
            border: 1px solid var(--metro-light-gray);
            border-radius: 5px;
            padding: 15px;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: transform 0.2s ease-out, box-shadow 0.2s ease-out;
            position: relative;
            overflow: hidden; /* Ensure content stays within bounds */
            user-select: none; /* Prevent text selection on long press */
            cursor: pointer; /* Indicate clickable */
             /* For keyboard navigation */
        }

        .grid-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.15);
        }

        .grid-item .file-checkbox {
            position: absolute;
            top: 10px;
            left: 10px;
            z-index: 1;
        }

        .grid-item .file-icon {
            font-size: 3.5em; /* Larger icon for grid */
            margin-bottom: 10px;
            width: auto;
            /* color: var(--metro-dark-gray); */ /* Default icon color for grid - now handled by internal.css */
        }

        /* Thumbnail Grid Specific Styles */
        .grid-thumbnail {
            width: 100%;
            height: 140px; /* Fixed height for consistent grid */
            object-fit: contain;
            margin-bottom: 10px;
            border-radius: 3px;
            border: 1px solid var(--metro-medium-gray);
            background-color: var(--metro-bg-color);
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: hidden;
            position: relative;
            font-size: 12px;
            color: var(--metro-text-color);
            text-align: left;
            padding: 5px;
            box-sizing: border-box;
        }

        .grid-thumbnail i {
            font-size: 4.5em; /* Larger icon for thumbnail placeholder */
            color: var(--metro-medium-gray);
        }

        .grid-thumbnail img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        .grid-thumbnail video, .grid-thumbnail audio {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .grid-thumbnail pre {
            font-size: 10px;
            white-space: pre-wrap;
            word-break: break-all;
            margin: 0;
            padding: 0;
            max-height: 100%;
            overflow: hidden;
        }

        .grid-thumbnail .file-type-label {
            font-size: 0.8em;
            color: #FFFFFF;
            position: absolute;
            bottom: 5px;
            right: 5px;
            background-color: rgba(0,0,0,0.5);
            padding: 3px 6px;
            border-radius: 3px;
        }

        .file-name {
            font-weight: 500;
            color: var(--metro-text-color);
            text-decoration: none;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 100%;
            display: block;
            margin-top: 5px;
            font-size: 1.05em;
            transition: color 0.2s ease-out;
        }
        
        .file-name:hover {
            color: var(--metro-blue);
        }

        .file-size {
            font-size: 0.85em;
            color: var(--metro-dark-gray);
            margin-top: 5px;
        }

        .action-buttons {
            display: flex;
            justify-content: center;
            margin-top: 15px;
            gap: 8px;
            width: 100%;
        }

        /* MODIFIED: Smaller action buttons for grid view */
        .action-buttons button {
            background-color: var(--metro-blue);
            color: white;
            border: none;
            padding: 6px 10px; /* Reduced padding */
            border-radius: 3px;
            cursor: pointer;
            font-size: 0.8em; /* Reduced font size */
            transition: background-color 0.2s ease-out, transform 0.1s ease-in-out;
        }

        .action-buttons button:hover {
            background-color: var(--metro-dark-blue);
            transform: translateY(-1px);
        }
        .action-buttons button:active {
            transform: translateY(0);
        }
        .action-buttons button.delete-button {
            background-color: var(--metro-error);
        }
        .action-buttons button.delete-button:hover {
            background-color: #C4001A;
        }
        /* Custom style for extract button hover in grid view */
        .action-buttons button.extract-button:hover {
            background-color: #ff3399; /* Custom hover color */
            color: #FFFFFF; /* Text color on hover */
        }

        /* Item More Button (three dots) */
        .item-more {
            position: absolute;
            top: 5px;
            right: 5px;
            background: none;
            border: none;
            font-size: 1.2em;
            color: var(--metro-dark-gray);
            cursor: pointer;
            padding: 5px;
            border-radius: 50%;
            transition: background-color 0.2s ease-out, color 0.2s ease-out;
            z-index: 10; /* Ensure it's above other elements */
        }
        .item-more:hover {
            background-color: rgba(0,0,0,0.1);
            color: var(--metro-text-color);
        }
        .file-table .item-more {
            position: static; /* Reset position for table view */
            margin-left: auto; /* Push to right in table cell */
            display: flex;
            align-items: center;
            justify-content: center;
            width: 30px; /* Fixed width for alignment */
            height: 30px; /* Fixed height for alignment */
        }


        /* Modal Styles (Pop-up CRUD) */
        .modal {
            display: flex; /* Changed to flex for centering */
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.6); /* Darker overlay */
            justify-content: center;
            align-items: center;
            opacity: 0;
            visibility: hidden; /* Hidden by default */
            transition: opacity 0.3s ease-out, visibility 0.3s ease-out;
        }

        .modal.show {
            opacity: 1;
            visibility: visible;
        }

        .modal-content {
            background-color: #FFFFFF;
            padding: 30px;
            border-radius: 5px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.3);
            width: 90%;
            max-width: 550px; /* Slightly larger modals */
            position: relative;
            transform: translateY(-20px); /* Initial position for animation */
            opacity: 0;
            transition: transform 0.3s ease-out, opacity 0.3s ease-out;
        }

        .modal.show .modal-content {
            transform: translateY(0);
            opacity: 1;
        }

        .close-button {
            color: var(--metro-dark-gray);
            position: absolute;
            top: 15px;
            right: 20px;
            font-size: 30px;
            font-weight: normal;
            cursor: pointer;
            transition: color 0.2s ease-out;
        }

        .close-button:hover,
        .close-button:focus {
            color: var(--metro-error);
        }

        .modal h2 {
            margin-top: 0;
            margin-bottom: 25px;
            color: var(--metro-text-color);
            font-size: 2em;
            font-weight: 300;
            border-bottom: 1px solid var(--metro-light-gray);
            padding-bottom: 15px;
        }

        .modal label {
            display: block;
            margin-bottom: 10px;
            font-weight: 600;
            color: var(--metro-text-color);
            font-size: 1.05em;
        }

        .modal input[type="text"],
        .modal input[type="file"] {
            width: calc(100% - 20px);
            padding: 12px;
            margin-bottom: 20px;
            border: 1px solid var(--metro-medium-gray);
            border-radius: 3px;
            font-size: 1em;
            color: var(--metro-text-color);
            background-color: #F9F9F9;
            transition: border-color 0.2s ease-out, box-shadow 0.2s ease-out;
        }
        
        .modal input[type="text"]:focus,
        .modal input[type="file"]:focus {
            border-color: var(--metro-blue);
            box-shadow: 0 0 0 2px rgba(0,120,215,0.3);
            outline: none;
            background-color: #FFFFFF;
        }

        .modal input[type="file"] {
            border: 1px solid var(--metro-medium-gray); /* Keep border for file input */
            padding: 10px;
        }

        .modal button[type="submit"] {
            background-color: var(--metro-blue);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 1.1em;
            transition: background-color 0.2s ease-out, transform 0.1s ease-in-out;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .modal button[type="submit"]:hover {
            background-color: var(--metro-dark-blue);
            transform: translateY(-1px);
        }
        .modal button[type="submit"]:active {
            transform: translateY(0);
        }

        .hidden {
            display: none !important;
        }

        /* Custom Notification Styles */
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

        /* Upload Preview Modal Specific Styles */
        #uploadPreviewModal .modal-content {
            max-width: 650px;
            padding: 25px;
        }

        #uploadPreviewModal .modal-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            border-bottom: 1px solid var(--metro-light-gray);
            padding-bottom: 15px;
        }

        #uploadPreviewModal .modal-header h2 {
            flex-grow: 1;
            margin: 0;
            font-size: 2.2em;
            font-weight: 300;
            border-bottom: none; /* Remove double border */
            padding-bottom: 0;
        }

        #uploadPreviewModal .modal-header .back-button {
            background: none;
            border: none;
            font-size: 1.8em;
            cursor: pointer;
            margin-right: 15px;
            color: var(--metro-dark-gray);
            transition: color 0.2s ease-out;
        }
        #uploadPreviewModal .modal-header .back-button:hover {
            color: var(--metro-blue);
        }

        #uploadPreviewList {
            max-height: 450px; /* Increased height */
            overflow-y: auto;
            margin-bottom: 20px;
            padding-right: 10px; /* For scrollbar */
        }

        .upload-item {
            display: flex;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid var(--metro-light-gray);
            transition: background-color 0.2s ease-out;
        }

        .upload-item:last-child {
            border-bottom: none;
        }
        .upload-item:hover {
            background-color: var(--metro-bg-color);
        }

        .upload-item .file-icon {
            font-size: 2.8em; /* Larger icons */
            margin-right: 20px;
            flex-shrink: 0;
            width: 45px;
            text-align: center;
            /* color: var(--metro-dark-gray); */ /* Default color - now handled by internal.css */
        }

        .upload-item-info {
            flex-grow: 1;
        }

        .upload-item-info strong {
            display: block;
            font-weight: 600;
            color: var(--metro-text-color);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 100%;
            font-size: 1.05em;
        }

        .upload-progress-container {
            width: 100%;
            background-color: var(--metro-light-gray);
            border-radius: 5px;
            height: 8px;
            margin-top: 8px;
            overflow: hidden;
            position: relative;
        }

        .upload-progress-bar {
            height: 100%;
            background-color: var(--metro-success);
            border-radius: 5px;
            width: 0%;
            transition: width 0.3s ease-out;
        }

        .upload-status-icon {
            font-size: 1.6em; /* Larger status icons */
            margin-left: 20px;
            flex-shrink: 0;
            width: 30px;
            text-align: center;
        }

        .upload-status-icon.processing { color: var(--metro-blue); }
        .upload-status-icon.success { color: var(--metro-success); }
        .upload-status-icon.error { color: var(--metro-error); }
        .upload-status-icon.cancelled { color: var(--metro-warning); }
        
        .upload-action-button {
            background: none;
            border: none;
            font-size: 1.4em; /* Larger action button */
            cursor: pointer;
            color: var(--metro-dark-gray);
            margin-left: 15px;
            transition: color 0.2s ease-out;
        }

        .upload-action-button:hover {
            color: var(--metro-error);
        }

        .upload-item.complete .upload-action-button {
            display: none;
        }

        /* Styles for the dropdown containers */
        .dropdown-container {
            position: relative;
            display: inline-block;
        }

        .dropdown-content {
            display: none;
            position: absolute;
            background-color: #FFFFFF;
            min-width: 180px; /* Wider dropdowns */
            box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
            z-index: 10;
            border-radius: 3px;
            /*top: 50%;*/
            /*left: 0;*/
            margin-top: 8px; /* Space between button and dropdown */
            animation: fadeInScale 0.2s ease-out forwards; /* Windows 7 like animation */
            transform-origin: top left;
        }

        .dropdown-content a {
            color: var(--metro-text-color);
            padding: 12px 18px;
            text-decoration: none;
            display: block;
            font-size: 0.95em;
            transition: background-color 0.2s ease-out, color 0.2s ease-out;
        }

        .dropdown-content a:hover {
            background-color: var(--metro-blue);
            color: #FFFFFF;
        }

        .dropdown-container.show .dropdown-content {
            display: block;
        }

        /* Style for filter buttons (icons only) */
        .filter-button {
            background-color: var(--metro-blue);
            color: white;
            border: none;
            padding: 10px 12px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 1.2em; /* Slightly larger icon */
            transition: background-color 0.2s ease-out, transform 0.1s ease-in-out;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .filter-button:hover {
            background-color: var(--metro-dark-blue);
            transform: translateY(-1px);
        }
        .filter-button:active {
            transform: translateY(0);
        }

        /* Share Link Modal */
        #shareLinkModal .modal-content {
            max-width: 500px;
        }
        #shareLinkModal input[type="text"] {
            width: calc(100% - 120px); /* Adjust width for copy button */
            margin-right: 10px;
            display: inline-block;
            vertical-align: middle;
            background-color: var(--metro-bg-color);
            border: 1px solid var(--metro-medium-gray);
            cursor: text;
        }
        #shareLinkModal button {
            display: inline-block;
            vertical-align: middle;
            padding: 10px 18px;
            font-size: 0.95em;
            background-color: var(--metro-blue);
            color: white;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            transition: background-color 0.2s ease-out, transform 0.1s ease-in-out;
        }
        #shareLinkModal button:hover {
            background-color: var(--metro-dark-blue);
            transform: translateY(-1px);
        }
        #shareLinkModal .share-link-container {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }
        #shareLinkModal .share-link-container button {
            margin-left: 0; /* No extra margin */
        }
        #shareLinkModal p.small-text {
            font-size: 0.85em;
            color: var(--metro-dark-gray);
            margin-top: 10px;
        }

        /* Windows 7-like Animations */
        @keyframes fadeInScale {
            from {
                opacity: 0;
                transform: scale(0.95);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        @keyframes slideInFromTop {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal.show .modal-content {
            animation: slideInFromTop 0.3s ease-out forwards;
        }

        /* General button hover/active effects */
        button {
            outline: none;
        }
        button:focus {
            box-shadow: 0 0 0 2px rgba(0,120,215,0.5); /* Focus ring */
        }

        /* ========================================================================== */
        /* Responsive Classes for iPad, Tablet, HP (Android & iOS) */
        /* ========================================================================== */

        /* Default for Desktop/Windows */
        .sidebar-toggle-btn {
            display: none; /* Hidden on desktop */
        }
        .sidebar.mobile-hidden {
            display: flex; /* Always visible on desktop */
            transform: translateX(0);
        }
        /* MODIFIED: Hide desktop search bar and profile user */
        .desktop-only {
            display: none !important;
        }
        .search-bar-desktop {
            display: none !important;
        }
        /* END MODIFIED */

        .header-main .my-drive-title {
            display: block; /* "My Drive" visible on desktop */
        }
        .search-bar-mobile {
            display: none; /* Mobile search bar hidden on desktop */
        }
        /* MODIFIED: Hide toolbar-filter-buttons by default on desktop */
        .toolbar-filter-buttons { 
            display: none;
        }

        /* Custom Scrollbar for Webkit browsers (Chrome, Safari) */
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

        /* Class for iPad & Tablet (Landscape: min-width 768px, max-width 1024px) */
        @media (min-width: 768px) and (max-width: 1024px) {
            body.tablet-landscape .sidebar {
                width: 220px; /* Slightly narrower sidebar */
            }
            body.tablet-landscape .main-content {
                margin: 0; /* MODIFIED: Full width */
                padding: 20px;
            }
            body.tablet-landscape .header-main {
                padding: 10px 20px;
                margin: 0; /* MODIFIED: Full width */
            }
            body.tablet-landscape .header-main h1 {
                font-size: 2em;
            }
            body.tablet-landscape .search-bar input {
                width: 200px;
            }
            body.tablet-landscape .file-table th,
            body.tablet-landscape .file-table td {
                padding: 12px 15px;
                font-size: 0.9em; /* Slightly smaller font for table cells */
            }
            body.tablet-landscape .grid-item {
                padding: 10px;
            }
            body.tablet-landscape .grid-thumbnail {
                height: 120px;
            }
            body.tablet-landscape .file-grid {
                grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
                gap: 20px;
            }
            body.tablet-landscape .toolbar-left button,
            body.tablet-landscape .toolbar-right button {
                padding: 8px 15px; /* Slightly smaller buttons */
                font-size: 0.9em;
            }
            body.tablet-landscape .filter-button {
                padding: 8px 10px; /* Smaller filter buttons */
                font-size: 1.1em;
            }
            /* MODIFIED: Hide toolbar-filter-buttons in header on desktop/tablet landscape */
            body.tablet-landscape .toolbar-filter-buttons { 
                display: none;
            }
            /* MODIFIED: Ukuran pop-up disamakan dengan pop-up Edit Profile */
            body.tablet-landscape .modal-content {
                max-width: 550px; /* Consistent with profile.php */
                padding: 30px; /* Consistent with profile.php */
            }
            body.tablet-landscape .modal h2 {
                font-size: 2em; /* Consistent with profile.php */
            }
            body.tablet-landscape .modal label {
                font-size: 1.05em; /* Consistent with profile.php */
            }
            body.tablet-landscape .modal input[type="text"],
            body.tablet-landscape .modal input[type="file"] {
                padding: 12px; /* Consistent with profile.php */
                font-size: 1em; /* Consistent with profile.php */
            }
            body.tablet-landscape .modal button[type="submit"] {
                padding: 12px 25px; /* Consistent with profile.php */
                font-size: 1.1em; /* Consistent with profile.php */
            }
            body.tablet-landscape .sidebar-menu a {
                font-size: var(--sidebar-font-size-tablet-landscape); /* Menggunakan variabel untuk tablet landscape */
            }
        }

        /* Class for iPad & Tablet (Portrait: min-width 768px, max-width 1024px) */
        @media (min-width: 768px) and (max-width: 1024px) and (orientation: portrait) {
            body.tablet-portrait .sidebar {
                position: fixed;
                top: 0;
                left: 0;
                height: 100%;
                z-index: 100;
                transform: translateX(-100%); /* Hidden by default */
                /*box-shadow: 2px 0 10px rgba(0,0,0,0.2);*/
            }
            body.tablet-portrait .sidebar.show-mobile-sidebar {
                transform: translateX(0); /* Show when active */
            }
            body.tablet-portrait .sidebar-toggle-btn {
                display: block; /* Show toggle button */
                background: none;
                border: none;
                font-size: 1.8em;
                color: var(--metro-text-color);
                cursor: pointer;
                margin-left: 10px; /* Space from logo */
                order: 0; /* Place on the left */
            }
            body.tablet-portrait .header-main {
                justify-content: space-between; /* Align items */
                padding: 10px 20px;
                margin: 0; /* MODIFIED: Full width */
            }
            body.tablet-portrait .header-main h1 {
                font-size: 2em;
                flex-grow: 1; /* Allow title to take space */
                text-align: center; /* Center title */
            }
            body.tablet-portrait .header-main .my-drive-title {
                display: none; /* Hide "My Drive" */
            }
            body.tablet-portrait .header-main .search-bar-desktop {
                display: none; /* Hide desktop search bar */
            }
            body.tablet-portrait .search-bar-mobile {
                display: flex; /* Show mobile search bar */
                margin: 0 auto 20px auto; /* Centered below header */
                width: calc(100% - 40px);
            }
            body.tablet-portrait .main-content {
                margin: 0; /* MODIFIED: Full width */
                padding: 20px;
            }
            body.tablet-portrait .toolbar {
                flex-wrap: wrap;
                gap: 10px;
                justify-content: center;
            }
            body.tablet-portrait .toolbar-left,
            body.tablet-portrait .toolbar-right {
                width: 100%;
                justify-content: center;
                margin-bottom: 10px;
                flex-wrap: wrap; /* Allow buttons to wrap */
            }
            body.tablet-portrait .toolbar-left button,
            body.tablet-portrait .toolbar-right button {
                padding: 8px 15px; /* Smaller buttons */
                font-size: 0.9em;
            }
            /* MODIFIED: Show toolbar-filter-buttons for tablet portrait */
            body.tablet-portrait .toolbar-filter-buttons { 
                display: grid; /* Changed to grid for better control */
                grid-template-columns: repeat(auto-fit, minmax(80px, 1fr)); /* Responsive grid columns */
                gap: 10px; /* Space between buttons */
                justify-content: center; /* Center buttons */
                margin-top: 15px; /* Space from toolbar */
                width: 100%;
            }
            /* MODIFIED: Adjust filter button size for tablet portrait */
            body.tablet-portrait .toolbar-filter-buttons .filter-button {
                padding: 8px 10px; /* Smaller filter buttons */
                font-size: 1.1em;
            }
            /* MODIFIED: Hide filter and view buttons in main toolbar for tablet portrait */
            body.tablet-portrait .toolbar .dropdown-container,
            body.tablet-portrait .toolbar .view-toggle { 
                display: none;
            }
            body.tablet-portrait .file-table th,
            body.tablet-portrait .file-table td {
                padding: 10px 12px;
                font-size: 0.85em; /* Smaller font for table cells */
            }
            body.tablet-portrait .grid-item {
                padding: 8px;
            }
            body.tablet-portrait .grid-thumbnail {
                height: 100px;
            }
            body.tablet-portrait .grid-thumbnail i {
                font-size: 3em;
            }
            body.tablet-portrait .file-grid {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
                gap: 15px;
            }
            /* MODIFIED: Ukuran pop-up disamakan dengan pop-up Edit Profile */
            body.tablet-portrait .modal-content {
                max-width: 550px; /* Consistent with profile.php */
                padding: 30px; /* Consistent with profile.php */
            }
            body.tablet-portrait .modal h2 {
                font-size: 2em; /* Consistent with profile.php */
            }
            body.tablet-portrait .modal label {
                font-size: 1.05em; /* Consistent with profile.php */
            }
            body.tablet-portrait .modal input[type="text"],
            body.tablet-portrait .modal input[type="file"] {
                padding: 12px; /* Consistent with profile.php */
                font-size: 1em; /* Consistent with profile.php */
            }
            body.tablet-portrait .modal button[type="submit"] {
                padding: 12px 25px; /* Consistent with profile.php */
                font-size: 1.1em; /* Consistent with profile.php */
            }
            /* MODIFIED: Scrollbar for File Type Filter dropdown */
            body.tablet-portrait .dropdown-content.file-type-filter-dropdown-content {
                max-height: 200px; /* Max height for 6 items + padding */
                overflow-y: auto;
            }
            body.tablet-portrait .sidebar-menu a {
                font-size: var(--sidebar-font-size-tablet-portrait); /* Menggunakan variabel untuk tablet portrait */
            }
        }

        /* Class for Mobile (HP Android & iOS: max-width 767px) */
        @media (max-width: 767px) {
            body.mobile .sidebar {
                position: fixed;
                top: 0;
                left: 0;
                height: 100%;
                width: 200px; /* Narrower sidebar for mobile */
                z-index: 100;
                transform: translateX(-100%); /* Hidden by default */
                /*box-shadow: 2px 0 10px rgba(0,0,0,0.2);*/
            }
            body.mobile .sidebar.show-mobile-sidebar {
                transform: translateX(0); /* Show when active */
            }
            body.mobile .sidebar-toggle-btn {
                display: block; /* Show toggle button */
                background: none;
                border: none;
                font-size: 1.5em;
                color: var(--metro-text-color);
                cursor: pointer;
                margin-left: 10px; /* Space from logo */
                order: 0; /* Place on the left */
            }
            body.mobile .header-main {
                justify-content: space-between; /* Align items */
                padding: 10px 15px;
                margin: 0; /* MODIFIED: Full width */
            }
            body.mobile .header-main h1 {
                font-size: 1.8em;
                flex-grow: 1; /* Allow title to take space */
                text-align: center; /* Center title */
            }
            body.mobile .header-main .my-drive-title {
                display: none; /* Hide "My Drive" */
            }
            body.mobile .header-main .search-bar-desktop {
                display: none; /* Hide desktop search bar */
            }
            body.mobile .search-bar-mobile {
                display: flex; /* Show mobile search bar */
                margin: 0 auto 15px auto; /* Centered below header */
                width: calc(100% - 30px);
            }
            body.mobile .main-content {
                margin: 0; /* MODIFIED: Full width */
                padding: 15px;
                overflow-x: hidden; /* Remove horizontal scrollbar */
            }
            body.mobile .file-list-container {
                overflow-x: hidden; /* Remove horizontal scrollbar for table */
            }
            body.mobile .file-table {
                width: 100%; /* Ensure table fits */
                border-collapse: collapse; /* Ensure collapse for proper rendering */
            }
            body.mobile .file-table thead {
                display: none; /* Hide table header on mobile for better stacking */
            }
            body.mobile .file-table tbody tr {
                display: flex;
                flex-wrap: wrap;
                border: 1px solid var(--metro-light-gray);
                margin-bottom: 10px;
                border-radius: 5px;
                background-color: #FFFFFF;
                /*box-shadow: 0 2px 5px rgba(0,0,0,0.05);*/
                position: relative; /* For checkbox positioning */
            }
            body.mobile .file-table td {
                display: block;
                width: 100%;
                padding: 8px 15px; /* Reduced padding */
                font-size: 0.8em; /* Smaller font for table cells */
                border-bottom: none; /* Remove individual cell borders */
                white-space: normal; /* Allow text to wrap */
                text-align: left;
            }
            body.mobile .file-table td:first-child { /* Checkbox */
                position: absolute;
                top: 8px;
                left: 8px;
                width: auto;
                padding: 0;
            }
            body.mobile .file-table td:nth-child(2) { /* Name */
                padding-left: 40px; /* Make space for checkbox */
                font-weight: 600;
                font-size: 0.9em;
            }
            body.mobile .file-table td:nth-child(3), /* Type */
            body.mobile .file-table td:nth-child(4), /* Size */
            body.mobile .file-table td:nth-child(5) { /* Last Modified */
                display: inline-block;
                width: 50%; /* Two columns per row */
                box-sizing: border-box;
                padding-top: 0;
                padding-bottom: 0;
            }
            body.mobile .file-table td:nth-child(3)::before { content: "Type: "; font-weight: normal; color: var(--metro-dark-gray); }
            body.mobile .file-table td:nth-child(4)::before { content: "Size: "; font-weight: normal; color: var(--metro-dark-gray); }
            body.mobile .file-table td:nth-child(5)::before { content: "Modified: "; font-weight: normal; color: var(--metro-dark-gray); }


            body.mobile .toolbar {
                flex-direction: column; /* Stack buttons */
                align-items: stretch; /* Stretch to full width */
                gap: 8px;
                padding-bottom: 10px;
            }
            body.mobile .toolbar-left,
            body.mobile .toolbar-right {
                width: 100%;
                flex-direction: row; /* Keep buttons in a row */
                flex-wrap: wrap; /* Allow buttons to wrap */
                justify-content: center; /* Center buttons */
                gap: 8px; /* Space between buttons */
                margin-right: 0;
            }
            body.mobile .toolbar-left button,
            body.mobile .toolbar-right button {
                flex-grow: 1; /* Allow buttons to grow */
                min-width: unset; /* Remove min-width constraint */
                padding: 8px 10px; /* Smaller padding */
                font-size: 0.85em; /* Smaller font size */
            }
            body.mobile .view-toggle {
                display: flex;
                width: 100%;
            }
            body.mobile .view-toggle button {
                flex-grow: 1;
            }
            body.mobile .file-icon {
                font-size: 1.1em;
                margin-right: 8px;
                width: 20px;
            }
            body.mobile .file-name-cell {
                max-width: 100%; /* Adjust for smaller screens */
            }
            body.mobile .grid-item {
                padding: 5px;
            }
            body.mobile .grid-thumbnail {
                height: 80px;
            }
            body.mobile .grid-thumbnail i {
                font-size: 3em;
            }
            body.mobile .file-grid {
                grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
                gap: 10px;
                padding: 10px;
            }
            body.mobile .file-name {
                font-size: 0.9em;
            }
            body.mobile .file-size {
                font-size: 0.75em;
            }
            body.mobile .action-buttons button {
                padding: 5px 8px;
                font-size: 0.7em;
            }
            /* MODIFIED: Adjust modal content for mobile */
            body.mobile .modal-content {
                max-width: 550px; /* Consistent with profile.php */
                padding: 30px; /* Consistent with profile.php */
            }
            body.mobile .modal h2 {
                font-size: 2em; /* Consistent with profile.php */
            }
            body.mobile .modal label {
                font-size: 1.05em; /* Consistent with profile.php */
            }
            body.mobile .modal input[type="text"],
            body.mobile .modal input[type="file"] {
                padding: 12px; /* Consistent with profile.php */
                font-size: 1em; /* Consistent with profile.php */
            }
            body.mobile .modal button[type="submit"] {
                padding: 12px 25px; /* Consistent with profile.php */
                font-size: 1.1em; /* Consistent with profile.php */
            }
            body.mobile .upload-item .file-icon {
                font-size: 2em;
                margin-right: 10px;
                width: 35px;
            }
            body.mobile .upload-status-icon {
                font-size: 1.2em;
            }
            body.mobile .upload-action-button {
                font-size: 1.1em;
            }
            body.mobile .share-link-container {
                flex-direction: column;
                align-items: stretch;
            }
            body.mobile #shareLinkModal input[type="text"] {
                width: 100%;
                margin-right: 0;
                margin-bottom: 10px;
            }
            body.mobile #shareLinkModal button {
                width: 100%;
            }
            /* MODIFIED: Show toolbar-filter-buttons for mobile */
            body.mobile .toolbar-filter-buttons { 
                display: grid; /* Changed to grid for better control */
                grid-template-columns: repeat(auto-fit, minmax(80px, 1fr)); /* Responsive grid columns */
                gap: 8px; /* Space between buttons */
                justify-content: center; /* Center buttons */
                margin-top: 10px; /* Space from toolbar */
                width: 100%;
            }
            /* MODIFIED: Adjust filter button size for mobile */
            body.mobile .toolbar-filter-buttons .filter-button {
                padding: 6px 8px; /* Even smaller filter buttons for mobile */
                font-size: 1em;
            }
            /* MODIFIED: Hide filter and view buttons in main toolbar for mobile */
            body.mobile .toolbar .dropdown-container,
            body.mobile .toolbar .view-toggle { 
                display: none;
            }
            /* MODIFIED: Scrollbar for File Type Filter dropdown */
            body.mobile .dropdown-content.file-type-filter-dropdown-content {
                max-height: 200px; /* Max height for 6 items + padding */
                overflow-y: auto;
            }
            body.mobile .sidebar-menu a {
                font-size: var(--sidebar-font-size-mobile); /* Menggunakan variabel untuk mobile */
            }
        }

        /* Overlay for mobile sidebar */
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

        /* MODIFIED: Remove border-bottom from dropdown content links */
        .dropdown-content a {
            border-bottom: none !important;
        }
        /* MODIFIED: Remove border-bottom from context menu list items */
        .context-menu li {
            border-bottom: none !important;
        }

    </style>
</head>
<body>
    <div class="sidebar mobile-hidden">
        <div class="sidebar-header">
            <img src="img/logo.png" alt="Dafino Logo">
        </div>
        <ul class="sidebar-menu">
            <?php if ($currentUserRole === 'admin' || $currentUserRole === 'moderator'): ?>
                <li><a href="control_center.php"><i class="fas fa-cogs"></i> Control Center</a></li>
            <?php endif; ?>
            <?php if (in_array($currentUserRole, ['admin', 'moderator', 'user', 'member'])): ?>
                <li><a href="index.php"><i class="fas fa-folder"></i> My Drive</a></li>
                <li><a href="priority_files.php"><i class="fas fa-star"></i> Priority File</a></li>
                <li><a href="recycle_bin.php" class="active"><i class="fas fa-trash"></i> Recycle Bin</a></li>
            <?php endif; ?>
            <li><a href="summary.php"><i class="fas fa-chart-line"></i> Summary</a></li>
            <li><a href="members.php"><i class="fas fa-users"></i> Members</a></li>
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
            <h1 class="my-drive-title">Recycle Bin</h1>
            <!-- Removed search-bar-desktop and profile-user desktop-only -->
        </div>

        <!-- Mobile Search Bar (moved below header for smaller screens) -->
        <div class="search-bar search-bar-mobile">
            <i class="fas fa-search"></i>
            <input type="text" id="searchInputMobile" placeholder="Search files in trash..." value="<?php echo htmlspecialchars($searchQuery); ?>">
        </div>

        <div class="toolbar">
            <div class="toolbar-left">
                <button id="restoreSelectedBtn" style="background-color: var(--metro-success);"><i class="fas fa-undo"></i> Restore Selected</button>
                <button id="deleteForeverSelectedBtn" style="background-color: var(--metro-error);"><i class="fas fa-times-circle"></i> Delete Forever Selected</button>
                <button id="emptyRecycleBinBtn" style="background-color: var(--metro-error);"><i class="fas fa-trash-alt"></i> Empty Recycle Bin</button>
            </div>
            <div class="toolbar-right">
                <!-- File Type Filter Button -->
                <div class="dropdown-container file-type-filter-dropdown-container">
                    <button id="fileTypeFilterBtn" class="filter-button"><i class="fas fa-filter"></i></button>
                    <div class="dropdown-content file-type-filter-dropdown-content">
                        <a href="#" data-filter="all">All Files</a>
                        <a href="#" data-filter="document">Documents</a>
                        <a href="#" data-filter="image">Images</a>
                        <a href="#" data-filter="music">Music</a>
                        <a href="#" data-filter="video">Videos</a>
                        <a href="#" data-filter="code">Code Files</a>
                        <a href="#" data-filter="archive">Archives</a>
                        <a href="#" data-filter="installation">Installation Files</a>
                        <a href="#" data-filter="p2p">Peer-to-Peer Files</a>
                        <a href="#" data-filter="cad">CAD Files</a>
                    </div>
                </div>

                <!-- Release Date Filter Button -->
                <div class="dropdown-container release-filter-dropdown-container">
                    <button id="releaseFilterBtn" class="filter-button"><i class="fas fa-calendar-alt"></i></button>
                    <div class="dropdown-content release-filter-dropdown-content">
                        <a href="#" data-filter="newest">Newest Deleted</a>
                        <a href="#" data-filter="oldest">Oldest Deleted</a>
                        <a href="#" data-filter="all">All Dates</a>
                    </div>
                </div>

                <!-- Sort Order Filter Button -->
                <div class="dropdown-container sort-order-dropdown-container">
                    <button id="sortOrderBtn" class="filter-button"><i class="fas fa-sort-alpha-down"></i></button>
                    <div class="dropdown-content sort-order-dropdown-content">
                        <a href="#" data-sort="asc">A-Z</a>
                        <a href="#" data-sort="desc">Z-A</a>
                    </div>
                </div>

                <!-- View Toggle Buttons -->
                <div class="view-toggle">
                    <button id="listViewBtn" class="active"><i class="fas fa-list"></i></button>
                    <button id="gridViewBtn"><i class="fas fa-th-large"></i></button>
                </div>
            </div>
        </div>

        <!-- NEW: Filter buttons moved here for mobile/tablet -->
        <div class="toolbar-filter-buttons">
            <!-- File Type Filter Button -->
            <div class="dropdown-container file-type-filter-dropdown-container">
                <button id="fileTypeFilterBtnHeader" class="filter-button"><i class="fas fa-filter"></i></button>
                <div class="dropdown-content file-type-filter-dropdown-content">
                    <a href="#" data-filter="all">All Files</a>
                    <a href="#" data-filter="document">Documents</a>
                    <a href="#" data-filter="image">Images</a>
                    <a href="#" data-filter="music">Music</a>
                    <a href="#" data-filter="video">Videos</a>
                    <a href="#" data-filter="code">Code Files</a>
                    <a href="#" data-filter="archive">Archives</a>
                    <a href="#" data-filter="installation">Installation Files</a>
                    <a href="#" data-filter="p2p">Peer-to-Peer Files</a>
                    <a href="#" data-filter="cad">CAD Files</a>
                </div>
            </div>

            <!-- Release Date Filter Button -->
            <div class="dropdown-container release-filter-dropdown-container">
                <button id="releaseFilterBtnHeader" class="filter-button"><i class="fas fa-calendar-alt"></i></button>
                <div class="dropdown-content release-filter-dropdown-content">
                    <a href="#" data-filter="newest">Newest Deleted</a>
                    <a href="#" data-filter="oldest">Oldest Deleted</a>
                    <a href="#" data-filter="all">All Dates</a>
                </div>
            </div>

            <!-- Sort Order Filter Button -->
            <div class="dropdown-container sort-order-dropdown-container">
                <button id="sortOrderBtnHeader" class="filter-button"><i class="fas fa-sort-alpha-down"></i></button>
                <div class="dropdown-content sort-order-dropdown-content">
                    <a href="#" data-sort="asc">A-Z</a>
                    <a href="#" data-sort="desc">Z-A</a>
                </div>
            </div>

            <!-- View Toggle Buttons -->
            <div class="view-toggle">
                <button id="listViewBtnHeader" class="active"><i class="fas fa-list"></i></button>
                <button id="gridViewBtnHeader"><i class="fas fa-th-large"></i></button>
            </div>
        </div>

        <div class="breadcrumbs">
            <span><i class="fas fa-trash"></i> Recycle Bin</span>
            <?php if (!empty($searchQuery)): ?>
                <span>/</span> <span>Search results for "<?php echo htmlspecialchars($searchQuery); ?>"</span>
            <?php endif; ?>
        </div>

        <div class="file-list-container">
            <div id="fileListView" class="file-view">
                <table class="file-table">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="selectAllCheckbox"></th>
                            <th>Name</th>
                            <th>Type</th>
                            <th>Size</th>
                            <th>Deleted At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($deletedItems) && !empty($searchQuery)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 20px;">No deleted files or folders found matching "<?php echo htmlspecialchars($searchQuery); ?>"</td>
                            </tr>
                        <?php elseif (empty($deletedItems) && empty($searchQuery)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 20px;">Recycle Bin is empty.</td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($deletedItems as $item): ?>
                            <?php
                                $itemId = $item['id'];
                                $itemType = $item['item_type'];
                                $itemName = $itemType === 'file' ? $item['file_name'] : $item['folder_name'];
                                $itemSize = $itemType === 'file' ? formatBytes($item['file_size']) : 'Folder'; // Folder size not stored in deleted_folders
                                $itemDeletedAt = date('Y-m-d H:i', strtotime($item['deleted_at']));
                                $fileExt = $itemType === 'file' ? strtolower($item['file_type']) : 'folder';
                                $iconClass = $itemType === 'file' ? getFontAwesomeIconClass($itemName) : 'fa-folder';
                                $colorClass = $itemType === 'file' ? getFileColorClassPhp($itemName) : 'folder';
                            ?>
                            <tr class="file-item" data-id="<?php echo $itemId; ?>" data-type="<?php echo $itemType; ?>" data-name="<?php echo htmlspecialchars($itemName); ?>" data-file-type="<?php echo $fileExt; ?>" tabindex="0">
                                <td><input type="checkbox" class="file-checkbox" data-id="<?php echo $itemId; ?>" data-type="<?php echo $itemType; ?>"></td>
                                <td class="file-name-cell">
                                    <i class="fas <?php echo $iconClass; ?> file-icon <?php echo $colorClass; ?>"></i>
                                    <span><?php echo htmlspecialchars($itemName); ?></span>
                                </td>
                                <td><?php echo ucfirst($itemType); ?></td>
                                <td><?php echo $itemSize; ?></td>
                                <td><?php echo $itemDeletedAt; ?></td>
                                <td>
                                    <button class="item-more" aria-haspopup="true" aria-label="More">⋮</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div id="fileGridView" class="file-view hidden">
                <div class="file-grid">
                    <?php if (empty($deletedItems) && !empty($searchQuery)): ?>
                        <div style="grid-column: 1 / -1; text-align: center; padding: 20px;">No deleted files or folders found matching "<?php echo htmlspecialchars($searchQuery); ?>"</div>
                    <?php elseif (empty($deletedItems) && empty($searchQuery)): ?>
                        <div style="grid-column: 1 / -1; text-align: center; padding: 20px;">Recycle Bin is empty.</div>
                    <?php endif; ?>

                    <?php foreach ($deletedItems as $item): ?>
                        <?php
                            $itemId = $item['id'];
                            $itemType = $item['item_type'];
                            $itemName = $itemType === 'file' ? $item['file_name'] : $item['folder_name'];
                            $itemSize = $itemType === 'file' ? formatBytes($item['file_size']) : 'Folder';
                            $itemDeletedAt = date('Y-m-d H:i', strtotime($item['deleted_at']));
                            $fileExt = $itemType === 'file' ? strtolower($item['file_type']) : 'folder';
                            $iconClass = $itemType === 'file' ? getFontAwesomeIconClass($itemName) : 'fa-folder';
                            $colorClass = $itemType === 'file' ? getFileColorClassPhp($itemName) : 'folder';
                        ?>
                        <div class="grid-item file-item" data-id="<?php echo $itemId; ?>" data-type="<?php echo $itemType; ?>" data-name="<?php echo htmlspecialchars($itemName); ?>" data-file-type="<?php echo $fileExt; ?>" tabindex="0">
                            <input type="checkbox" class="file-checkbox" data-id="<?php echo $itemId; ?>" data-type="<?php echo $itemType; ?>">
                            <div class="grid-thumbnail">
                                <?php if ($itemType === 'file'): ?>
                                    <?php
                                    $filePath = htmlspecialchars($item['file_path']); // Note: physical file might be gone
                                    if (in_array($fileExt, $imageExt) && file_exists($filePath)):
                                    ?>
                                        <img src="<?php echo $filePath; ?>" alt="<?php echo $itemName; ?>">
                                    <?php elseif (in_array($fileExt, $docExt)):
                                        // Attempt to read content if file still exists
                                        $content = @file_get_contents($item['file_path'], false, null, 0, 500);
                                        if ($content !== false) {
                                            echo '<pre>' . htmlspecialchars(substr(strip_tags($content), 0, 200)) . '...</pre>';
                                        } else {
                                            echo '<i class="fas ' . $iconClass . ' file-icon ' . $colorClass . '"></i>';
                                        }
                                    ?>
                                    <?php elseif (in_array($fileExt, $musicExt) && file_exists($filePath)): ?>
                                        <audio controls style='width:100%; height: auto;'><source src='<?php echo $filePath; ?>' type='audio/<?php echo $fileExt; ?>'></audio>
                                    <?php elseif (in_array($fileExt, $videoExt) && file_exists($filePath)): ?>
                                        <video controls style='width:100%; height:100%;'><source src='<?php echo $filePath; ?>' type='video/<?php echo $fileExt; ?>'></video>
                                    <?php elseif (in_array($fileExt, $codeExt)):
                                        $code = @file_get_contents($item['file_path'], false, null, 0, 500);
                                        if ($code !== false) {
                                            echo '<pre>' . htmlspecialchars(substr($code, 0, 200)) . '...</pre>';
                                        } else {
                                            echo '<i class="fas ' . $iconClass . ' file-icon ' . $colorClass . '"></i>';
                                        }
                                    ?>
                                    <?php else: ?>
                                        <i class="fas <?php echo $iconClass; ?> file-icon <?php echo $colorClass; ?>"></i>
                                    <?php endif; ?>
                                <?php else: // Folder ?>
                                    <i class="fas <?php echo $iconClass; ?> file-icon <?php echo $colorClass; ?>"></i>
                                <?php endif; ?>
                                <span class="file-type-label"><?php echo ucfirst($fileExt); ?></span>
                            </div>
                            <span class="file-name"><?php echo htmlspecialchars($itemName); ?></span>
                            <span class="file-size"><?php echo $itemSize; ?></span>
                            <button class="item-more" aria-haspopup="true" aria-label="More">⋮</button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <div id="customNotification" class="notification"></div>

    <!-- Custom context menu (shared UI, populated by JS) -->
    <div id="context-menu" class="context-menu" hidden>
        <ul>
            <li data-action="restore"><i class="fas fa-undo"></i> Restore</li>
            <li class="separator"></li>
            <li data-action="delete-forever"><i class="fas fa-times-circle"></i> Delete Forever</li>
        </ul>
    </div>

    <!-- Overlay for mobile sidebar -->
    <div class="overlay" id="mobileOverlay"></div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const restoreSelectedBtn = document.getElementById('restoreSelectedBtn');
            const deleteForeverSelectedBtn = document.getElementById('deleteForeverSelectedBtn');
            const emptyRecycleBinBtn = document.getElementById('emptyRecycleBinBtn');
            
            // Dropdown elements (main toolbar)
            const releaseFilterDropdownContainer = document.querySelector('.toolbar .release-filter-dropdown-container');
            const releaseFilterBtn = document.getElementById('releaseFilterBtn');
            const releaseFilterDropdownContent = document.querySelector('.toolbar .release-filter-dropdown-content');

            const sortOrderDropdownContainer = document.querySelector('.toolbar .sort-order-dropdown-container');
            const sortOrderBtn = document.getElementById('sortOrderBtn');
            const sortOrderDropdownContent = document.querySelector('.toolbar .sort-order-dropdown-content');

            const fileTypeFilterDropdownContainer = document.querySelector('.toolbar .file-type-filter-dropdown-container');
            const fileTypeFilterBtn = document.getElementById('fileTypeFilterBtn');
            const fileTypeFilterDropdownContent = document.querySelector('.toolbar .file-type-filter-dropdown-content');

            // Dropdown elements (header)
            const fileTypeFilterBtnHeader = document.getElementById('fileTypeFilterBtnHeader');
            const releaseFilterBtnHeader = document.getElementById('releaseFilterBtnHeader');
            const sortOrderBtnHeader = document.getElementById('sortOrderBtnHeader');
            const listViewBtnHeader = document.getElementById('listViewBtnHeader');
            const gridViewBtnHeader = document.getElementById('gridViewBtnHeader');


            const listViewBtn = document.getElementById('listViewBtn');
            const gridViewBtn = document.getElementById('gridViewBtn');
            const fileListView = document.getElementById('fileListView');
            const fileGridView = document.getElementById('fileGridView');
            const selectAllCheckbox = document.getElementById('selectAllCheckbox');
            const searchInput = document.getElementById('searchInput'); // Desktop search
            const searchInputMobile = document.getElementById('searchInputMobile'); // Mobile search
            const customNotification = document.getElementById('customNotification');

            // Context Menu elements
            const contextMenu = document.getElementById('context-menu');
            const contextRestore = document.querySelector('#context-menu [data-action="restore"]');
            const contextDeleteForever = document.querySelector('#context-menu [data-action="delete-forever"]');

            // Mobile sidebar elements
            const sidebar = document.querySelector('.sidebar');
            const sidebarToggleBtn = document.getElementById('sidebarToggleBtn');
            const mobileOverlay = document.getElementById('mobileOverlay');

            // Sidebar menu items for active state management
            const sidebarMenuItems = document.querySelectorAll('.sidebar-menu a');

            // Current state variables for AJAX filtering/sorting
            let currentSearchQuery = <?php echo json_encode($searchQuery); ?>;
            let currentReleaseFilter = <?php echo json_encode($releaseFilter); ?>;
            let currentSortOrder = <?php echo json_encode($sortOrder); ?>;
            let currentFileTypeFilter = <?php echo json_encode($fileTypeFilter); ?>;

            /*** Util helpers ****/
            function debounce(fn, ms=150){ let t; return (...a)=>{ clearTimeout(t); t=setTimeout(()=>fn(...a), ms); }; }
            function closestFileItem(el){ return el && el.closest('.file-item'); }

            /*** Device detection & body class toggling ***/
            function setDeviceClass() {
                const ua = navigator.userAgent || '';
                const isIPad = /iPad/.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
                const w = window.innerWidth;
                document.body.classList.remove('mobile', 'tablet-portrait', 'tablet-landscape', 'desktop'); // Clear all
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
            }
            window.addEventListener('resize', debounce(setDeviceClass, 150));
            window.addEventListener('orientationchange', setDeviceClass); // Listen for orientation changes
            setDeviceClass(); // init

            // Function to get file icon class based on extension (for JS side, if needed for dynamic elements)
            function getFileIconClass(fileName) {
                const extension = fileName.split('.').pop().toLowerCase();
                switch (extension) {
                    case 'pdf': return 'fa-file-pdf';
                    case 'doc':
                    case 'docx': return 'fa-file-word';
                    case 'xls':
                    case 'xlsx': return 'fa-file-excel';
                    case 'ppt':
                    case 'pptx': return 'fa-file-powerpoint';
                    case 'jpg':
                    case 'jpeg':
                    case 'png':
                    case 'gif':
                    case 'bmp':
                    case 'webp': return 'fa-file-image';
                    case 'zip':
                    case 'rar':
                    case '7z': return 'fa-file-archive';
                    case 'txt':
                    case 'log':
                    case 'md': return 'fa-file-alt';
                    case 'exe':
                    case 'apk': return 'fa-box';
                    case 'mp3':
                    case 'wav':
                    case 'flac': return 'fa-file-audio';
                    case 'mp4':
                    case 'avi':
                    case 'mkv': return 'fa-file-video';
                    case 'html':
                    case 'htm': return 'fa-file-code';
                    case 'css': return 'fa-file-code';
                    case 'js': return 'fa-file-code';
                    case 'php': return 'fa-file-code';
                    case 'py': return 'fa-file-code';
                    case 'json': return 'fa-file-code';
                    case 'sql': return 'fa-database';
                    case 'svg': return 'fa-file-image';
                    case 'sh':
                    case 'bat': return 'fa-file-code';
                    case 'ini':
                    case 'yml':
                    case 'yaml': return 'fa-file-code';
                    case 'java': return 'fa-java';
                    case 'c':
                    case 'cpp': return 'fa-file-code';
                    case 'dwg':
                    case 'dxf':
                    case 'dgn':
                    case 'iges':
                    case 'igs':
                    case 'step':
                    case 'stp':
                    case 'stl':
                    case '3ds':
                    case 'obj':
                    case 'sldprt':
                    case 'sldasm':
                    case 'ipt':
                    case 'iam':
                    case 'catpart':
                    case 'catproduct':
                    case 'prt':
                    case 'asm':
                    case 'fcstd':
                    case 'skp':
                    case 'x_t':
                    case 'x_b': return 'fa-cube';
                    default: return 'fa-file';
                }
            }

            // Function to get file color class based on extension (for JS side, if needed for dynamic elements)
            function getFileColorClass(fileName) {
                const extension = fileName.split('.').pop().toLowerCase();
                const colorClasses = {
                    'pdf': 'file-color-pdf', 'doc': 'file-color-doc', 'docx': 'file-color-doc',
                    'xls': 'file-color-xls', 'xlsx': 'file-color-xls', 'ppt': 'file-color-ppt',
                    'pptx': 'file-color-ppt', 'txt': 'file-color-txt', 'rtf': 'file-color-txt',
                    'md': 'file-color-txt', 'csv': 'file-color-csv', 'odt': 'file-color-doc',
                    'odp': 'file-color-ppt', 'log': 'file-color-txt', 'tex': 'file-color-txt',
                    'jpg': 'file-color-image', 'jpeg': 'file-color-image', 'png': 'file-color-image',
                    'gif': 'file-color-image', 'bmp': 'file-color-image', 'webp': 'file-color-image',
                    'svg': 'file-color-image', 'tiff': 'file-color-image',
                    'mp3': 'file-color-audio', 'wav': 'file-color-audio', 'ogg': 'file-color-audio',
                    'flac': 'file-color-audio', 'aac': 'file-color-audio', 'm4a': 'file-color-audio',
                    'alac': 'file-color-audio', 'wma': 'file-color-audio', 'opus': 'file-color-audio',
                    'amr': 'file-color-audio', 'mid': 'file-color-audio',
                    'mp4': 'file-color-video', 'avi': 'file-color-video', 'mov': 'file-color-video',
                    'wmv': 'file-color-video', 'flv': 'file-color-video', 'webm': 'file-color-video',
                    '3gp': 'file-color-video', 'm4v': 'file-color-video', 'mpg': 'file-color-video',
                    'mpeg': 'file-color-video', 'ts': 'file-color-video', 'ogv': 'file-color-video',
                    'zip': 'file-color-archive', 'rar': 'file-color-archive', '7z': 'file-color-archive',
                    'tar': 'file-color-archive', 'gz': 'file-color-archive', 'bz2': 'file-color-archive',
                    'xz': 'file-color-archive', 'iso': 'file-color-archive', 'cab': 'file-color-archive',
                    'arj': 'file-color-archive',
                    'html': 'file-color-code', 'htm': 'file-color-code', 'css': 'file-color-code',
                    'js': 'file-color-code', 'php': 'file-color-code', 'py': 'file-color-code',
                    'java': 'file-color-code', 'json': 'file-color-code', 'xml': 'file-color-code',
                    'ts': 'file-color-code', 'tsx': 'file-color-code', 'jsx': 'file-color-code',
                    'vue': 'file-color-code', 'cpp': 'file-color-code', 'c': 'file-color-code',
                    'cs': 'file-color-code', 'rb': 'file-color-code', 'go': 'file-color-code',
                    'swift': 'file-color-code', 'sql': 'file-color-code', 'sh': 'file-color-code',
                    'bat': 'file-color-code', 'ini': 'file-color-code', 'yml': 'file-color-code',
                    'yaml': 'file-color-code', 'pl': 'file-color-code', 'r': 'file-color-code',
                    'exe': 'file-color-exe', 'msi': 'file-color-exe', 'apk': 'file-color-exe',
                    'ipa': 'file-color-exe', 'jar': 'file-color-exe', 'appimage': 'file-color-exe',
                    'dmg': 'file-color-exe', 'bin': 'file-color-exe',
                    'torrent': 'file-color-default', 'nzb': 'file-color-default', 'ed2k': 'file-color-default',
                    'part': 'file-color-default', '!ut': 'file-color-default',
                    'dwg': 'file-color-cad', 'dxf': 'file-color-cad', 'dgn': 'file-color-cad',
                    'iges': 'file-color-cad', 'igs': 'file-color-cad', 'step': 'file-color-cad',
                    'stp': 'file-color-cad', 'stl': 'file-color-cad', '3ds': 'file-color-cad',
                    'obj': 'file-color-cad', 'sldprt': 'file-color-cad', 'sldasm': 'file-color-cad',
                    'ipt': 'file-color-cad', 'iam': 'file-color-cad', 'catpart': 'file-color-cad',
                    'catproduct': 'file-color-cad', 'prt': 'file-color-cad', 'asm': 'file-color-cad',
                    'fcstd': 'file-color-cad', 'skp': 'file-color-cad', 'x_t': 'file-color-cad',
                    'x_b': 'file-color-cad',
                    'default': 'file-color-default'
                };
                return colorClasses[extension] || colorClasses['default'];
            }

            // Function to show custom notification
            function showNotification(message, type) {
                customNotification.innerHTML = message;
                customNotification.className = 'notification show ' + type;
                setTimeout(() => {
                    customNotification.classList.remove('show');
                }, 3000);
            }

            // --- View Toggle Logic ---
            function setupViewToggle(listViewBtnElement, gridViewBtnElement) {
                listViewBtnElement.addEventListener('click', () => {
                    listViewBtnElement.classList.add('active');
                    gridViewBtnElement.classList.remove('active');
                    fileListView.classList.remove('hidden');
                    fileGridView.classList.add('hidden');
                    localStorage.setItem('recycleBinView', 'list');
                });

                gridViewBtnElement.addEventListener('click', () => {
                    gridViewBtnElement.classList.add('active');
                    listViewBtnElement.classList.remove('active');
                    fileGridView.classList.remove('hidden');
                    fileListView.classList.add('hidden');
                    localStorage.setItem('recycleBinView', 'grid');
                });
            }

            setupViewToggle(listViewBtn, gridViewBtn); // For main toolbar
            setupViewToggle(listViewBtnHeader, gridViewBtnHeader); // For header toolbar

            const savedView = localStorage.getItem('recycleBinView');
            if (savedView === 'grid') {
                gridViewBtn.click();
                gridViewBtnHeader.click();
            } else {
                listViewBtn.click();
                listViewBtnHeader.click();
            }

            // --- Select All Checkbox Logic ---
            function updateSelectAllCheckboxListener() {
                const fileCheckboxes = document.querySelectorAll('.file-checkbox');
                selectAllCheckbox.checked = false;
                selectAllCheckbox.removeEventListener('change', handleSelectAllChange);
                selectAllCheckbox.addEventListener('change', handleSelectAllChange);

                fileCheckboxes.forEach(checkbox => {
                    checkbox.removeEventListener('change', handleIndividualCheckboxChange);
                    checkbox.addEventListener('change', handleIndividualCheckboxChange);
                });
            }

            function handleSelectAllChange() {
                const fileCheckboxes = document.querySelectorAll('.file-checkbox');
                fileCheckboxes.forEach(checkbox => {
                    checkbox.checked = this.checked;
                });
            }

            function handleIndividualCheckboxChange() {
                const fileCheckboxes = document.querySelectorAll('.file-checkbox');
                if (!this.checked) {
                    selectAllCheckbox.checked = false;
                } else {
                    const allChecked = Array.from(fileCheckboxes).every(cb => cb.checked);
                    selectAllCheckbox.checked = allChecked;
                }
            }

            updateSelectAllCheckboxListener();

            // --- Restore Selected Files/Folders ---
            restoreSelectedBtn.addEventListener('click', async () => {
                const checkboxes = document.querySelectorAll('.file-checkbox:checked');
                const selectedItems = Array.from(checkboxes).map(cb => {
                    return { id: cb.dataset.id, type: cb.dataset.type };
                });

                if (selectedItems.length === 0) {
                    showNotification('Please select at least one file or folder to restore!', 'error');
                    return;
                }

                if (!confirm('Are you sure you want to restore the selected items?')) {
                    return;
                }

                try {
                    const response = await fetch('actions/restore_items.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ items: selectedItems })
                    });
                    const data = await response.json();
                    if (data.success) {
                        showNotification(data.message, 'success');
                        updateRecycleBinContent(); // Update content without full reload
                    } else {
                        showNotification('Failed to restore items: ' + data.message, 'error');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    showNotification('An error occurred while restoring items.', 'error');
                }
            });

            // --- Delete Forever Selected Files/Folders ---
            deleteForeverSelectedBtn.addEventListener('click', async () => {
                const checkboxes = document.querySelectorAll('.file-checkbox:checked');
                const selectedItems = Array.from(checkboxes).map(cb => {
                    return { id: cb.dataset.id, type: cb.dataset.type };
                });

                if (selectedItems.length === 0) {
                    showNotification('Please select at least one file or folder to delete permanently!', 'error');
                    return;
                }

                if (!confirm('Are you sure you want to PERMANENTLY delete the selected items? This action cannot be undone!')) {
                    return;
                }

                try {
                    const response = await fetch('actions/delete_forever.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ items: selectedItems })
                    });
                    const data = await response.json();
                    if (data.success) {
                        showNotification(data.message, 'success');
                        updateRecycleBinContent(); // Update content without full reload
                    } else {
                        showNotification('Failed to delete items permanently: ' + data.message, 'error');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    showNotification('An error occurred while deleting items permanently.', 'error');
                }
            });

            // --- Empty Recycle Bin ---
            emptyRecycleBinBtn.addEventListener('click', async () => {
                if (!confirm('Are you sure you want to EMPTY the entire Recycle Bin? All items will be PERMANENTLY deleted and this action cannot be undone!')) {
                    return;
                }

                try {
                    const response = await fetch('actions/empty_recycle_bin.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ user_id: <?php echo $userId; ?> })
                    });
                    const data = await response.json();
                    if (data.success) {
                        showNotification(data.message, 'success');
                        updateRecycleBinContent(); // Update content without full reload
                    } else {
                        showNotification('Failed to empty Recycle Bin: ' + data.message, 'error');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    showNotification('An error occurred while emptying Recycle Bin.', 'error');
                }
            });

            // --- Search Functionality ---
            function performSearch(query) {
                currentSearchQuery = query.trim();
                updateRecycleBinContent();
            }

            searchInputMobile.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    performSearch(this.value);
                }
            });

            // --- File Type Filter ---
            function setupFileTypeFilterDropdown(buttonId, dropdownContentSelector) {
                const button = document.getElementById(buttonId);
                const dropdownContent = document.querySelector(dropdownContentSelector);
                const dropdownContainer = button.closest('.dropdown-container');

                if (!button || !dropdownContent || !dropdownContainer) return;

                button.addEventListener('click', (event) => {
                    event.stopPropagation();
                    dropdownContainer.classList.toggle('show');
                });

                dropdownContent.querySelectorAll('a').forEach(link => {
                    link.addEventListener('click', (event) => {
                        event.preventDefault();
                        dropdownContainer.classList.remove('show');
                        currentFileTypeFilter = event.target.dataset.filter;
                        updateRecycleBinContent();
                    });
                });
            }

            setupFileTypeFilterDropdown('fileTypeFilterBtn', '.toolbar .file-type-filter-dropdown-content');
            setupFileTypeFilterDropdown('fileTypeFilterBtnHeader', '.toolbar-filter-buttons .file-type-filter-dropdown-content');


            // --- Release Date Filter ---
            function setupReleaseFilterDropdown(buttonId, dropdownContentSelector) {
                const button = document.getElementById(buttonId);
                const dropdownContent = document.querySelector(dropdownContentSelector);
                const dropdownContainer = button.closest('.dropdown-container');

                if (!button || !dropdownContent || !dropdownContainer) return;

                button.addEventListener('click', (event) => {
                    event.stopPropagation();
                    dropdownContainer.classList.toggle('show');
                });

                dropdownContent.querySelectorAll('a').forEach(link => {
                    link.addEventListener('click', (event) => {
                        event.preventDefault();
                        dropdownContainer.classList.remove('show');
                        currentReleaseFilter = event.target.dataset.filter;
                        updateRecycleBinContent();
                    });
                });
            }

            setupReleaseFilterDropdown('releaseFilterBtn', '.toolbar .release-filter-dropdown-content');
            setupReleaseFilterDropdown('releaseFilterBtnHeader', '.toolbar-filter-buttons .release-filter-dropdown-content');


            // --- Sort Order Filter ---
            function setupSortOrderDropdown(buttonId, dropdownContentSelector) {
                const button = document.getElementById(buttonId);
                const dropdownContent = document.querySelector(dropdownContentSelector);
                const dropdownContainer = button.closest('.dropdown-container');

                if (!button || !dropdownContent || !dropdownContainer) return;

                button.addEventListener('click', (event) => {
                    event.stopPropagation();
                    dropdownContainer.classList.toggle('show');
                });

                dropdownContent.querySelectorAll('a').forEach(link => {
                    link.addEventListener('click', (event) => {
                        event.preventDefault();
                        dropdownContainer.classList.remove('show');
                        currentSortOrder = event.target.dataset.sort;
                        updateRecycleBinContent();
                    });
                });
            }

            setupSortOrderDropdown('sortOrderBtn', '.toolbar .sort-order-dropdown-content');
            setupSortOrderDropdown('sortOrderBtnHeader', '.toolbar-filter-buttons .sort-order-dropdown-content');


            /*** Context menu element ***/
            function showContextMenuFor(fileEl, x, y) {
                if (!fileEl) return;
                contextMenu.dataset.targetId = fileEl.dataset.id;
                contextMenu.dataset.targetType = fileEl.dataset.type;
                contextMenu.dataset.targetName = fileEl.dataset.name;
                contextMenu.dataset.targetFileType = fileEl.dataset.fileType || '';

                // Position - keep inside viewport
                const rect = contextMenu.getBoundingClientRect();
                const menuWidth = rect.width || 200;
                const menuHeight = rect.height || 100; // Adjusted for fewer options

                let finalLeft = x;
                let finalTop = y;

                if (x + menuWidth > window.innerWidth) {
                    finalLeft = window.innerWidth - menuWidth - 10;
                }
                if (y + menuHeight > window.innerHeight) {
                    finalTop = window.innerHeight - menuHeight - 10;
                }

                contextMenu.style.left = finalLeft + 'px';
                contextMenu.style.top = finalTop + 'px';
                contextMenu.classList.add('visible');
                contextMenu.hidden = false;
                suppressOpenClickTemporarily();
            }

            function hideContextMenu(){ 
                contextMenu.classList.remove('visible'); 
                contextMenu.hidden = true; 
                contextMenu.dataset.targetId = '';
                contextMenu.dataset.targetType = '';
                contextMenu.dataset.targetName = '';
                contextMenu.dataset.targetFileType = '';
            }

            let _suppressOpenUntil = 0;
            function suppressOpenClickTemporarily(ms=350){
                _suppressOpenUntil = Date.now() + ms;
            }

            // No direct "open" action for recycle bin items, only restore/delete forever
            function handleMenuAction(action, id, type){
                switch(action){
                    case 'restore': restoreItem(id, type); break;
                    case 'delete-forever': deleteItemForever(id, type); break;
                    default: console.log('Unknown action', action);
                }
            }

            // Delegated click for item-more button
            document.addEventListener('click', function(e){
                const moreBtn = e.target.closest('.item-more');
                if (moreBtn) {
                    const file = closestFileItem(moreBtn);
                    const r = moreBtn.getBoundingClientRect();
                    showContextMenuFor(file, r.right - 5, r.bottom + 5);
                    e.stopPropagation();
                    return;
                }
                hideContextMenu();
            });

            // Desktop right-click (contextmenu)
            document.addEventListener('contextmenu', function(e){
                if (! (document.body.classList.contains('desktop') || document.body.classList.contains('tablet-landscape')) ) return;
                const file = closestFileItem(e.target);
                if (file) {
                    e.preventDefault();
                    showContextMenuFor(file, e.clientX, e.clientY);
                } else {
                    hideContextMenu();
                }
            });

            // Long-press for touch devices
            let lpTimer = null;
            let lpStart = null;
            const longPressDuration = 600;
            const longPressMoveThreshold = 10;

            document.addEventListener('pointerdown', function(e){
                if (! (document.body.classList.contains('mobile') ||
                    document.body.classList.contains('tablet-portrait') ||
                    document.body.classList.contains('device-ipad')) ) return;

                const file = closestFileItem(e.target);
                if (!file) return;
                if (e.target.classList.contains('file-checkbox')) return;

                if (e.pointerType !== 'touch') return;

                const startX = e.clientX, startY = e.clientY;
                lpStart = file;
                lpTimer = setTimeout(()=> {
                    showContextMenuFor(file, startX, startY);
                    lpTimer = null;
                    suppressOpenClickTemporarily(); 
                }, longPressDuration);

                function onMove(ev){
                    if (Math.hypot(ev.clientX - startX, ev.clientY - startY) > longPressMoveThreshold) {
                        clearLongPress();
                    }
                }
                function clearLongPress(){
                    if (lpTimer) clearTimeout(lpTimer);
                    lpTimer = null;
                    lpStart = null;
                    file.removeEventListener('pointermove', onMove);
                    file.removeEventListener('pointerup', clearLongPress);
                    file.removeEventListener('pointercancel', clearLongPress);
                }
                file.addEventListener('pointermove', onMove);
                file.addEventListener('pointerup', clearLongPress);
                file.addEventListener('pointercancel', clearLongPress);
            });

            // Keyboard support: ContextMenu key / Shift+F10 opens menu for focused item
            document.addEventListener('keydown', function(e){
                const focused = document.activeElement && document.activeElement.closest && document.activeElement.closest('.file-item');
                if (!focused) return;
                if (e.key === 'ContextMenu' || (e.shiftKey && e.key === 'F10')) {
                    e.preventDefault();
                    const rect = focused.getBoundingClientRect();
                    showContextMenuFor(focused, rect.left + 8, rect.bottom + 8);
                }
            });

            // Click inside context menu => execute actions
            contextMenu.addEventListener('click', function(e){
                const li = e.target.closest('[data-action]');
                if (!li) return;
                const action = li.dataset.action;
                const targetId = contextMenu.dataset.targetId;
                const targetType = contextMenu.dataset.targetType;

                handleMenuAction(action, targetId, targetType);
                hideContextMenu();
            });

            // Hide menu on outside clicks/touch
            document.addEventListener('click', function(e){ 
                if (!e.target.closest('#context-menu') && !e.target.closest('.item-more')) {
                    hideContextMenu(); 
                }
                // Close all dropdowns if clicked outside
                if (releaseFilterDropdownContainer && !releaseFilterDropdownContainer.contains(e.target)) {
                    releaseFilterDropdownContainer.classList.remove('show');
                }
                if (sortOrderDropdownContainer && !sortOrderDropdownContainer.contains(e.target)) {
                    sortOrderDropdownContainer.classList.remove('show');
                }
                if (fileTypeFilterDropdownContainer && !fileTypeFilterDropdownContainer.contains(e.target)) {
                    fileTypeFilterDropdownContainer.classList.remove('show');
                }
                const headerFileTypeDropdownContainer = document.querySelector('.toolbar-filter-buttons .file-type-filter-dropdown-container');
                const headerReleaseDropdownContainer = document.querySelector('.toolbar-filter-buttons .release-filter-dropdown-container');
                const headerSortOrderDropdownContainer = document.querySelector('.toolbar-filter-buttons .sort-order-dropdown-container');
                if (headerFileTypeDropdownContainer && !headerFileTypeDropdownContainer.contains(e.target)) {
                    headerFileTypeDropdownContainer.classList.remove('show');
                }
                if (headerReleaseDropdownContainer && !headerReleaseDropdownContainer.contains(e.target)) {
                    headerReleaseDropdownContainer.classList.remove('show');
                }
                if (headerSortOrderDropdownContainer && !headerSortOrderDropdownContainer.contains(e.target)) {
                    headerSortOrderDropdownContainer.classList.remove('show');
                }
            });
            window.addEventListener('blur', hideContextMenu);

            // --- Individual Restore/Delete Forever ---
            async function restoreItem(id, type) {
                if (!confirm(`Are you sure you want to restore this ${type}?`)) {
                    return;
                }
                try {
                    const response = await fetch('actions/restore_items.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ items: [{ id: id, type: type }] })
                    });
                    const data = await response.json();
                    if (data.success) {
                        showNotification(data.message, 'success');
                        updateRecycleBinContent();
                    } else {
                        showNotification(data.message, 'error');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    showNotification('An error occurred while restoring the item.', 'error');
                }
            }

            async function deleteItemForever(id, type) {
                if (!confirm(`Are you sure you want to PERMANENTLY delete this ${type}? This action cannot be undone!`)) {
                    return;
                }
                try {
                    const response = await fetch('actions/delete_forever.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ items: [{ id: id, type: type }] })
                    });
                    const data = await response.json();
                    if (data.success) {
                        showNotification(data.message, 'success');
                        updateRecycleBinContent();
                    } else {
                        showNotification(data.message, 'error');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    showNotification('An error occurred while deleting the item permanently.', 'error');
                }
            }

            // --- AJAX Content Update Function ---
            async function updateRecycleBinContent() {
                const params = new URLSearchParams();
                if (currentSearchQuery) {
                    params.set('search', currentSearchQuery);
                }
                if (currentReleaseFilter && currentReleaseFilter !== 'newest') { // 'newest' is default for trash
                    params.set('release', currentReleaseFilter);
                }
                if (currentSortOrder && currentSortOrder !== 'asc') {
                    params.set('sort', currentSortOrder);
                }
                if (currentFileTypeFilter && currentFileTypeFilter !== 'all') {
                    params.set('file_type', currentFileTypeFilter);
                }

                const url = `recycle_bin.php?${params.toString()}&ajax=1`;

                try {
                    const response = await fetch(url);
                    const html = await response.text();

                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = html;

                    const newFileListView = tempDiv.querySelector('#fileListView table tbody').innerHTML;
                    const newFileGridView = tempDiv.querySelector('#fileGridView .file-grid').innerHTML;
                    const newStorageInfo = tempDiv.querySelector('.storage-info').innerHTML;
                    const newBreadcrumbs = tempDiv.querySelector('.breadcrumbs').innerHTML; // Update breadcrumbs too

                    document.querySelector('#fileListView table tbody').innerHTML = newFileListView;
                    document.querySelector('#fileGridView .file-grid').innerHTML = newFileGridView;
                    document.querySelector('.storage-info').innerHTML = newStorageInfo;
                    document.querySelector('.breadcrumbs').innerHTML = newBreadcrumbs;


                    updateSelectAllCheckboxListener();
                    history.pushState(null, '', `recycle_bin.php?${params.toString()}`);

                } catch (error) {
                    console.error('Error updating recycle bin content:', error);
                    showNotification('Failed to update recycle bin content. Please refresh the page.', 'error');
                }
            }

            // --- Mobile Sidebar Toggle ---
            sidebarToggleBtn.addEventListener('click', () => {
                sidebar.classList.toggle('show-mobile-sidebar');
                mobileOverlay.classList.toggle('show');
            });

            // Close sidebar when clicking overlay
            mobileOverlay.addEventListener('click', () => {
                sidebar.classList.remove('show-mobile-sidebar');
                mobileOverlay.classList.remove('show');
            });

            // Initial call to attach listeners
            updateSelectAllCheckboxListener();

            // Set active class for current page in sidebar
            const currentPage = window.location.pathname.split('/').pop();
            sidebarMenuItems.forEach(item => {
                item.classList.remove('active');
                const itemHref = item.getAttribute('href');
                if (itemHref === currentPage || (currentPage === 'recycle_bin.php' && itemHref === 'recycle_bin.php')) {
                    item.classList.add('active');
                }
            });
        });
    </script>
</body>
</html>
