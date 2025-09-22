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
$sizeFilter = isset($_GET['size']) ? $_GET['size'] : 'none'; // 'asc', 'desc', 'none'
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
$cadExt = ['dwg', 'dxf', 'dgn', 'iges', 'igs', 'step', 'stp', 'stl', '3ds', 'obj', 'sldprt', 'sldasm', 'ipt', 'iam', 'catpart', 'catproduct', 'prt', 'asm', 'fcstd', 'skp', 'x_t', 'x_b'];

// Define restricted file types
$restrictedFileTypes = array_merge($codeExt, $instExt, $ptpExt);

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

// --- NEW: Handle restricted file types for non-admin/moderator users ---
if ($currentUserRole !== 'admin' && $currentUserRole !== 'moderator') {
    // If a non-admin/moderator tries to filter by a restricted type, reset to 'all'
    if (in_array($fileTypeFilter, ['code', 'installation', 'p2p'])) {
        $fileTypeFilter = 'all';
        $filterExtensions = []; // Clear filter extensions
        // Optionally, add a notification here if this were a full page reload
    }
    // When displaying files, ensure restricted types are not shown
    // This will be handled in the SQL query below
}
// --- END NEW ---

// Function to check if a folder or its subfolders contain files of a specific type
function folderContainsFilteredFiles($conn, $folderId, $filterExtensions, $baseUploadDir, $currentUserRole, $restrictedFileTypes) {
    if (empty($filterExtensions)) {
        // If no specific file type filter, check if folder contains any files visible to the user
        $sql = "SELECT COUNT(id) FROM files WHERE folder_id = ?";
        $params = [$folderId];
        $types = "i";

        if ($currentUserRole !== 'admin' && $currentUserRole !== 'moderator') {
            // Exclude restricted file types for non-admin/moderator
            $placeholders = implode(',', array_fill(0, count($restrictedFileTypes), '?'));
            $sql .= " AND file_type NOT IN ($placeholders)";
            foreach ($restrictedFileTypes as $ext) {
                $params[] = $ext;
                $types .= "s";
            }
        }
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_row();
        if ($row[0] > 0) {
            $stmt->close();
            return true;
        }
        $stmt->close();
    } else {
        // Specific file type filter is active
        $sql = "SELECT COUNT(id) FROM files WHERE folder_id = ?";
        $params = [$folderId];
        $types = "i";

        $placeholders = implode(',', array_fill(0, count($filterExtensions), '?'));
        $sql .= " AND file_type IN ($placeholders)";
        foreach ($filterExtensions as $ext) {
            $params[] = $ext;
            $types .= "s";
        }

        // If current filter extensions contain restricted types AND user is not admin/moderator,
        // then this specific filter should not return true for restricted types.
        // However, the main query already handles this by setting $filterExtensions to empty if restricted.
        // So, this part only needs to ensure that if a restricted type is in $filterExtensions,
        // it's only counted if the user is admin/moderator.
        // If $filterExtensions is NOT empty, it means the user is admin/moderator OR the filter is not restricted.
        // So, no additional WHERE clause needed here for $currentUserRole.

        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_row();
        if ($row[0] > 0) {
            $stmt->close();
            return true;
        }
        $stmt->close();
    }

    // Recursively check subfolders
    $subfolders = [];
    $stmt = $conn->prepare("SELECT id FROM folders WHERE parent_id = ?");
    $stmt->bind_param("i", $folderId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $subfolders[] = $row['id'];
    }
    $stmt->close();

    foreach ($subfolders as $subfolderId) {
        if (folderContainsFilteredFiles($conn, $subfolderId, $filterExtensions, $baseUploadDir, $currentUserRole, $restrictedFileTypes)) {
            return true;
        }
    }

    return false;
}


// Fetch folders in current directory
$folders = [];
$sqlFolders = "SELECT id, folder_name, created_at, updated_at FROM folders WHERE parent_id <=> ?";
$folderParams = [$currentFolderId];
$folderTypes = "i";

if (!empty($searchQuery)) {
    $sqlFolders .= " AND folder_name LIKE ?";
    $searchTerm = '%' . $searchQuery . '%';
    $folderParams[] = $searchTerm;
    $folderTypes .= "s";
}

// Apply sorting for folders (alphabetical by default, size sorting handled after fetching)
$sqlFolders .= " ORDER BY folder_name ASC";

$stmt = $conn->prepare($sqlFolders);
$stmt->bind_param($folderTypes, ...$folderParams);
$stmt->execute();
$result = $stmt->get_result();
$tempFolders = [];
while ($row = $result->fetch_assoc()) {
    // Apply file type filter to folders, considering user role
    if (empty($filterExtensions) || folderContainsFilteredFiles($conn, $row['id'], $filterExtensions, $baseUploadDir, $currentUserRole, $restrictedFileTypes)) {
        $tempFolders[] = $row;
    }
}
$stmt->close();

// Calculate folder sizes for sorting
foreach ($tempFolders as &$folder) {
    $folderPath = $baseUploadDir . getFolderPath($conn, $folder['id']);
    $folder['calculated_size'] = getFolderSize($folderPath);
}
unset($folder); // Unset reference

// Apply size sorting for folders
if ($sizeFilter === 'asc') {
    usort($tempFolders, function($a, $b) {
        return $a['calculated_size'] <=> $b['calculated_size'];
    });
} elseif ($sizeFilter === 'desc') {
    usort($tempFolders, function($a, $b) {
        return $b['calculated_size'] <=> $a['calculated_size'];
    });
}
$folders = $tempFolders;


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

// --- NEW: Restrict file types based on user role ---
if ($currentUserRole !== 'admin' && $currentUserRole !== 'moderator') {
    // If user is not admin/moderator, exclude restricted file types
    if (!empty($restrictedFileTypes)) {
        $placeholders = implode(',', array_fill(0, count($restrictedFileTypes), '?'));
        $sqlFiles .= " AND file_type NOT IN ($placeholders)";
        foreach ($restrictedFileTypes as $ext) {
            $params[] = $ext;
            $types .= "s";
        }
    }
}

if (!empty($filterExtensions)) {
    $placeholders = implode(',', array_fill(0, count($filterExtensions), '?'));
    $sqlFiles .= " AND file_type IN ($placeholders)";
    foreach ($filterExtensions as $ext) {
        $params[] = $ext;
        $types .= "s";
    }
}

// Apply size sorting for files
if ($sizeFilter === 'asc') {
    $sqlFiles .= " ORDER BY file_size ASC";
} elseif ($sizeFilter === 'desc') {
    $sqlFiles .= " ORDER BY file_size DESC";
} else {
    // Default alphabetical sorting if no size filter
    $sqlFiles .= " ORDER BY file_name ASC";
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
// Changed total storage to 700 GB
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SKMI Cloud Storage</title>
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
        border-collapse: collapse;
        table-layout: fixed;
        }

        .file-table th, 
        .file-table td {
            padding: 12px 24px;
            vertical-align: middle;
            border: none; /* no cell-level borders */
        }

        .file-table tbody tr {
            border-bottom: 1px solid #e0e0e0; /* every row, including last one */
        }

        .file-table tbody tr:last-child td {
            border-bottom: 1px solid #e0e0e0; /* keep consistent */
        } */

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
            height: 100%; /* new */
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

        .file-checkbox {
            margin-right: 16px; /* Google Drive spacing */
            transform: scale(1.0);
            accent-color: #1a73e8; /* Google Drive blue for checkbox */
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

        .grid-thumbnail video, .grid-thumbnail audio {
            width: 100%;
            height: 100%;
            object-fit: cover;
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
            /* NEW: Adjust dropdown position to the left */
            right: 0; /* Align right edge of dropdown with right edge of parent */
            left: auto; /* Override default left alignment */
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
            white-space: nowrap; /* Prevent text wrapping */
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
            body.tablet-landscape .dropdown-content { /* NEW: Adjust dropdown position for tablet landscape */
                right: 0;
                left: auto;
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
            <li><a href="index.php" class="active"><i class="fas fa-folder"></i> <span data-lang-key="myDrive">My Drive</span></a></li>
            <li><a href="priority_files.php"><i class="fas fa-star"></i> <span data-lang-key="priorityFile">Priority File</span></a></li>
            <li><a href="recycle_bin.php"><i class="fas fa-trash"></i> <span data-lang-key="recycleBin">Recycle Bin</span></a></li>
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
            <h1 class="my-drive-title" data-lang-key="myDriveTitle">My Drive</h1>
            <div class="search-bar search-bar-desktop">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" placeholder="Search files..." value="<?php echo htmlspecialchars($searchQuery); ?>" data-lang-placeholder="searchFiles">
            </div>
        </div>

        <!-- Mobile Search Bar (moved below header for smaller screens) -->
        <div class="search-bar search-bar-mobile">
            <i class="fas fa-search"></i>
            <input type="text" id="searchInputMobile" placeholder="Search files..." value="<?php echo htmlspecialchars($searchQuery); ?>" data-lang-placeholder="searchFiles">
        </div>

        <div class="toolbar">
            <div class="toolbar-left">
                <button id="uploadFileBtn" <?php echo $isStorageFull ? 'disabled' : ''; ?>><i class="fas fa-upload"></i></button>
                <button id="createFolderBtn" <?php echo $isStorageFull ? 'disabled' : ''; ?>><i class="fas fa-folder-plus"></i></button>
                <button id="deleteSelectedBtn" style="background-color: var(--error-color);"><i class="fas fa-trash-alt"></i></button>
            </div>
            <div class="toolbar-right">
                <!-- Archive Button with Dropdown (Hidden on mobile/tablet portrait) -->
                <div class="dropdown-container archive-dropdown-container">
                    <button id="archiveSelectedBtn" class="filter-button"><i class="fas fa-archive"></i></button>
                    <div class="dropdown-content archive-dropdown-content">
                        <a href="#" data-format="zip">.zip (PHP Native)</a>
                    </div>
                </div>

                <!-- File Type Filter Button (Hidden on mobile/tablet portrait) -->
                <div class="dropdown-container file-type-filter-dropdown-container">
                    <button id="fileTypeFilterBtn" class="filter-button"><i class="fas fa-filter"></i></button>
                    <div class="dropdown-content file-type-filter-dropdown-content">
                        <a href="#" data-filter="all" data-lang-key="allFiles">All Files</a>
                        <a href="#" data-filter="document" data-lang-key="documents">Documents</a>
                        <a href="#" data-filter="image" data-lang-key="images">Images</a>
                        <a href="#" data-filter="music" data-lang-key="music">Music</a>
                        <a href="#" data-filter="video" data-lang-key="videos">Videos</a>
                        <a href="#" data-filter="archive" data-lang-key="archives">Archives</a>
                        <a href="#" data-filter="cad" data-lang-key="cadFiles">CAD Files</a>
                        <?php if ($currentUserRole === 'admin' || $currentUserRole === 'moderator'): ?>
                            <a href="#" data-filter="code" data-lang-key="codeFiles">Code Files</a>
                            <a href="#" data-filter="installation" data-lang-key="installationFiles">Installation Files</a>
                            <a href="#" data-filter="p2p" data-lang-key="p2pFiles">Peer-to-Peer Files</a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Size Filter Button (Replaces Release Date Filter) -->
                <div class="dropdown-container size-filter-dropdown-container">
                    <button id="sizeFilterBtn" class="filter-button"><i class="fas fa-sort-amount-down"></i></button>
                    <div class="dropdown-content size-filter-dropdown-content">
                        <a href="#" data-size="desc" data-lang-key="sizeLargeToSmall">Size (Large to Small)</a>
                        <a href="#" data-size="asc" data-lang-key="sizeSmallToLarge">Size (Small to Large)</a>
                        <a href="#" data-size="none" data-lang-key="defaultAlphabetical">Default (Alphabetical)</a>
                    </div>
                </div>

                <!-- View Toggle Buttons (Hidden on mobile/tablet portrait) -->
                <div class="view-toggle">
                    <button id="listViewBtn" class="active"><i class="fas fa-list"></i></button>
                    <button id="gridViewBtn"><i class="fas fa-th-large"></i></button>
                </div>
            </div>
        </div>

        <!-- NEW: Filter buttons moved here for mobile/tablet -->
        <div class="toolbar-filter-buttons">
            <!-- Archive Button with Dropdown -->
            <div class="dropdown-container archive-dropdown-container">
                <button id="archiveSelectedBtnHeader" class="filter-button" style="background-color: var(--warning-color);"><i class="fas fa-archive"></i></button>
                <div class="dropdown-content archive-dropdown-content">
                    <a href="#" data-format="zip">.zip (PHP Native)</a>
                </div>
            </div>

            <!-- File Type Filter Button -->
            <div class="dropdown-container file-type-filter-dropdown-container">
                <button id="fileTypeFilterBtnHeader" class="filter-button"><i class="fas fa-filter"></i></button>
                <div class="dropdown-content file-type-filter-dropdown-content">
                    <a href="#" data-filter="all" data-lang-key="allFiles">All Files</a>
                    <a href="#" data-filter="document" data-lang-key="documents">Documents</a>
                    <a href="#" data-filter="image" data-lang-key="images">Images</a>
                    <a href="#" data-filter="music" data-lang-key="music">Music</a>
                    <a href="#" data-filter="video" data-lang-key="videos">Videos</a>
                    <a href="#" data-filter="archive" data-lang-key="archives">Archives</a>
                    <a href="#" data-filter="cad" data-lang-key="cadFiles">CAD Files</a>
                    <?php if ($currentUserRole === 'admin' || $currentUserRole === 'moderator'): ?>
                        <a href="#" data-filter="code" data-lang-key="codeFiles">Code Files</a>
                        <a href="#" data-filter="installation" data-lang-key="installationFiles">Installation Files</a>
                        <a href="#" data-filter="p2p" data-lang-key="p2pFiles">Peer-to-Peer Files</a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Size Filter Button (Replaces Release Date Filter) -->
            <div class="dropdown-container size-filter-dropdown-container">
                <button id="sizeFilterBtnHeader" class="filter-button"><i class="fas fa-sort-amount-down"></i></button>
                <div class="dropdown-content size-filter-dropdown-content">
                    <a href="#" data-size="desc" data-lang-key="sizeLargeToSmall">Size (Large to Small)</a>
                    <a href="#" data-size="asc" data-lang-key="sizeSmallToLarge">Size (Small to Large)</a>
                    <a href="#" data-size="none" data-lang-key="defaultAlphabetical">Default (Alphabetical)</a>
                </div>
            </div>

            <!-- View Toggle Buttons -->
            <div class="view-toggle">
                <button id="listViewBtnHeader" class="active"><i class="fas fa-list"></i></button>
                <button id="gridViewBtnHeader"><i class="fas fa-th-large"></i></button>
            </div>
        </div>

        <div class="breadcrumbs">
            <a href="index.php" id="rootBreadcrumb"><i class="fas fa-home"></i> <span data-lang-key="root">Root</span></a>
            <?php foreach ($breadcrumbs as $crumb): ?>
                <span>/</span> <a href="index.php?folder=<?php echo $crumb['id']; ?>" class="folder-breadcrumb-link"><?php echo htmlspecialchars($crumb['name']); ?></a>
            <?php endforeach; ?>
            <?php if (!empty($searchQuery)): ?>
                <span>/</span> <span data-lang-key="searchResultsFor">Search results for</span> "<?php echo htmlspecialchars($searchQuery); ?>"</span>
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
                            <th data-lang-key="lastModified">Last Modified</th>
                            <th data-lang-key="actions">Actions</th> <!-- Added Actions column header -->
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($folders) && empty($files) && !empty($searchQuery)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 20px;" data-lang-key="noFilesOrFoldersFoundSearch">No files or folders found matching "<?php echo htmlspecialchars($searchQuery); ?>"</td>
                            </tr>
                        <?php elseif (empty($folders) && empty($files) && empty($searchQuery)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 20px;" data-lang-key="noFilesOrFoldersFoundDirectory">No files or folders found in this directory.</td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($folders as $folder): ?>
                            <tr class="file-item" data-id="<?php echo $folder['id']; ?>" data-type="folder" data-name="<?php echo htmlspecialchars($folder['folder_name']); ?>" data-path="<?php echo htmlspecialchars($baseUploadDir . getFolderPath($conn, $folder['id'])); ?>" tabindex="0">
                                <td><input type="checkbox" class="file-checkbox" data-id="<?php echo $folder['id']; ?>" data-type="folder"></td>
                                <td class="file-name-cell">
                                    <i class="fas fa-folder file-icon folder"></i>
                                    <a href="index.php?folder=<?php echo $folder['id']; ?>" class="file-link-clickable" onclick="event.stopPropagation();"><?php echo htmlspecialchars($folder['folder_name']); ?></a>
                                </td>
                                <td data-lang-key="folder">Folder</td>
                                <td>
                                    <?php
                                        // NEW: Calculate and display folder size
                                        // Get the full physical path of the folder
                                        echo formatBytes($folder['calculated_size']);
                                    ?>
                                </td>
                                <td>
                                    <?php
                                        // Display modification date if available, otherwise creation date
                                        $displayDate = $folder['updated_at'] ?? $folder['created_at'];
                                        echo date('Y-m-d H:i', strtotime($displayDate));
                                    ?>
                                </td>
                                <td>
                                    <button class="item-more" aria-haspopup="true" aria-label="More">⋮</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php foreach ($files as $file): ?>
                            <tr class="file-item" data-id="<?php echo $file['id']; ?>" data-type="file" data-name="<?php echo htmlspecialchars($file['file_name']); ?>" data-path="<?php echo htmlspecialchars($file['file_path']); ?>" data-file-type="<?php echo strtolower($file['file_type']); ?>" tabindex="0">
                                <td><input type="checkbox" class="file-checkbox" data-id="<?php echo $file['id']; ?>" data-type="file"></td>
                                <td class="file-name-cell">
                                    <i class="fas <?php echo getFontAwesomeIconClass($file['file_name']); ?> file-icon <?php echo getFileColorClassPhp($file['file_name']); ?>"></i>
                                    <a href="view.php?file_id=<?php echo $file['id']; ?>" class="file-link-clickable" onclick="event.stopPropagation();"><?php echo htmlspecialchars($file['file_name']); ?></a>
                                    </td>
                                <td><?php echo strtoupper($file['file_type']); ?></td>
                                <td><?php echo formatBytes($file['file_size']); ?></td>
                                <td><?php echo date('Y-m-d H:i', strtotime($file['uploaded_at'])); ?></td>
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
                    <?php if (empty($folders) && empty($files) && !empty($searchQuery)): ?>
                        <div style="grid-column: 1 / -1; text-align: center; padding: 20px;" data-lang-key="noFilesOrFoldersFoundSearch">No files or folders found matching "<?php echo htmlspecialchars($searchQuery); ?>"</div>
                    <?php elseif (empty($folders) && empty($files) && empty($searchQuery)): ?>
                        <div style="grid-column: 1 / -1; text-align: center; padding: 20px;" data-lang-key="noFilesOrFoldersFoundDirectory">No files or folders found in this directory.</div>
                    <?php endif; ?>

                    <?php foreach ($folders as $folder): ?>
                        <div class="grid-item file-item" data-id="<?php echo $folder['id']; ?>" data-type="folder" data-name="<?php echo htmlspecialchars($folder['folder_name']); ?>" data-path="<?php echo htmlspecialchars($baseUploadDir . getFolderPath($conn, $folder['id'])); ?>" tabindex="0">
                            <input type="checkbox" class="file-checkbox" data-id="<?php echo $folder['id']; ?>" data-type="folder">
                            <div class="grid-thumbnail">
                                <i class="fas fa-folder file-icon folder"></i>
                                <span class="file-type-label" data-lang-key="folder">Folder</span>
                            </div>
                            <a href="index.php?folder=<?php echo $folder['id']; ?>" class="file-name file-link-clickable" onclick="event.stopPropagation();"><?php echo htmlspecialchars($folder['folder_name']); ?></a>
                            <span class="file-size">
                                <?php
                                    // NEW: Calculate and display folder size
                                    // Get the full physical path of the folder
                                    echo formatBytes($folder['calculated_size']);
                                ?>
                            </span>
                            <button class="item-more" aria-haspopup="true" aria-label="More">⋮</button>
                        </div>
                    <?php endforeach; ?>

                    <?php foreach ($files as $file): ?>
                        <div class="grid-item file-item" data-id="<?php echo $file['id']; ?>" data-type="file" data-name="<?php echo htmlspecialchars($file['file_name']); ?>" data-path="<?php echo htmlspecialchars($file['file_path']); ?>" data-file-type="<?php echo strtolower($file['file_type']); ?>" tabindex="0">
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
                                        echo '<i class="fas ' . getFontAwesomeIconClass($file['file_name']) . ' file-icon ' . getFileColorClassPhp($file['file_name']) . '"></i>';
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
                                        echo '<i class="fas ' . getFontAwesomeIconClass($file['file_name']) . ' file-icon ' . getFileColorClassPhp($file['file_name']) . '"></i>';
                                    }
                                ?>
                                <?php elseif (in_array($fileExt, $cadExt)): // NEW: CAD Thumbnail Preview ?>
                                    <i class="fas <?php echo getFontAwesomeIconClass($file['file_name']); ?> file-icon <?php echo getFileColorClassPhp($file['file_name']); ?>"></i>
                                    <span style="font-size: 0.9em; margin-top: 5px;" data-lang-key="cadFile">CAD File</span>
                                <?php elseif (in_array($fileExt, $archiveExt) || in_array($fileExt, $instExt) || in_array($fileExt, $ptpExt)): ?>
                                    <i class="fas <?php echo getFontAwesomeIconClass($file['file_name']); ?> file-icon <?php echo getFileColorClassPhp($file['file_name']); ?>"></i>
                                    <span style="font-size: 0.9em; margin-top: 5px;"><?php echo strtoupper($fileExt); ?> <span data-lang-key="file">File</span></span>
                                <?php else: ?>
                                    <i class="fas <?php echo getFontAwesomeIconClass($file['file_name']); ?> file-icon <?php echo getFileColorClassPhp($file['file_name']); ?>"></i>
                                <?php endif; ?>
                                <span class="file-type-label"><?php echo strtoupper($fileExt); ?></span>
                            </div>
                            <a href="view.php?file_id=<?php echo $file['id']; ?>" class="file-name file-link-clickable" onclick="event.stopPropagation();"><?php echo $fileName; ?></a>
                            <span class="file-size"><?php echo formatBytes($file['file_size']); ?></span>
                            <button class="item-more" aria-haspopup="true" aria-label="More">⋮</button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <div id="uploadFileModal" class="modal">
        <div class="modal-content">
            <span class="close-button">&times;</span>
            <h2 data-lang-key="uploadFile">Upload File</h2>
            <form id="uploadForm" enctype="multipart/form-data">
                <input type="hidden" name="current_folder_id" value="<?php echo htmlspecialchars($currentFolderId); ?>">
                <input type="hidden" name="current_folder_path" value="<?php echo htmlspecialchars($currentFolderPath); ?>">
                <label for="fileToUpload" data-lang-key="selectFiles">Select File(s):</label>
                <input type="file" name="fileToUpload[]" id="fileToUpload" multiple required <?php echo $isStorageFull ? 'disabled' : ''; ?>>
                <button type="submit" id="startUploadBtn" <?php echo $isStorageFull ? 'disabled' : ''; ?> data-lang-key="upload">Upload</button>
            </form>
        </div>
    </div>

    <div id="createFolderModal" class="modal">
        <div class="modal-content">
            <span class="close-button">&times;</span>
            <h2 data-lang-key="createNewFolder">Create New Folder</h2>
            <form id="createFolderForm" action="create_folder.php" method="POST">
                <input type="hidden" name="parent_folder_id" value="<?php echo htmlspecialchars($currentFolderId); ?>">
                <input type="hidden" name="parent_folder_path" value="<?php echo htmlspecialchars($currentFolderPath); ?>">
                <label for="folderName" data-lang-key="folderName">Folder Name:</label>
                <input type="text" name="folderName" id="folderName" required <?php echo $isStorageFull ? 'disabled' : ''; ?>>
                <button type="submit" <?php echo $isStorageFull ? 'disabled' : ''; ?> data-lang-key="createFolder">Create Folder</button>
            </form>
        </div>
    </div>

    <div id="renameModal" class="modal">
        <div class="modal-content">
            <span class="close-button">&times;</span>
            <h2 data-lang-key="rename">Rename <span id="renameItemType"></span></h2>
            <form id="renameForm" action="rename.php" method="POST">
                <input type="hidden" name="itemId" id="renameItemId">
                <input type="hidden" name="itemType" id="renameItemActualType">
                <label for="newName" data-lang-key="newName">New Name:</label>
                <input type="text" name="newName" id="newName" required>
                <button type="submit" data-lang-key="renameBtn">Rename</button>
            </form>
        </div>
    </div>

    <div id="uploadPreviewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <button class="back-button" id="uploadPreviewBackBtn"><i class="fas fa-chevron-left"></i></button>
                <h2 data-lang-key="fileUpload">File Upload</h2>
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
            <h2 data-lang-key="shareLink">Share Link</h2>
            <p data-lang-key="shareLinkDescription">Here is the shareable link for your file:</p>
            <div class="share-link-container">
                <input type="text" id="shortLinkOutput" value="" readonly>
                <button id="copyShortLinkBtn"><i class="fas fa-copy"></i> <span data-lang-key="copy">Copy</span></button>
            </div>
            <p class="small-text" data-lang-key="anyoneWithLink">Anyone with this link can view the file.</p>
        </div>
    </div>

    <div id="customNotification" class="notification"></div>

    <!-- Custom context menu (shared UI, populated by JS) -->
    <div id="context-menu" class="context-menu" hidden>
        <ul>
            <li data-action="rename"><i class="fas fa-pen"></i> <span data-lang-key="rename">Rename</span></li>
            <li data-action="download" class="hidden"><i class="fas fa-download"></i> <span data-lang-key="download">Download</span></li>
            <li data-action="share" class="hidden"><i class="fas fa-share-alt"></i> <span data-lang-key="shareLink">Share Link</span></li>
            <li data-action="extract" class="hidden"><i class="fas fa-file-archive"></i> <span data-lang-key="extractZip">Extract ZIP</span></li>
            <li data-action="toggle-star"><i class="fas fa-star"></i> <span data-lang-key="pinToPriority">Pin to Priority</span></li>
            <li class="separator"></li>
            <li data-action="delete"><i class="fas fa-trash"></i> <span data-lang-key="delete">Delete</span></li>
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
            'storageFull': { 'id': 'Penyimpanan Penuh! Tidak dapat mengunggah lebih banyak file.', 'en': 'Storage Full! Cannot upload more files.' },
            'ofUsed': { 'id': 'dari', 'en': 'of' },
            'usedTextId': 'terpakai',
            'usedTextEn': 'used',

            // Main Content - Header
            'myDriveTitle': { 'id': 'Drive Saya', 'en': 'My Drive' },
            'searchFiles': { 'id': 'Cari file...', 'en': 'Search files...' },

            // Toolbar
            'uploadFile': { 'id': 'Unggah File', 'en': 'Upload File' },
            'createFolder': { 'id': 'Buat Folder', 'en': 'Create Folder' },
            'deleteSelected': { 'id': 'Hapus Terpilih', 'en': 'Delete Selected' },
            'archive': { 'id': 'Arsipkan', 'en': 'Archive' },
            'allFiles': { 'id': 'Semua File', 'en': 'All Files' },
            'documents': { 'id': 'Dokumen', 'en': 'Documents' },
            'images': { 'id': 'Gambar', 'en': 'Images' },
            'music': { 'id': 'Musik', 'en': 'Music' },
            'videos': { 'id': 'Video', 'en': 'Videos' },
            'archives': { 'id': 'Arsip', 'en': 'Archives' },
            'cadFiles': { 'id': 'File CAD', 'en': 'CAD Files' },
            'codeFiles': { 'id': 'File Kode', 'en': 'Code Files' },
            'installationFiles': { 'id': 'File Instalasi', 'en': 'Installation Files' },
            'p2pFiles': { 'id': 'File Peer-to-Peer', 'en': 'Peer-to-Peer Files' },
            'sizeLargeToSmall': { 'id': 'Ukuran (Besar ke Kecil)', 'en': 'Size (Large to Small)' },
            'sizeSmallToLarge': { 'id': 'Ukuran (Kecil ke Besar)', 'en': 'Size (Small to Large)' },
            'defaultAlphabetical': { 'id': 'Default (Abjad)', 'en': 'Default (Alphabetical)' },

            // Breadcrumbs
            'root': { 'id': 'Root', 'en': 'Root' },
            'searchResultsFor': { 'id': 'Hasil pencarian untuk', 'en': 'Search results for' },

            // File List/Grid
            'noFilesOrFoldersFoundSearch': { 'id': 'Tidak ada file atau folder yang ditemukan cocok dengan', 'en': 'No files or folders found matching' },
            'noFilesOrFoldersFoundDirectory': { 'id': 'Tidak ada file atau folder yang ditemukan di direktori ini.', 'en': 'No files or folders found in this directory.' },
            'name': { 'id': 'Nama', 'en': 'Name' },
            'type': { 'id': 'Tipe', 'en': 'Type' },
            'size': { 'id': 'Ukuran', 'en': 'Size' },
            'lastModified': { 'id': 'Terakhir Dimodifikasi', 'en': 'Last Modified' },
            'actions': { 'id': 'Tindakan', 'en': 'Actions' },
            'folder': { 'id': 'Folder', 'en': 'Folder' },
            'cadFile': { 'id': 'File CAD', 'en': 'CAD File' },
            'file': { 'id': 'File', 'en': 'File' },

            // Modals
            'selectFiles': { 'id': 'Pilih File(s):', 'en': 'Select File(s):' },
            'upload': { 'id': 'Unggah', 'en': 'Upload' },
            'createNewFolder': { 'id': 'Buat Folder Baru', 'en': 'Create New Folder' },
            'folderName': { 'id': 'Nama Folder:', 'en': 'Folder Name:' },
            'rename': { 'id': 'Ganti Nama', 'en': 'Rename' },
            'newName': { 'id': 'Nama Baru:', 'en': 'New Name:' },
            'renameBtn': { 'id': 'Ganti Nama', 'en': 'Rename' },
            'fileUpload': { 'id': 'Unggah File', 'en': 'File Upload' },
            'shareLink': { 'id': 'Bagikan Tautan', 'en': 'Share Link' },
            'shareLinkDescription': { 'id': 'Berikut adalah tautan yang dapat dibagikan untuk file Anda:', 'en': 'Here is the shareable link for your file:' },
            'copy': { 'id': 'Salin', 'en': 'Copy' },
            'anyoneWithLink': { 'id': 'Siapa pun dengan tautan ini dapat melihat file.', 'en': 'Anyone with this link can view the file.' },

            // Context Menu
            'download': { 'id': 'Unduh', 'en': 'Download' },
            'extractZip': { 'id': 'Ekstrak ZIP', 'en': 'Extract ZIP' },
            'pinToPriority': { 'id': 'Sematkan ke Prioritas', 'en': 'Pin to Priority' },
            'delete': { 'id': 'Hapus', 'en': 'Delete' },

            // Notifications
            'pleaseSelectToDelete': { 'id': 'Pilih setidaknya satu file atau folder untuk dihapus!', 'en': 'Please select at least one file or folder to delete!' },
            'noPermissionRestrictedDelete': { 'id': 'Anda tidak memiliki izin untuk menghapus jenis file terbatas.', 'en': 'You do not have permission to delete restricted file types.' },
            'confirmDelete': { 'id': 'Anda yakin ingin menghapus item yang dipilih? Ini akan menghapus semua file dan subfolder di dalamnya!', 'en': 'Are you sure you want to delete the selected items? This will delete all files and subfolders within them!' },
            'itemsDeletedSuccess': { 'id': 'Item berhasil dihapus!', 'en': 'Items deleted successfully!' },
            'failedToDelete': { 'id': 'Gagal menghapus item:', 'en': 'Failed to delete items:' },
            'errorDeleting': { 'id': 'Terjadi kesalahan saat menghapus item.', 'en': 'An error occurred while deleting items.' },
            'pleaseSelectToArchive': { 'id': 'Pilih setidaknya satu file atau folder untuk diarsipkan!', 'en': 'Please select at least one file or folder to archive!' },
            'noPermissionRestrictedArchive': { 'id': 'Anda tidak memiliki izin untuk mengarsipkan jenis file terbatas.', 'en': 'You do not have permission to archive restricted file types.' },
            'confirmArchive': { 'id': 'Anda yakin ingin mengarsipkan item yang dipilih ke format', 'en': 'Are you sure you want to archive the selected items to' },
            'startingArchive': { 'id': 'Memulai proses pengarsipan...', 'en': 'Starting archive process...' },
            'failedToArchive': { 'id': 'Gagal mengarsipkan:', 'en': 'Failed to archive:' },
            'errorArchiving': { 'id': 'Terjadi kesalahan saat mengarsipkan item.', 'en': 'An error occurred while archiving items.' },
            'noPermissionRestrictedRename': { 'id': 'Anda tidak memiliki izin untuk mengganti nama jenis file terbatas.', 'en': 'You do not have permission to rename restricted file types.' },
            'noPermissionRestrictedDownload': { 'id': 'Anda tidak memiliki izin untuk mengunduh jenis file terbatas.', 'en': 'You do not have permission to download restricted file types.' },
            'confirmExtract': { 'id': 'Anda yakin ingin mengekstrak file ZIP ini? Ini akan diekstrak ke folder baru bernama file ZIP di direktori saat ini.', 'en': 'Are you sure you want to extract this ZIP file? It will be extracted to a new folder named after the ZIP file in the current directory.' },
            'extractingZip': { 'id': 'Mengekstrak file ZIP...', 'en': 'Extracting ZIP file...' },
            'extractionFailed': { 'id': 'Ekstraksi gagal:', 'en': 'Extraction failed:' },
            'errorExtracting': { 'id': 'Terjadi kesalahan selama ekstraksi.', 'en': 'An error occurred during extraction.' },
            'noPermissionRestrictedPin': { 'id': 'Anda tidak memiliki izin untuk menyematkan jenis file terbatas ke prioritas.', 'en': 'You do not have permission to pin restricted file types to priority.' },
            'failedToToggleStar': { 'id': 'Gagal menyematkan:', 'en': 'Failed to toggle star:' },
            'errorTogglingStar': { 'id': 'Terjadi kesalahan saat menyematkan.', 'en': 'An error occurred while toggling star.' },
            'folderCreatedSuccess': { 'id': 'Folder berhasil dibuat!', 'en': 'Folder created successfully!' },
            'failedToCreateFolder': { 'id': 'Gagal membuat folder:', 'en': 'Failed to create folder:' },
            'errorCreatingFolder': { 'id': 'Terjadi kesalahan saat membuat folder.', 'en': 'An error occurred while creating the folder.' },
            'itemRenamedSuccess': { 'id': 'Item berhasil diganti namanya!', 'en': 'Item renamed successfully!' },
            'failedToRename': { 'id': 'Gagal mengganti nama:', 'en': 'Failed to rename:' },
            'errorRenaming': { 'id': 'Terjadi kesalahan saat mengganti nama.', 'en': 'An error occurred while renaming.' },
            'noFilesToUpload': { 'id': 'Pilih file untuk diunggah terlebih dahulu.', 'en': 'Please select files to upload first.' },
            'fileRestrictedUpload': { 'id': 'File ini adalah jenis terbatas dan tidak dapat diunggah.', 'en': 'This file is a restricted type and cannot be uploaded.' },
            'noFilesEligible': { 'id': 'Tidak ada file yang memenuhi syarat untuk diunggah.', 'en': 'No files eligible for upload.' },
            'fileUploadedSuccess': { 'id': 'File berhasil diunggah.', 'en': 'File uploaded successfully.' },
            'uploadCancelled': { 'id': 'Unggahan dibatalkan.', 'en': 'Upload cancelled.' },
            'failedToUpload': { 'id': 'Gagal mengunggah:', 'en': 'Failed to upload:' },
            'uploadManuallyCancelled': { 'id': 'Unggahan dibatalkan secara manual.', 'en': 'Upload manually cancelled.' },
            'generatingShareLink': { 'id': 'Membuat tautan berbagi...', 'en': 'Generating share link...' },
            'onlyFilesCanBeShared': { 'id': 'Hanya file yang dapat dibagikan melalui tautan singkat.', 'en': 'Only files can be shared via shortlink.' },
            'noPermissionRestrictedShare': { 'id': 'Anda tidak memiliki izin untuk membagikan jenis file terbatas.', 'en': 'You do not have permission to share restricted file types.' },
            'shareLinkGenerated': { 'id': 'Tautan berbagi berhasil dibuat!', 'en': 'Share link generated!' },
            'failedToGenerateShareLink': { 'id': 'Gagal membuat tautan berbagi:', 'en': 'Failed to generate share link:' },
            'errorGeneratingShareLink': { 'id': 'Terjadi kesalahan saat membuat tautan berbagi.', 'en': 'An error occurred while generating the share link.' },
            'linkCopied': { 'id': 'Tautan disalin ke clipboard!', 'en': 'Link copied to clipboard!' },
            'noPermissionRestrictedOpen': { 'id': 'Anda tidak memiliki izin untuk membuka jenis file terbatas.', 'en': 'You do not have permission to open restricted file types.' },
            'failedToUpdateFileList': { 'id': 'Gagal memperbarui daftar file. Harap segarkan halaman.', 'en': 'Failed to update file list. Please refresh the page.' },
            'storageFullCannotUpload': { 'id': 'Penyimpanan penuh! Tidak dapat mengunggah lebih banyak file.', 'en': 'Storage full! Cannot upload more files.' },
            'storageFullCannotCreateFolder': { 'id': 'Penyimpanan penuh! Tidak dapat membuat folder baru.', 'en': 'Storage full! Cannot create new folders.' },
        };

        let currentLanguage = localStorage.getItem('lang') || 'id'; // Default to Indonesian

        function formatBytes(bytes, precision = 2) {
            if (bytes === 0) return '0 B';
            const k = 1024;
            const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(precision)) + ' ' + sizes[i];
        }

        function applyTranslation(lang) {
            document.querySelectorAll('[data-lang-key]').forEach(element => {
                const key = element.getAttribute('data-lang-key');
                if (translations[key] && translations[key][lang]) {
                    element.textContent = translations[key][lang];
                }
            });

            document.querySelectorAll('[data-lang-placeholder]').forEach(element => {
                const key = element.getAttribute('data-lang-placeholder');
                if (translations[key] && translations[key][lang]) {
                    element.placeholder = translations[key][lang];
                }
            });

            // Special handling for "of X used" text in storage info
            const storageTextElement = document.getElementById('storageText');
            if (storageTextElement) {
                const usedBytes = <?php echo $usedStorageBytes; ?>;
                const totalBytes = <?php echo $totalStorageBytes; ?>;
                storageTextElement.textContent = `${formatBytes(usedBytes)} ${translations['ofUsed'][lang]} ${formatBytes(totalBytes)} ${translations['usedText' + (lang === 'id' ? 'Id' : 'En')]}`;
            }

            // Special handling for search results text
            const searchResultsSpan = document.querySelector('.breadcrumbs span[data-lang-key="searchResultsFor"]');
            if (searchResultsSpan) {
                const originalQuery = "<?php echo htmlspecialchars($searchQuery); ?>";
                searchResultsSpan.textContent = `${translations['searchResultsFor'][lang]} "${originalQuery}"`;
            }

            // Special handling for folder/file type labels in grid view
            document.querySelectorAll('.grid-thumbnail .file-type-label').forEach(element => {
                const key = element.getAttribute('data-lang-key');
                if (key && translations[key] && translations[key][lang]) {
                    element.textContent = translations[key][lang];
                }
            });

            // Special handling for file type labels like "CAD File" or "ZIP File"
            document.querySelectorAll('.grid-thumbnail span').forEach(element => {
                if (element.textContent.includes('CAD File')) {
                    element.textContent = translations['cadFile'][lang];
                } else if (element.textContent.includes('File')) {
                    const fileExt = element.textContent.split(' ')[0];
                    element.textContent = `${fileExt} ${translations['file'][lang]}`;
                }
            });

            // Update placeholder for rename modal
            const renameItemTypeSpan = document.getElementById('renameItemType');
            if (renameItemTypeSpan && renameItemTypeSpan.textContent) {
                const originalType = renameItemTypeSpan.textContent;
                if (originalType === 'Folder' || originalType === 'File') {
                    renameItemTypeSpan.textContent = translations[originalType.toLowerCase()][lang];
                }
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const uploadFileBtn = document.getElementById('uploadFileBtn');
            const createFolderBtn = document.getElementById('createFolderBtn');
            const deleteSelectedBtn = document.getElementById('deleteSelectedBtn');
            
            // Dropdown elements (main toolbar)
            const archiveDropdownContainer = document.querySelector('.toolbar .archive-dropdown-container');
            const archiveSelectedBtn = document.getElementById('archiveSelectedBtn');
            const archiveDropdownContent = document.querySelector('.toolbar .archive-dropdown-content');

            const sizeFilterDropdownContainer = document.querySelector('.toolbar .size-filter-dropdown-container');
            const sizeFilterBtn = document.getElementById('sizeFilterBtn');
            const sizeFilterDropdownContent = document.querySelector('.toolbar .size-filter-dropdown-content');

            const fileTypeFilterDropdownContainer = document.querySelector('.toolbar .file-type-filter-dropdown-container');
            const fileTypeFilterBtn = document.getElementById('fileTypeFilterBtn');
            const fileTypeFilterDropdownContent = document.querySelector('.toolbar .file-type-filter-dropdown-content');

            // Dropdown elements (header)
            const archiveSelectedBtnHeader = document.getElementById('archiveSelectedBtnHeader');
            const fileTypeFilterBtnHeader = document.getElementById('fileTypeFilterBtnHeader');
            const sizeFilterBtnHeader = document.getElementById('sizeFilterBtnHeader'); // NEW
            const listViewBtnHeader = document.getElementById('listViewBtnHeader'); // NEW
            const gridViewBtnHeader = document.getElementById('gridViewBtnHeader'); // NEW


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
            const searchInput = document.getElementById('searchInput'); // Desktop search
            const searchInputMobile = document.getElementById('searchInputMobile'); // Mobile search
            const customNotification = document.getElementById('customNotification');

            // New elements for Share Link
            const shareLinkModal = document.getElementById('shareLinkModal');
            const shortLinkOutput = document.getElementById('shortLinkOutput');
            const copyShortLinkBtn = document.getElementById('copyShortLinkBtn');

            // Context Menu elements
            const contextMenu = document.getElementById('context-menu'); // Changed from 'contextMenu' to 'context-menu'
            const contextRename = document.querySelector('#context-menu [data-action="rename"]');
            const contextDownload = document.querySelector('#context-menu [data-action="download"]');
            const contextShare = document.querySelector('#context-menu [data-action="share"]');
            const contextExtract = document.querySelector('#context-menu [data-action="extract"]');
            const contextToggleStar = document.querySelector('#context-menu [data-action="toggle-star"]'); // Changed to toggle-star
            const contextDelete = document.querySelector('#context-menu [data-action="delete"]');

            // Mobile sidebar elements
            const sidebar = document.querySelector('.sidebar');
            const sidebarToggleBtn = document.getElementById('sidebarToggleBtn');
            const mobileOverlay = document.getElementById('mobileOverlay');
            const myDriveTitle = document.querySelector('.my-drive-title');
            const desktopSearchBar = document.querySelector('.search-bar-desktop');
            const mobileSearchBar = document.querySelector('.search-bar-mobile');

            // Sidebar menu items for active state management
            const sidebarMenuItems = document.querySelectorAll('.sidebar-menu a');
            const mainContent = document.getElementById('mainContent'); // Get main-content for animations

            let activeUploads = new Map();
            let currentContextItem = null; // To store the item clicked for context menu

            // Variables for long press
            let lpTimer = null;
            let lpStart = null;
            const longPressDuration = 600; // milliseconds
            const longPressMoveThreshold = 10; // pixels

            // Current state variables for AJAX filtering/sorting
            let currentFolderId = <?php echo json_encode($currentFolderId); ?>;
            let currentSearchQuery = <?php echo json_encode($searchQuery); ?>;
            let currentSizeFilter = <?php echo json_encode($sizeFilter); ?>; // Changed from releaseFilter
            let currentFileTypeFilter = <?php echo json_encode($fileTypeFilter); ?>;

            // PHP variables passed to JS
            const currentUserRole = <?php echo json_encode($currentUserRole); ?>;
            const restrictedFileTypes = <?php echo json_encode($restrictedFileTypes); ?>; // Array of restricted extensions

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
            // This function is now primarily handled by PHP's getFontAwesomeIconClass
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
                    // ... (existing cases) ...
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
                    case 'x_b': return 'fa-cube'; // NEW: CAD Icon for JS
                    default: return 'fa-file';
                }
            }

            // Function to get file color class based on extension (for JS side, if needed for dynamic elements)
            // This function is now primarily handled by PHP's getFileColorClassPhp
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
            function showNotification(messageKey, type, dynamicContent = '') {
                let message = translations[messageKey] ? translations[messageKey][currentLanguage] : messageKey;
                if (dynamicContent) {
                    message += ` ${dynamicContent}`;
                }
                customNotification.innerHTML = message;
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
                // Check if storage is full before opening modal
                if (uploadFileBtn.disabled) {
                    showNotification('storageFullCannotUpload', 'error');
                    return;
                }
                openModal(uploadFileModal);
                fileToUploadInput.value = ''; 
                uploadPreviewList.innerHTML = '';
                startUploadBtn.style.display = 'none';
            });

            createFolderBtn.addEventListener('click', () => {
                // Check if storage is full before opening modal
                if (createFolderBtn.disabled) {
                    showNotification('storageFullCannotCreateFolder', 'error');
                    return;
                }
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
                // Main toolbar dropdowns
                if (archiveDropdownContainer && !archiveDropdownContainer.contains(event.target)) {
                    archiveDropdownContainer.classList.remove('show');
                }
                if (sizeFilterDropdownContainer && !sizeFilterDropdownContainer.contains(event.target)) {
                    sizeFilterDropdownContainer.classList.remove('show');
                }
                if (fileTypeFilterDropdownContainer && !fileTypeFilterDropdownContainer.contains(event.target)) {
                    fileTypeFilterDropdownContainer.classList.remove('show');
                }

                // Header toolbar dropdowns (now toolbar-filter-buttons)
                const headerArchiveDropdownContainer = document.querySelector('.toolbar-filter-buttons .archive-dropdown-container');
                const headerFileTypeDropdownContainer = document.querySelector('.toolbar-filter-buttons .file-type-filter-dropdown-container');
                const headerSizeFilterDropdownContainer = document.querySelector('.toolbar-filter-buttons .size-filter-dropdown-container'); // NEW
                const headerViewToggle = document.querySelector('.toolbar-filter-buttons .view-toggle'); // NEW

                if (headerArchiveDropdownContainer && !headerArchiveDropdownContainer.contains(event.target)) {
                    headerArchiveDropdownContainer.classList.remove('show');
                }
                if (headerFileTypeDropdownContainer && !headerFileTypeDropdownContainer.contains(event.target)) {
                    headerFileTypeDropdownContainer.classList.remove('show');
                }
                if (headerSizeFilterDropdownContainer && !headerSizeFilterDropdownContainer.contains(event.target)) { // NEW
                    headerSizeFilterDropdownContainer.classList.remove('show');
                }
                // NEW: Close view toggle dropdown if clicked outside
                if (headerViewToggle && !headerViewToggle.contains(event.target)) {
                    // No specific class to remove, just ensure it's not active if it has one
                }


                // Close context menu if clicked outside
                if (!contextMenu.contains(event.target)) {
                    hideContextMenu(); // Use the new hide function
                }
                // Close mobile sidebar if overlay is clicked
                if (event.target == mobileOverlay && sidebar.classList.contains('show-mobile-sidebar')) {
                    sidebar.classList.remove('show-mobile-sidebar');
                    mobileOverlay.classList.remove('show');
                }
            });

            // --- View Toggle Logic ---
            function setupViewToggle(listViewBtnElement, gridViewBtnElement) {
                listViewBtnElement.addEventListener('click', () => {
                    listViewBtnElement.classList.add('active');
                    gridViewBtnElement.classList.remove('active');
                    fileListView.classList.remove('hidden');
                    fileGridView.classList.add('hidden');
                    localStorage.setItem('fileView', 'list');
                });

                gridViewBtnElement.addEventListener('click', () => {
                    gridViewBtnElement.classList.add('active');
                    listViewBtnElement.classList.remove('active');
                    fileGridView.classList.remove('hidden');
                    fileListView.classList.add('hidden');
                    localStorage.setItem('fileView', 'grid');
                });
            }

            setupViewToggle(listViewBtn, gridViewBtn); // For main toolbar
            setupViewToggle(listViewBtnHeader, gridViewBtnHeader); // For header button

            const savedView = localStorage.getItem('fileView');
            if (savedView === 'grid') {
                gridViewBtn.click(); // Simulate click to activate
                gridViewBtnHeader.click(); // Simulate click for header button
            } else {
                listViewBtn.click(); // Simulate click to activate
                listViewBtnHeader.click(); // Simulate click for header button
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
            deleteSelectedBtn.addEventListener('click', async () => {
                const checkboxes = document.querySelectorAll('.file-checkbox:checked');
                const selectedItems = Array.from(checkboxes).map(cb => {
                    return { id: cb.dataset.id, type: cb.dataset.type };
                });

                if (selectedItems.length === 0) {
                    showNotification('pleaseSelectToDelete', 'error');
                    return;
                }

                // --- NEW: Check for restricted file types in selected items ---
                const hasRestricted = selectedItems.some(item => {
                    if (item.type === 'file') {
                        const fileElement = document.querySelector(`.file-item[data-id="${CSS.escape(item.id)}"]`);
                        const fileType = fileElement ? fileElement.dataset.fileType : '';
                        return restrictedFileTypes.includes(fileType);
                    }
                    return false;
                });

                if (hasRestricted && currentUserRole !== 'admin' && currentUserRole !== 'moderator') {
                    showNotification('noPermissionRestrictedDelete', 'error');
                    return;
                }
                // --- END NEW ---

                if (!confirm(translations['confirmDelete'][currentLanguage])) {
                    return;
                }

                try {
                    const response = await fetch('delete_selected.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ items: selectedItems })
                    });
                    const data = await response.json();
                    if (data.success) {
                        showNotification('itemsDeletedSuccess', 'success');
                        updateFileListAndFolders(); // Update content without full reload
                    } else {
                        showNotification('failedToDelete', 'error', data.message);
                    }
                } catch (error) {
                    console.error('Error:', error);
                    showNotification('errorDeleting', 'error');
                }
            });

            // --- Archive Selected Files/Folders ---
            function setupArchiveDropdown(buttonId, dropdownContentSelector) {
                const button = document.getElementById(buttonId);
                const dropdownContent = document.querySelector(dropdownContentSelector);
                const dropdownContainer = button.closest('.dropdown-container');

                if (!button || !dropdownContent || !dropdownContainer) return;

                button.addEventListener('click', (event) => {
                    event.stopPropagation();
                    // Close other dropdowns in the same toolbar-right section
                    document.querySelectorAll('.toolbar-right .dropdown-container.show').forEach(openDropdown => {
                        if (openDropdown !== dropdownContainer) {
                            openDropdown.classList.remove('show');
                        }
                    });
                    document.querySelectorAll('.toolbar-filter-buttons .dropdown-container.show').forEach(openDropdown => {
                        if (openDropdown !== dropdownContainer) {
                            openDropdown.classList.remove('show');
                        }
                    });
                    dropdownContainer.classList.toggle('show');
                });

                dropdownContent.querySelectorAll('a').forEach(link => {
                    link.addEventListener('click', async (event) => {
                        event.preventDefault();
                        dropdownContainer.classList.remove('show');

                        const format = event.target.dataset.format;
                        const checkboxes = document.querySelectorAll('.file-checkbox:checked');
                        const selectedItems = Array.from(checkboxes).map(cb => {
                            return { id: cb.dataset.id, type: cb.dataset.type };
                        });

                        if (selectedItems.length === 0) {
                            showNotification('pleaseSelectToArchive', 'error');
                            return;
                        }

                        // --- NEW: Check for restricted file types in selected items ---
                        const hasRestricted = selectedItems.some(item => {
                            if (item.type === 'file') {
                                const fileElement = document.querySelector(`.file-item[data-id="${CSS.escape(item.id)}`);
                                const fileType = fileElement ? fileElement.dataset.fileType : '';
                                return restrictedFileTypes.includes(fileType);
                            }
                            return false;
                        });

                        if (hasRestricted && currentUserRole !== 'admin' && currentUserRole !== 'moderator') {
                            showNotification('noPermissionRestrictedArchive', 'error');
                            return;
                        }
                        // --- END NEW ---

                        if (!confirm(`${translations['confirmArchive'][currentLanguage]} ${format.toUpperCase()} format?`)) {
                            return;
                        }

                        showNotification('startingArchive', 'info');

                        try {
                            const response = await fetch('compress.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                },
                                body: JSON.stringify({ 
                                    items: selectedItems, 
                                    format: format,
                                    current_folder_id: currentFolderId
                                })
                            });
                            const data = await response.json();
                            if (data.success) {
                                showNotification(data.message, 'success'); // Message from backend is already translated
                                updateFileListAndFolders();
                            } else {
                                showNotification('failedToArchive', 'error', data.message);
                            }
                        } catch (error) {
                            console.error('Error:', error);
                            showNotification('errorArchiving', 'error');
                        }
                    });
                });
            }

            setupArchiveDropdown('archiveSelectedBtn', '.toolbar .archive-dropdown-container');
            setupArchiveDropdown('archiveSelectedBtnHeader', '.toolbar-filter-buttons .archive-dropdown-container');


            // --- File Type Filter ---
            function setupFileTypeFilterDropdown(buttonId, dropdownContentSelector) {
                const button = document.getElementById(buttonId);
                const dropdownContent = document.querySelector(dropdownContentSelector);
                const dropdownContainer = button.closest('.dropdown-container');

                if (!button || !dropdownContent || !dropdownContainer) return;

                button.addEventListener('click', (event) => {
                    event.stopPropagation();
                    // Close other dropdowns in the same toolbar-right section
                    document.querySelectorAll('.toolbar-right .dropdown-container.show').forEach(openDropdown => {
                        if (openDropdown !== dropdownContainer) {
                            openDropdown.classList.remove('show');
                        }
                    });
                    document.querySelectorAll('.toolbar-filter-buttons .dropdown-container.show').forEach(openDropdown => {
                        if (openDropdown !== dropdownContainer) {
                            openDropdown.classList.remove('show');
                        }
                    });
                    dropdownContainer.classList.toggle('show');
                });

                dropdownContent.querySelectorAll('a').forEach(link => {
                    link.addEventListener('click', (event) => {
                        event.preventDefault();
                        dropdownContainer.classList.remove('show');
                        currentFileTypeFilter = event.target.dataset.filter;
                        updateFileListAndFolders();
                    });
                });
            }

            setupFileTypeFilterDropdown('fileTypeFilterBtn', '.toolbar .file-type-filter-dropdown-container');
            setupFileTypeFilterDropdown('fileTypeFilterBtnHeader', '.toolbar-filter-buttons .file-type-filter-dropdown-container');


            // --- Size Filter (Replaces Release Date and Sort Order) ---
            function setupSizeFilterDropdown(buttonId, dropdownContentSelector) {
                const button = document.getElementById(buttonId);
                const dropdownContent = document.querySelector(dropdownContentSelector);
                const dropdownContainer = button.closest('.dropdown-container');

                if (!button || !dropdownContent || !dropdownContainer) return;

                button.addEventListener('click', (event) => {
                    event.stopPropagation();
                    // Close other dropdowns in the same toolbar-right section
                    document.querySelectorAll('.toolbar-right .dropdown-container.show').forEach(openDropdown => {
                        if (openDropdown !== dropdownContainer) {
                            openDropdown.classList.remove('show');
                        }
                    });
                    document.querySelectorAll('.toolbar-filter-buttons .dropdown-container.show').forEach(openDropdown => {
                        if (openDropdown !== dropdownContainer) {
                            openDropdown.classList.remove('show');
                        }
                    });
                    dropdownContainer.classList.toggle('show');
                });

                dropdownContent.querySelectorAll('a').forEach(link => {
                    link.addEventListener('click', (event) => {
                        event.preventDefault();
                        dropdownContainer.classList.remove('show');
                        currentSizeFilter = event.target.dataset.size;
                        updateFileListAndFolders();
                    });
                });
            }

            setupSizeFilterDropdown('sizeFilterBtn', '.toolbar .size-filter-dropdown-container');
            setupSizeFilterDropdown('sizeFilterBtnHeader', '.toolbar-filter-buttons .size-filter-dropdown-container');


            // --- Rename File/Folder ---
            function renameFile(id) {
                const item = document.querySelector(`.file-item[data-id="${CSS.escape(id)}"]`);
                if (!item) return;

                const itemType = item.dataset.type;
                const itemName = item.dataset.name;
                const fileType = item.dataset.fileType;

                // Restriction check
                if (
                    itemType === 'file' &&
                    restrictedFileTypes.includes(fileType) &&
                    currentUserRole !== 'admin' &&
                    currentUserRole !== 'moderator'
                ) {
                    showNotification('noPermissionRestrictedRename', 'error');
                    return;
                }

                document.getElementById('renameItemId').value = id;
                document.getElementById('renameItemActualType').value = itemType;
                document.getElementById('newName').value = itemName;

                // ✅ Safe check before setting textContent
                const renameItemTypeElement = document.getElementById('renameItemType');
                if (renameItemTypeElement) {
                    renameItemTypeElement.textContent = translations[itemType]?.[currentLanguage] ?? itemType;
                }

                openModal(renameModal);
            }

            // --- Download File ---
            function downloadFile(id) {
                const item = document.querySelector(`.file-item[data-id="${CSS.escape(id)}"]`);
                if (!item) return;
                const filePath = item.dataset.path;
                const fileName = item.dataset.name;
                const fileType = item.dataset.fileType; // Get file type for restriction check

                // --- NEW: Check for restricted file types ---
                if (restrictedFileTypes.includes(fileType) && currentUserRole !== 'admin' && currentUserRole !== 'moderator') {
                    showNotification('noPermissionRestrictedDownload', 'error');
                    return;
                }
                // --- END NEW ---

                const link = document.createElement('a');
                link.href = filePath;
                link.download = fileName;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            }

            // --- Individual Delete File/Folder ---
            async function deleteFile(id) {
                const item = document.querySelector(`.file-item[data-id="${CSS.escape(id)}"]`);
                if (!item) return;
                const type = item.dataset.type;
                const fileType = item.dataset.fileType; // Get file type for restriction check

                // --- NEW: Check for restricted file types ---
                if (type === 'file' && restrictedFileTypes.includes(fileType) && currentUserRole !== 'admin' && currentUserRole !== 'moderator') {
                    showNotification('noPermissionRestrictedDelete', 'error');
                    return;
                }
                // --- END NEW ---

                const confirmMessage = type === 'file'
                    ? translations['confirmDelete'][currentLanguage] // Use generic confirm message for files too
                    : translations['confirmDelete'][currentLanguage];

                if (confirm(confirmMessage)) {
                    try {
                        const response = await fetch('delete.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({ id: id, type: type })
                        });
                        const data = await response.json();
                        if (data.success) {
                            showNotification(data.message, 'success'); // Message from backend is already translated
                            updateFileListAndFolders(); // Update content without full reload
                        } else {
                            showNotification(data.message, 'error'); // Message from backend is already translated
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        showNotification('errorDeleting', 'error');
                    }
                }
            }

            // --- Extract ZIP File ---
            async function extractZipFile(id) {
                const item = document.querySelector(`.file-item[data-id="${CSS.escape(id)}"]`);
                if (!item) return;
                const filePath = item.dataset.path; // Path relatif dari file ZIP
                const fileType = item.dataset.fileType; // Get file type for restriction check

                // --- NEW: Check for restricted file types ---
                if (restrictedFileTypes.includes(fileType) && currentUserRole !== 'admin' && currentUserRole !== 'moderator') {
                    showNotification('noPermissionRestrictedExtract', 'error');
                    return;
                }
                // --- END NEW ---

                if (!confirm(translations['confirmExtract'][currentLanguage])) {
                    return;
                }

                showNotification('extractingZip', 'info');

                try {
                    const response = await fetch('extract.php', { // Endpoint baru untuk ekstrak
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ file_id: id, file_path: filePath })
                    });
                    const data = await response.json();
                    if (data.success) {
                        showNotification(data.message, 'success'); // Message from backend is already translated
                        updateFileListAndFolders(); // Refresh list to show new folder if extracted to current view
                    } else {
                        showNotification('extractionFailed', 'error', data.message);
                    }
                } catch (error) {
                    console.error('Error:', error);
                    showNotification('errorExtracting', 'error');
                }
            }

            // --- Toggle Star (Pin to Priority) ---
            async function toggleStar(id, type) {
                const item = document.querySelector(`.file-item[data-id="${CSS.escape(id)}"]`);
                if (!item) return;
                const itemName = item.dataset.name;
                const fileType = item.dataset.fileType; // Get file type for restriction check

                // --- NEW: Check for restricted file types ---
                if (type === 'file' && restrictedFileTypes.includes(fileType) && currentUserRole !== 'admin' && currentUserRole !== 'moderator') {
                    showNotification('noPermissionRestrictedPin', 'error');
                    return;
                }
                // --- END NEW ---

                try {
                    const response = await fetch('toggle_star.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ id: id, type: type, name: itemName }) // Pass item name
                    });
                    const data = await response.json();
                    if (data.success) {
                        showNotification(data.message, 'success'); // Message from backend is already translated
                        // No need to update UI here, as it's just a star/unstar action
                        // The priority_files.php page will handle its own loading
                    } else {
                        showNotification('failedToToggleStar', 'error', data.message);
                    }
                } catch (error) {
                    console.error('Error toggling star:', error);
                    showNotification('errorTogglingStar', 'error');
                }
            }

            // --- Form Submissions for Create Folder and Rename ---
            const createFolderForm = document.getElementById('createFolderForm');
            const renameForm = document.getElementById('renameForm');

            // FIX: Ensure createFolderForm submission is handled correctly
            createFolderForm.addEventListener('submit', async function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                
                // Disable the submit button to prevent multiple submissions
                const submitButton = this.querySelector('button[type="submit"]');
                if (submitButton) {
                    submitButton.disabled = true;
                }

                try {
                    const response = await fetch(this.action, {
                        method: 'POST',
                        body: formData
                    });
                    const data = await response.json();
                    if (data.success) {
                        showNotification('folderCreatedSuccess', 'success');
                        closeModal(createFolderModal);
                        updateFileListAndFolders(); // Update content without full reload
                    } else {
                        showNotification('failedToCreateFolder', 'error', data.message);
                    }
                } catch (error) {
                    console.error('Error:', error);
                    showNotification('errorCreatingFolder', 'error');
                } finally {
                    // Re-enable the submit button regardless of success or failure
                    if (submitButton) {
                        submitButton.disabled = false;
                    }
                }
            });

            renameForm.addEventListener('submit', async function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                const itemType = formData.get('itemType');
                const itemId = formData.get('itemId');
                
                // Disable the submit button
                const submitButton = this.querySelector('button[type="submit"]');
                if (submitButton) {
                    submitButton.disabled = true;
                }

                // --- NEW: Check for restricted file types before renaming ---
                if (itemType === 'file') {
                    const itemElement = document.querySelector(`.file-item[data-id="${CSS.escape(itemId)}"]`);
                    const fileType = itemElement ? itemElement.dataset.fileType : '';
                    if (restrictedFileTypes.includes(fileType) && currentUserRole !== 'admin' && currentUserRole !== 'moderator') {
                        showNotification('noPermissionRestrictedRename', 'error');
                        if (submitButton) {
                            submitButton.disabled = false;
                        }
                        return;
                    }
                }
                // --- END NEW ---

                try {
                    const response = await fetch(this.action, {
                        method: 'POST',
                        body: formData
                    });
                    const data = await response.json();
                    if (data.success) {
                        showNotification('itemRenamedSuccess', 'success');
                        closeModal(renameModal);
                        updateFileListAndFolders(); // Update content without full reload
                    } else {
                        showNotification('failedToRename', 'error', data.message);
                    }
                } catch (error) {
                    console.error('Error:', error);
                    showNotification('errorRenaming', 'error');
                } finally {
                    // Re-enable the submit button
                    if (submitButton) {
                        submitButton.disabled = false;
                    }
                }
            });

            // --- Search Functionality ---
            function performSearch(query) {
                currentSearchQuery = query.trim();
                updateFileListAndFolders();
            }

            searchInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    performSearch(this.value);
                }
            });

            searchInputMobile.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    performSearch(this.value);
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
                        const fileExt = file.name.split('.').pop().toLowerCase();

                        // --- NEW: Check for restricted file types during preview ---
                        if (restrictedFileTypes.includes(fileExt) && currentUserRole !== 'admin' && currentUserRole !== 'moderator') {
                            showNotification('fileRestrictedUpload', 'error', `'${file.name}'`);
                            // Do not add to preview list, or add with an error state
                            const uploadItemHtml = `
                                <div class="upload-item" id="${fileId}">
                                    <i class="fas fa-ban file-icon file-color-error"></i>
                                    <div class="upload-item-info">
                                        <strong>${file.name}</strong>
                                        <div class="upload-progress-container">
                                            <div class="upload-progress-bar" style="width: 100%; background-color: var(--error-color);"></div>
                                        </div>
                                    </div>
                                    <span class="upload-status-icon error"><i class="fas fa-times-circle"></i></span>
                                </div>
                            `;
                            uploadPreviewList.insertAdjacentHTML('beforeend', uploadItemHtml);
                            return; // Skip this file
                        }
                        // --- END NEW ---

                        // Use JS functions for dynamic elements if needed, or rely on PHP for initial render
                        const iconClass = getFileIconClass(file.name); // Assuming getFileIconClass is defined in JS
                        const colorClass = getFileColorClass(file.name); // Assuming getFileColorClass is defined in JS
                        
                        const uploadItemHtml = `
                            <div class="upload-item" id="${fileId}">
                                <i class="fas ${iconClass} file-icon ${colorClass}"></i>
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
                    showNotification('noFilesToUpload', 'error');
                    return;
                }
                // Check if storage is full before starting upload
                if (this.disabled) {
                    showNotification('storageFullCannotUpload', 'error');
                    return;
                }

                closeModal(uploadFileModal);
                openModal(uploadPreviewModal);

                let allUploadsCompleted = 0;
                const filesToUpload = Array.from(activeUploads.values()).filter(item => {
                    const fileExt = item.file.name.split('.').pop().toLowerCase();
                    return !(restrictedFileTypes.includes(fileExt) && currentUserRole !== 'admin' && currentUserRole !== 'moderator');
                });
                const totalUploads = filesToUpload.length;

                if (totalUploads === 0) {
                    showNotification('noFilesEligible', 'info');
                    setTimeout(() => closeModal(uploadPreviewModal), 1000);
                    return;
                }

                filesToUpload.forEach((item) => {
                    const controller = new AbortController();
                    item.controller = controller;
                    uploadFile(item.file, item.element.id, controller.signal).then(() => {
                        allUploadsCompleted++;
                        if (allUploadsCompleted === totalUploads) {
                            // All uploads finished, refresh the file list
                            setTimeout(() => {
                                updateFileListAndFolders();
                                closeModal(uploadPreviewModal);
                            }, 1000); // Give a small delay for visual feedback
                        }
                    });
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
                        progressBar.style.backgroundColor = 'var(--success-color)';
                        statusIcon.innerHTML = '<i class="fas fa-check"></i>';
                        statusIcon.classList.remove('processing', 'error', 'cancelled');
                        statusIcon.classList.add('success');
                        uploadItemElement.classList.add('complete');
                        showNotification('fileUploadedSuccess', 'success', `'${file.name}'`);
                    } else {
                        throw new Error(data.message || 'Unknown error during upload.');
                    }
                } catch (error) {
                    if (error.name === 'AbortError') {
                        progressBar.style.width = '100%';
                        progressBar.style.backgroundColor = 'var(--warning-color)';
                        statusIcon.innerHTML = '<i class="fas fa-times-circle"></i>';
                        statusIcon.classList.remove('processing', 'error');
                        statusIcon.classList.add('cancelled');
                        showNotification('uploadCancelled', 'error', `'${file.name}'`);
                    } else {
                        progressBar.style.width = '100%';
                        progressBar.style.backgroundColor = 'var(--error-color)';
                        statusIcon.innerHTML = '<i class="fas fa-times-circle"></i>';
                        statusIcon.classList.remove('processing', 'success', 'cancelled');
                        statusIcon.classList.add('error');
                        showNotification('failedToUpload', 'error', `'${file.name}': ${error.message}`);
                    }
                    uploadItemElement.classList.add('complete');
                } finally {
                    cancelButton.style.display = 'none';
                    activeUploads.delete(fileId);
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
                        progressBar.style.backgroundColor = 'var(--warning-color)';
                        statusIcon.innerHTML = '<i class="fas fa-times-circle"></i>';
                        statusIcon.classList.remove('processing', 'success', 'error');
                        statusIcon.classList.add('cancelled');
                        button.style.display = 'none';
                        uploadItemElement.classList.add('complete');
                        showNotification('uploadManuallyCancelled', 'error', `'${uploadItem.file.name}'`);
                        
                        if (activeUploads.size === 0) {
                            setTimeout(() => {
                                updateFileListAndFolders();
                                closeModal(uploadPreviewModal);
                            }, 1000);
                        }
                    }
                }
            });

            // --- Share Link Functionality ---
            async function shareFileLink(id) {
                const item = document.querySelector(`.file-item[data-id="${CSS.escape(id)}"]`);
                if (!item) return;
                const itemType = item.dataset.type; // Will always be 'file' for this button
                const fileType = item.dataset.fileType; // Get file type for restriction check

                if (itemType !== 'file') {
                    showNotification('onlyFilesCanBeShared', 'error');
                    return;
                }

                // --- NEW: Check for restricted file types ---
                if (restrictedFileTypes.includes(fileType) && currentUserRole !== 'admin' && currentUserRole !== 'moderator') {
                    showNotification('noPermissionRestrictedShare', 'error');
                    return;
                }
                // --- END NEW ---

                showNotification('generatingShareLink', 'info');

                try {
                    const response = await fetch('generate_share_link.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded', // Important for $_POST
                        },
                        body: `file_id=${id}`
                    });

                    const data = await response.json();

                    if (data.success) {
                        shortLinkOutput.value = data.shortlink;
                        openModal(shareLinkModal);
                        showNotification('shareLinkGenerated', 'success');
                    } else {
                        showNotification('failedToGenerateShareLink', 'error', data.message);
                    }
                } catch (error) {
                    console.error('Error:', error);
                    showNotification('errorGeneratingShareLink', 'error');
                }
            }

            copyShortLinkBtn.addEventListener('click', () => {
                shortLinkOutput.select();
                shortLinkOutput.setSelectionRange(0, 99999); // For mobile devices
                document.execCommand('copy');
                showNotification('linkCopied', 'success');
            });

            /*** Context menu element ***/
            function showContextMenuFor(fileEl, x, y) {
                if (!fileEl) return;
                // attach target id
                contextMenu.dataset.targetId = fileEl.dataset.id;
                contextMenu.dataset.targetType = fileEl.dataset.type;
                contextMenu.dataset.targetName = fileEl.dataset.name; // Pass item name to context menu
                contextMenu.dataset.targetFileType = fileEl.dataset.fileType || ''; // For files

                // Show/hide options based on item type and user role
                const itemType = fileEl.dataset.type;
                const fileType = fileEl.dataset.fileType;
                const isRestricted = restrictedFileTypes.includes(fileType);
                const canAccessRestricted = (currentUserRole === 'admin' || currentUserRole === 'moderator');

                // Reset all context menu items to hidden first
                contextRename.classList.remove('hidden');
                contextDownload.classList.remove('hidden');
                contextShare.classList.remove('hidden');
                contextExtract.classList.remove('hidden');
                contextToggleStar.classList.remove('hidden');
                contextDelete.classList.remove('hidden');

                if (itemType === 'folder') {
                    contextDownload.classList.add('hidden');
                    contextShare.classList.add('hidden');
                    contextExtract.classList.add('hidden');
                } else if (itemType === 'file') {
                    if (fileType !== 'zip') {
                        contextExtract.classList.add('hidden');
                    }
                    // --- NEW: Hide actions for restricted files if user is not admin/moderator ---
                    if (isRestricted && !canAccessRestricted) {
                        contextRename.classList.add('hidden');
                        contextDownload.classList.add('hidden');
                        contextShare.classList.add('hidden');
                        contextExtract.classList.add('hidden');
                        contextToggleStar.classList.add('hidden');
                        contextDelete.classList.add('hidden');
                    }
                    // --- END NEW ---
                }

                // position - keep inside viewport
                const rect = contextMenu.getBoundingClientRect();
                const menuWidth = rect.width || 200;
                const menuHeight = rect.height || 220;

                let finalLeft = x;
                let finalTop = y;

                // If menu too close to right edge, shift left
                if (x + menuWidth > window.innerWidth) {
                    finalLeft = window.innerWidth - menuWidth - 10; // 10px padding from right edge
                }

                // If menu too close to bottom edge, shift up
                if (y + menuHeight > window.innerHeight) {
                    finalTop = window.innerHeight - menuHeight - 10; // 10px padding from bottom edge
                }

                contextMenu.style.left = finalLeft + 'px';
                contextMenu.style.top = finalTop + 'px';
                contextMenu.classList.add('visible');
                contextMenu.hidden = false;
                // prevent immediate click opening file
                suppressOpenClickTemporarily();
            }

            function hideContextMenu(){ 
                contextMenu.classList.remove('visible'); 
                contextMenu.hidden = true; 
                contextMenu.dataset.targetId = '';
                contextMenu.dataset.targetType = '';
                contextMenu.dataset.targetName = ''; // Clear item name
                contextMenu.dataset.targetFileType = '';
            }

            /*** Prevent immediate click after open context (so right-click/long-press doesn't also open file) */
            let _suppressOpenUntil = 0;
            function suppressOpenClickTemporarily(ms=350){
                _suppressOpenUntil = Date.now() + ms;
            }

            /*** Open file action (example) */
            function openFileById(id){
                if (Date.now() < _suppressOpenUntil) { return; } // Suppress if context menu just opened
                const fileItem = document.querySelector(`.file-item[data-id="${CSS.escape(id)}"]`);
                if (fileItem) {
                    const itemType = fileItem.dataset.type;
                    const fileType = fileItem.dataset.fileType;
                    const isRestricted = restrictedFileTypes.includes(fileType);
                    const canAccessRestricted = (currentUserRole === 'admin' || currentUserRole === 'moderator');

                    // --- NEW: Prevent opening restricted files for non-admin/moderator ---
                    if (itemType === 'file' && isRestricted && !canAccessRestricted) {
                        showNotification('noPermissionRestrictedOpen', 'error');
                        return;
                    }
                    // --- END NEW ---

                    if (itemType === 'file') {
                        window.location.href = `view.php?file_id=${id}`;
                    } else if (itemType === 'folder') {
                        // For folder navigation, use AJAX to update content without full page reload
                        currentFolderId = parseInt(id);
                        currentSearchQuery = ''; // Reset search when navigating folders
                        searchInput.value = ''; // Clear desktop search input
                        searchInputMobile.value = ''; // Clear mobile search input
                        updateFileListAndFolders();
                    }
                }
            }

            /*** Delegated click: open on click (but blocked if context menu just opened) */
            document.addEventListener('click', function(e){
                // item-more button: open menu (works across devices)
                const moreBtn = e.target.closest('.item-more');
                if (moreBtn) {
                    const file = closestFileItem(moreBtn);
                    const r = moreBtn.getBoundingClientRect();
                    showContextMenuFor(file, r.right - 5, r.bottom + 5);
                    e.stopPropagation(); // Prevent click from bubbling to document and closing menu
                    return;
                }

                // normal click to open file (only if not suppressed)
                const file = closestFileItem(e.target);
                // MODIFIED: Only open file/folder if the click is NOT on the checkbox
                if (file && !e.target.classList.contains('file-checkbox')) {
                    openFileById(file.dataset.id);
                } else {
                    // click outside => close menu
                    hideContextMenu();
                }
            });

            /*** Desktop right-click (contextmenu) */
            document.addEventListener('contextmenu', function(e){
                if (! (document.body.classList.contains('desktop') || document.body.classList.contains('tablet-landscape')) ) return; // only desktop and tablet landscape
                const file = closestFileItem(e.target);
                if (file) {
                    e.preventDefault();
                    showContextMenuFor(file, e.clientX, e.clientY);
                } else {
                    hideContextMenu();
                }
            });

            /*** Long-press for touch devices (iPad/tablet/phone)
                Implementation: listen pointerdown, if pointerType touch and hold >600ms => show menu.
                Cancel if pointer moves > threshold or pointerup/cancel before timer.
            ***/
            document.addEventListener('pointerdown', function(e){
                if (! (document.body.classList.contains('mobile') ||
                    document.body.classList.contains('tablet-portrait') ||
                    document.body.classList.contains('device-ipad')) ) return; // Only for mobile and tablet portrait

                const file = closestFileItem(e.target);
                if (!file) return;
                // MODIFIED: Do not trigger long press if the target is the checkbox
                if (e.target.classList.contains('file-checkbox')) return;

                if (e.pointerType !== 'touch') return; // only touch long-press

                const startX = e.clientX, startY = e.clientY;
                lpStart = file;
                lpTimer = setTimeout(()=> {
                    showContextMenuFor(file, startX, startY);
                    lpTimer = null;
                    // Prevent default click behavior after long press
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

            /*** Keyboard support: Enter opens, ContextMenu key / Shift+F10 opens menu for focused item */
            document.addEventListener('keydown', function(e){
                const focused = document.activeElement && document.activeElement.closest && document.activeElement.closest('.file-item');
                if (!focused) return;
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    // MODIFIED: Only open file/folder if the focused element is not a checkbox
                    if (!document.activeElement.classList.contains('file-checkbox')) {
                        openFileById(focused.dataset.id);
                    }
                } else if (e.key === 'ContextMenu' || (e.shiftKey && e.key === 'F10')) {
                    e.preventDefault();
                    const rect = focused.getBoundingClientRect();
                    showContextMenuFor(focused, rect.left + 8, rect.bottom + 8);
                }
            });

            /*** Click inside context menu => execute actions */
            contextMenu.addEventListener('click', function(e){
                const li = e.target.closest('[data-action]');
                if (!li) return;
                const action = li.dataset.action;
                const targetId = contextMenu.dataset.targetId;
                const targetType = contextMenu.dataset.targetType;
                const targetName = contextMenu.dataset.targetName; // Get item name
                const targetFileType = contextMenu.dataset.targetFileType; // Get item file type

                // --- NEW: Check for restricted file types before executing action ---
                const isRestricted = restrictedFileTypes.includes(targetFileType);
                const canAccessRestricted = (currentUserRole === 'admin' || currentUserRole === 'moderator');

                if (isRestricted && !canAccessRestricted && (action === 'rename' || action === 'download' || action === 'share' || action === 'extract' || action === 'toggle-star' || action === 'delete')) {
                    showNotification('noPermissionRestrictedAction', 'error'); // Generic message for restricted actions
                    hideContextMenu();
                    return;
                }
                // --- END NEW ---

                if (action === 'toggle-star') { // Handle toggle-star action
                    toggleStar(targetId, targetType, targetName);
                } else {
                    handleMenuAction(action, targetId);
                }
                hideContextMenu();
            });

            /*** Hide menu on outside clicks/touch */
            document.addEventListener('click', function(e){ 
                if (!e.target.closest('#context-menu') && !e.target.closest('.item-more')) { // Exclude item-more button
                    hideContextMenu(); 
                }
            });
            window.addEventListener('blur', hideContextMenu);

            /*** Menu handlers (placeholders - ganti sesuai API/backend) */
            function handleMenuAction(action, id){
                switch(action){
                    case 'rename': renameFile(id); break;
                    case 'download': downloadFile(id); break;
                    case 'share': shareFileLink(id); break;
                    case 'extract': extractZipFile(id); break; // Added extract
                    case 'delete': deleteFile(id); break;
                    default: console.log('Unknown action', action);
                }
            }

            // --- AJAX Content Update Function ---
            async function updateFileListAndFolders() {
                const params = new URLSearchParams();
                if (currentFolderId !== null) {
                    params.set('folder', currentFolderId);
                }
                if (currentSearchQuery) {
                    params.set('search', currentSearchQuery);
                }
                if (currentSizeFilter && currentSizeFilter !== 'none') { // Changed from releaseFilter
                    params.set('size', currentSizeFilter); // Changed from release
                }
                if (currentFileTypeFilter && currentFileTypeFilter !== 'all') {
                    params.set('file_type', currentFileTypeFilter);
                }

                const url = `index.php?${params.toString()}&ajax=1`; // Add ajax=1 to indicate AJAX request

                try {
                    const response = await fetch(url);
                    const html = await response.text();

                    // Create a temporary div to parse the HTML
                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = html;

                    // Extract the relevant parts
                    const newFileListView = tempDiv.querySelector('#fileListView table tbody').innerHTML;
                    const newFileGridView = tempDiv.querySelector('#fileGridView .file-grid').innerHTML;
                    const newBreadcrumbs = tempDiv.querySelector('.breadcrumbs').innerHTML;
                    const newStorageInfo = tempDiv.querySelector('.storage-info').innerHTML;

                    // Update the DOM
                    document.querySelector('#fileListView table tbody').innerHTML = newFileListView;
                    document.querySelector('#fileGridView .file-grid').innerHTML = newFileGridView;
                    document.querySelector('.breadcrumbs').innerHTML = newBreadcrumbs;
                    document.querySelector('.storage-info').innerHTML = newStorageInfo;

                    // Re-attach event listeners to new elements
                    updateSelectAllCheckboxListener(); // Re-attach select all listener

                    // Update URL in browser history without reloading
                    history.pushState(null, '', `index.php?${params.toString()}`);

                    // Re-apply translation after content update
                    applyTranslation(currentLanguage);

                } catch (error) {
                    console.error('Error updating file list:', error);
                    // Removed: showNotification('failedToUpdateFileList', 'error');
                }
            }

            // Event listener for breadcrumbs (folder navigation)
            document.querySelector('.breadcrumbs').addEventListener('click', function(event) {
                // Check if the clicked element is a folder-breadcrumb-link (for subfolders)
                if (event.target.classList.contains('folder-breadcrumb-link')) {
                    event.preventDefault(); // Prevent default full page reload
                    const href = event.target.getAttribute('href');
                    const url = new URL(href, window.location.origin);
                    const folderId = url.searchParams.get('folder');
                    currentFolderId = folderId ? parseInt(folderId) : null;
                    currentSearchQuery = ''; // Reset search when navigating folders
                    searchInput.value = ''; // Clear desktop search input
                    searchInputMobile.value = ''; // Clear mobile search input
                    updateFileListAndFolders();
                } 
                // Handle "Root" breadcrumb separately
                else if (event.target.closest('#rootBreadcrumb')) {
                    event.preventDefault(); // Prevent default full page reload
                    currentFolderId = null; // Set to null for root
                    currentSearchQuery = ''; // Reset search
                    searchInput.value = '';
                    searchInputMobile.value = '';
                    updateFileListAndFolders(); // Update content via AJAX
                }
            });

            // --- Mobile Sidebar Toggle ---
            sidebarToggleBtn.addEventListener('click', () => {
                sidebar.classList.toggle('show-mobile-sidebar');
                mobileOverlay.classList.toggle('show');
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

            // Set active class for current page in sidebar
            const currentPage = window.location.pathname.split('/').pop();
            sidebarMenuItems.forEach(item => {
                item.classList.remove('active');
                const itemHref = item.getAttribute('href');
                if (itemHref === currentPage || (currentPage === 'index.php' && itemHref === 'index.php')) {
                    item.classList.add('active');
                }
            });

            // Initial call to attach listeners
            updateSelectAllCheckboxListener();

            // Check if the request is an AJAX request
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('ajax') && urlParams.get('ajax') === '1') {
                // If it's an AJAX request, only output the content needed for AJAX update
                // This part should ideally be handled by a separate PHP file that returns JSON or HTML fragments
                // For this example, we'll assume index.php can conditionally render.
                // In a real application, you'd have a dedicated API endpoint.
                // Since the current PHP code already renders the full HTML, we'll just let it render.
                // The JS will then parse it.
            }

            // Apply initial translation on page load
            applyTranslation(currentLanguage);
        });
    </script>
</body>
</html>