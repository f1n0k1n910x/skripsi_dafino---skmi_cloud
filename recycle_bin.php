<?php
include 'config.php';
include 'functions.php'; // Include functions.php file

session_start();
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Define $currentUserRole from session
$currentUserRole = isset($_SESSION['role']) ? $_SESSION['role'] : 'guest'; // Default to 'guest' or 'user' if not set

// Current folder ID, default to NULL for root (not directly used in recycle bin, but kept for consistency)
$currentFolderId = isset($_GET['folder']) ? (int)$_GET['folder'] : NULL;

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
    
$userId = $_SESSION['user_id'];

// Get search query
$searchQuery = isset($_GET['search']) ? $_GET['search'] : '';

// Get sorting parameters
$sizeFilter = isset($_GET['size']) ? $_GET['size'] : 'none'; // 'none', 'largest', 'smallest'
$sortOrder = isset($_GET['sort']) ? $_GET['sort'] : 'asc'; // 'asc', 'desc' (for alphabetical if size filter is 'none')
$fileTypeFilter = isset($_GET['file_type']) ? $_GET['file_type'] : 'all'; // 'all', 'document', 'music', etc.

// Define file categories for filtering (same as index.php)
$docExt = ['doc','docx','pdf','ppt','pptx','xls','xlsx','txt','odt','odp','rtf','md','log','csv','tex'];
$musicExt = ['mp3','wav','aac','ogg','flac','m4a','alac','wma','opus','amr','mid'];
$videoExt = ['mp4','mkv','avi','mov','wmv','flv','webm','3gp','m4v','mpg','mpeg','ts','ogv'];
$imageExt = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg', 'tiff'];
$cadExt = ['dwg', 'dxf', 'dgn', 'iges', 'igs', 'step', 'stp', 'stl', '3ds', 'obj', 'sldprt', 'sldasm', 'ipt', 'iam', 'catpart', 'catproduct', 'prt', 'asm', 'fcstd', 'skp', 'x_t', 'x_b'];

// Restricted file types for admin/moderator only
$codeExt = ['html','htm','css','js','php','py','java','json','xml','ts','tsx','jsx','vue','cpp','c','cs','rb','go','swift','sql','sh','bat','ini','yml','yaml','md','pl','r'];
$archiveExt = ['zip','rar','7z','tar','gz','bz2','xz','iso','cab','arj'];
$instExt = ['exe','msi','apk','ipa','sh','bat','jar','appimage','dmg','bin'];
$ptpExt = ['torrent','nzb','ed2k','part','!ut'];

// Combine all restricted extensions for easier checking
$allRestrictedExt = array_merge($codeExt, $instExt, $ptpExt);

// Map filter types to actual extensions
$filterExtensions = [];
switch ($fileTypeFilter) {
    case 'document': $filterExtensions = $docExt; break;
    case 'music': $filterExtensions = $musicExt; break;
    case 'video': $filterExtensions = $videoExt; break;
    case 'image': $filterExtensions = $imageExt; break;
    case 'cad': $filterExtensions = $cadExt; break;
    // Only include restricted types if user is admin or moderator
    case 'code': 
        if ($currentUserRole === 'admin' || $currentUserRole === 'moderator') {
            $filterExtensions = $codeExt;
        }
        break;
    case 'archive': 
        if ($currentUserRole === 'admin' || $currentUserRole === 'moderator') {
            $filterExtensions = $archiveExt;
        }
        break;
    case 'installation': 
        if ($currentUserRole === 'admin' || $currentUserRole === 'moderator') {
            $filterExtensions = $instExt;
        }
        break;
    case 'p2p': 
        if ($currentUserRole === 'admin' || $currentUserRole === 'moderator') {
            $filterExtensions = $ptpExt;
        }
        break;
    case 'all': default: $filterExtensions = []; break; // No specific filter
}

// Fetch deleted files and folders for the current user
$deletedItems = [];

// SQL for deleted files
// MODIFIED: Removed user_id filter to show all deleted files
$sqlFiles = "SELECT id, file_name, file_path, file_size, file_type, deleted_at FROM deleted_files WHERE 1=1";
$paramsFiles = [];
$typesFiles = "";

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

// Apply sorting based on size or alphabetical
if ($sizeFilter === 'largest') {
    $sqlFiles .= " ORDER BY file_size DESC";
} elseif ($sizeFilter === 'smallest') {
    $sqlFiles .= " ORDER BY file_size ASC";
} else {
    // Apply alphabetical sorting if no size filter or 'none'
    if ($sortOrder === 'asc') {
        $sqlFiles .= " ORDER BY file_name ASC";
    } else {
        $sqlFiles .= " ORDER BY file_name DESC";
    }
}

$stmtFiles = $conn->prepare($sqlFiles);
if (!empty($paramsFiles)) {
    $stmtFiles->bind_param($typesFiles, ...$paramsFiles);
}
$stmtFiles->execute();
$resultFiles = $stmtFiles->get_result();
while ($row = $resultFiles->fetch_assoc()) {
    $fileExtension = strtolower($row['file_type']);
    $isRestricted = in_array($fileExtension, $allRestrictedExt);
    
    // Only add to deletedItems if not restricted OR if user is admin/moderator
    if (!$isRestricted || ($currentUserRole === 'admin' || $currentUserRole === 'moderator')) {
        $row['item_type'] = 'file';
        $row['is_restricted'] = $isRestricted; // Add flag for JS to handle
        $deletedItems[] = $row;
    }
}
$stmtFiles->close();

// SQL for deleted folders (folders don't have size, so only alphabetical sorting applies)
// MODIFIED: Removed user_id filter to show all deleted folders
$sqlFolders = "SELECT id, folder_name, deleted_at FROM deleted_folders WHERE 1=1";
$paramsFolders = [];
$typesFolders = "";

if (!empty($searchQuery)) {
    $sqlFolders .= " AND folder_name LIKE ?";
    $searchTerm = '%' . $searchQuery . '%';
    $paramsFolders[] = $searchTerm;
    $typesFolders .= "s";
}

// Apply alphabetical sorting for folders
if ($sortOrder === 'asc') {
    $sqlFolders .= " ORDER BY folder_name ASC";
} else {
    $sqlFolders .= " ORDER BY folder_name DESC";
}

$stmtFolders = $conn->prepare($sqlFolders);
if (!empty($paramsFolders)) {
    $stmtFolders->bind_param($typesFolders, ...$paramsFolders);
}
$stmtFolders->execute();
$resultFolders = $stmtFolders->get_result();
while ($row = $resultFolders->fetch_assoc()) {
    $row['item_type'] = 'folder';
    $row['is_restricted'] = false; // Folders are not restricted by type
    $deletedItems[] = $row;
}
$stmtFolders->close();

// Final sorting of all deleted items (files and folders)
if ($sizeFilter === 'largest') {
    usort($deletedItems, function($a, $b) {
        $sizeA = $a['item_type'] === 'file' ? $a['file_size'] : 0; // Folders treated as 0 size for sorting
        $sizeB = $b['item_type'] === 'file' ? $b['file_size'] : 0;
        return $sizeB - $sizeA;
    });
} elseif ($sizeFilter === 'smallest') {
    usort($deletedItems, function($a, $b) {
        $sizeA = $a['item_type'] === 'file' ? $a['file_size'] : 0;
        $sizeB = $b['item_type'] === 'file' ? $b['file_size'] : 0;
        return $sizeA - $sizeB;
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
        /* Material Design Google + Admin LTE */
        :root {
            --primary-color: #3F51B5; /* Indigo 500 - Material Design */
            --primary-dark-color: #303F9F; /* Indigo 700 */
            --accent-color: #FF4081; /* Pink A200 */
            --text-color: #212121; /* Grey 900 */
            --secondary-text-color: #757575; /* Grey 600 */
            --divider-color: #BDBDBD; /* Grey 400 */
            --background-color: #F5F5F5; /* Grey 100 */
            --surface-color: #FFFFFF; /* White */
            --success-color: #4CAF50; /* Green 500 */
            --error-color: #F44336; /* Red 500 */
            --warning-color: #FFC107; /* Amber 500 */

            /* AdminLTE specific colors */
            --adminlte-sidebar-bg: #222d32;
            --adminlte-sidebar-text: #b8c7ce;
            --adminlte-sidebar-hover-bg: #1e282c;
            --adminlte-sidebar-active-bg: #1e282c;
            --adminlte-sidebar-active-text: #ffffff;
            --adminlte-header-bg: #ffffff;
            --adminlte-header-text: #333333;

            /* --- LOKASI EDIT UKURAN FONT SIDEBAR --- */
            --sidebar-font-size-desktop: 0.9em; /* Ukuran font default untuk desktop */
            --sidebar-font-size-tablet-landscape: 1.0em; /* Ukuran font untuk tablet landscape */
            --sidebar-font-size-tablet-portrait: 0.95em; /* Ukuran font untuk tablet portrait */
            --sidebar-font-size-mobile: 0.9em; /* Ukuran font untuk mobile */
            /* --- AKHIR LOKASI EDIT UKURAN FONT SIDEBAR --- */
        }

        body {
            font-family: 'Roboto', sans-serif; /* Material Design font */
            margin: 0;
            display: flex;
            height: 100vh;
            background-color: var(--background-color);
            color: var(--text-color);
            overflow: hidden; /* Prevent body scroll, main-content handles it */
        }

        /* Base Sidebar (AdminLTE style) */
        .sidebar {
            width: 250px;
            background-color: var(--adminlte-sidebar-bg);
            color: var(--adminlte-sidebar-text);
            display: flex;
            flex-direction: column;
            padding: 0; /* No padding at top/bottom */
            transition: width 0.3s ease-in-out, transform 0.3s ease-in-out;
            flex-shrink: 0;
            box-shadow: none; /* No box-shadow */
        }

        .sidebar-header {
            padding: 15px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .sidebar-header img {
            width: 120px; /* Slightly smaller logo */
            height: auto;
            display: block;
        }

        .sidebar-header h2 {
            margin: 0;
            font-size: 1.5em;
            color: var(--adminlte-sidebar-text);
            font-weight: 400;
        }

        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 0;
            flex-grow: 1;
            overflow-y: auto;
            overflow-x: hidden;
        }

        .sidebar-menu li {
            margin-bottom: 0; /* No extra spacing */
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 12px 15px; /* AdminLTE padding */
            color: var(--adminlte-sidebar-text);
            text-decoration: none;
            font-size: var(--sidebar-font-size-desktop);
            transition: background-color 0.2s ease-out, color 0.2s ease-out;
            border-left: 3px solid transparent; /* For active state */
        }

        .sidebar-menu a i {
            margin-right: 10px;
            font-size: 1.2em;
            width: 20px;
            text-align: center;
        }

        .sidebar-menu a:hover {
            background-color: var(--adminlte-sidebar-hover-bg);
            color: var(--adminlte-sidebar-active-text);
            transform: translateX(0); /* No slide effect */
        }

        .sidebar-menu a.active {
            background-color: var(--adminlte-sidebar-active-bg);
            border-left-color: var(--primary-color); /* Material primary color for active */
            color: var(--adminlte-sidebar-active-text);
            font-weight: 500;
        }

        /* Storage Info (AdminLTE style) */
        .storage-info {
            padding: 15px;
            border-top: 1px solid rgba(255,255,255,0.1);
            text-align: center;
            font-size: 0.85em;
            margin-top: auto;
            padding-top: 15px;
        }

        .storage-info h4 {
            margin-top: 0;
            margin-bottom: 10px;
            color: var(--adminlte-sidebar-text);
            font-weight: 400;
        }

        .progress-bar-container {
            width: 100%;
            background-color: rgba(255,255,255,0.2);
            border-radius: 0; /* Siku-siku */
            height: 6px;
            margin-bottom: 8px;
            overflow: hidden;
            position: relative;
        }

        .progress-bar {
            height: 100%;
            background-color: var(--success-color);
            border-radius: 0; /* Siku-siku */
            transition: width 0.5s ease-in-out;
            position: relative;
            overflow: hidden;
        }

        .progress-bar-text {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: #fff;
            font-size: 0.6em;
            font-weight: bold;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
            white-space: nowrap;
        }

        .storage-text {
            font-size: 0.8em;
            color: var(--adminlte-sidebar-text);
        }

        /* Main Content (Full-width, unique & professional) */
        .main-content {
            flex-grow: 1;
            padding: 20px; /* Reduced padding */
            display: flex;
            flex-direction: column;
            overflow-y: auto;
            background-color: var(--background-color); /* Light grey background */
            border-radius: 0; /* Siku-siku */
            margin: 0; /* Full width */
            box-shadow: none; /* No box-shadow */
            /* MODIFIED: Initial state for fly-in animation */
            opacity: 0;
            transform: translateY(100%);
            animation: flyInFromBottom 0.5s ease-out forwards; /* Fly In animation from bottom */
        }

        .main-content.fly-out {
            animation: flyOutToTop 0.5s ease-in forwards; /* Fly Out animation to top */
        }

        /* Header Main (Full-width, white, no background residue) */
        .header-main {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px; /* Reduced margin */
            padding: 15px 20px; /* Padding for header */
            border-bottom: 1px solid var(--divider-color);
            background-color: var(--adminlte-header-bg); /* White header */
            margin: -20px -20px 20px -20px; /* Adjust margin to cover full width */
            border-radius: 0; /* Siku-siku */
            box-shadow: none; /* No box-shadow */
        }

        .header-main h1 {
            margin: 0;
            color: var(--adminlte-header-text);
            font-size: 2em; /* Slightly smaller title */
            font-weight: 400; /* Lighter font weight */
        }

        .search-bar {
            display: flex;
            align-items: center;
            background-color: var(--background-color); /* Light grey for search bar */
            border-radius: 0; /* Siku-siku */
            padding: 8px 12px;
            box-shadow: none; /* No box-shadow */
            border: 1px solid var(--divider-color); /* Subtle border */
            transition: border-color 0.2s ease-out;
        }

        .search-bar:focus-within {
            background-color: var(--surface-color);
            border-color: var(--primary-color); /* Material primary color on focus */
            box-shadow: none; /* No box-shadow */
        }

        .search-bar input {
            border: none;
            outline: none;
            padding: 5px;
            font-size: 0.95em;
            width: 250px; /* Slightly narrower */
            background: transparent;
            color: var(--text-color);
        }

        .search-bar i {
            color: var(--secondary-text-color);
            margin-right: 10px;
        }

        /* Toolbar */
        .toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px; /* Reduced margin */
            padding-bottom: 10px;
            border-bottom: 1px solid var(--divider-color);
        }

        .toolbar-left, .toolbar-right {
            display: flex;
            gap: 8px; /* Reduced gap */
        }

        .toolbar-left button, .toolbar-right button {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 9px 18px; /* Reduced padding */
            border-radius: 0; /* Siku-siku */
            cursor: pointer;
            font-size: 0.9em;
            transition: background-color 0.2s ease-out, transform 0.1s ease-in-out;
            box-shadow: none; /* No box-shadow */
            white-space: nowrap;
        }

        .toolbar-left button:hover, .toolbar-right button:hover {
            background-color: var(--primary-dark-color);
            transform: translateY(0); /* No lift */
        }

        .toolbar-left button:active, .toolbar-right button:active {
            transform: translateY(0);
            box-shadow: none; /* No box-shadow */
        }

        .toolbar-left button i {
            margin-right: 6px; /* Reduced margin */
        }

        /* Archive button specific style */
        #archiveSelectedBtn {
            background-color: var(--warning-color); /* Amber for archive */
        }
        #archiveSelectedBtn:hover {
            background-color: #FFB300; /* Darker amber on hover */
        }

        .view-toggle button {
            background-color: var(--background-color);
            border: 1px solid var(--divider-color);
            padding: 7px 10px; /* Reduced padding */
            border-radius: 0; /* Siku-siku */
            cursor: pointer;
            font-size: 1em;
            color: var(--secondary-text-color);
            transition: background-color 0.2s ease-out, color 0.2s ease-out, border-color 0.2s ease-out;
        }

        .view-toggle button.active {
            background-color: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        /* Breadcrumbs (Material Design style) */
        .breadcrumbs {
            margin-bottom: 15px;
            font-size: 0.9em;
            color: var(--secondary-text-color);
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            padding: 8px 0;
        }

        .breadcrumbs a {
            color: var(--primary-color);
            text-decoration: none;
            margin-right: 5px;
            transition: color 0.2s ease-out;
        }

        .breadcrumbs a:hover {
            text-decoration: underline;
            color: var(--primary-dark-color);
        }

        .breadcrumbs span {
            margin-right: 5px;
            color: var(--divider-color);
        }

        /* File and Folder Display */
        .file-list-container {
            flex-grow: 1;
            background-color: var(--surface-color);
            border-radius: 0; /* Siku-siku */
            box-shadow: none; /* No box-shadow */
            padding: 0;
            overflow: auto;
            -webkit-overflow-scrolling: touch;
            touch-action: pan-y;
            border: 1px solid var(--divider-color); /* Subtle border for container */
        }

        /* List View (Google Drive Style) */
        .file-table {
            width: 100%;
            border-collapse: collapse; /* Kunci utama */
            table-layout: fixed; /* Kunci utama */
            margin-top: 0;
            border: none; /* Remove outer border, container has it */
        }

        .file-table th, .file-table td {
            border-bottom: 1px solid #dadce0; /* Google Drive border color */
            border-top: none;
            border-left: none;
            border-right: none;
            padding: 12px 24px;
            vertical-align: middle; /* Pastikan konten vertikal rata tengah */
            font-size: 0.875em;
            color: #3c4043; /* Google Drive text color */
        }

        .file-table th {
            background-color: #f8f9fa; /* Google Drive header background */
            color: #5f6368; /* Google Drive header text */
            font-weight: 500;
            text-transform: none;
            position: sticky;
            top: 0;
            z-index: 1;
            text-align: left; /* Biar header selalu rata */
        }

        .file-table tbody tr:last-child td {
            border-bottom: none;
        }

        .file-table tbody tr:hover {
            background-color: #f0f0f0; /* Google Drive hover effect */
        }

        /* Hilangkan efek patah di icon atau checkbox */
        .file-table td:first-child,
        .file-table th:first-child {
            width: 40px; /* Lebar tetap untuk checkbox/icon */
            text-align: center;
        }

        .file-icon {
            margin-right: 16px; /* Google Drive spacing */
            font-size: 1.2em;
            width: auto;
            text-align: center;
            flex-shrink: 0;
        }

        .file-icon.folder { color: #fbc02d; } /* Google Drive folder color */
        /* Other file icon colors are handled by internal.css */

        .file-name-cell {
            display: flex;
            align-items: center;
            max-width: 400px; /* Adjusted max-width */
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .file-name-cell a {
            color: #3c4043; /* Google Drive text color */
            text-decoration: none;
            font-weight: 400;
            display: block;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            transition: color 0.2s ease-out;
        }

        .file-name-cell a:hover {
            color: #1a73e8; /* Google Drive blue on hover */
        }

        /* Context Menu Styles (Material Design) */
        .context-menu {
            position: fixed;
            z-index: 12000;
            background: var(--surface-color);
            border: 1px solid var(--divider-color);
            box-shadow: 0px 2px 4px rgba(0,0,0,0.2); /* Subtle shadow */
            border-radius: 0; /* Siku-siku */
            overflow: hidden;
            min-width: 180px;
            display: none;
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
            color: var(--text-color);
            padding: 10px 15px;
            text-decoration: none;
            display: flex;
            align-items: center;
            font-size: 0.9em;
            transition: background-color 0.2s ease-out, color 0.2s ease-out;
            cursor: pointer;
            border-bottom: none !important; /* No border-bottom */
        }

        .context-menu li i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }

        .context-menu li:hover {
            background-color: var(--primary-color);
            color: #FFFFFF;
        }

        .context-menu .separator {
            height: 1px;
            background-color: var(--divider-color);
            margin: 5px 0;
        }

        /* Grid View (Google Drive Style) */
        .file-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 16px; /* Reduced gap */
            padding: 16px; /* Padding for the grid container */
        }

        .grid-item {
            background-color: var(--surface-color);
            border: 1px solid #dadce0; /* Google Drive border color */
            border-radius: 8px; /* Rounded corners */
            padding: 12px;
            display: flex;
            flex-direction: column;
            align-items: flex-start; /* Align content to start */
            text-align: left; /* Align text to left */
            box-shadow: none; /* No box-shadow */
            transition: all 0.2s ease-out;
            position: relative;
            overflow: hidden;
            user-select: none;
            cursor: pointer;
        }

        .grid-item:hover {
            box-shadow: 0 1px 3px rgba(60,64,67,.3), 0 4px 8px rgba(60,64,67,.15); /* Google Drive hover shadow */
            transform: translateY(0); /* No lift */
            border-color: transparent; /* Border disappears on hover */
        }

        .grid-item .file-checkbox {
            position: absolute;
            top: 8px;
            left: 8px;
            z-index: 2;
            transform: scale(1.0);
            accent-color: #1a73e8;
        }

        .grid-item .item-more {
            position: absolute;
            top: 8px;
            right: 8px;
            z-index: 2;
            background-color: rgba(255,255,255,0.8);
            border-radius: 50%;
            padding: 4px;
            font-size: 1em;
            color: #5f6368;
            opacity: 0; /* Hidden by default */
            transition: opacity 0.2s ease-out;
        }

        .grid-item:hover .item-more {
            opacity: 1; /* Show on hover */
        }

        .grid-thumbnail {
            width: 100%;
            height: 120px;
            margin-bottom: 8px;
            border: none;
            background-color: #e8f0fe; /* Light blue background for folders/generic files */
            border-radius: 4px;
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: hidden;
            position: relative;
            font-size: 12px;
            color: var(--text-color);
            text-align: left;
            padding: 5px;
            box-sizing: border-box;
        }

        .grid-thumbnail i {
            font-size: 3.5em;
            color: #1a73e8; /* Google Drive blue for icons */
        }

        .grid-thumbnail img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        .grid-thumbnail pre {
            font-size: 9px;
            white-space: pre-wrap;
            word-break: break-all;
            margin: 0;
            padding: 0;
            max-height: 100%;
            overflow: hidden;
        }

        .grid-thumbnail .file-type-label {
            display: none; /* Hide file type label in grid thumbnail */
        }

        .file-name {
            font-weight: 400;
            color: #3c4043;
            text-decoration: none;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 100%;
            display: block;
            margin-top: 0;
            padding-left: 4px;
            padding-right: 4px;
            font-size: 0.9375em;
            transition: color 0.2s ease-out;
        }
        
        .file-name:hover {
            color: #1a73e8;
        }

        .file-size {
            font-size: 0.8125em;
            color: #5f6368;
            margin-top: 4px;
            padding-left: 4px;
        }

        .action-buttons {
            display: flex;
            justify-content: center;
            margin-top: 10px;
            gap: 6px;
            width: 100%;
        }

        .action-buttons button {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 5px 9px;
            border-radius: 0; /* Siku-siku */
            cursor: pointer;
            font-size: 0.75em;
            transition: background-color 0.2s ease-out;
            box-shadow: none; /* No box-shadow */
        }

        .action-buttons button:hover {
            background-color: var(--primary-dark-color);
        }
        .action-buttons button.delete-button {
            background-color: var(--error-color);
        }
        .action-buttons button.delete-button:hover {
            background-color: #D32F2F;
        }
        .action-buttons button.extract-button:hover {
            background-color: #FF8F00; /* Darker amber on hover */
            color: #FFFFFF;
        }

        /* Modal Styles (Material Design) */
        .modal {
            display: flex;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.6);
            justify-content: center;
            align-items: center;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease-out, visibility 0.3s ease-out;
        }

        .modal.show {
            opacity: 1;
            visibility: visible;
        }

        .modal-content {
            background-color: var(--surface-color);
            padding: 30px;
            border-radius: 0; /* Siku-siku */
            box-shadow: 0 8px 17px 2px rgba(0,0,0,0.14), 0 3px 14px 2px rgba(0,0,0,0.12), 0 5px 5px -3px rgba(0,0,0,0.2); /* Material Design shadow */
            width: 90%;
            max-width: 550px;
            position: relative;
            transform: translateY(-20px);
            opacity: 0;
            transition: transform 0.3s ease-out, opacity 0.3s ease-out;
        }

        .modal.show .modal-content {
            transform: translateY(0);
            opacity: 1;
        }

        .close-button {
            color: var(--secondary-text-color);
            position: absolute;
            top: 15px;
            right: 20px;
            font-size: 28px; /* Slightly smaller */
            font-weight: normal;
            cursor: pointer;
            transition: color 0.2s ease-out;
        }

        .close-button:hover, .close-button:focus {
            color: var(--error-color);
        }

        .modal h2 {
            margin-top: 0;
            margin-bottom: 20px; /* Reduced margin */
            color: var(--text-color);
            font-size: 1.8em; /* Slightly smaller */
            font-weight: 400;
            border-bottom: 1px solid var(--divider-color);
            padding-bottom: 15px;
        }

        .modal label {
            display: block;
            margin-bottom: 8px; /* Reduced margin */
            font-weight: 500;
            color: var(--text-color);
            font-size: 1em;
        }

        .modal input[type="text"], .modal input[type="file"] {
            width: calc(100% - 24px); /* Adjust for padding and border */
            padding: 10px; /* Reduced padding */
            margin-bottom: 15px; /* Reduced margin */
            border: 1px solid var(--divider-color);
            border-radius: 0; /* Siku-siku */
            font-size: 0.95em;
            color: var(--text-color);
            background-color: var(--background-color);
            transition: border-color 0.2s ease-out, box-shadow 0.2s ease-out;
        }
        
        .modal input[type="text"]:focus, .modal input[type="file"]:focus {
            border-color: var(--primary-color);
            box-shadow: none; /* No box-shadow */
            outline: none;
            background-color: var(--surface-color);
        }

        .modal button[type="submit"] {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 10px 20px; /* Reduced padding */
            border-radius: 0; /* Siku-siku */
            cursor: pointer;
            font-size: 1em;
            transition: background-color 0.2s ease-out;
            box-shadow: none; /* No box-shadow */
        }

        .modal button[type="submit"]:hover {
            background-color: var(--primary-dark-color);
        }

        .hidden {
            display: none !important;
        }

        /* Custom Notification Styles (Material Design) */
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 12px 20px; /* Reduced padding */
            border-radius: 0; /* Siku-siku */
            color: white;
            font-weight: 500;
            z-index: 1001;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2); /* Subtle shadow */
            opacity: 0;
            transform: translateY(-20px);
            transition: opacity 0.3s ease-out, transform 0.3s ease-out;
        }

        .notification.show {
            opacity: 1;
            transform: translateY(0);
        }

        .notification.success {
            background-color: var(--success-color);
        }

        .notification.error {
            background-color: var(--error-color);
        }

        .notification.info {
            background-color: var(--primary-color);
        }

        /* Upload Preview Modal Specific Styles */
        #uploadPreviewModal .modal-content {
            max-width: 600px; /* Slightly smaller */
            padding: 20px;
        }

        #uploadPreviewModal .modal-header {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            border-bottom: 1px solid var(--divider-color);
            padding-bottom: 10px;
        }

        #uploadPreviewModal .modal-header h2 {
            flex-grow: 1;
            margin: 0;
            font-size: 1.8em;
            font-weight: 400;
            border-bottom: none;
            padding-bottom: 0;
        }

        #uploadPreviewModal .modal-header .back-button {
            background: none;
            border: none;
            font-size: 1.5em;
            cursor: pointer;
            margin-right: 10px;
            color: var(--secondary-text-color);
            transition: color 0.2s ease-out;
        }
        #uploadPreviewModal .modal-header .back-button:hover {
            color: var(--primary-color);
        }

        #uploadPreviewList {
            max-height: 400px; /* Reduced height */
            overflow-y: auto;
            margin-bottom: 15px;
            padding-right: 8px;
        }

        .upload-item {
            display: flex;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid var(--divider-color);
            transition: background-color 0.2s ease-out;
        }

        .upload-item:last-child {
            border-bottom: none;
        }
        .upload-item:hover {
            background-color: var(--background-color);
        }

        .upload-item .file-icon {
            font-size: 2.2em; /* Reduced icon size */
            margin-right: 15px;
            flex-shrink: 0;
            width: 40px;
            text-align: center;
        }

        .upload-item-info {
            flex-grow: 1;
        }

        .upload-item-info strong {
            display: block;
            font-weight: 500;
            color: var(--text-color);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 100%;
            font-size: 1em;
        }

        .upload-progress-container {
            width: 100%;
            background-color: var(--divider-color);
            border-radius: 0; /* Siku-siku */
            height: 6px;
            margin-top: 6px;
            overflow: hidden;
            position: relative;
        }

        .upload-progress-bar {
            height: 100%;
            background-color: var(--success-color);
            border-radius: 0; /* Siku-siku */
            width: 0%;
            transition: width 0.3s ease-out;
        }

        .upload-status-icon {
            font-size: 1.4em; /* Reduced status icon size */
            margin-left: 15px;
            flex-shrink: 0;
            width: 25px;
            text-align: center;
        }

        .upload-status-icon.processing { color: var(--primary-color); }
        .upload-status-icon.success { color: var(--success-color); }
        .upload-status-icon.error { color: var(--error-color); }
        .upload-status-icon.cancelled { color: var(--warning-color); }
        
        .upload-action-button {
            background: none;
            border: none;
            font-size: 1.2em; /* Reduced action button size */
            cursor: pointer;
            color: var(--secondary-text-color);
            margin-left: 10px;
            transition: color 0.2s ease-out;
        }

        .upload-action-button:hover {
            color: var(--error-color);
        }

        .upload-item.complete .upload-action-button {
            display: none;
        }

        /* Styles for the dropdown containers (Material Design) */
        .dropdown-container {
            position: relative;
            display: inline-block;
        }

        .dropdown-content {
            display: none;
            position: absolute;
            background-color: var(--surface-color);
            min-width: 180px;
            box-shadow: 0px 2px 4px rgba(0,0,0,0.2); /* Subtle shadow */
            z-index: 10;
            border-radius: 0; /* Siku-siku */
            margin-top: 8px;
            animation: fadeInScale 0.2s ease-out forwards;
            transform-origin: top left;
        }

        .dropdown-content a {
            color: var(--text-color);
            padding: 10px 15px;
            text-decoration: none;
            display: block;
            font-size: 0.9em;
            transition: background-color 0.2s ease-out, color 0.2s ease-out;
            border-bottom: none !important; /* No border-bottom */
        }

        .dropdown-content a:hover {
            background-color: var(--primary-color);
            color: #FFFFFF;
        }

        .dropdown-container.show .dropdown-content {
            display: block;
        }

        /* Style for filter buttons (icons only) */
        .filter-button {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 9px 11px; /* Adjusted padding */
            border-radius: 0; /* Siku-siku */
            cursor: pointer;
            font-size: 1.1em;
            transition: background-color 0.2s ease-out;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: none; /* No box-shadow */
        }

        .filter-button:hover {
            background-color: var(--primary-dark-color);
        }

        /* Share Link Modal */
        #shareLinkModal .modal-content {
            max-width: 450px;
        }
        #shareLinkModal input[type="text"] {
            width: calc(100% - 110px); /* Adjust width for copy button */
            margin-right: 8px;
            display: inline-block;
            vertical-align: middle;
            background-color: var(--background-color);
            border: 1px solid var(--divider-color);
            cursor: text;
        }
        #shareLinkModal button {
            display: inline-block;
            vertical-align: middle;
            padding: 9px 16px;
            font-size: 0.9em;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 0; /* Siku-siku */
            cursor: pointer;
            transition: background-color 0.2s ease-out;
        }
        #shareLinkModal button:hover {
            background-color: var(--primary-dark-color);
        }
        #shareLinkModal .share-link-container {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }
        #shareLinkModal .share-link-container button {
            margin-left: 0;
        }
        #shareLinkModal p.small-text {
            font-size: 0.8em;
            color: var(--secondary-text-color);
            margin-top: 8px;
        }

        /* Animations */
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

        /* Fly In/Out Animations for main-content */
        @keyframes flyInFromBottom {
            from {
                opacity: 0;
                transform: translateY(100%);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes flyOutToTop {
            from {
                opacity: 1;
                transform: translateY(0);
            }
            to {
                opacity: 0;
                transform: translateY(-100%);
            }
        }

        /* General button focus effects */
        button {
            outline: none;
        }
        button:focus {
            box-shadow: 0 0 0 2px rgba(63,81,181,0.5); /* Material Design focus ring */
        }

        /* ========================================================================== */
        /* Responsive Classes for iPad, Tablet, HP (Android & iOS) */
        /* ========================================================================== */

        /* Default for Desktop */
        .sidebar-toggle-btn {
            display: none;
        }
        .sidebar.mobile-hidden {
            display: flex;
            transform: translateX(0);
        }
        .header-main .my-drive-title {
            display: block;
        }
        .header-main .search-bar-desktop {
            display: flex;
        }
        .search-bar-mobile {
            display: none;
        }
        .toolbar-filter-buttons { 
            display: none;
        }

        /* Custom Scrollbar for Webkit browsers (Chrome, Safari) */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: var(--background-color);
            border-radius: 0; /* Siku-siku */
        }

        ::-webkit-scrollbar-thumb {
            background: var(--divider-color);
            border-radius: 0; /* Siku-siku */
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--secondary-text-color);
        }

        /* Tablet Landscape */
        @media (min-width: 768px) and (max-width: 1024px) {
            body.tablet-landscape .sidebar {
                width: 200px; /* Narrower sidebar */
            }
            body.tablet-landscape .sidebar-header img {
                width: 100px;
            }
            body.tablet-landscape .main-content {
                padding: 15px;
            }
            body.tablet-landscape .header-main {
                padding: 10px 15px;
                margin: -15px -15px 15px -15px;
            }
            body.tablet-landscape .header-main h1 {
                font-size: 1.8em;
            }
            body.tablet-landscape .search-bar input {
                width: 180px;
            }
            body.tablet-landscape .toolbar-left button,
            body.tablet-landscape .toolbar-right button {
                padding: 8px 15px;
                font-size: 0.85em;
            }
            body.tablet-landscape .filter-button {
                padding: 8px 10px;
                font-size: 1em;
            }
            body.tablet-landscape .file-table th,
            body.tablet-landscape .file-table td {
                padding: 10px 20px;
                font-size: 0.85em;
            }
            body.tablet-landscape .file-grid {
                grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
                gap: 12px;
                padding: 12px;
            }
            body.tablet-landscape .grid-thumbnail {
                height: 100px;
            }
            body.tablet-landscape .modal-content {
                max-width: 500px;
                padding: 25px;
            }
            body.tablet-landscape .sidebar-menu a {
                font-size: var(--sidebar-font-size-tablet-landscape);
            }
        }

        /* Tablet Portrait */
        @media (min-width: 768px) and (max-width: 1024px) and (orientation: portrait) {
            body.tablet-portrait .sidebar {
                position: fixed;
                top: 0;
                left: 0;
                height: 100%;
                z-index: 100;
                transform: translateX(-100%);
                box-shadow: 2px 0 5px rgba(0,0,0,0.2); /* Subtle shadow for mobile sidebar */
            }
            body.tablet-portrait .sidebar.show-mobile-sidebar {
                transform: translateX(0);
            }
            body.tablet-portrait .sidebar-toggle-btn {
                display: block;
                background: none;
                border: none;
                font-size: 1.6em;
                color: var(--adminlte-header-text);
                cursor: pointer;
                margin-left: 0;
                order: 0;
            }
            body.tablet-portrait .header-main {
                justify-content: flex-start; /* Align items to start */
                padding: 10px 15px;
                margin: -15px -15px 15px -15px;
            }
            body.tablet-portrait .header-main h1 {
                font-size: 1.6em;
                flex-grow: 1;
                text-align: center;
                margin-left: -30px; /* Counteract toggle button space */
            }
            body.tablet-portrait .header-main .my-drive-title {
                display: none;
            }
            body.tablet-portrait .header-main .search-bar-desktop {
                display: none;
            }
            body.tablet-portrait .search-bar-mobile {
                display: flex;
                margin: 0 auto 15px auto;
                width: calc(100% - 30px);
            }
            body.tablet-portrait .main-content {
                padding: 15px;
            }
            body.tablet-portrait .toolbar {
                flex-wrap: wrap;
                gap: 8px;
                justify-content: center;
            }
            body.tablet-portrait .toolbar-left,
            body.tablet-portrait .toolbar-right {
                width: 100%;
                justify-content: center;
                margin-bottom: 8px;
                flex-wrap: wrap;
            }
            body.tablet-portrait .toolbar-left button,
            body.tablet-portrait .toolbar-right button {
                padding: 7px 12px;
                font-size: 0.8em;
            }
            body.tablet-portrait .toolbar-filter-buttons { 
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(70px, 1fr));
                gap: 8px;
                justify-content: center;
                margin-top: 10px;
                width: 100%;
            }
            body.tablet-portrait .toolbar-filter-buttons .filter-button {
                padding: 7px 9px;
                font-size: 1em;
            }
            body.tablet-portrait .toolbar .dropdown-container,
            body.tablet-portrait .toolbar .view-toggle { 
                display: none;
            }
            body.tablet-portrait .file-table th,
            body.tablet-portrait .file-table td {
                padding: 8px 18px;
                font-size: 0.8em;
            }
            body.tablet-portrait .file-grid {
                grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
                gap: 10px;
                padding: 10px;
            }
            body.tablet-portrait .grid-thumbnail {
                height: 90px;
            }
            body.tablet-portrait .modal-content {
                max-width: 500px;
                padding: 25px;
            }
            body.tablet-portrait .dropdown-content.file-type-filter-dropdown-content {
                max-height: 200px;
                overflow-y: auto;
            }
            body.tablet-portrait .sidebar-menu a {
                font-size: var(--sidebar-font-size-tablet-portrait);
            }
        }

        /* Mobile */
        @media (max-width: 767px) {
            body.mobile .sidebar {
                position: fixed;
                top: 0;
                left: 0;
                height: 100%;
                width: 180px; /* Even narrower sidebar */
                z-index: 100;
                transform: translateX(-100%);
                box-shadow: 2px 0 5px rgba(0,0,0,0.2);
            }
            body.mobile .sidebar.show-mobile-sidebar {
                transform: translateX(0);
            }
            body.mobile .sidebar-toggle-btn {
                display: block;
                background: none;
                border: none;
                font-size: 1.4em;
                color: var(--adminlte-header-text);
                cursor: pointer;
                margin-left: 0;
                order: 0;
            }
            body.mobile .header-main {
                justify-content: flex-start;
                padding: 10px 10px;
                margin: -15px -15px 15px -15px;
            }
            body.mobile .header-main h1 {
                font-size: 1.5em;
                flex-grow: 1;
                text-align: center;
                margin-left: -25px; /* Counteract toggle button space */
            }
            body.mobile .header-main .my-drive-title {
                display: none;
            }
            body.mobile .header-main .search-bar-desktop {
                display: none;
            }
            body.mobile .search-bar-mobile {
                display: flex;
                margin: 0 auto 10px auto;
                width: calc(100% - 20px);
            }
            body.mobile .main-content {
                padding: 10px;
                overflow-x: hidden;
            }
            body.mobile .file-list-container {
                overflow-x: hidden;
            }
            body.mobile .file-table thead {
                display: none;
            }
            body.mobile .file-table tbody tr {
                display: flex;
                flex-wrap: wrap;
                border: 1px solid #dadce0;
                margin-bottom: 8px;
                border-radius: 8px;
                background-color: var(--surface-color);
                box-shadow: none;
                position: relative;
            }
            body.mobile .file-table td {
                display: block;
                width: 100%;
                padding: 8px 16px;
                font-size: 0.875em;
                border-bottom: none;
                white-space: normal;
                text-align: left;
            }
            body.mobile .file-table td:first-child {
                position: absolute;
                top: 12px;
                left: 12px;
                width: auto;
                padding: 0;
            }
            body.mobile .file-table td:nth-child(2) {
                padding-left: 48px;
                font-weight: 500;
                font-size: 0.9em;
            }
            body.mobile .file-table td:nth-child(3),
            body.mobile .file-table td:nth-child(4),
            body.mobile .file-table td:nth-child(5) {
                display: inline-block;
                width: 50%;
                box-sizing: border-box;
                padding-top: 4px;
                padding-bottom: 4px;
                color: #5f6368;
            }
            body.mobile .file-table td:nth-child(3)::before { content: "Type: "; font-weight: normal; color: #5f6368; }
            body.mobile .file-table td:nth-child(4)::before { content: "Size: "; font-weight: normal; color: #5f6368; }
            body.mobile .file-table td:nth-child(5)::before { content: "Modified: "; font-weight: normal; color: #5f6368; }

            body.mobile .toolbar {
                flex-direction: column;
                align-items: stretch;
                gap: 6px;
                padding-bottom: 8px;
            }
            body.mobile .toolbar-left,
            body.mobile .toolbar-right {
                width: 100%;
                flex-direction: row;
                flex-wrap: wrap;
                justify-content: center;
                gap: 6px;
                margin-right: 0;
            }
            body.mobile .toolbar-left button,
            body.mobile .toolbar-right button {
                flex-grow: 1;
                min-width: unset;
                padding: 7px 9px;
                font-size: 0.8em;
            }
            body.mobile .view-toggle {
                display: flex;
                width: 100%;
            }
            body.mobile .view-toggle button {
                flex-grow: 1;
            }
            body.mobile .file-icon {
                font-size: 1em;
                margin-right: 6px;
                width: 18px;
            }
            body.mobile .file-name-cell {
                max-width: 100%;
            }
            body.mobile .grid-item {
                padding: 8px;
            }
            body.mobile .grid-thumbnail {
                height: 70px;
            }
            body.mobile .grid-thumbnail i {
                font-size: 2.8em;
            }
            body.mobile .file-grid {
                grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
                gap: 8px;
                padding: 8px;
            }
            body.mobile .file-name {
                font-size: 0.85em;
            }
            body.mobile .file-size {
                font-size: 0.7em;
            }
            body.mobile .action-buttons button {
                padding: 4px 7px;
                font-size: 0.65em;
            }
            body.mobile .modal-content {
                max-width: 450px;
                padding: 20px;
            }
            body.mobile .upload-item .file-icon {
                font-size: 1.8em;
                margin-right: 8px;
                width: 30px;
            }
            body.mobile .upload-status-icon {
                font-size: 1em;
            }
            body.mobile .upload-action-button {
                font-size: 1em;
            }
            body.mobile .share-link-container {
                flex-direction: column;
                align-items: stretch;
            }
            body.mobile #shareLinkModal input[type="text"] {
                width: 100%;
                margin-right: 0;
                margin-bottom: 8px;
            }
            body.mobile #shareLinkModal button {
                width: 100%;
            }
            body.mobile .toolbar-filter-buttons { 
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(60px, 1fr));
                gap: 6px;
                justify-content: center;
                margin-top: 8px;
                width: 100%;
            }
            body.mobile .toolbar-filter-buttons .filter-button {
                padding: 6px 8px;
                font-size: 0.9em;
            }
            body.mobile .toolbar .dropdown-container,
            body.mobile .toolbar .view-toggle { 
                display: none;
            }
            body.mobile .dropdown-content.file-type-filter-dropdown-content {
                max-height: 180px;
                overflow-y: auto;
            }
            body.mobile .sidebar-menu a {
                font-size: var(--sidebar-font-size-mobile);
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

    </style>
</head>
<body>
    <div class="sidebar mobile-hidden">
        <div class="sidebar-header">
            <img src="img/logo.png" alt="Dafino Logo">
        </div>
        <ul class="sidebar-menu">
            <?php if ($currentUserRole === 'admin' || $currentUserRole === 'moderator'): ?>
                <li><a href="control_center.php"><i class="fas fa-cogs"></i> <span data-lang-key="controlCenter">Control Center</span></a></li>
            <?php endif; ?>
            <?php if (in_array($currentUserRole, ['admin', 'moderator', 'user', 'member'])): ?>
                <li><a href="index.php"><i class="fas fa-folder"></i> <span data-lang-key="myDrive">My Drive</span></a></li>
                <li><a href="priority_files.php"><i class="fas fa-star"></i> <span data-lang-key="priorityFile">Priority File</span></a></li>
                <li><a href="recycle_bin.php" class="active"><i class="fas fa-trash"></i> <span data-lang-key="recycleBin">Recycle Bin</span></a></li>
            <?php endif; ?>
            <li><a href="summary.php"><i class="fas fa-chart-line"></i> <span data-lang-key="summary">Summary</span></a></li>
            <li><a href="members.php"><i class="fas fa-users"></i> <span data-lang-key="members">Members</span></a></li>
            <li><a href="profile.php"><i class="fas fa-user"></i> <span data-lang-key="profile">Profile</span></a></li>
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> <span data-lang-key="logout">Logout</span></a></li>
        </ul>
        <div class="storage-info">
            <h4 data-lang-key="storage">Storage</h4>
            <div class="progress-bar-container">
                <div class="progress-bar" style="width: <?php echo round($usedPercentage, 2); ?>%;">
                    <span class="progress-bar-text"><?php echo round($usedPercentage, 2); ?>%</span>
                </div>
            </div>
            <p class="storage-text" id="storageText"><?php echo formatBytes($usedStorageBytes); ?> of <?php echo formatBytes($totalStorageBytes); ?> used</p>
            <?php if ($isStorageFull): ?>
                <p class="storage-text storage-full-message" style="color: var(--error-color); font-weight: bold;" data-lang-key="storageFull">Storage Full! Cannot upload more files.</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="main-content" id="mainContent">
        <div class="header-main">
            <button class="sidebar-toggle-btn" id="sidebarToggleBtn"><i class="fas fa-bars"></i></button>
            <h1 class="my-drive-title" data-lang-key="recycleBinTitle">Recycle Bin</h1>
            <!-- Removed search-bar-desktop and profile-user desktop-only -->
        </div>

        <!-- Mobile Search Bar (moved below header for smaller screens) -->
        <div class="search-bar search-bar-mobile">
            <i class="fas fa-search"></i>
            <input type="text" id="searchInputMobile" placeholder="Search files in trash..." value="<?php echo htmlspecialchars($searchQuery); ?>" data-lang-key="searchTrashPlaceholder">
        </div>

        <div class="toolbar">
            <div class="toolbar-left">
                <button id="restoreSelectedBtn" style="background-color: var(--success-color);"><i class="fas fa-undo"></i> <span data-lang-key="restoreSelected">Restore Selected</span></button>
                <button id="deleteForeverSelectedBtn" style="background-color: var(--error-color);"><i class="fas fa-times-circle"></i> <span data-lang-key="deleteForeverSelected">Delete Forever Selected</span></button>
                <button id="emptyRecycleBinBtn" style="background-color: var(--error-color);"><i class="fas fa-trash-alt"></i> <span data-lang-key="emptyRecycleBin">Empty Recycle Bin</span></button>
            </div>
            <div class="toolbar-right">
                <!-- File Type Filter Button -->
                <div class="dropdown-container file-type-filter-dropdown-container">
                    <button id="fileTypeFilterBtn" class="filter-button"><i class="fas fa-filter"></i></button>
                    <div class="dropdown-content file-type-filter-dropdown-content">
                        <a href="#" data-filter="all" data-lang-key="allFiles">All Files</a>
                        <a href="#" data-filter="document" data-lang-key="documents">Documents</a>
                        <a href="#" data-filter="image" data-lang-key="images">Images</a>
                        <a href="#" data-filter="music" data-lang-key="music">Music</a>
                        <a href="#" data-filter="video" data-lang-key="videos">Videos</a>
                        <a href="#" data-filter="cad" data-lang-key="cadFiles">CAD Files</a>
                        <?php if ($currentUserRole === 'admin' || $currentUserRole === 'moderator'): ?>
                            <a href="#" data-filter="code" data-lang-key="codeFiles">Code Files</a>
                            <a href="#" data-filter="archive" data-lang-key="archives">Archives</a>
                            <a href="#" data-filter="installation" data-lang-key="installationFiles">Installation Files</a>
                            <a href="#" data-filter="p2p" data-lang-key="p2pFiles">Peer-to-Peer Files</a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Size Filter Button (Replaces Release Date Filter) -->
                <div class="dropdown-container size-filter-dropdown-container">
                    <button id="sizeFilterBtn" class="filter-button"><i class="fas fa-sort-amount-down"></i></button>
                    <div class="dropdown-content size-filter-dropdown-content">
                        <a href="#" data-filter="largest" data-lang-key="largestSize">Largest First</a>
                        <a href="#" data-filter="smallest" data-lang-key="smallestSize">Smallest First</a>
                        <a href="#" data-filter="none" data-lang-key="noSizeFilter">No Size Filter</a>
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
                    <a href="#" data-filter="all" data-lang-key="allFiles">All Files</a>
                    <a href="#" data-filter="document" data-lang-key="documents">Documents</a>
                    <a href="#" data-filter="image" data-lang-key="images">Images</a>
                    <a href="#" data-filter="music" data-lang-key="music">Music</a>
                    <a href="#" data-filter="video" data-lang-key="videos">Videos</a>
                    <a href="#" data-filter="cad" data-lang-key="cadFiles">CAD Files</a>
                    <?php if ($currentUserRole === 'admin' || $currentUserRole === 'moderator'): ?>
                        <a href="#" data-filter="code" data-lang-key="codeFiles">Code Files</a>
                        <a href="#" data-filter="archive" data-lang-key="archives">Archives</a>
                        <a href="#" data-filter="installation" data-lang-key="installationFiles">Installation Files</a>
                        <a href="#" data-filter="p2p" data-lang-key="p2pFiles">Peer-to-Peer Files</a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Size Filter Button (Replaces Release Date Filter) -->
            <div class="dropdown-container size-filter-dropdown-container">
                <button id="sizeFilterBtnHeader" class="filter-button"><i class="fas fa-sort-amount-down"></i></button>
                <div class="dropdown-content size-filter-dropdown-content">
                    <a href="#" data-filter="largest" data-lang-key="largestSize">Largest First</a>
                    <a href="#" data-filter="smallest" data-lang-key="smallestSize">Smallest First</a>
                    <a href="#" data-filter="none" data-lang-key="noSizeFilter">No Size Filter</a>
                </div>
            </div>

            <!-- View Toggle Buttons -->
            <div class="view-toggle">
                <button id="listViewBtnHeader" class="active"><i class="fas fa-list"></i></button>
                <button id="gridViewBtnHeader"><i class="fas fa-th-large"></i></button>
            </div>
        </div>

        <div class="breadcrumbs">
            <span data-lang-key="recycleBinBreadcrumb"><i class="fas fa-trash"></i> Recycle Bin</span>
            <?php if (!empty($searchQuery)): ?>
                <span data-lang-key="breadcrumbSeparator">/</span> <span data-lang-key="searchResultsFor">Search results for "<?php echo htmlspecialchars($searchQuery); ?>"</span>
            <?php endif; ?>
        </div>

        <div class="file-list-container">
            <div id="fileListView" class="file-view">
                <table class="file-table">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="selectAllCheckbox"></th>
                            <th data-lang-key="name">Name</th>
                            <th data-lang-key="type">Type</th>
                            <th data-lang-key="size">Size</th>
                            <th data-lang-key="deletedAt">Deleted At</th>
                            <th data-lang-key="actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($deletedItems) && !empty($searchQuery)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 20px;" data-lang-key="noSearchResults">No deleted files or folders found matching "<?php echo htmlspecialchars($searchQuery); ?>"</td>
                            </tr>
                        <?php elseif (empty($deletedItems) && empty($searchQuery)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 20px;" data-lang-key="recycleBinEmpty">Recycle Bin is empty.</td>
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
                                $isRestrictedItem = isset($item['is_restricted']) && $item['is_restricted'];
                            ?>
                            <tr class="file-item" data-id="<?php echo $itemId; ?>" data-type="<?php echo $itemType; ?>" data-name="<?php echo htmlspecialchars($itemName); ?>" data-file-type="<?php echo $fileExt; ?>" data-is-restricted="<?php echo $isRestrictedItem ? 'true' : 'false'; ?>" tabindex="0">
                                <td><input type="checkbox" class="file-checkbox" data-id="<?php echo $itemId; ?>" data-type="<?php echo $itemType; ?>"></td>
                                <td class="file-name-cell">
                                    <i class="fas <?php echo $iconClass; ?> file-icon <?php echo $colorClass; ?>"></i>
                                    <span><?php echo htmlspecialchars($itemName); ?></span>
                                </td>
                                <td data-lang-key="<?php echo $itemType; ?>Type"><?php echo ucfirst($itemType); ?></td>
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
                        <div style="grid-column: 1 / -1; text-align: center; padding: 20px;" data-lang-key="noSearchResults">No deleted files or folders found matching "<?php echo htmlspecialchars($searchQuery); ?>"</div>
                    <?php elseif (empty($deletedItems) && empty($searchQuery)): ?>
                        <div style="grid-column: 1 / -1; text-align: center; padding: 20px;" data-lang-key="recycleBinEmpty">Recycle Bin is empty.</div>
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
                            $isRestrictedItem = isset($item['is_restricted']) && $item['is_restricted'];
                        ?>
                        <div class="grid-item file-item" data-id="<?php echo $itemId; ?>" data-type="<?php echo $itemType; ?>" data-name="<?php echo htmlspecialchars($itemName); ?>" data-file-type="<?php echo $fileExt; ?>" data-is-restricted="<?php echo $isRestrictedItem ? 'true' : 'false'; ?>" tabindex="0">
                            <input type="checkbox" class="file-checkbox" data-id="<?php echo $itemId; ?>" data-type="<?php echo $itemType; ?>">
                            <div class="grid-thumbnail">
                                <?php if ($itemType === 'file'): ?>
                                    <?php
                                    $filePath = htmlspecialchars($item['file_path']); // Note: physical file might be gone
                                    // In recycle bin, we don't expect files to exist physically, so no direct image/video preview
                                    // Just show the icon and type label
                                    ?>
                                    <i class="fas <?php echo $iconClass; ?> file-icon <?php echo $colorClass; ?>"></i>
                                <?php else: // Folder ?>
                                    <i class="fas <?php echo $iconClass; ?> file-icon <?php echo $colorClass; ?>"></i>
                                <?php endif; ?>
                                <span class="file-type-label" data-lang-key="<?php echo $fileExt; ?>Type"><?php echo ucfirst($fileExt); ?></span>
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
            <li data-action="restore"><i class="fas fa-undo"></i> <span data-lang-key="restore">Restore</span></li>
            <li class="separator"></li>
            <li data-action="delete-forever"><i class="fas fa-times-circle"></i> <span data-lang-key="deleteForever">Delete Forever</span></li>
        </ul>
    </div>

    <!-- Overlay for mobile sidebar -->
    <div class="overlay" id="mobileOverlay"></div>

    <script>
        // --- Translation Data (Global) ---
        const translations = {
            // Sidebar
            'controlCenter': { 'id': 'Control Center', 'en': 'Control Center' },
            'myDrive': { 'id': 'Drive Saya', 'en': 'My Drive' },
            'priorityFile': { 'id': 'File Prioritas', 'en': 'Priority File' },
            'recycleBin': { 'id': 'Tempat Sampah', 'en': 'Recycle Bin' },
            'summary': { 'id': 'Ringkasan', 'en': 'Summary' },
            'members': { 'id': 'Anggota', 'en': 'Members' },
            'profile': { 'id': 'Profil', 'en': 'Profile' },
            'logout': { 'id': 'Keluar', 'en': 'Logout' },
            'storage': { 'id': 'Penyimpanan', 'en': 'Storage' },
            'storageFull': { 'id': 'Penyimpanan Penuh!', 'en': 'Storage Full!' },

            // Recycle Bin Page Specific
            'recycleBinTitle': { 'id': 'Tempat Sampah', 'en': 'Recycle Bin' },
            'searchTrashPlaceholder': { 'id': 'Cari file di tempat sampah...', 'en': 'Search files in trash...' },
            'restoreSelected': { 'id': 'Pulihkan Terpilih', 'en': 'Restore Selected' },
            'deleteForeverSelected': { 'id': 'Hapus Permanen Terpilih', 'en': 'Delete Forever Selected' },
            'emptyRecycleBin': { 'id': 'Kosongkan Tempat Sampah', 'en': 'Empty Recycle Bin' },
            'allFiles': { 'id': 'Semua File', 'en': 'All Files' },
            'documents': { 'id': 'Dokumen', 'en': 'Documents' },
            'images': { 'id': 'Gambar', 'en': 'Images' },
            'music': { 'id': 'Musik', 'en': 'Music' },
            'videos': { 'id': 'Video', 'en': 'Videos' },
            'codeFiles': { 'id': 'File Kode', 'en': 'Code Files' },
            'archives': { 'id': 'Arsip', 'en': 'Archives' },
            'installationFiles': { 'id': 'File Instalasi', 'en': 'Installation Files' },
            'p2pFiles': { 'id': 'File Peer-to-Peer', 'en': 'Peer-to-Peer Files' },
            'cadFiles': { 'id': 'File CAD', 'en': 'CAD Files' },
            'largestSize': { 'id': 'Ukuran Terbesar', 'en': 'Largest First' },
            'smallestSize': { 'id': 'Ukuran Terkecil', 'en': 'Smallest First' },
            'noSizeFilter': { 'id': 'Tanpa Filter Ukuran', 'en': 'No Size Filter' },
            'az': { 'id': 'A-Z', 'en': 'A-Z' }, // Still used for alphabetical if no size filter
            'za': { 'id': 'Z-A', 'en': 'Z-A' }, // Still used for alphabetical if no size filter
            'recycleBinBreadcrumb': { 'id': 'Tempat Sampah', 'en': 'Recycle Bin' },
            'breadcrumbSeparator': { 'id': '/', 'en': '/' },
            'searchResultsFor': { 'id': 'Hasil pencarian untuk', 'en': 'Search results for' },
            'name': { 'id': 'Nama', 'en': 'Name' },
            'type': { 'id': 'Tipe', 'en': 'Type' },
            'size': { 'id': 'Ukuran', 'en': 'Size' },
            'deletedAt': { 'id': 'Dihapus Pada', 'en': 'Deleted At' },
            'actions': { 'id': 'Tindakan', 'en': 'Actions' },
            'noSearchResults': { 'id': 'Tidak ada file atau folder yang dihapus yang cocok dengan', 'en': 'No deleted files or folders found matching' },
            'recycleBinEmpty': { 'id': 'Tempat Sampah kosong.', 'en': 'Recycle Bin is empty.' },
            'fileType': { 'id': 'File', 'en': 'File' }, // For item type in table
            'folderType': { 'id': 'Folder', 'en': 'Folder' }, // For item type in table
            'restore': { 'id': 'Pulihkan', 'en': 'Restore' },
            'deleteForever': { 'id': 'Hapus Permanen', 'en': 'Delete Forever' },
            'confirmRestoreSelected': { 'id': 'Anda yakin ingin memulihkan item yang dipilih?', 'en': 'Are you sure you want to restore the selected items?' },
            'confirmDeleteForeverSelected': { 'id': 'Anda yakin ingin MENGHAPUS PERMANEN item yang dipilih? Tindakan ini tidak dapat dibatalkan!', 'en': 'Are you sure you want to PERMANENTLY delete the selected items? This action cannot be undone!' },
            'confirmEmptyRecycleBin': { 'id': 'Anda yakin ingin MENGOSONGKAN seluruh Tempat Sampah? Semua item akan dihapus PERMANEN dan tindakan ini tidak dapat dibatalkan!', 'en': 'Are you sure you want to EMPTY the entire Recycle Bin? All items will be PERMANENTLY deleted and this action cannot be undone!' },
            'selectItemToRestore': { 'id': 'Pilih setidaknya satu file atau folder untuk dipulihkan!', 'en': 'Please select at least one file or folder to restore!' },
            'selectItemToDelete': { 'id': 'Pilih setidaknya satu file atau folder untuk dihapus secara permanen!', 'en': 'Please select at least one file or folder to delete permanently!' },
            'restoreSuccess': { 'id': 'Item berhasil dipulihkan.', 'en': 'Item restored successfully.' },
            'restoreFailed': { 'id': 'Gagal memulihkan item:', 'en': 'Failed to restore items:' },
            'deleteSuccess': { 'id': 'Item berhasil dihapus secara permanen.', 'en': 'Item deleted permanently.' },
            'deleteFailed': { 'id': 'Gagal menghapus item secara permanen:', 'en': 'Failed to delete items permanently:' },
            'emptyBinSuccess': { 'id': 'Tempat Sampah berhasil dikosongkan.', 'en': 'Recycle Bin emptied successfully.' },
            'emptyBinFailed': { 'id': 'Gagal mengosongkan Tempat Sampah:', 'en': 'Failed to empty Recycle Bin:' },
            'errorOccurred': { 'id': 'Terjadi kesalahan.', 'en': 'An error occurred.' },
            'updateFailed': { 'id': 'Gagal memperbarui konten tempat sampah. Harap segarkan halaman.', 'en': 'Failed to update recycle bin content. Please refresh the page.' },
            'file': { 'id': 'File', 'en': 'File' }, // For item type in table
            'folder': { 'id': 'Folder', 'en': 'Folder' }, // For item type in table
            // Add more file type translations if needed for grid view labels
            'documentType': { 'id': 'Dokumen', 'en': 'Document' },
            'imageType': { 'id': 'Gambar', 'en': 'Image' },
            'musicType': { 'id': 'Musik', 'en': 'Music' },
            'videoType': { 'id': 'Video', 'en': 'Video' },
            'codeType': { 'id': 'Kode', 'en': 'Code' },
            'archiveType': { 'id': 'Arsip', 'en': 'Archive' },
            'installationType': { 'id': 'Instalasi', 'en': 'Installation' },
            'p2pType': { 'id': 'P2P', 'en': 'P2P' },
            'cadType': { 'id': 'CAD', 'en': 'CAD' },
            'defaultType': { 'id': 'Lainnya', 'en': 'Other' },
        };

        let currentLanguage = localStorage.getItem('lang') || 'id'; // Default to Indonesian

        function applyTranslation(lang) {
            document.querySelectorAll('[data-lang-key]').forEach(element => {
                const key = element.getAttribute('data-lang-key');
                if (translations[key] && translations[key][lang]) {
                    if (element.tagName === 'INPUT' && element.hasAttribute('placeholder')) {
                        element.setAttribute('placeholder', translations[key][lang]);
                    } else {
                        element.textContent = translations[key][lang];
                    }
                }
            });

            // Special handling for "of X used" text in sidebar
            const storageTextElement = document.getElementById('storageText');
            if (storageTextElement) {
                const usedBytes = <?php echo $usedStorageBytes; ?>;
                const totalBytes = <?php echo $totalStorageBytes; ?>;
                const formattedUsed = formatBytes(usedBytes);
                const formattedTotal = formatBytes(totalBytes);
                const ofText = translations['ofUsed'] ? translations['ofUsed'][lang] : (lang === 'id' ? 'dari' : 'of');
                const usedSuffix = translations['usedText' + (lang === 'id' ? 'Id' : 'En')] || (lang === 'id' ? 'terpakai' : 'used');
                storageTextElement.textContent = `${formattedUsed} ${ofText} ${formattedTotal} ${usedSuffix}`;
            }

            // Special handling for search results breadcrumb
            const searchResultsSpan = document.querySelector('[data-lang-key="searchResultsFor"]');
            if (searchResultsSpan) {
                const originalQuery = "<?php echo htmlspecialchars($searchQuery); ?>";
                searchResultsSpan.textContent = `${translations['searchResultsFor'][lang]} "${originalQuery}"`;
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const restoreSelectedBtn = document.getElementById('restoreSelectedBtn');
            const deleteForeverSelectedBtn = document.getElementById('deleteForeverSelectedBtn');
            const emptyRecycleBinBtn = document.getElementById('emptyRecycleBinBtn');
            
            // Dropdown elements (main toolbar)
            const fileTypeFilterDropdownContainer = document.querySelector('.toolbar .file-type-filter-dropdown-container');
            const fileTypeFilterBtn = document.getElementById('fileTypeFilterBtn');
            const fileTypeFilterDropdownContent = document.querySelector('.toolbar .file-type-filter-dropdown-content');

            const sizeFilterDropdownContainer = document.querySelector('.toolbar .size-filter-dropdown-container');
            const sizeFilterBtn = document.getElementById('sizeFilterBtn');
            const sizeFilterDropdownContent = document.querySelector('.toolbar .size-filter-dropdown-content');

            // Dropdown elements (header)
            const fileTypeFilterBtnHeader = document.getElementById('fileTypeFilterBtnHeader');
            const sizeFilterBtnHeader = document.getElementById('sizeFilterBtnHeader');
            const listViewBtnHeader = document.getElementById('listViewBtnHeader');
            const gridViewBtnHeader = document.getElementById('gridViewBtnHeader');


            const listViewBtn = document.getElementById('listViewBtn');
            const gridViewBtn = document.getElementById('gridViewBtn');
            const fileListView = document.getElementById('fileListView');
            const fileGridView = document.getElementById('fileGridView');
            const selectAllCheckbox = document.getElementById('selectAllCheckbox');
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
            const mainContent = document.getElementById('mainContent'); // Get main-content for animations

            // Current state variables for AJAX filtering/sorting
            let currentSearchQuery = <?php echo json_encode($searchQuery); ?>;
            let currentSizeFilter = <?php echo json_encode($sizeFilter); ?>;
            let currentSortOrder = <?php echo json_encode($sortOrder); ?>; // Keep for alphabetical if no size filter
            let currentFileTypeFilter = <?php echo json_encode($fileTypeFilter); ?>;
            const currentUserRole = <?php echo json_encode($currentUserRole); ?>; // Pass PHP role to JS

            /*** Util helpers ****/
            function debounce(fn, ms=150){ let t; return (...a)=>{ clearTimeout(t); t=setTimeout(()=>fn(...a), ms); }; }
            function closestFileItem(el){ return el && el.closest('.file-item'); }

            // Helper function for formatBytes (replicate PHP's formatBytes)
            function formatBytes(bytes, precision = 2) {
                const units = ['B', 'KB', 'MB', 'GB', 'TB'];
                bytes = Math.max(bytes, 0);
                const pow = Math.floor((bytes ? Math.log(bytes) : 0) / Math.log(1024));
                const unitIndex = Math.min(pow, units.length - 1);
                bytes /= (1 << (10 * unitIndex));
                return bytes.toFixed(precision) + ' ' + units[unitIndex];
            }

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
                    // Only check/uncheck if the item is not restricted for the current user
                    const itemElement = closestFileItem(checkbox);
                    if (itemElement && itemElement.dataset.isRestricted === 'true' && !(currentUserRole === 'admin' || currentUserRole === 'moderator')) {
                        // Do nothing, or visually indicate it's unselectable
                    } else {
                        checkbox.checked = this.checked;
                    }
                });
            }

            function handleIndividualCheckboxChange() {
                const fileCheckboxes = document.querySelectorAll('.file-checkbox');
                if (!this.checked) {
                    selectAllCheckbox.checked = false;
                } else {
                    // Check if all *selectable* checkboxes are checked
                    const allSelectableChecked = Array.from(fileCheckboxes).every(cb => {
                        const itemElement = closestFileItem(cb);
                        if (itemElement && itemElement.dataset.isRestricted === 'true' && !(currentUserRole === 'admin' || currentUserRole === 'moderator')) {
                            return true; // Treat restricted but unselectable items as 'checked' for the purpose of 'all selected'
                        }
                        return cb.checked;
                    });
                    selectAllCheckbox.checked = allSelectableChecked;
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
                    showNotification(translations['selectItemToRestore'][currentLanguage], 'error');
                    return;
                }

                if (!confirm(translations['confirmRestoreSelected'][currentLanguage])) {
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
                        showNotification(translations['restoreSuccess'][currentLanguage], 'success');
                        updateRecycleBinContent(); // Update content without full reload
                    } else {
                        showNotification(translations['restoreFailed'][currentLanguage] + ' ' + data.message, 'error');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    showNotification(translations['errorOccurred'][currentLanguage] + ' ' + translations['restoreFailed'][currentLanguage], 'error');
                }
            });

            // --- Delete Forever Selected Files/Folders ---
            deleteForeverSelectedBtn.addEventListener('click', async () => {
                const checkboxes = document.querySelectorAll('.file-checkbox:checked');
                const selectedItems = Array.from(checkboxes).map(cb => {
                    return { id: cb.dataset.id, type: cb.dataset.type };
                });

                if (selectedItems.length === 0) {
                    showNotification(translations['selectItemToDelete'][currentLanguage], 'error');
                    return;
                }

                if (!confirm(translations['confirmDeleteForeverSelected'][currentLanguage])) {
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
                        showNotification(translations['deleteSuccess'][currentLanguage], 'success');
                        updateRecycleBinContent(); // Update content without full reload
                    } else {
                        showNotification(translations['deleteFailed'][currentLanguage] + ' ' + data.message, 'error');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    showNotification(translations['errorOccurred'][currentLanguage] + ' ' + translations['deleteFailed'][currentLanguage], 'error');
                }
            });

            // --- Empty Recycle Bin ---
            emptyRecycleBinBtn.addEventListener('click', async () => {
                if (!confirm(translations['confirmEmptyRecycleBin'][currentLanguage])) {
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
                        showNotification(translations['emptyBinSuccess'][currentLanguage], 'success');
                        updateRecycleBinContent(); // Update content without full reload
                    } else {
                        showNotification(translations['emptyBinFailed'][currentLanguage] + ' ' + data.message, 'error');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    showNotification(translations['errorOccurred'][currentLanguage] + ' ' + translations['emptyBinFailed'][currentLanguage], 'error');
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

            // --- Dropdown Exclusive Logic ---
            const allDropdownContainers = document.querySelectorAll('.dropdown-container');

            function closeAllDropdowns() {
                allDropdownContainers.forEach(container => {
                    container.classList.remove('show');
                });
            }

            function setupDropdown(buttonId, dropdownContentSelector, filterType) {
                const button = document.getElementById(buttonId);
                const dropdownContent = document.querySelector(dropdownContentSelector);
                const dropdownContainer = button.closest('.dropdown-container');

                if (!button || !dropdownContent || !dropdownContainer) return;

                button.addEventListener('click', (event) => {
                    event.stopPropagation();
                    const isShowing = dropdownContainer.classList.contains('show');
                    closeAllDropdowns(); // Close all other dropdowns
                    if (!isShowing) {
                        dropdownContainer.classList.add('show'); // Open this one
                    }
                });

                dropdownContent.querySelectorAll('a').forEach(link => {
                    link.addEventListener('click', (event) => {
                        event.preventDefault();
                        dropdownContainer.classList.remove('show');
                        if (filterType === 'file_type') {
                            currentFileTypeFilter = event.target.dataset.filter;
                        } else if (filterType === 'size') {
                            currentSizeFilter = event.target.dataset.filter;
                            // If size filter is 'none', reset sortOrder to 'asc' for alphabetical
                            if (currentSizeFilter === 'none') {
                                currentSortOrder = 'asc';
                            }
                        }
                        updateRecycleBinContent();
                    });
                });
            }

            setupDropdown('fileTypeFilterBtn', '.toolbar .file-type-filter-dropdown-content', 'file_type');
            setupDropdown('fileTypeFilterBtnHeader', '.toolbar-filter-buttons .file-type-filter-dropdown-content', 'file_type');
            setupDropdown('sizeFilterBtn', '.toolbar .size-filter-dropdown-content', 'size');
            setupDropdown('sizeFilterBtnHeader', '.toolbar-filter-buttons .size-filter-dropdown-content', 'size');


            /*** Context menu element ***/
            function showContextMenuFor(fileEl, x, y) {
                if (!fileEl) return;
                contextMenu.dataset.targetId = fileEl.dataset.id;
                contextMenu.dataset.targetType = fileEl.dataset.type;
                contextMenu.dataset.targetName = fileEl.dataset.name;
                contextMenu.dataset.targetFileType = fileEl.dataset.fileType || '';
                contextMenu.dataset.isRestricted = fileEl.dataset.isRestricted || 'false';

                // Hide/show context menu options based on restriction and user role
                const isRestrictedItem = contextMenu.dataset.isRestricted === 'true';
                const isAdminOrModerator = (currentUserRole === 'admin' || currentUserRole === 'moderator');

                // Restore option is always available for visible items
                contextRestore.style.display = 'flex'; 
                
                // Delete Forever option is always available for visible items
                contextDeleteForever.style.display = 'flex';

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
                contextMenu.dataset.isRestricted = '';
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
                if (!e.target.closest('.dropdown-container')) {
                    closeAllDropdowns();
                }
            });
            window.addEventListener('blur', hideContextMenu);

            // --- Individual Restore/Delete Forever ---
            async function restoreItem(id, type) {
                if (!confirm(translations['confirmRestoreSelected'][currentLanguage])) {
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
                        showNotification(translations['restoreSuccess'][currentLanguage], 'success');
                        updateRecycleBinContent();
                    } else {
                        showNotification(translations['restoreFailed'][currentLanguage] + ' ' + data.message, 'error');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    showNotification(translations['errorOccurred'][currentLanguage] + ' ' + translations['restoreFailed'][currentLanguage], 'error');
                }
            }

            async function deleteItemForever(id, type) {
                if (!confirm(translations['confirmDeleteForeverSelected'][currentLanguage])) {
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
                        showNotification(translations['deleteSuccess'][currentLanguage], 'success');
                        updateRecycleBinContent();
                    } else {
                        showNotification(translations['deleteFailed'][currentLanguage] + ' ' + data.message, 'error');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    showNotification(translations['errorOccurred'][currentLanguage] + ' ' + translations['deleteFailed'][currentLanguage], 'error');
                }
            }

            // --- AJAX Content Update Function ---
            async function updateRecycleBinContent() {
                const params = new URLSearchParams();
                if (currentSearchQuery) {
                    params.set('search', currentSearchQuery);
                }
                if (currentSizeFilter && currentSizeFilter !== 'none') {
                    params.set('size', currentSizeFilter);
                } else {
                    // If no size filter, use alphabetical sort order
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
                    applyTranslation(currentLanguage); // Apply translation after content update

                } catch (error) {
                    console.error('Error updating recycle bin content:', error);
                    // showNotification(translations['updateFailed'][currentLanguage], 'error'); // Baris ini dihapus
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

            // --- Sidebar Menu Navigation with Fly Out Animation ---
            sidebarMenuItems.forEach(item => {
                item.addEventListener('click', function(event) {
                    // Only apply animation if it's a navigation link and not the current active page
                    if (this.getAttribute('href') && !this.classList.contains('active')) {
                        event.preventDefault(); // Prevent default navigation immediately
                        const targetUrl = this.getAttribute('href');

                        mainContent.classList.add('fly-out'); // Start fly-out animation

                        mainContent.addEventListener('animationend', function handler() {
                            mainContent.removeEventListener('animationend', handler);
                            window.location.href = targetUrl; // Navigate after animation
                        });
                    }
                });
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

            // Apply initial translation
            applyTranslation(currentLanguage);
        });
    </script>
</body>
</html>
