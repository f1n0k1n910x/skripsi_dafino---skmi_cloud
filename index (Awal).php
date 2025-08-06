<?php
include 'config.php';
include 'functions.php'; // Include functions.php file

session_start();
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Current folder ID, default to NULL for root
$currentFolderId = isset($_GET['folder']) ? (int)$_GET['folder'] : NULL;
$currentFolderName = 'Root';
$currentFolderPath = ''; // To build the full path for uploads and display

// Define the base upload directory
$baseUploadDir = 'uploads/'; // Adjust to your upload directory

// Fetch current folder details for breadcrumbs
$breadcrumbs = [];
if ($currentFolderId) {
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
            // Folder not found, reset to root
            $currentFolderId = NULL;
            $currentFolderName = 'Root';
            $currentFolderPath = '';
            $breadcrumbs = [];
            break;
        }
    }
    $breadcrumbs = $path;
    if (!empty($path)) {
        $currentFolderName = end($path)['name'];
    }
    
    // Build current folder path for uploads
    $currentFolderPathArray = array_map(function($f) { return $f['name']; }, $path);
    $currentFolderPath = implode('/', $currentFolderPathArray);
}

// Get search query
$searchQuery = isset($_GET['search']) ? $_GET['search'] : '';

// Get sorting parameters
$releaseFilter = isset($_GET['release']) ? $_GET['release'] : 'all'; // 'newest', 'oldest', 'all'
$sortOrder = isset($_GET['sort']) ? $_GET['sort'] : 'asc'; // 'asc', 'desc'
$fileTypeFilter = isset($_GET['file_type']) ? $_GET['file_type'] : 'all'; // 'all', 'document', 'music', etc.

// Define file categories for filtering and thumbnail preview
$docExt = ['doc','docx','pdf','ppt','pptx','xls','xlsx','txt','odt','odp','rtf','md','log','csv','tex'];
$musicExt = ['mp3','wav','aac','ogg','flac','m4a','alac','wma','opus','amr','mid'];
$videoExt = ['mp4','mkv','avi','mov','wmv','flv','webm','3gp','m4v','mpg','mpeg','ts','ogv'];
$codeExt = ['html','htm','css','js','php','py','java','json','xml','ts','tsx','jsx','vue','cpp','c','cs','rb','go','swift','sql','sh','bat','ini','yml','yaml','md','pl','r'];
$archiveExt = ['zip','rar','7z','tar','gz','bz2','xz','iso','cab','arj'];
$instExt = ['exe','msi','apk','ipa','sh','bat','jar','appimage','dmg','bin'];
$ptpExt = ['torrent','nzb','ed2k','part','!ut'];
$imageExt = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg', 'tiff'];

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
    case 'all': default: $filterExtensions = []; break; // No specific filter
}


// Fetch folders in current directory
$folders = [];
$sqlFolders = "SELECT id, folder_name, created_at, updated_at FROM folders WHERE parent_id <=> ?";
if (!empty($searchQuery)) {
    $sqlFolders .= " AND folder_name LIKE ?";
}

// Apply sorting for folders
if ($sortOrder === 'asc') {
    $sqlFolders .= " ORDER BY folder_name ASC";
} else {
    $sqlFolders .= " ORDER BY folder_name DESC";
}

$stmt = $conn->prepare($sqlFolders);
if (!empty($searchQuery)) {
    $searchTerm = '%' . $searchQuery . '%';
    $stmt->bind_param("is", $currentFolderId, $searchTerm);
} else {
    $stmt->bind_param("i", $currentFolderId);
}
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $folders[] = $row;
}
$stmt->close();


// Fetch files in current directory
$files = [];
$sqlFiles = "SELECT id, file_name, file_path, file_size, file_type, uploaded_at FROM files WHERE folder_id <=> ?";
$params = [$currentFolderId];
$types = "i";

if (!empty($searchQuery)) {
    $sqlFiles .= " AND file_name LIKE ?";
    $searchTerm = '%' . $searchQuery . '%';
    $params[] = $searchTerm;
    $types .= "s";
}

if (!empty($filterExtensions)) {
    $placeholders = implode(',', array_fill(0, count($filterExtensions), '?'));
    $sqlFiles .= " AND file_type IN ($placeholders)";
    foreach ($filterExtensions as $ext) {
        $params[] = $ext;
        $types .= "s";
    }
}

// Apply release date filter
if ($releaseFilter === 'newest') {
    $sqlFiles .= " ORDER BY uploaded_at DESC";
} elseif ($releaseFilter === 'oldest') {
    $sqlFiles .= " ORDER BY uploaded_at ASC";
} else {
    // Apply alphabetical sorting if no release filter or 'all'
    if ($sortOrder === 'asc') {
        $sqlFiles .= " ORDER BY file_name ASC";
    } else {
        $sqlFiles .= " ORDER BY file_name DESC";
    }
}

$stmt = $conn->prepare($sqlFiles);
// Dynamically bind parameters
$stmt->bind_param($types, ...$params);

$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $files[] = $row;
}
$stmt->close();


// Simulated data for storage (Replace with actual data from your database/system)
// You'd need to query your database for actual storage usage
$totalStorageGB = 500; // For example, total storage capacity 500 GB
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

$usedPercentage = ($totalStorageGB > 0) ? ($usedStorageGB / $totalStorageGB) * 100 : 0;
if ($usedPercentage > 100) $usedPercentage = 100; // Cap at 100%
$freeStorageGB = $totalStorageGB - $usedStorageGB;

// Define file categories for thumbnail preview (already defined above for filtering)
// $docExt = ['doc','docx','pdf','ppt','pptx','xls','xlsx','txt','odt','odp','rtf','md','log','csv','tex'];
// $musicExt = ['mp3','wav','aac','ogg','flac','m4a','alac','wma','opus','amr','mid'];
// $videoExt = ['mp4','mkv','avi','mov','wmv','flv','webm','3gp','m4v','mpg','mpeg','ts','ogv'];
// $codeExt = ['html','htm','css','js','php','py','java','json','xml','ts','tsx','jsx','vue','cpp','c','cs','rb','go','swift','sql','sh','bat','ini','yml','yaml','md','pl','r'];
// $archiveExt = ['zip','rar','7z','tar','gz','bz2','xz','iso','cab','arj'];
// $instExt = ['exe','msi','apk','ipa','sh','bat','jar','appimage','dmg','bin'];
// $ptpExt = ['torrent','nzb','ed2k','part','!ut'];
// $imageExt = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg', 'tiff']; // Added image extensions
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dafino Cloud Storage - Metro UI</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
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

        /* Sidebar */
        .sidebar {
            width: 190px; /* Wider sidebar for Metro feel */
            background-color: var(--metro-sidebar-bg);
            color: var(--metro-sidebar-text);
            display: flex;
            flex-direction: column;
            padding: 20px 0;
            box-shadow: 3px 0 8px rgba(0,0,0,0.2); /* More pronounced shadow */
            transition: width 0.3s ease-in-out;
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
        }

        .progress-bar {
            height: 100%;
            background-color: var(--metro-success); /* Green for progress */
            border-radius: 5px;
            transition: width 0.5s ease-in-out;
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
            margin: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .header-main {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--metro-light-gray);
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

        .toolbar-left button,
        .toolbar-right button {
            background-color: var(--metro-blue);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 3px; /* Sharper corners */
            cursor: pointer;
            font-size: 1em;
            margin-right: 10px;
            transition: background-color 0.2s ease-out, transform 0.1s ease-out;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
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

        .toolbar-right {
            display: flex;
            gap: 10px;
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
            overflow-x: auto; /* Allow horizontal scrolling for wide tables */
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
        .file-icon.pdf { color: #E81123; } /* Red */
        .file-icon.doc { color: #2B579A; } /* Dark Blue */
        .file-icon.xls { color: #107C10; } /* Dark Green */
        .file-icon.ppt { color: #D24726; } /* Orange-Red */
        .file-icon.jpg, .file-icon.png, .file-icon.gif { color: #8E24AA; } /* Purple */
        .file-icon.zip { color: #F7B500; } /* Amber */
        .file-icon.txt { color: #666666; } /* Dark Gray */
        .file-icon.exe, .file-icon.apk { color: #0078D7; } /* Metro Blue */
        .file-icon.mp3, .file-icon.wav { color: #00B294; } /* Teal */
        .file-icon.mp4, .file-icon.avi { color: #FFB900; } /* Gold */
        .file-icon.html, .file-icon.css, .file-icon.js, .file-icon.php, .file-icon.py, .file-icon.json, .file-icon.sql, .file-icon.java, .file-icon.c { color: #505050; } /* Code files: dark gray */
        .file-icon.folder { color: #FFD700; } /* Gold for folders */
        .file-icon.default { color: #999999; } /* Light Gray */

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

        .file-actions {
            display: flex;
            gap: 5px; /* Closer action buttons */
        }

        .file-actions button {
            background: none;
            border: none;
            color: var(--metro-dark-gray);
            cursor: pointer;
            font-size: 1.1em; /* Slightly larger icons */
            padding: 5px; /* Add padding for easier click */
            border-radius: 3px;
            transition: color 0.2s ease-out, background-color 0.2s ease-out;
        }

        .file-actions button:hover {
            color: var(--metro-blue);
            background-color: var(--metro-light-gray);
        }
        .file-actions button.delete-button:hover {
            color: var(--metro-error);
            background-color: var(--metro-light-gray);
        }

        /* Grid View */
        .file-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); /* Adjusted minmax for better preview */
            gap: 25px; /* Increased gap */
            padding: 20px;
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
            color: var(--metro-dark-gray); /* Default icon color for grid */
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

        .action-buttons button {
            background-color: var(--metro-blue);
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 0.9em;
            transition: background-color 0.2s ease-out, transform 0.1s ease-out;
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

        /* Modal Styles (Pop-up CRUD) */
        .modal {
            display: none;
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
            transition: opacity 0.3s ease-out;
        }

        .modal.show {
            display: flex;
            opacity: 1;
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
            transition: background-color 0.2s ease-out, transform 0.1s ease-out;
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
            display: none;
            opacity: 0;
            transform: translateY(-20px);
            transition: opacity 0.3s ease-out, transform 0.3s ease-out;
        }

        .notification.show {
            display: block;
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
            color: var(--metro-dark-gray); /* Default color */
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
            top: 100%;
            left: 0;
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
            transition: background-color 0.2s ease-out, transform 0.1s ease-out;
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
            transition: background-color 0.2s ease-out, transform 0.1s ease-out;
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

    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header">
            <img src="img/logo.png" alt="Dafino Logo">
        </div>
        <ul class="sidebar-menu">
            <li><a href="index.php" class="active"><i class="fas fa-folder"></i> My Drive</a></li>
            <li><a href="summary.php"><i class="fas fa-chart-line"></i> Summary</a></li>
            <li><a href="members.php"><i class="fas fa-users"></i> Members</a></li> <!-- NEW: Members Link -->
            <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        <div class="storage-info">
            <h4>Storage</h4>
            <div class="progress-bar-container">
                <div class="progress-bar" style="width: <?php echo round($usedPercentage, 2); ?>%;"></div>
            </div>
            <p class="storage-text"><?php echo formatBytes($usedStorageBytes); ?> of <?php echo formatBytes($totalStorageGB * 1024 * 1024 * 1024); ?> used</p>
        </div>
    </div>

    <div class="main-content">
        <div class="header-main">
            <h1>My Drive</h1>
            <div class="search-bar">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" placeholder="Search files..." value="<?php echo htmlspecialchars($searchQuery); ?>">
            </div>
        </div>

        <div class="toolbar">
            <div class="toolbar-left">
                <button id="uploadFileBtn"><i class="fas fa-upload"></i> Upload File</button>
                <button id="createFolderBtn"><i class="fas fa-folder-plus"></i> Create Folder</button>
                <button id="deleteSelectedBtn" style="background-color: var(--metro-error);"><i class="fas fa-trash-alt"></i> Delete Selected</button>
            </div>
            <div class="toolbar-right">
                <!-- Archive Button with Dropdown -->
                <div class="dropdown-container archive-dropdown-container">
                    <button id="archiveSelectedBtn" class="filter-button"><i class="fas fa-archive"></i></button>
                    <div class="dropdown-content archive-dropdown-content">
                        <a href="#" data-format="zip">.zip (PHP Native)</a>
                        <a href="#" data-format="tar">.tar</a>
                        <a href="#" data-format="gz">.tar.gz</a>
                        <a href="#" data-format="bz2">.tar.bz2</a>
                        <a href="#" data-format="xz">.tar.xz</a>
                        <a href="#" data-format="rar">.rar</a>
                        <a href="#" data-format="7z">.7z</a>
                        <a href="#" data-format="iso">.iso</a>
                        <a href="#" data-format="cab">.cab</a>
                        <a href="#" data-format="arj">.arj</a>
                    </div>
                </div>

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
                    </div>
                </div>

                <!-- Release Date Filter Button -->
                <div class="dropdown-container release-filter-dropdown-container">
                    <button id="releaseFilterBtn" class="filter-button"><i class="fas fa-calendar-alt"></i></button>
                    <div class="dropdown-content release-filter-dropdown-content">
                        <a href="#" data-filter="newest">Newest</a>
                        <a href="#" data-filter="oldest">Oldest</a>
                        <a href="#" data-filter="all">All Files</a>
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

                <div class="view-toggle">
                    <button id="listViewBtn" class="active"><i class="fas fa-list"></i></button>
                    <button id="gridViewBtn"><i class="fas fa-th-large"></i></button>
                </div>
            </div>
        </div>

        <div class="breadcrumbs">
            <a href="index.php"><i class="fas fa-home"></i> Root</a>
            <?php foreach ($breadcrumbs as $crumb): ?>
                <span>/</span> <a href="index.php?folder=<?php echo $crumb['id']; ?>"><?php echo htmlspecialchars($crumb['name']); ?></a>
            <?php endforeach; ?>
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
                            <th>Last Modified</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($folders) && empty($files) && !empty($searchQuery)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 20px;">No files or folders found matching "<?php echo htmlspecialchars($searchQuery); ?>".</td>
                            </tr>
                        <?php elseif (empty($folders) && empty($files) && empty($searchQuery)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 20px;">No files or folders found in this directory.</td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($folders as $folder): ?>
                            <tr>
                                <td><input type="checkbox" class="file-checkbox" data-id="<?php echo $folder['id']; ?>" data-type="folder"></td>
                                <td class="file-name-cell">
                                    <i class="fas fa-folder file-icon folder"></i>
                                    <a href="index.php?folder=<?php echo $folder['id']; ?>"><?php echo htmlspecialchars($folder['folder_name']); ?></a>
                                </td>
                                <td>Folder</td>
                                <td>
                                    <?php
                                        // NEW: Calculate and display folder size
                                        // Get the full physical path of the folder
                                        $folderPath = $baseUploadDir . getFolderPath($conn, $folder['id']);
                                        $folderSize = getFolderSize($folderPath);
                                        echo formatBytes($folderSize);
                                    ?>
                                </td>
                                <td>
                                    <?php
                                        // Display modification date if available, otherwise creation date
                                        $displayDate = $folder['updated_at'] ?? $folder['created_at'];
                                        echo date('Y-m-d H:i', strtotime($displayDate));
                                    ?>
                                </td>
                                <td class="file-actions">
                                    <button class="rename-button" data-id="<?php echo $folder['id']; ?>" data-type="folder" data-name="<?php echo htmlspecialchars($folder['folder_name']); ?>"><i class="fas fa-pen"></i></button>
                                    <button class="delete-button" data-id="<?php echo $folder['id']; ?>" data-type="folder"><i class="fas fa-trash"></i></button>
                                    <!-- Tombol Share Link untuk Folder (Opsional, jika Anda ingin share folder) -->
                                    <!-- <button class="share-link-button" data-id="<?php echo $folder['id']; ?>" data-type="folder"><i class="fas fa-share-alt"></i></button> -->
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php foreach ($files as $file): ?>
                            <tr>
                                <td><input type="checkbox" class="file-checkbox" data-id="<?php echo $file['id']; ?>" data-type="file"></td>
                                <td class="file-name-cell">
                                    <i class="fas <?php echo getFileIconClassPhp($file['file_name']); ?> file-icon"></i>
                                    <a href="view.php?file_id=<?php echo $file['id']; ?>"><?php echo htmlspecialchars($file['file_name']); ?></a>
                                    </td>
                                <td><?php echo strtoupper($file['file_type']); ?></td>
                                <td><?php echo formatBytes($file['file_size']); ?></td>
                                <td><?php echo date('Y-m-d H:i', strtotime($file['uploaded_at'])); ?></td>
                                <td class="file-actions">
                                    <button class="rename-button" data-id="<?php echo $file['id']; ?>" data-type="file" data-name="<?php echo htmlspecialchars($file['file_name']); ?>"><i class="fas fa-pen"></i></button>
                                    <button class="download-button" data-path="<?php echo htmlspecialchars($file['file_path']); ?>" data-name="<?php echo htmlspecialchars($file['file_name']); ?>"><i class="fas fa-download"></i></button>
                                    <button class="share-link-button" data-id="<?php echo $file['id']; ?>" data-type="file"><i class="fas fa-share-alt"></i></button>
                                    <button class="delete-button" data-id="<?php echo $file['id']; ?>" data-type="file"><i class="fas fa-trash"></i></button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div id="fileGridView" class="file-view hidden">
                <div class="file-grid">
                    <?php if (empty($folders) && empty($files) && !empty($searchQuery)): ?>
                        <div style="grid-column: 1 / -1; text-align: center; padding: 20px;">No files or folders found matching "<?php echo htmlspecialchars($searchQuery); ?>".</div>
                    <?php elseif (empty($folders) && empty($files) && empty($searchQuery)): ?>
                        <div style="grid-column: 1 / -1; text-align: center; padding: 20px;">No files or folders found in this directory.</div>
                    <?php endif; ?>

                    <?php foreach ($folders as $folder): ?>
                        <div class="grid-item">
                            <input type="checkbox" class="file-checkbox" data-id="<?php echo $folder['id']; ?>" data-type="folder">
                            <div class="grid-thumbnail">
                                <i class="fas fa-folder file-icon folder"></i>
                                <span class="file-type-label">Folder</span>
                            </div>
                            <a href="index.php?folder=<?php echo $folder['id']; ?>" class="file-name"><?php echo htmlspecialchars($folder['folder_name']); ?></a>
                            <span class="file-size">
                                <?php
                                    $folderPath = $baseUploadDir . getFolderPath($conn, $folder['id']);
                                    $folderSize = getFolderSize($folderPath);
                                    echo formatBytes($folderSize);
                                ?>
                            </span>
                            <div class="action-buttons">
                                <button class="rename-button" data-id="<?php echo $folder['id']; ?>" data-type="folder" data-name="<?php echo htmlspecialchars($folder['folder_name']); ?>"><i class="fas fa-pen"></i></button>
                                <button class="delete-button" data-id="<?php echo $folder['id']; ?>" data-type="folder"><i class="fas fa-trash"></i></button>
                                <!-- Tombol Share Link untuk Folder (Opsional, jika Anda ingin share folder) -->
                                <!-- <button class="share-link-button" data-id="<?php echo $folder['id']; ?>" data-type="folder"><i class="fas fa-share-alt"></i></button> -->
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <?php foreach ($files as $file): ?>
                        <div class="grid-item">
                            <input type="checkbox" class="file-checkbox" data-id="<?php echo $file['id']; ?>" data-type="file">
                            <div class="grid-thumbnail">
                                <?php
                                $fileExt = strtolower(pathinfo($file['file_name'], PATHINFO_EXTENSION));
                                $filePath = htmlspecialchars($file['file_path']);
                                $fileName = htmlspecialchars($file['file_name']);

                                if (in_array($fileExt, $imageExt)):
                                ?>
                                    <img src="<?php echo $filePath; ?>" alt="<?php echo $fileName; ?>">
                                <?php elseif (in_array($fileExt, $docExt)):
                                    $content = @file_get_contents($file['file_path'], false, null, 0, 500);
                                    if ($content !== false) {
                                        echo '<pre>' . htmlspecialchars(substr(strip_tags($content), 0, 200)) . '...</pre>';
                                    } else {
                                        echo '<i class="fas ' . getFileIconClassPhp($file['file_name']) . ' file-icon"></i>';
                                    }
                                ?>
                                <?php elseif (in_array($fileExt, $musicExt)): ?>
                                    <audio controls style='width:100%; height: auto;'><source src='<?php echo $filePath; ?>' type='audio/<?php echo $fileExt; ?>'></audio>
                                <?php elseif (in_array($fileExt, $videoExt)): ?>
                                    <video controls style='width:100%; height:100%;'><source src='<?php echo $filePath; ?>' type='video/<?php echo $fileExt; ?>'></video>
                                <?php elseif (in_array($fileExt, $codeExt)):
                                    $code = @file_get_contents($file['file_path'], false, null, 0, 500);
                                    if ($code !== false) {
                                        echo '<pre>' . htmlspecialchars(substr($code, 0, 200)) . '...</pre>';
                                    } else {
                                        echo '<i class="fas ' . getFileIconClassPhp($file['file_name']) . ' file-icon"></i>';
                                    }
                                ?>
                                <?php elseif (in_array($fileExt, $archiveExt) || in_array($fileExt, $instExt) || in_array($fileExt, $ptpExt)): ?>
                                    <i class="fas <?php echo getFileIconClassPhp($file['file_name']); ?> file-icon"></i>
                                    <span style="font-size: 0.9em; margin-top: 5px;"><?php echo strtoupper($fileExt); ?> File</span>
                                <?php else: ?>
                                    <i class="fas <?php echo getFileIconClassPhp($file['file_name']); ?> file-icon"></i>
                                <?php endif; ?>
                                <span class="file-type-label"><?php echo strtoupper($fileExt); ?></span>
                            </div>
                            <a href="view.php?file_id=<?php echo $file['id']; ?>" class="file-name"><?php echo $fileName; ?></a>
                            <span class="file-size"><?php echo formatBytes($file['file_size']); ?></span>
                            <div class="action-buttons">
                                <button class="rename-button" data-id="<?php echo $file['id']; ?>" data-type="file" data-name="<?php echo $fileName; ?>"><i class="fas fa-pen"></i></button>
                                <button class="download-button" data-path="<?php echo $filePath; ?>" data-name="<?php echo $fileName; ?>"><i class="fas fa-download"></i></button>
                                <button class="share-link-button" data-id="<?php echo $file['id']; ?>" data-type="file"><i class="fas fa-share-alt"></i></button>
                                <button class="delete-button" data-id="<?php echo $file['id']; ?>" data-type="file"><i class="fas fa-trash"></i></button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <div id="uploadFileModal" class="modal">
        <div class="modal-content">
            <span class="close-button">&times;</span>
            <h2>Upload File</h2>
            <form id="uploadForm" enctype="multipart/form-data">
                <input type="hidden" name="current_folder_id" value="<?php echo htmlspecialchars($currentFolderId); ?>">
                <input type="hidden" name="current_folder_path" value="<?php echo htmlspecialchars($currentFolderPath); ?>">
                <label for="fileToUpload">Select File(s):</label>
                <input type="file" name="fileToUpload[]" id="fileToUpload" multiple required>
                <button type="submit" id="startUploadBtn">Upload</button>
            </form>
        </div>
    </div>

    <div id="createFolderModal" class="modal">
        <div class="modal-content">
            <span class="close-button">&times;</span>
            <h2>Create New Folder</h2>
            <form id="createFolderForm" action="create_folder.php" method="POST">
                <input type="hidden" name="parent_folder_id" value="<?php echo htmlspecialchars($currentFolderId); ?>">
                <input type="hidden" name="parent_folder_path" value="<?php echo htmlspecialchars($currentFolderPath); ?>">
                <label for="folderName">Folder Name:</label>
                <input type="text" name="folderName" id="folderName" required>
                <button type="submit">Create Folder</button>
            </form>
        </div>
    </div>

    <div id="renameModal" class="modal">
        <div class="modal-content">
            <span class="close-button">&times;</span>
            <h2>Rename <span id="renameItemType"></span></h2>
            <form id="renameForm" action="rename.php" method="POST">
                <input type="hidden" name="itemId" id="renameItemId">
                <input type="hidden" name="itemType" id="renameItemActualType">
                <label for="newName">New Name:</label>
                <input type="text" name="newName" id="newName" required>
                <button type="submit">Rename</button>
            </form>
        </div>
    </div>

    <div id="uploadPreviewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <button class="back-button" id="uploadPreviewBackBtn"><i class="fas fa-chevron-left"></i></button>
                <h2>File Upload</h2>
                <span class="close-button" id="closeUploadPreviewBtn">&times;</span>
            </div>
            <div id="uploadPreviewList">
                </div>
            </div>
    </div>

    <!-- Modal untuk Share Link -->
    <div id="shareLinkModal" class="modal">
        <div class="modal-content">
            <span class="close-button">&times;</span>
            <h2>Share Link</h2>
            <p>Here is the shareable link for your file:</p>
            <div class="share-link-container">
                <input type="text" id="shortLinkOutput" value="" readonly>
                <button id="copyShortLinkBtn"><i class="fas fa-copy"></i> Copy</button>
            </div>
            <p class="small-text">Anyone with this link can view the file.</p>
        </div>
    </div>

    <div id="customNotification" class="notification"></div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const uploadFileBtn = document.getElementById('uploadFileBtn');
            const createFolderBtn = document.getElementById('createFolderBtn');
            const deleteSelectedBtn = document.getElementById('deleteSelectedBtn');
            
            // Dropdown elements
            const archiveDropdownContainer = document.querySelector('.archive-dropdown-container');
            const archiveSelectedBtn = document.getElementById('archiveSelectedBtn');
            const archiveDropdownContent = document.querySelector('.archive-dropdown-content');

            const releaseFilterDropdownContainer = document.querySelector('.release-filter-dropdown-container');
            const releaseFilterBtn = document.getElementById('releaseFilterBtn');
            const releaseFilterDropdownContent = document.querySelector('.release-filter-dropdown-content');

            const sortOrderDropdownContainer = document.querySelector('.sort-order-dropdown-container');
            const sortOrderBtn = document.getElementById('sortOrderBtn');
            const sortOrderDropdownContent = document.querySelector('.sort-order-dropdown-content');

            // NEW: File Type Filter elements
            const fileTypeFilterDropdownContainer = document.querySelector('.file-type-filter-dropdown-container');
            const fileTypeFilterBtn = document.getElementById('fileTypeFilterBtn');
            const fileTypeFilterDropdownContent = document.querySelector('.file-type-filter-dropdown-content');


            const uploadFileModal = document.getElementById('uploadFileModal');
            const createFolderModal = document.getElementById('createFolderModal');
            const renameModal = document.getElementById('renameModal');
            const uploadPreviewModal = document.getElementById('uploadPreviewModal');
            const uploadPreviewList = document.getElementById('uploadPreviewList');
            const fileToUploadInput = document.getElementById('fileToUpload');
            const startUploadBtn = document.getElementById('startUploadBtn');
            const uploadPreviewBackBtn = document.getElementById('uploadPreviewBackBtn');
            const closeUploadPreviewBtn = document.getElementById('closeUploadPreviewBtn');

            const closeButtons = document.querySelectorAll('.close-button');

            const listViewBtn = document.getElementById('listViewBtn');
            const gridViewBtn = document.getElementById('gridViewBtn');
            const fileListView = document.getElementById('fileListView');
            const fileGridView = document.getElementById('fileGridView');
            const selectAllCheckbox = document.getElementById('selectAllCheckbox');
            const searchInput = document.getElementById('searchInput');
            const customNotification = document.getElementById('customNotification');

            // New elements for Share Link
            const shareLinkModal = document.getElementById('shareLinkModal');
            const shortLinkOutput = document.getElementById('shortLinkOutput');
            const copyShortLinkBtn = document.getElementById('copyShortLinkBtn');

            let activeUploads = new Map();

            // Function to get file icon class based on extension
            function getFileIconClass(fileName) {
                const extension = fileName.split('.').pop().toLowerCase();
                switch (extension) {
                    case 'pdf': return 'fa-file-pdf pdf';
                    case 'doc':
                    case 'docx': return 'fa-file-word doc';
                    case 'xls':
                    case 'xlsx': return 'fa-file-excel xls';
                    case 'ppt':
                    case 'pptx': return 'fa-file-powerpoint ppt';
                    case 'jpg':
                    case 'jpeg':
                    case 'png':
                    case 'gif':
                    case 'bmp':
                    case 'webp': return 'fa-file-image jpg';
                    case 'zip':
                    case 'rar':
                    case '7z': return 'fa-file-archive zip';
                    case 'txt':
                    case 'log':
                    case 'md': return 'fa-file-alt txt';
                    case 'exe':
                    case 'apk': return 'fa-box exe';
                    case 'mp3':
                    case 'wav':
                    case 'flac': return 'fa-file-audio mp3'; /* Changed to mp3 for specific icon */
                    case 'mp4':
                    case 'avi':
                    case 'mkv': return 'fa-file-video mp4'; /* Changed to mp4 for specific icon */
                    case 'html':
                    case 'htm': return 'fa-file-code html';
                    case 'css': return 'fa-file-code css';
                    case 'js': return 'fa-file-code js';
                    case 'php': return 'fa-file-code php';
                    case 'py': return 'fa-file-code py';
                    case 'json': return 'fa-file-code json';
                    case 'sql': return 'fa-database sql';
                    case 'svg': return 'fa-file-image svg'; /* Changed to svg for specific icon */
                    case 'sh':
                    case 'bat': return 'fa-file-code sh'; /* Changed to sh for specific icon */
                    case 'ini':
                    case 'yml':
                    case 'yaml': return 'fa-file-code ini'; /* Changed to ini for specific icon */
                    case 'java': return 'fa-java java';
                    case 'c':
                    case 'cpp': return 'fa-file-code c';
                    default: return 'fa-file default';
                }
            }

            // Function to show custom notification
            function showNotification(message, type) {
                customNotification.textContent = message;
                customNotification.className = 'notification show ' + type;
                setTimeout(() => {
                    customNotification.classList.remove('show');
                }, 3000);
            }

            // --- Modal Open/Close Logic ---
            function openModal(modalElement) {
                modalElement.classList.add('show');
            }

            function closeModal(modalElement) {
                modalElement.classList.remove('show');
                // Reset form if it's a form modal
                const form = modalElement.querySelector('form');
                if (form) {
                    form.reset();
                }
            }

            uploadFileBtn.addEventListener('click', () => {
                openModal(uploadFileModal);
                fileToUploadInput.value = ''; 
                uploadPreviewList.innerHTML = '';
                startUploadBtn.style.display = 'none';
            });

            createFolderBtn.addEventListener('click', () => {
                openModal(createFolderModal);
            });

            closeButtons.forEach(button => {
                button.addEventListener('click', () => {
                    closeModal(uploadFileModal);
                    closeModal(createFolderModal);
                    closeModal(renameModal);
                    closeModal(uploadPreviewModal);
                    closeModal(shareLinkModal);
                    activeUploads.forEach(controller => controller.abort());
                    activeUploads.clear();
                });
            });

            uploadPreviewBackBtn.addEventListener('click', () => {
                closeModal(uploadPreviewModal);
                openModal(uploadFileModal);
                activeUploads.forEach(controller => controller.abort());
                activeUploads.clear();
            });

            window.addEventListener('click', (event) => {
                if (event.target == uploadFileModal) {
                    closeModal(uploadFileModal);
                }
                if (event.target == createFolderModal) {
                    closeModal(createFolderModal);
                }
                if (event.target == renameModal) {
                    closeModal(renameModal);
                }
                if (event.target == uploadPreviewModal) {
                    closeModal(uploadPreviewModal);
                    activeUploads.forEach(controller => controller.abort());
                    activeUploads.clear();
                }
                if (event.target == shareLinkModal) {
                    closeModal(shareLinkModal);
                }
                // Close all dropdowns if clicked outside
                if (!archiveDropdownContainer.contains(event.target)) {
                    archiveDropdownContainer.classList.remove('show');
                }
                if (!releaseFilterDropdownContainer.contains(event.target)) {
                    releaseFilterDropdownContainer.classList.remove('show');
                }
                if (!sortOrderDropdownContainer.contains(event.target)) {
                    sortOrderDropdownContainer.classList.remove('show');
                }
                // NEW: Close file type filter dropdown
                if (!fileTypeFilterDropdownContainer.contains(event.target)) {
                    fileTypeFilterDropdownContainer.classList.remove('show');
                }
            });

            // --- View Toggle Logic ---
            listViewBtn.addEventListener('click', () => {
                listViewBtn.classList.add('active');
                gridViewBtn.classList.remove('active');
                fileListView.classList.remove('hidden');
                fileGridView.classList.add('hidden');
                localStorage.setItem('fileView', 'list');
            });

            gridViewBtn.addEventListener('click', () => {
                gridViewBtn.classList.add('active');
                listViewBtn.classList.remove('active');
                fileGridView.classList.remove('hidden');
                fileListView.classList.add('hidden');
                localStorage.setItem('fileView', 'grid');
            });

            const savedView = localStorage.getItem('fileView');
            if (savedView === 'grid') {
                gridViewBtn.click();
            } else {
                listViewBtn.click();
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

            // --- Delete Selected Files/Folders ---
            deleteSelectedBtn.addEventListener('click', () => {
                const checkboxes = document.querySelectorAll('.file-checkbox:checked');
                const selectedItems = Array.from(checkboxes).map(cb => {
                    return { id: cb.dataset.id, type: cb.dataset.type };
                });

                if (selectedItems.length === 0) {
                    showNotification('Please select at least one file or folder to delete!', 'error');
                    return;
                }

                if (!confirm('Are you sure you want to delete the selected items? This will delete all files and subfolders within them!')) {
                    return;
                }

                fetch('delete_selected.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ items: selectedItems })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification('Items deleted successfully!', 'success');
                        location.reload();
                    } else {
                        showNotification('Failed to delete items: ' + data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('An error occurred while deleting items.', 'error');
                });
            });

            // --- Archive Selected Files/Folders ---
            archiveSelectedBtn.addEventListener('click', (event) => {
                event.stopPropagation();
                archiveDropdownContainer.classList.toggle('show');
            });

            archiveDropdownContent.querySelectorAll('a').forEach(link => {
                link.addEventListener('click', (event) => {
                    event.preventDefault();
                    archiveDropdownContainer.classList.remove('show');

                    const format = event.target.dataset.format;
                    const checkboxes = document.querySelectorAll('.file-checkbox:checked');
                    const selectedItems = Array.from(checkboxes).map(cb => {
                        return { id: cb.dataset.id, type: cb.dataset.type };
                    });

                    if (selectedItems.length === 0) {
                        showNotification('Please select at least one file or folder to archive!', 'error');
                        return;
                    }

                    if (!confirm(`Are you sure you want to archive the selected items to ${format.toUpperCase()} format?`)) {
                        return;
                    }

                    showNotification('Starting archive process...', 'info');

                    fetch('compress.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ 
                            items: selectedItems, 
                            format: format,
                            current_folder_id: <?php echo json_encode($currentFolderId); ?>
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showNotification(data.message, 'success');
                            location.reload();
                        } else {
                            showNotification('Failed to archive: ' + data.message, 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showNotification('An error occurred while archiving items.', 'error');
                    });
                });
            });

            // --- File Type Filter ---
            fileTypeFilterBtn.addEventListener('click', (event) => {
                event.stopPropagation();
                fileTypeFilterDropdownContainer.classList.toggle('show');
            });

            fileTypeFilterDropdownContent.querySelectorAll('a').forEach(link => {
                link.addEventListener('click', (event) => {
                    event.preventDefault();
                    fileTypeFilterDropdownContainer.classList.remove('show');
                    const filter = event.target.dataset.filter;
                    
                    const url = new URL(window.location.href);
                    url.searchParams.set('file_type', filter);
                    // Reset other filters if needed, or keep them
                    // For now, just apply the file_type filter
                    window.location.href = url.toString();
                });
            });


            // --- Release Date Filter ---
            releaseFilterBtn.addEventListener('click', (event) => {
                event.stopPropagation();
                releaseFilterDropdownContainer.classList.toggle('show');
            });

            releaseFilterDropdownContent.querySelectorAll('a').forEach(link => {
                link.addEventListener('click', (event) => {
                    event.preventDefault();
                    releaseFilterDropdownContainer.classList.remove('show');
                    const filter = event.target.dataset.filter;
                    
                    const url = new URL(window.location.href);
                    url.searchParams.set('release', filter);
                    // Remove sort parameter if release filter is applied, as release filter takes precedence for files
                    if (filter !== 'all') {
                        url.searchParams.delete('sort');
                    }
                    window.location.href = url.toString();
                });
            });

            // --- Sort Order Filter ---
            sortOrderBtn.addEventListener('click', (event) => {
                event.stopPropagation();
                sortOrderDropdownContainer.classList.toggle('show');
            });

            sortOrderDropdownContent.querySelectorAll('a').forEach(link => {
                link.addEventListener('click', (event) => {
                    event.preventDefault();
                    sortOrderDropdownContainer.classList.remove('show');
                    const sort = event.target.dataset.sort;

                    const url = new URL(window.location.href);
                    url.searchParams.set('sort', sort);
                    // If a release filter is active, alphabetical sort only applies to folders
                    // and files when release filter is 'all'
                    if (url.searchParams.get('release') === 'newest' || url.searchParams.get('release') === 'oldest') {
                        // Do nothing, release filter takes precedence for files
                    } else {
                        url.searchParams.set('release', 'all'); // Ensure 'all' is set if sorting alphabetically
                    }
                    window.location.href = url.toString();
                });
            });


            // --- Rename File/Folder ---
            function attachRenameListeners() {
                document.querySelectorAll('.rename-button').forEach(button => {
                    button.removeEventListener('click', handleRenameClick);
                    button.addEventListener('click', handleRenameClick);
                });
            }

            function handleRenameClick() {
                const itemId = this.dataset.id;
                const itemType = this.dataset.type;
                const itemName = this.dataset.name;

                document.getElementById('renameItemId').value = itemId;
                document.getElementById('renameItemActualType').value = itemType;
                document.getElementById('newName').value = itemName;
                document.getElementById('renameItemType').textContent = itemType.charAt(0).toUpperCase() + itemType.slice(1);

                openModal(renameModal);
            }
            attachRenameListeners();

            // --- Download File ---
            function attachDownloadListeners() {
                document.querySelectorAll('.download-button').forEach(button => {
                    button.removeEventListener('click', handleDownloadClick);
                    button.addEventListener('click', handleDownloadClick);
                });
            }

            function handleDownloadClick() {
                const filePath = this.dataset.path;
                const fileName = this.dataset.name;
                const link = document.createElement('a');
                link.href = filePath;
                link.download = fileName;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            }
            attachDownloadListeners();

            // --- Individual Delete File/Folder ---
            function attachIndividualDeleteListeners() {
                document.querySelectorAll('.delete-button').forEach(button => {
                    button.removeEventListener('click', handleIndividualDeleteClick);
                    button.addEventListener('click', handleIndividualDeleteClick);
                });
            }

            function handleIndividualDeleteClick() {
                const id = this.dataset.id;
                const type = this.dataset.type;
                const confirmMessage = type === 'file'
                    ? 'Are you sure you want to permanently delete this file?'
                    : 'Are you sure you want to permanently delete this folder and all its contents?';

                if (confirm(confirmMessage)) {
                    fetch('delete.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({ id: id, type: type })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showNotification(data.message, 'success');
                            location.reload();
                        } else {
                            showNotification('Failed to delete: ' + data.message, 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showNotification('An error occurred while contacting the server for deletion.', 'error');
                    });
                }
            }
            attachIndividualDeleteListeners();

            // --- Form Submissions for Create Folder and Rename ---
            const createFolderForm = document.getElementById('createFolderForm');
            const renameForm = document.getElementById('renameForm');

            createFolderForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                fetch(this.action, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification(data.message, 'success');
                        closeModal(createFolderModal);
                        location.reload();
                    } else {
                        showNotification(data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('An error occurred while creating the folder.', 'error');
                });
            });

            renameForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                fetch(this.action, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification(data.message, 'success');
                        closeModal(renameModal);
                        location.reload();
                    } else {
                        showNotification(data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('An error occurred while renaming.', 'error');
                });
            });

            // --- Search Functionality ---
            searchInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    const currentFolder = <?php echo json_encode($currentFolderId); ?>;
                    let url = 'index.php';
                    const searchValue = this.value.trim();

                    // Get current filter parameters to preserve them
                    const currentUrl = new URL(window.location.href);
                    const releaseParam = currentUrl.searchParams.get('release');
                    const sortParam = currentUrl.searchParams.get('sort');
                    const fileTypeParam = currentUrl.searchParams.get('file_type'); // NEW: Get file_type param

                    const params = new URLSearchParams();
                    if (searchValue) {
                        params.set('search', searchValue);
                    }
                    if (currentFolder !== null) {
                        params.set('folder', currentFolder);
                    }
                    if (releaseParam) {
                        params.set('release', releaseParam);
                    }
                    if (sortParam) {
                        params.set('sort', sortParam);
                    }
                    if (fileTypeParam) { // NEW: Add file_type param to search URL
                        params.set('file_type', fileTypeParam);
                    }
                    
                    window.location.href = url + '?' + params.toString();
                }
            });

            // --- File Upload Preview and Handling ---
            fileToUploadInput.addEventListener('change', function() {
                uploadPreviewList.innerHTML = '';
                startUploadBtn.style.display = 'block';
                activeUploads.clear();

                if (this.files.length > 0) {
                    Array.from(this.files).forEach((file, index) => {
                        const fileId = `upload-item-${index}-${Date.now()}`;
                        const iconClass = getFileIconClass(file.name);
                        
                        const uploadItemHtml = `
                            <div class="upload-item" id="${fileId}">
                                <i class="fas ${iconClass} file-icon"></i>
                                <div class="upload-item-info">
                                    <strong>${file.name}</strong>
                                    <div class="upload-progress-container">
                                        <div class="upload-progress-bar" style="width: 0%;"></div>
                                    </div>
                                </div>
                                <span class="upload-status-icon processing"><i class="fas fa-spinner fa-spin"></i></span>
                                <button class="upload-action-button cancel-upload-btn" data-file-id="${fileId}"><i class="fas fa-times"></i></button>
                            </div>
                        `;
                        uploadPreviewList.insertAdjacentHTML('beforeend', uploadItemHtml);

                        const fileElement = document.getElementById(fileId);
                        activeUploads.set(fileId, { file: file, element: fileElement, controller: null });
                    });
                } else {
                    startUploadBtn.style.display = 'none';
                }
            });

            document.getElementById('startUploadBtn').addEventListener('click', function(e) {
                e.preventDefault();
                if (fileToUploadInput.files.length === 0) {
                    showNotification('Please select files to upload first.', 'error');
                    return;
                }

                closeModal(uploadFileModal);
                openModal(uploadPreviewModal);

                activeUploads.forEach((item, fileId) => {
                    const controller = new AbortController();
                    item.controller = controller;
                    uploadFile(item.file, fileId, controller.signal);
                });
            });

            async function uploadFile(file, fileId, signal) {
                const currentFolderId = document.querySelector('input[name="current_folder_id"]').value;
                const currentFolderPath = document.querySelector('input[name="current_folder_path"]').value;

                const formData = new FormData();
                formData.append('fileToUpload[]', file);
                formData.append('current_folder_id', currentFolderId);
                formData.append('current_folder_path', currentFolderPath);

                const uploadItemElement = document.getElementById(fileId);
                const progressBar = uploadItemElement.querySelector('.upload-progress-bar');
                const statusIcon = uploadItemElement.querySelector('.upload-status-icon');
                const cancelButton = uploadItemElement.querySelector('.cancel-upload-btn');

                statusIcon.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                statusIcon.classList.remove('success', 'error', 'cancelled');
                statusIcon.classList.add('processing');
                cancelButton.style.display = 'block';

                try {
                    const response = await fetch('upload.php', {
                        method: 'POST',
                        body: formData,
                        signal: signal,
                    });

                    if (!response.ok) {
                        throw new Error(`Server responded with status ${response.status}`);
                    }

                    const data = await response.json();

                    if (data.success) {
                        progressBar.style.width = '100%';
                        progressBar.style.backgroundColor = 'var(--metro-success)';
                        statusIcon.innerHTML = '<i class="fas fa-check"></i>';
                        statusIcon.classList.remove('processing', 'error', 'cancelled');
                        statusIcon.classList.add('success');
                        uploadItemElement.classList.add('complete');
                        showNotification(`File '${file.name}' uploaded successfully.`, 'success');
                    } else {
                        throw new Error(data.message || 'Unknown error during upload.');
                    }
                } catch (error) {
                    if (error.name === 'AbortError') {
                        progressBar.style.width = '100%';
                        progressBar.style.backgroundColor = 'var(--metro-warning)';
                        statusIcon.innerHTML = '<i class="fas fa-times-circle"></i>';
                        statusIcon.classList.remove('processing', 'success', 'error');
                        statusIcon.classList.add('cancelled');
                        showNotification(`Upload for '${file.name}' cancelled.`, 'error');
                    } else {
                        progressBar.style.width = '100%';
                        progressBar.style.backgroundColor = 'var(--metro-error)';
                        statusIcon.innerHTML = '<i class="fas fa-times-circle"></i>';
                        statusIcon.classList.remove('processing', 'success', 'cancelled');
                        statusIcon.classList.add('error');
                        showNotification(`Failed to upload '${file.name}': ${error.message}`, 'error');
                    }
                    uploadItemElement.classList.add('complete');
                } finally {
                    cancelButton.style.display = 'none';
                    activeUploads.delete(fileId);
                    if (activeUploads.size === 0) {
                        setTimeout(() => {
                            location.reload();
                        }, 1000);
                    }
                }
            }

            uploadPreviewList.addEventListener('click', function(event) {
                if (event.target.closest('.cancel-upload-btn')) {
                    const button = event.target.closest('.cancel-upload-btn');
                    const fileId = button.dataset.fileId;
                    const uploadItem = activeUploads.get(fileId);

                    if (uploadItem && uploadItem.controller) {
                        uploadItem.controller.abort();
                        activeUploads.delete(fileId);
                        const uploadItemElement = document.getElementById(fileId);
                        const progressBar = uploadItemElement.querySelector('.upload-progress-bar');
                        const statusIcon = uploadItemElement.querySelector('.upload-status-icon');

                        progressBar.style.width = '100%';
                        progressBar.style.backgroundColor = 'var(--metro-warning)';
                        statusIcon.innerHTML = '<i class="fas fa-times-circle"></i>';
                        statusIcon.classList.remove('processing', 'success', 'error');
                        statusIcon.classList.add('cancelled');
                        button.style.display = 'none';
                        uploadItemElement.classList.add('complete');
                        showNotification(`Upload for '${uploadItem.file.name}' manually cancelled.`, 'error');
                        
                        if (activeUploads.size === 0) {
                            setTimeout(() => {
                                location.reload();
                            }, 1000);
                        }
                    }
                }
            });

            // --- Share Link Functionality ---
            function attachShareLinkListeners() {
                document.querySelectorAll('.share-link-button').forEach(button => {
                    button.removeEventListener('click', handleShareLinkClick);
                    button.addEventListener('click', handleShareLinkClick);
                });
            }

            async function handleShareLinkClick() {
                const fileId = this.dataset.id;
                const itemType = this.dataset.type; // Will always be 'file' for this button

                if (itemType !== 'file') {
                    showNotification('Only files can be shared via shortlink.', 'error');
                    return;
                }

                showNotification('Generating share link...', 'info');

                try {
                    const response = await fetch('generate_share_link.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded', // Important for $_POST
                        },
                        body: `file_id=${fileId}`
                    });

                    const data = await response.json();

                    if (data.success) {
                        shortLinkOutput.value = data.shortlink;
                        openModal(shareLinkModal);
                        showNotification('Share link generated!', 'success');
                    } else {
                        showNotification('Failed to generate share link: ' + data.message, 'error');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    showNotification('An error occurred while generating the share link.', 'error');
                }
            }

            copyShortLinkBtn.addEventListener('click', () => {
                shortLinkOutput.select();
                shortLinkOutput.setSelectionRange(0, 99999); // For mobile devices
                document.execCommand('copy');
                showNotification('Link copied to clipboard!', 'success');
            });

            // Call attachShareLinkListeners when DOMContentLoaded and after operations that reload content (if any)
            attachShareLinkListeners();
            // If you have functions that reload the file/folder list without a full page refresh,
            // make sure to call attachShareLinkListeners() again after the new content is loaded.
        });
    </script>
</body>
</html>
