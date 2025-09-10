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

$current_user_id = $_SESSION['user_id'];
$baseUploadDir = 'uploads/';

// Path to the starred.json file
$starred_file = 'starred.json';

// Ensure starred.json exists and is readable/writable
if (!file_exists($starred_file)) {
    file_put_contents($starred_file, json_encode([]));
}

// Function to read starred items
function getStarredItems($starred_file) {
    $content = file_get_contents($starred_file);
    return json_decode($content, true) ?: [];
}

// Function to write starred items
function saveStarredItems($starred_file, $items) {
    file_put_contents($starred_file, json_encode($items, JSON_PRETTY_PRINT));
}

// Handle AJAX requests for user list and starred items list
if (isset($_GET['action'])) {
    header('Content-Type: application/json');

    if ($_GET['action'] === 'get_users') {
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = 20; // 4 rows * 5 users
        $offset = ($page - 1) * $limit;

        $users = [];
        $total_users = 0;

        // Get total users
        $stmt_count = $conn->prepare("SELECT COUNT(id) AS total FROM users");
        $stmt_count->execute();
        $result_count = $stmt_count->get_result();
        $total_users = $result_count->fetch_assoc()['total'];
        $stmt_count->close();

        // Get users for current page
        $stmt_users = $conn->prepare("SELECT id, username, full_name, profile_picture FROM users LIMIT ? OFFSET ?");
        $stmt_users->bind_param("ii", $limit, $offset);
        $stmt_users->execute();
        $result_users = $stmt_users->get_result();
        while ($row = $result_users->fetch_assoc()) {
            $row['profile_picture'] = $row['profile_picture'] ?: 'img/photo_profile.png';
            $users[] = $row;
        }
        $stmt_users->close();

        echo json_encode(['users' => $users, 'total_users' => $total_users, 'per_page' => $limit]);
        exit();
    }

    if ($_GET['action'] === 'get_starred_items') {
        $user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : $current_user_id;
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = 7; // 7 items per page
        $offset = ($page - 1) * $limit;

        $starred_items_data = getStarredItems($starred_file);
        $user_starred_items = $starred_items_data[$user_id] ?? [];

        // Sort starred items by 'starred_at' in descending order (newest first)
        usort($user_starred_items, function($a, $b) {
            return strtotime($b['starred_at']) - strtotime($a['starred_at']);
        });

        $items_to_display = [];
        $total_items = count($user_starred_items);

        // Manually paginate the array
        $paginated_items = array_slice($user_starred_items, $offset, $limit);

        foreach ($paginated_items as $item) {
            if ($item['type'] === 'file') {
                $stmt = $conn->prepare("SELECT id, file_name, file_path, file_size, file_type, uploaded_at FROM files WHERE id = ?");
                $stmt->bind_param("i", $item['id']);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($file = $result->fetch_assoc()) {
                    $file['display_type'] = strtoupper($file['file_type']);
                    $file['display_size'] = formatBytes($file['file_size']);
                    $file['display_date'] = date('Y-m-d H:i', strtotime($file['uploaded_at']));
                    $file['icon_class'] = getFontAwesomeIconClass($file['file_name']);
                    $file['color_class'] = getFileColorClassPhp($file['file_name']);
                    $items_to_display[] = ['type' => 'file', 'data' => $file];
                }
                $stmt->close();
            } elseif ($item['type'] === 'folder') {
                $stmt = $conn->prepare("SELECT id, folder_name, created_at, updated_at FROM folders WHERE id = ?");
                $stmt->bind_param("i", $item['id']);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($folder = $result->fetch_assoc()) {
                    $folderPath = $baseUploadDir . getFolderPath($conn, $folder['id']);
                    $folderSize = getFolderSize($folderPath);
                    $folder['display_type'] = 'Folder';
                    $folder['display_size'] = formatBytes($folderSize);
                    $folder['display_date'] = date('Y-m-d H:i', strtotime($folder['updated_at'] ?? $folder['created_at']));
                    $folder['icon_class'] = 'fa-folder';
                    $folder['color_class'] = 'folder'; // Custom class for folder color
                    $items_to_display[] = ['type' => 'folder', 'data' => $folder];
                }
                $stmt->close();
            }
        }
        echo json_encode(['items' => $items_to_display, 'total_items' => $total_items, 'per_page' => $limit]);
        exit();
    }
}

// Simulated data for storage (consistent with index.php and profile.php)
$totalStorageGB = 500; 
$totalStorageBytes = $totalStorageGB * 1024 * 1024 * 1024; // Convert GB to Bytes

$usedStorageBytes = 0;
$stmt = $conn->prepare("SELECT SUM(file_size) as total_size FROM files");
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
if ($row['total_size']) {
    $usedStorageBytes = $row['total_size'];
}
$stmt->close();

$usedPercentage = ($totalStorageBytes > 0) ? ($usedStorageBytes / $totalStorageBytes) * 100 : 0;
if ($usedPercentage > 100) $usedPercentage = 100;
$isStorageFull = isStorageFull($conn, $totalStorageBytes);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Priority Files - SKMI Cloud Storage</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
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

        /* User Grid Profile (Google Drive Style) */
        .user-grid-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 16px; /* Reduced gap */
            padding: 16px; /* Padding for the grid container */
            background-color: var(--surface-color); /* White background */
            border-radius: 0; /* Siku-siku */
            margin-bottom: 20px;
            border: 1px solid var(--divider-color); /* Subtle border */
        }

        .user-profile-card {
            background-color: var(--background-color); /* Light grey background */
            border: 1px solid #dadce0; /* Google Drive border color */
            border-radius: 8px; /* Rounded corners */
            padding: 12px;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s ease-out;
            box-shadow: none; /* No box-shadow */
        }

        .user-profile-card:hover {
            box-shadow: 0 1px 3px rgba(60,64,67,.3), 0 4px 8px rgba(60,64,67,.15); /* Google Drive hover shadow */
            transform: translateY(0); /* No lift */
            border-color: transparent; /* Border disappears on hover */
        }

        .user-profile-card.active {
            border: 2px solid var(--primary-color); /* Material primary color for active */
            background-color: var(--surface-color); /* White background when active */
        }

        .user-profile-card img {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 10px;
            border: 2px solid var(--divider-color); /* Subtle border */
        }

        .user-profile-card h3 {
            margin: 0;
            font-size: 1.1em;
            color: var(--text-color);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            width: 100%;
            font-weight: 500; /* Medium font weight */
        }

        .user-profile-card p {
            margin: 5px 0 0;
            font-size: 0.9em;
            color: var(--secondary-text-color);
        }

        /* Pagination for User Grid */
        .user-pagination {
            display: flex;
            justify-content: center;
            margin-top: 10px;
            margin-bottom: 20px;
        }

        .user-pagination button {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 9px 18px; /* Reduced padding */
            border-radius: 0; /* Siku-siku */
            cursor: pointer;
            font-size: 0.9em;
            margin: 0 5px;
            transition: background-color 0.2s ease-out;
            box-shadow: none; /* No box-shadow */
        }

        .user-pagination button:hover:not(:disabled) {
            background-color: var(--primary-dark-color);
        }

        .user-pagination button:disabled {
            background-color: var(--divider-color);
            cursor: not-allowed;
        }

        /* Starred Items List (Google Drive Table Style) */
        .starred-items-list {
            background-color: var(--surface-color); /* White background */
            border-radius: 0; /* Siku-siku */
            padding: 0; /* No padding here, table handles it */
            border: 1px solid var(--divider-color); /* Subtle border */
            overflow: auto; /* For responsive table */
        }

        .starred-items-list h2 {
            font-size: 1.8em;
            color: var(--text-color);
            margin: 0;
            padding: 15px 20px; /* Padding for title */
            border-bottom: 1px solid var(--divider-color);
            font-weight: 400; /* Lighter font weight */
        }

        .starred-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed; /* Google Drive table layout */
            margin-top: 0;
            border: none; /* Remove outer border */
        }

        .starred-table th, .starred-table td {
            text-align: left;
            padding: 12px 24px; /* Google Drive padding */
            border-bottom: 1px solid #dadce0; /* Google Drive border color */
            font-size: 0.875em;
            color: #3c4043; /* Google Drive text color */
            vertical-align: middle;
        }

        .starred-table th {
            background-color: #f8f9fa; /* Google Drive header background */
            color: #5f6368; /* Google Drive header text */
            font-weight: 500;
            text-transform: none;
            position: sticky;
            top: 0;
            z-index: 1;
        }

        .starred-table tbody tr:hover {
            background-color: #f0f0f0; /* Google Drive hover effect */
        }

        .starred-table .file-icon {
            margin-right: 16px; /* Google Drive spacing */
            font-size: 1.2em;
            width: auto;
            text-align: center;
            flex-shrink: 0;
        }
        .starred-table .file-icon.folder { color: #fbc02d; } /* Google Drive folder color */

        .starred-table .file-name-cell {
            display: flex;
            align-items: center;
            max-width: 400px; /* Adjusted max-width */
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .starred-table .file-name-cell a {
            color: #3c4043; /* Google Drive text color */
            text-decoration: none;
            font-weight: 400;
            display: block;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            transition: color 0.2s ease-out;
        }

        .starred-table .file-name-cell a:hover {
            color: #1a73e8; /* Google Drive blue on hover */
        }

        .starred-actions button {
            background: none;
            border: none;
            font-size: 1.1em;
            cursor: pointer;
            color: var(--secondary-text-color);
            margin-left: 5px;
            transition: color 0.2s ease-out;
            box-shadow: none; /* No box-shadow */
        }

        .starred-actions button:hover {
            color: var(--primary-color);
        }

        .starred-actions button.unstar-btn:hover {
            color: var(--warning-color); /* Amber for unstar */
        }

        .starred-actions button.delete-btn:hover {
            color: var(--error-color);
        }

        /* Pagination for Starred Items */
        .starred-pagination {
            display: flex;
            justify-content: center;
            margin-top: 20px;
            padding: 15px 0;
            border-top: 1px solid var(--divider-color);
            background-color: var(--background-color); /* Light grey background */
        }

        .starred-pagination button {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 9px 18px; /* Reduced padding */
            border-radius: 0; /* Siku-siku */
            cursor: pointer;
            font-size: 0.9em;
            margin: 0 5px;
            transition: background-color 0.2s ease-out;
            box-shadow: none; /* No box-shadow */
        }

        .starred-pagination button:hover:not(:disabled) {
            background-color: var(--primary-dark-color);
        }

        .starred-pagination button:disabled {
            background-color: var(--divider-color);
            cursor: not-allowed;
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

        /* General button focus effects */
        button {
            outline: none;
        }
        button:focus {
            box-shadow: 0 0 0 2px rgba(63,81,181,0.5); /* Material Design focus ring */
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
        .header-main .priority-files-title {
            display: block;
        }

        /* Class for iPad & Tablet (Landscape: min-width 768px, max-width 1024px) */
        @media (min-width: 768px) and (max-width: 1024px) {
            body.tablet-landscape .sidebar {
                width: 220px; /* Slightly narrower sidebar */
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
            body.tablet-landscape .user-grid-container {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
                gap: 12px;
                padding: 12px;
            }
            body.tablet-landscape .user-profile-card img {
                width: 70px;
                height: 70px;
            }
            body.tablet-landscape .user-profile-card h3 {
                font-size: 1em;
            }
            body.tablet-landscape .starred-table th,
            body.tablet-landscape .starred-table td {
                padding: 10px 20px;
                font-size: 0.85em;
            }
            body.tablet-landscape .starred-actions button {
                font-size: 1em;
            }
            body.tablet-landscape .sidebar-menu a {
                font-size: var(--sidebar-font-size-tablet-landscape);
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
            body.tablet-portrait .header-main .priority-files-title {
                display: none;
            }
            body.tablet-portrait .main-content {
                padding: 15px;
            }
            body.tablet-portrait .user-grid-container {
                grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
                gap: 10px;
                padding: 10px;
            }
            body.tablet-portrait .user-profile-card img {
                width: 60px;
                height: 60px;
            }
            body.tablet-portrait .user-profile-card h3 {
                font-size: 0.9em;
            }
            body.tablet-portrait .starred-table thead {
                display: none; /* Hide table header on mobile for better stacking */
            }
            body.tablet-portrait .starred-table tbody tr {
                display: flex;
                flex-wrap: wrap;
                border: 1px solid #dadce0; /* Google Drive border color */
                margin-bottom: 8px;
                border-radius: 8px;
                background-color: var(--surface-color);
                box-shadow: none;
                position: relative;
            }
            body.tablet-portrait .starred-table td {
                display: block;
                width: 100%;
                padding: 8px 16px;
                font-size: 0.875em;
                border-bottom: none;
                white-space: normal;
                text-align: left;
            }
            body.tablet-portrait .starred-table td:nth-child(1) { /* Name */
                padding-left: 48px;
                font-weight: 500;
                font-size: 0.9em;
            }
            body.tablet-portrait .starred-table td:nth-child(1) .file-icon {
                position: absolute;
                left: 16px;
                top: 12px;
            }
            body.tablet-portrait .starred-table td:nth-child(2)::before { content: "Type: "; font-weight: normal; color: #5f6368; }
            body.tablet-portrait .starred-table td:nth-child(3)::before { content: "Size: "; font-weight: normal; color: #5f6368; }
            body.tablet-portrait .starred-table td:nth-child(4)::before { content: "Modified: "; font-weight: normal; color: #5f6368; }
            body.tablet-portrait .starred-table td:nth-child(5) { /* Actions */
                display: flex;
                justify-content: flex-end;
                padding-top: 0;
                padding-bottom: 8px;
            }
            body.tablet-portrait .starred-table td:nth-child(2),
            body.tablet-portrait .starred-table td:nth-child(3),
            body.tablet-portrait .starred-table td:nth-child(4) {
                display: inline-block;
                width: 50%;
                box-sizing: border-box;
                padding-top: 4px;
                padding-bottom: 4px;
                color: #5f6368;
            }
            body.tablet-portrait .sidebar-menu a {
                font-size: var(--sidebar-font-size-tablet-portrait);
            }
        }

        /* Class for Mobile (HP Android & iOS: max-width 767px) */
        @media (max-width: 767px) {
            body.mobile .sidebar {
                position: fixed;
                top: 0;
                left: 0;
                height: 100%;
                width: 180px; /* Even narrower sidebar for mobile */
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
            body.mobile .header-main .priority-files-title {
                display: none;
            }
            body.mobile .main-content {
                padding: 10px;
                overflow-x: hidden;
            }
            body.mobile .user-grid-container {
                grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
                gap: 8px;
                padding: 8px;
            }
            body.mobile .user-profile-card img {
                width: 50px;
                height: 50px;
            }
            body.mobile .user-profile-card h3 {
                font-size: 0.8em;
            }
            body.mobile .starred-table thead {
                display: none;
            }
            body.mobile .starred-table tbody tr {
                display: flex;
                flex-wrap: wrap;
                border: 1px solid #dadce0;
                margin-bottom: 8px;
                border-radius: 8px;
                background-color: var(--surface-color);
                box-shadow: none;
                position: relative;
            }
            body.mobile .starred-table td {
                display: block;
                width: 100%;
                padding: 8px 16px;
                font-size: 0.875em;
                border-bottom: none;
                white-space: normal;
                text-align: left;
            }
            body.mobile .starred-table td:nth-child(1) { /* Name */
                padding-left: 48px;
                font-weight: 500;
                font-size: 0.9em;
            }
            body.mobile .starred-table td:nth-child(1) .file-icon {
                position: absolute;
                left: 16px;
                top: 12px;
            }
            body.mobile .starred-table td:nth-child(2)::before { content: "Type: "; font-weight: normal; color: #5f6368; }
            body.mobile .starred-table td:nth-child(3)::before { content: "Size: "; font-weight: normal; color: #5f6368; }
            body.mobile .starred-table td:nth-child(4)::before { content: "Modified: "; font-weight: normal; color: #5f6368; }
            body.mobile .starred-table td:nth-child(5) { /* Actions */
                display: flex;
                justify-content: flex-end;
                padding-top: 0;
                padding-bottom: 8px;
            }
            body.mobile .starred-table td:nth-child(2),
            body.mobile .starred-table td:nth-child(3),
            body.mobile .starred-table td:nth-child(4) {
                display: inline-block;
                width: 50%;
                box-sizing: border-box;
                padding-top: 4px;
                padding-bottom: 4px;
                color: #5f6368;
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

        /* Animations */
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
                <li><a href="priority_files.php" class="active"><i class="fas fa-star"></i> <span data-lang-key="priorityFile">Priority File</span></a></li>
                <li><a href="recycle_bin.php"><i class="fas fa-trash"></i> <span data-lang-key="recycleBin">Recycle Bin</span></a></li>
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
                <p class="storage-text storage-full-message" style="color: var(--error-color); font-weight: bold;" data-lang-key="storageFull">Storage Full!</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="main-content" id="mainContent">
        <div class="header-main">
            <button class="sidebar-toggle-btn" id="sidebarToggleBtn"><i class="fas fa-bars"></i></button>
            <h1 class="priority-files-title" data-lang-key="priorityFilesTitle">Priority Files</h1>
        </div>

        <div class="user-grid-container" id="userGridContainer">
            <!-- User profiles will be loaded here -->
        </div>
        <div class="user-pagination" id="userPagination">
            <button id="prevUserPage" disabled><span data-lang-key="previous">Previous</span></button>
            <span id="currentUserPage"><span data-lang-key="page">Page</span> 1</span> / <span id="totalUserPages"></span>
            <button id="nextUserPage"><span data-lang-key="next">Next</span></button>
        </div>

        <div class="starred-items-list" id="starredItemsList" style="display: none;">
            <h2 id="starredItemsTitle"><span data-lang-key="starredItemsFor">Starred Items for</span> <span id="selectedUserName"></span></h2>
            <table class="starred-table">
                <thead>
                    <tr>
                        <th data-lang-key="name">Name</th>
                        <th data-lang-key="type">Type</th>
                        <th data-lang-key="size">Size</th>
                        <th data-lang-key="lastModified">Last Modified</th>
                        <th data-lang-key="actions">Actions</th>
                    </tr>
                </thead>
                <tbody id="starredItemsTableBody">
                    <!-- Starred files/folders will be loaded here -->
                </tbody>
            </table>
            <div class="starred-pagination" id="starredPagination">
                <button id="prevStarredPage" disabled><span data-lang-key="previous">Previous</span></button>
                <span id="currentStarredPage"><span data-lang-key="page">Page</span> 1</span> / <span id="totalStarredPages"></span>
                <button id="nextStarredPage"><span data-lang-key="next">Next</span></button>
            </div>
        </div>
    </div>

    <div id="customNotification" class="notification"></div>
    <div class="overlay" id="mobileOverlay"></div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const userGridContainer = document.getElementById('userGridContainer');
            const userPagination = document.getElementById('userPagination');
            const prevUserPageBtn = document.getElementById('prevUserPage');
            const nextUserPageBtn = document.getElementById('nextUserPage');
            const currentUserPageSpan = document.getElementById('currentUserPage');
            const totalUserPagesSpan = document.getElementById('totalUserPages');

            const starredItemsListDiv = document.getElementById('starredItemsList');
            const selectedUserNameSpan = document.getElementById('selectedUserName');
            const starredItemsTableBody = document.getElementById('starredItemsTableBody');
            const starredPagination = document.getElementById('starredPagination');
            const prevStarredPageBtn = document.getElementById('prevStarredPage');
            const nextStarredPageBtn = document.getElementById('nextStarredPage');
            const currentStarredPageSpan = document.getElementById('currentStarredPage');
            const totalStarredPagesSpan = document.getElementById('totalStarredPages');
            const customNotification = document.getElementById('customNotification');

            // Mobile sidebar elements
            const sidebar = document.querySelector('.sidebar');
            const sidebarToggleBtn = document.getElementById('sidebarToggleBtn');
            const mobileOverlay = document.getElementById('mobileOverlay');
            const mainContent = document.getElementById('mainContent'); // Get main-content for animations

            // Sidebar menu items for active state management
            const sidebarMenuItems = document.querySelectorAll('.sidebar-menu a');

            let currentUserPage = 1;
            let totalUserPages = 1;
            let selectedUserId = null;
            let currentStarredPage = 1;
            let totalStarredPages = 1;

            // Get current user role from PHP (passed via a hidden input or directly in JS)
            const currentUserRole = "<?php echo $currentUserRole; ?>";

            // Define restricted file extensions
            const restrictedExtensions = {
                'p2p': ['torrent', 'nzb', 'ed2k', 'part', '!ut'],
                'code': ['html', 'htm', 'css', 'js', 'php', 'py', 'java', 'json', 'xml', 'ts', 'tsx', 'jsx', 'vue', 'cpp', 'c', 'cs', 'rb', 'go', 'swift', 'sql', 'sh', 'bat', 'ini', 'yml', 'yaml', 'md', 'pl', 'r'],
                'installation': ['exe', 'msi', 'apk', 'ipa', 'sh', 'bat', 'jar', 'appimage', 'dmg', 'bin']
            };

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

                // Priority Files Page
                'priorityFilesTitle': { 'id': 'File Prioritas', 'en': 'Priority Files' },
                'previous': { 'id': 'Sebelumnya', 'en': 'Previous' },
                'next': { 'id': 'Berikutnya', 'en': 'Next' },
                'page': { 'id': 'Halaman', 'en': 'Page' },
                'starredItemsFor': { 'id': 'Item Prioritas untuk', 'en': 'Starred Items for' },
                'name': { 'id': 'Nama', 'en': 'Name' },
                'type': { 'id': 'Tipe', 'en': 'Type' },
                'size': { 'id': 'Ukuran', 'en': 'Size' },
                'lastModified': { 'id': 'Terakhir Dimodifikasi', 'en': 'Last Modified' },
                'actions': { 'id': 'Tindakan', 'en': 'Actions' },
                'noStarredItems': { 'id': 'Tidak ada item prioritas untuk pengguna ini.', 'en': 'No starred items for this user.' },
                'unpinFromPriority': { 'id': 'Hapus dari Prioritas', 'en': 'Unpin from Priority' },
                'download': { 'id': 'Unduh', 'en': 'Download' },
                'delete': { 'id': 'Hapus', 'en': 'Delete' },
                'confirmDelete': { 'id': 'Apakah Anda yakin ingin menghapus {type} ini secara permanen?', 'en': 'Are you sure you want to permanently delete this {type}?' },
                'failedToLoadUsers': { 'id': 'Gagal memuat profil pengguna.', 'en': 'Failed to load user profiles.' },
                'failedToLoadStarredItems': { 'id': 'Gagal memuat item prioritas.', 'en': 'Failed to load starred items.' },
                'failedToToggleStar': { 'id': 'Gagal mengubah status prioritas:', 'en': 'Failed to toggle star:' },
                'failedToDelete': { 'id': 'Gagal menghapus:', 'en': 'Failed to delete:' },
                'anErrorOccurredToggleStar': { 'id': 'Terjadi kesalahan saat mengubah status prioritas.', 'en': 'An error occurred while toggling star.' },
                'anErrorOccurredDelete': { 'id': 'Terjadi kesalahan saat menghapus item.', 'en': 'An error occurred while deleting item.' },
                'starToggledSuccess': { 'id': 'Status prioritas berhasil diubah.', 'en': 'Star status toggled successfully.' },
                'deleteSuccess': { 'id': 'Berhasil dihapus.', 'en': 'Successfully deleted.' },
                'usedTextId': 'terpakai',
                'usedTextEn': 'used',
            };

            let currentLanguage = localStorage.getItem('lang') || 'id'; // Default to Indonesian

            function applyTranslation(lang) {
                document.querySelectorAll('[data-lang-key]').forEach(element => {
                    const key = element.getAttribute('data-lang-key');
                    if (translations[key] && translations[key][lang]) {
                        element.textContent = translations[key][lang];
                    }
                });

                // Special handling for "of X used" text in sidebar
                const storageTextElement = document.getElementById('storageText');
                if (storageTextElement) {
                    const usedBytes = <?php echo $usedStorageBytes; ?>;
                    const totalBytes = <?php echo $totalStorageBytes; ?>;
                    storageTextElement.textContent = `${formatBytes(usedBytes)} ${translations['usedText' + (lang === 'id' ? 'Id' : 'En')]} ${formatBytes(totalBytes)} ${translations['usedText' + (lang === 'id' ? 'Id' : 'En')]}`;
                }

                // Update pagination text
                currentUserPageSpan.innerHTML = `<span data-lang-key="page">${translations['page'][lang]}</span> ${currentUserPage}`;
                currentStarredPageSpan.innerHTML = `<span data-lang-key="page">${translations['page'][lang]}</span> ${currentStarredPage}`;

                // Update starred items title
                if (selectedUserNameSpan.textContent) {
                    document.getElementById('starredItemsTitle').innerHTML = `<span data-lang-key="starredItemsFor">${translations['starredItemsFor'][lang]}</span> <span id="selectedUserName">${selectedUserNameSpan.textContent}</span>`;
                }

                // Update table headers
                document.querySelector('.starred-table th[data-lang-key="name"]').textContent = translations['name'][lang];
                document.querySelector('.starred-table th[data-lang-key="type"]').textContent = translations['type'][lang];
                document.querySelector('.starred-table th[data-lang-key="size"]').textContent = translations['size'][lang];
                document.querySelector('.starred-table th[data-lang-key="lastModified"]').textContent = translations['lastModified'][lang];
                document.querySelector('.starred-table th[data-lang-key="actions"]').textContent = translations['actions'][lang];

                // Update button titles
                document.querySelectorAll('.unstar-btn').forEach(btn => {
                    btn.title = translations['unpinFromPriority'][lang];
                });
                document.querySelectorAll('.starred-actions a[download]').forEach(btn => {
                    btn.title = translations['download'][lang];
                });
                document.querySelectorAll('.delete-btn').forEach(btn => {
                    btn.title = translations['delete'][lang];
                });

                // Update "No starred items" message
                const noStarredItemsRow = starredItemsTableBody.querySelector('tr td[colspan="5"]');
                if (noStarredItemsRow && noStarredItemsRow.getAttribute('data-lang-key') === 'noStarredItems') {
                    noStarredItemsRow.textContent = translations['noStarredItems'][lang];
                }
            }

            /*** Device detection & body class toggling ***/
            function setDeviceClass() {
                const ua = navigator.userAgent || '';
                const w = window.innerWidth;
                document.body.classList.remove('mobile', 'tablet-portrait', 'tablet-landscape', 'desktop'); // Clear all
                if (w <= 767) {
                    document.body.classList.add('mobile');
                    sidebar.classList.add('mobile-hidden'); // Ensure sidebar is hidden by default on mobile
                } else if (w >= 768 && w <= 1024) {
                    if (window.matchMedia("(orientation: portrait)").matches) {
                        document.body.classList.add('tablet-portrait');
                        sidebar.classList.add('mobile-hidden'); // Ensure sidebar is hidden by default on tablet portrait
                    } else {
                        document.body.classList.add('tablet-landscape');
                        sidebar.classList.remove('mobile-hidden'); // Sidebar visible on tablet landscape
                        sidebar.classList.remove('show-mobile-sidebar');
                        mobileOverlay.classList.remove('show');
                    }
                } else {
                    document.body.classList.add('desktop');
                    sidebar.classList.remove('mobile-hidden'); // Sidebar visible on desktop
                    sidebar.classList.remove('show-mobile-sidebar');
                    mobileOverlay.classList.remove('show');
                }
            }
            window.addEventListener('resize', setDeviceClass);
            window.addEventListener('orientationchange', setDeviceClass); // Listen for orientation changes
            setDeviceClass(); // init

            // Function to show custom notification
            function showNotification(message, type) {
                customNotification.textContent = message;
                customNotification.className = 'notification show ' + type;
                setTimeout(() => {
                    customNotification.classList.remove('show');
                }, 3000);
            }

            // Helper to format bytes (replicate from PHP)
            function formatBytes(bytes, precision = 2) {
                const units = ['B', 'KB', 'MB', 'GB', 'TB'];
                bytes = Math.max(bytes, 0);
                const pow = Math.floor((bytes ? Math.log(bytes) : 0) / Math.log(1024));
                const p = Math.min(pow, units.length - 1);
                bytes /= (1 << (10 * p));
                return bytes.toFixed(precision) + ' ' + units[p];
            }

            // Function to get file icon class (replicate from PHP)
            function getFileIconClass(fileName) {
                const extension = fileName.split('.').pop().toLowerCase();
                const iconClasses = {
                    'pdf': 'fa-file-pdf', 'doc': 'fa-file-word', 'docx': 'fa-file-word',
                    'xls': 'fa-file-excel', 'xlsx': 'fa-file-excel', 'ppt': 'fa-file-powerpoint',
                    'pptx': 'fa-file-powerpoint', 'txt': 'fa-file-alt', 'rtf': 'fa-file-alt',
                    'md': 'fa-file-alt', 'csv': 'fa-file-csv', 'odt': 'fa-file-alt',
                    'odp': 'fa-file-powerpoint', 'log': 'fa-file-alt', 'tex': 'fa-file-alt',
                    'jpg': 'fa-file-image', 'jpeg': 'fa-file-image', 'png': 'fa-file-image',
                    'gif': 'fa-file-image', 'bmp': 'fa-file-image', 'webp': 'fa-file-image',
                    'svg': 'fa-file-image', 'tiff': 'fa-file-image',
                    'mp3': 'fa-file-audio', 'wav': 'fa-file-audio', 'ogg': 'fa-file-audio',
                    'flac': 'fa-file-audio', 'aac': 'fa-file-audio', 'm4a': 'fa-file-audio',
                    'alac': 'fa-file-audio', 'wma': 'fa-file-audio', 'opus': 'fa-file-audio',
                    'amr': 'fa-file-audio', 'mid': 'fa-file-audio',
                    'mp4': 'fa-file-video', 'avi': 'fa-file-video', 'mov': 'fa-file-video',
                    'wmv': 'fa-file-video', 'flv': 'fa-file-video', 'webm': 'fa-file-video',
                    '3gp': 'fa-file-video', 'm4v': 'fa-file-video', 'mpg': 'fa-file-video',
                    'mpeg': 'fa-file-video', 'ts': 'fa-file-video', 'ogv': 'fa-file-video',
                    'zip': 'fa-file-archive', 'rar': 'fa-file-archive', '7z': 'fa-file-archive',
                    'tar': 'fa-file-archive', 'gz': 'fa-file-archive', 'bz2': 'fa-file-archive',
                    'xz': 'fa-file-archive', 'iso': 'fa-file-archive', 'cab': 'fa-file-archive',
                    'arj': 'fa-file-archive',
                    'html': 'fa-file-code', 'htm': 'fa-file-code', 'css': 'fa-file-code',
                    'js': 'fa-file-code', 'php': 'fa-file-code', 'py': 'fa-file-code',
                    'java': 'fa-file-code', 'json': 'fa-file-code', 'xml': 'fa-file-code',
                    'ts': 'fa-file-code', 'tsx': 'fa-file-code', 'jsx': 'fa-file-code',
                    'vue': 'fa-file-code', 'cpp': 'fa-file-code', 'c': 'fa-file-code',
                    'cs': 'fa-file-code', 'rb': 'fa-file-code', 'go': 'fa-file-code',
                    'swift': 'fa-file-code', 'sql': 'fa-database', 'sh': 'fa-file-code',
                    'bat': 'fa-file-code', 'ini': 'fa-file-code', 'yml': 'fa-file-code',
                    'yaml': 'fa-file-code', 'pl': 'fa-file-code', 'r': 'fa-file-code',
                    'exe': 'fa-box', 'msi': 'fa-box', 'apk': 'fa-box', 'ipa': 'fa-box',
                    'jar': 'fa-box', 'appimage': 'fa-box', 'dmg': 'fa-box', 'bin': 'fa-box',
                    'torrent': 'fa-magnet', 'nzb': 'fa-magnet', 'ed2k': 'fa-magnet',
                    'part': 'fa-magnet', '!ut': 'fa-magnet',
                    'dwg': 'fa-cube', 'dxf': 'fa-cube', 'dgn': 'fa-cube', 'iges': 'fa-cube',
                    'igs': 'fa-cube', 'step': 'fa-cube', 'stp': 'fa-cube', 'stl': 'fa-cube',
                    '3ds': 'fa-cube', 'obj': 'fa-cube', 'sldprt': 'fa-cube', 'sldasm': 'fa-cube',
                    'ipt': 'fa-cube', 'iam': 'fa-cube', 'catpart': 'fa-cube', 'catproduct': 'fa-cube',
                    'prt': 'fa-cube', 'asm': 'fa-cube', 'fcstd': 'fa-cube', 'skp': 'fa-cube',
                    'x_t': 'fa-cube', 'x_b': 'fa-cube',
                    'default': 'fa-file'
                };
                return iconClasses[extension] || iconClasses['default'];
            }

            // Function to get file color class (replicate from PHP)
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

            // Function to check if an extension is restricted for non-admin/moderator
            function isRestrictedExtension(fileName) {
                const extension = fileName.split('.').pop().toLowerCase();
                const isRestricted = Object.values(restrictedExtensions).some(arr => arr.includes(extension));
                return isRestricted;
            }

            // Load Users Grid
            async function loadUsers(page) {
                try {
                    const response = await fetch(`priority_files.php?action=get_users&page=${page}`);
                    const data = await response.json();

                    userGridContainer.innerHTML = '';
                    data.users.forEach(user => {
                        const userCard = document.createElement('div');
                        userCard.className = 'user-profile-card';
                        if (user.id === selectedUserId) {
                            userCard.classList.add('active');
                        }
                        userCard.dataset.userId = user.id;
                        userCard.dataset.userName = user.full_name || user.username;
                        userCard.innerHTML = `
                            <img src="${user.profile_picture}" alt="${user.full_name || user.username}">
                            <h3>${user.full_name || user.username}</h3>
                            <p>${user.username}</p>
                        `;
                        userCard.addEventListener('click', () => {
                            selectUser(user.id, user.full_name || user.username);
                        });
                        userGridContainer.appendChild(userCard);
                    });

                    currentUserPage = page;
                    totalUserPages = Math.ceil(data.total_users / data.per_page);
                    currentUserPageSpan.innerHTML = `<span data-lang-key="page">${translations['page'][currentLanguage]}</span> ${currentUserPage}`;
                    totalUserPagesSpan.textContent = totalUserPages;

                    prevUserPageBtn.disabled = currentUserPage === 1;
                    nextUserPageBtn.disabled = currentUserPage === totalUserPages;

                } catch (error) {
                    console.error('Error loading users:', error);
                    showNotification(translations['failedToLoadUsers'][currentLanguage], 'error');
                }
            }

            // Load Starred Items for a specific user
            async function loadStarredItems(userId, page) {
                try {
                    const response = await fetch(`priority_files.php?action=get_starred_items&user_id=${userId}&page=${page}`);
                    const data = await response.json();

                    starredItemsTableBody.innerHTML = '';
                    let itemsDisplayedCount = 0;

                    if (data.items.length === 0) {
                        starredItemsTableBody.innerHTML = `<tr><td colspan="5" style="text-align: center; padding: 20px;" data-lang-key="noStarredItems">${translations['noStarredItems'][currentLanguage]}</td></tr>`;
                    } else {
                        data.items.forEach(item => {
                            const itemData = item.data;
                            const itemName = item.type === 'folder' ? itemData.folder_name : itemData.file_name;
                            const isFileRestricted = item.type === 'file' && isRestrictedExtension(itemName);

                            // Only display if user is admin/moderator OR if the file is not restricted
                            if (currentUserRole === 'admin' || currentUserRole === 'moderator' || !isFileRestricted) {
                                const row = document.createElement('tr');
                                const iconClass = item.type === 'folder' ? 'fas fa-folder' : `fas ${getFileIconClass(itemName)}`;
                                const colorClass = item.type === 'folder' ? 'folder' : getFileColorClass(itemName);
                                const itemLink = item.type === 'folder' ? `index.php?folder=${itemData.id}` : `view.php?file_id=${itemData.id}`;

                                row.innerHTML = `
                                    <td class="file-name-cell">
                                        <i class="fas ${iconClass} file-icon ${colorClass}"></i>
                                        <a href="${itemLink}">${itemName}</a>
                                    </td>
                                    <td>${itemData.display_type}</td>
                                    <td>${itemData.display_size}</td>
                                    <td>${itemData.display_date}</td>
                                    <td class="starred-actions">
                                        <button class="unstar-btn" data-id="${itemData.id}" data-type="${item.type}" data-name="${itemName}" title="${translations['unpinFromPriority'][currentLanguage]}"><i class="fas fa-star"></i></button>
                                        ${item.type === 'file' ? `<a href="${itemData.file_path}" download="${itemData.file_name}" title="${translations['download'][currentLanguage]}"><button><i class="fas fa-download"></i></button></a>` : ''}
                                        <button class="delete-btn" data-id="${itemData.id}" data-type="${item.type}" title="${translations['delete'][currentLanguage]}"><i class="fas fa-trash"></i></button>
                                    </td>
                                `;
                                starredItemsTableBody.appendChild(row);
                                itemsDisplayedCount++;
                            }
                        });

                        if (itemsDisplayedCount === 0) {
                            starredItemsTableBody.innerHTML = `<tr><td colspan="5" style="text-align: center; padding: 20px;" data-lang-key="noStarredItems">${translations['noStarredItems'][currentLanguage]}</td></tr>`;
                        }
                    }

                    currentStarredPage = page;
                    // Note: totalStarredPages calculation here is based on total items from backend,
                    // not just displayed items. This is fine as pagination is handled by backend.
                    totalStarredPages = Math.ceil(data.total_items / data.per_page);
                    currentStarredPageSpan.innerHTML = `<span data-lang-key="page">${translations['page'][currentLanguage]}</span> ${currentStarredPage}`;
                    totalStarredPagesSpan.textContent = totalStarredPages;

                    prevStarredPageBtn.disabled = currentStarredPage === 1;
                    nextStarredPageBtn.disabled = currentStarredPage === totalStarredPages;

                    // Attach event listeners to newly loaded buttons
                    attachStarredItemEventListeners();

                } catch (error) {
                    console.error('Error loading starred items:', error);
                    showNotification(translations['failedToLoadStarredItems'][currentLanguage], 'error');
                }
            }

            // Function to attach event listeners to unstar and delete buttons
            function attachStarredItemEventListeners() {
                document.querySelectorAll('.unstar-btn').forEach(button => {
                    button.onclick = function() {
                        const id = this.dataset.id;
                        const type = this.dataset.type;
                        const name = this.dataset.name;
                        toggleStar(id, type, name, true); // Explicitly unstar
                    };
                });

                document.querySelectorAll('.delete-btn').forEach(button => {
                    button.onclick = function() {
                        const id = this.dataset.id;
                        const type = this.dataset.type;
                        deleteItem(id, type);
                    };
                });
            }


            // Select a user and show their starred items
            function selectUser(userId, userName) {
                selectedUserId = userId;
                selectedUserNameSpan.textContent = userName;
                starredItemsListDiv.style.display = 'block';
                currentStarredPage = 1; // Reset starred items pagination
                loadStarredItems(selectedUserId, currentStarredPage);

                // Update active state for user cards
                document.querySelectorAll('.user-profile-card').forEach(card => {
                    card.classList.remove('active');
                    if (parseInt(card.dataset.userId) === userId) {
                        card.classList.add('active');
                    }
                });
            }

            // Toggle Star function (for unstarring from this page)
            async function toggleStar(id, type, name, unstar = false) {
                try {
                    const response = await fetch('toggle_star.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ id: id, type: type, name: name, unstar: unstar })
                    });
                    const data = await response.json();
                    if (data.success) {
                        showNotification(translations['starToggledSuccess'][currentLanguage], 'success');
                        loadStarredItems(selectedUserId, currentStarredPage); // Reload current page
                    } else {
                        showNotification(`${translations['failedToToggleStar'][currentLanguage]} ${data.message}`, 'error');
                    }
                } catch (error) {
                    console.error('Error toggling star:', error);
                    showNotification(translations['anErrorOccurredToggleStar'][currentLanguage], 'error');
                }
            }

            // Delete Item function (from this page)
            async function deleteItem(id, type) {
                const confirmMessage = translations['confirmDelete'][currentLanguage].replace('{type}', type);
                if (!confirm(confirmMessage)) {
                    return;
                }
                try {
                    const response = await fetch('delete.php', { // Use existing delete.php
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ id: id, type: type })
                    });
                    const data = await response.json();
                    if (data.success) {
                        showNotification(translations['deleteSuccess'][currentLanguage], 'success');
                        // Also unstar the item after deletion
                        // Note: The `toggleStar` function itself handles both starring and unstarring.
                        // By passing `true` for `unstar`, we explicitly tell it to remove the item.
                        toggleStar(id, type, '', true); // Unstar it from starred.json, name not needed for unstar
                        // loadStarredItems(selectedUserId, currentStarredPage); // This will be called by toggleStar
                    } else {
                        showNotification(`${translations['failedToDelete'][currentLanguage]} ${data.message}`, 'error');
                    }
                } catch (error) {
                    console.error('Error deleting item:', error);
                    showNotification(translations['anErrorOccurredDelete'][currentLanguage], 'error');
                }
            }

            // Pagination Event Listeners for Users
            prevUserPageBtn.addEventListener('click', () => {
                if (currentUserPage > 1) {
                    loadUsers(currentUserPage - 1);
                }
            });
            nextUserPageBtn.addEventListener('click', () => {
                if (currentUserPage < totalUserPages) {
                    loadUsers(currentUserPage + 1);
                }
            });

            // Pagination Event Listeners for Starred Items
            prevStarredPageBtn.addEventListener('click', () => {
                if (currentStarredPage > 1) {
                    loadStarredItems(selectedUserId, currentStarredPage - 1);
                }
            });
            nextStarredPageBtn.addEventListener('click', () => {
                if (currentStarredPage < totalStarredPages) {
                    loadStarredItems(selectedUserId, currentStarredPage + 1);
                }
            });

            // --- Mobile Sidebar Toggle ---
            sidebarToggleBtn.addEventListener('click', () => {
                sidebar.classList.toggle('show-mobile-sidebar');
                mobileOverlay.classList.toggle('show');
            });

            // Close mobile sidebar if overlay is clicked
            mobileOverlay.addEventListener('click', () => {
                if (sidebar.classList.contains('show-mobile-sidebar')) {
                    sidebar.classList.remove('show-mobile-sidebar');
                    mobileOverlay.classList.remove('show');
                }
            });

            // Set active class for current page in sidebar
            const currentPagePath = window.location.pathname.split('/').pop();
            sidebarMenuItems.forEach(item => {
                item.classList.remove('active');
                const itemHref = item.getAttribute('href');
                if (itemHref === currentPagePath) {
                    item.classList.add('active');
                }
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

            // Initial load
            loadUsers(currentUserPage);
            applyTranslation(currentLanguage); // Apply translation on initial load
        });
    </script>
</body>
</html>
