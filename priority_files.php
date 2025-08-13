<?php
include 'config.php';
include 'functions.php'; // Include functions.php file

session_start();
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

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

        /* Base Sidebar */
        .sidebar {
            width: 250px;
            background-color: var(--metro-sidebar-bg);
            color: var(--metro-sidebar-text);
            display: flex;
            flex-direction: column;
            padding: 20px 0;
            transition: width 0.3s ease-in-out, transform 0.3s ease-in-out;
            flex-shrink: 0;
        }

        .sidebar-header {
            padding: 0 20px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .sidebar-header img {
            width: 150px;
            height: auto;
            display: block;
        }

        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 0;
            flex-grow: 1;
        }

        .sidebar-menu li {
            margin-bottom: 5px;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 15px 20px;
            color: var(--metro-sidebar-text);
            text-decoration: none;
            font-size: var(--sidebar-font-size-desktop); /* Menggunakan variabel untuk desktop */
            transition: background-color 0.2s ease-out, color 0.2s ease-out;
            border-left: 5px solid transparent;
        }

        .sidebar-menu a i {
            margin-right: 15px;
            font-size: 1.4em;
            width: 25px;
            text-align: center;
        }

        .sidebar-menu a:hover {
            background-color: rgba(255,255,255,0.1);
            color: #FFFFFF;
        }

        .sidebar-menu a.active {
            background-color: var(--metro-blue);
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
            position: relative;
        }

        .progress-bar {
            height: 100%;
            background-color: var(--metro-success);
            border-radius: 5px;
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
            font-size: 0.7em;
            font-weight: bold;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
            white-space: nowrap;
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
            overflow-y: auto;
            background-color: #FFFFFF;
            border-radius: 8px;
            margin: 0; /* MODIFIED: Full width for all devices */
        }

        /* Header Main */
        .header-main {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--metro-light-gray);
            background-color: #FFFFFF; /* Consistent white header */
            padding: 15px 30px;
            margin: -30px -30px 25px -30px; /* MODIFIED: Full width for all devices */
            border-radius: 0; /* MODIFIED: No rounded top corners for full width */
            /*box-shadow: 0 2px 5px rgba(0,0,0,0.05);*/
        }

        .header-main h1 {
            margin: 0;
            color: var(--metro-text-color);
            font-size: 2.5em;
            font-weight: 300;
        }

        /* User Grid Profile */
        .user-grid-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 20px;
            padding: 20px;
            background-color: var(--metro-bg-color);
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .user-profile-card {
            background-color: #FFFFFF;
            border: 1px solid var(--metro-light-gray);
            border-radius: 5px;
            padding: 15px;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            cursor: pointer;
            transition: transform 0.2s ease-out, border-color 0.2s ease-out;
        }

        .user-profile-card:hover {
            transform: translateY(-3px);
            border-color: var(--metro-blue);
        }

        .user-profile-card.active {
            border: 2px solid var(--metro-blue);
            background-color: var(--metro-light-gray);
        }

        .user-profile-card img {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 10px;
            border: 2px solid var(--metro-medium-gray);
        }

        .user-profile-card h3 {
            margin: 0;
            font-size: 1.1em;
            color: var(--metro-text-color);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            width: 100%;
        }

        .user-profile-card p {
            margin: 5px 0 0;
            font-size: 0.9em;
            color: var(--metro-dark-gray);
        }

        /* Pagination for User Grid */
        .user-pagination {
            display: flex;
            justify-content: center;
            margin-top: 10px;
            margin-bottom: 20px;
        }

        .user-pagination button {
            background-color: var(--metro-blue);
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 0.9em;
            margin: 0 5px;
            transition: background-color 0.2s ease-out;
        }

        .user-pagination button:hover:not(:disabled) {
            background-color: var(--metro-dark-blue);
        }

        .user-pagination button:disabled {
            background-color: var(--metro-medium-gray);
            cursor: not-allowed;
        }

        /* Starred Items List */
        .starred-items-list {
            background-color: #FFFFFF;
            border-radius: 8px;
            padding: 20px;
        }

        .starred-items-list h2 {
            font-size: 1.8em;
            color: var(--metro-text-color);
            margin-top: 0;
            margin-bottom: 20px;
            border-bottom: 1px solid var(--metro-light-gray);
            padding-bottom: 10px;
        }

        .starred-table {
            width: 100%;
            border-collapse: collapse;
        }

        .starred-table th, .starred-table td {
            text-align: left;
            padding: 12px 15px;
            border-bottom: 1px solid var(--metro-light-gray);
            font-size: 0.95em;
        }

        .starred-table th {
            background-color: var(--metro-bg-color);
            color: var(--metro-dark-gray);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85em;
        }

        .starred-table tbody tr:hover {
            background-color: var(--metro-light-gray);
        }

        .starred-table .file-icon {
            margin-right: 10px;
            font-size: 1.2em;
            width: 25px;
            text-align: center;
        }

        .starred-table .file-name-cell {
            display: flex;
            align-items: center;
        }

        .starred-table .file-name-cell a {
            color: var(--metro-text-color);
            text-decoration: none;
            font-weight: 400;
            transition: color 0.2s ease-out;
        }

        .starred-table .file-name-cell a:hover {
            color: var(--metro-blue);
        }

        .starred-actions button {
            background: none;
            border: none;
            font-size: 1.1em;
            cursor: pointer;
            color: var(--metro-dark-gray);
            margin-left: 5px;
            transition: color 0.2s ease-out;
        }

        .starred-actions button:hover {
            color: var(--metro-blue);
        }

        .starred-actions button.unstar-btn:hover {
            color: var(--metro-warning); /* Orange for unstar */
        }

        .starred-actions button.delete-btn:hover {
            color: var(--metro-error);
        }

        /* Pagination for Starred Items */
        .starred-pagination {
            display: flex;
            justify-content: center;
            margin-top: 20px;
        }

        .starred-pagination button {
            background-color: var(--metro-blue);
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 0.9em;
            margin: 0 5px;
            transition: background-color 0.2s ease-out;
        }

        .starred-pagination button:hover:not(:disabled) {
            background-color: var(--metro-dark-blue);
        }

        .starred-pagination button:disabled {
            background-color: var(--metro-medium-gray);
            cursor: not-allowed;
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

        /* Custom Scrollbar for Webkit browsers (Chrome, Safari) */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: var(--metro-light-gray);
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb {
            background: var(--metro-medium-gray);
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--metro-dark-gray);
        }

        /* Sidebar Toggle Button (for mobile/tablet) */
        .sidebar-toggle-btn {
            display: none; /* Hidden by default on desktop */
            background: none;
            border: none;
            font-size: 1.5em;
            color: var(--metro-text-color);
            cursor: pointer;
            margin-right: 15px;
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

        /* ========================================================================== */
        /* Responsive Classes for iPad, Tablet, HP (Android & iOS) */
        /* ========================================================================== */

        /* Default for Desktop/Windows */
        body.desktop .sidebar {
            width: 250px;
            transform: translateX(0);
        }
        body.desktop .sidebar-toggle-btn {
            display: none;
        }
        body.desktop .main-content {
            margin: 0; /* Full width */
            padding: 30px;
        }
        body.desktop .header-main {
            padding: 15px 30px;
            margin: -30px -30px 25px -30px;
        }
        body.desktop .header-main h1 {
            font-size: 2.5em;
        }

        /* Class for iPad & Tablet (Landscape: min-width 768px, max-width 1024px) */
        @media (min-width: 768px) and (max-width: 1024px) {
            body.tablet-landscape .sidebar {
                width: 220px; /* Slightly narrower sidebar */
                transform: translateX(0);
            }
            body.tablet-landscape .sidebar-toggle-btn {
                display: none;
            }
            body.tablet-landscape .main-content {
                margin: 0; /* Full width */
                padding: 20px;
            }
            body.tablet-landscape .header-main {
                padding: 10px 20px;
                margin: -20px -20px 25px -20px; /* Adjusted margin for full width */
            }
            body.tablet-landscape .header-main h1 {
                font-size: 2em;
            }
            body.tablet-landscape .user-grid-container {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
                gap: 15px;
                padding: 15px;
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
                padding: 10px 12px;
                font-size: 0.9em;
            }
            body.tablet-landscape .starred-actions button {
                font-size: 1em;
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
                order: -1; /* Place on the left */
            }
            body.tablet-portrait .header-main {
                justify-content: flex-start; /* Align items to start */
                padding: 10px 20px;
                margin: -20px -20px 25px -20px; /* Adjusted margin for full width */
            }
            body.tablet-portrait .header-main h1 {
                font-size: 2em;
                flex-grow: 1; /* Allow title to take space */
                text-align: center; /* Center title */
                margin-right: 15px; /* Space for toggle button */
            }
            body.tablet-portrait .main-content {
                margin: 0; /* Full width */
                padding: 20px;
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
            body.tablet-portrait .starred-table th,
            body.tablet-portrait .starred-table td {
                padding: 8px 10px;
                font-size: 0.85em;
            }
            body.tablet-portrait .starred-actions button {
                font-size: 0.9em;
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
                order: -1; /* Place on the left */
            }
            body.mobile .header-main {
                justify-content: flex-start; /* Align items to start */
                padding: 10px 15px;
                margin: -15px -15px 20px -15px; /* Adjusted margin for full width */
            }
            body.mobile .header-main h1 {
                font-size: 1.8em;
                flex-grow: 1; /* Allow title to take space */
                text-align: center; /* Center title */
                margin-right: 10px; /* Space for toggle button */
            }
            body.mobile .main-content {
                margin: 0; /* Full width */
                padding: 15px;
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
            body.mobile .starred-table th,
            body.mobile .starred-table td {
                padding: 6px 8px;
                font-size: 0.8em;
            }
            body.mobile .starred-actions button {
                font-size: 0.8em;
            }
            .header-main .priority-files-title {
                display: block; /* "My Drive" visible on desktop */
            }
            
            body.tablet-portrait .header-main .priority-files-title {
                    display: none; /* Hide "My Drive" */
            }
                
            body.mobile .header-main .priority-files-title {
                    display: none; /* Hide "My Drive" */
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
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header">
            <img src="img/logo.png" alt="Dafino Logo">
        </div>
        <ul class="sidebar-menu">
            <li><a href="index.php"><i class="fas fa-folder"></i> My Drive</a></li>
            <li><a href="priority_files.php" class="active"><i class="fas fa-star"></i> Priority File</a></li>
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
            <h1 class="priority-files-title">Priority Files</h1>
        </div>

        <div class="user-grid-container" id="userGridContainer">
            <!-- User profiles will be loaded here -->
        </div>
        <div class="user-pagination" id="userPagination">
            <button id="prevUserPage" disabled>Previous</button>
            <span id="currentUserPage">Page 1</span> / <span id="totalUserPages"></span>
            <button id="nextUserPage">Next</button>
        </div>

        <div class="starred-items-list" id="starredItemsList" style="display: none;">
            <h2 id="starredItemsTitle">Starred Items for <span id="selectedUserName"></span></h2>
            <table class="starred-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Size</th>
                        <th>Last Modified</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="starredItemsTableBody">
                    <!-- Starred files/folders will be loaded here -->
                </tbody>
            </table>
            <div class="starred-pagination" id="starredPagination">
                <button id="prevStarredPage" disabled>Previous</button>
                <span id="currentStarredPage">Page 1</span> / <span id="totalStarredPages"></span>
                <button id="nextStarredPage">Next</button>
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
            const myDriveTitle = document.querySelector('.priority-files-title');
            const sidebarToggleBtn = document.getElementById('sidebarToggleBtn');
            const mobileOverlay = document.getElementById('mobileOverlay');

            let currentUserPage = 1;
            let totalUserPages = 1;
            let selectedUserId = null;
            let currentStarredPage = 1;
            let totalStarredPages = 1;

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
                    currentUserPageSpan.textContent = `Page ${currentUserPage}`;
                    totalUserPagesSpan.textContent = totalUserPages;

                    prevUserPageBtn.disabled = currentUserPage === 1;
                    nextUserPageBtn.disabled = currentUserPage === totalUserPages;

                } catch (error) {
                    console.error('Error loading users:', error);
                    showNotification('Failed to load user profiles.', 'error');
                }
            }

            // Load Starred Items for a specific user
            async function loadStarredItems(userId, page) {
                try {
                    const response = await fetch(`priority_files.php?action=get_starred_items&user_id=${userId}&page=${page}`);
                    const data = await response.json();

                    starredItemsTableBody.innerHTML = '';
                    if (data.items.length === 0) {
                        starredItemsTableBody.innerHTML = `<tr><td colspan="5" style="text-align: center; padding: 20px;">No starred items for this user.</td></tr>`;
                    } else {
                        data.items.forEach(item => {
                            const row = document.createElement('tr');
                            const itemData = item.data;
                            const iconClass = item.type === 'folder' ? 'fas fa-folder' : `fas ${getFileIconClass(itemData.file_name)}`;
                            const colorClass = item.type === 'folder' ? 'folder' : getFileColorClass(itemData.file_name);
                            const itemName = item.type === 'folder' ? itemData.folder_name : itemData.file_name;
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
                                    <button class="unstar-btn" data-id="${itemData.id}" data-type="${item.type}" data-name="${itemName}" title="Unpin from Priority"><i class="fas fa-star"></i></button>
                                    ${item.type === 'file' ? `<a href="${itemData.file_path}" download="${itemData.file_name}" title="Download"><button><i class="fas fa-download"></i></button></a>` : ''}
                                    <button class="delete-btn" data-id="${itemData.id}" data-type="${item.type}" title="Delete"><i class="fas fa-trash"></i></button>
                                </td>
                            `;
                            starredItemsTableBody.appendChild(row);
                        });

                        // Add event listeners for unstar and delete buttons
                        starredItemsTableBody.querySelectorAll('.unstar-btn').forEach(button => {
                            button.addEventListener('click', (e) => {
                                const id = e.currentTarget.dataset.id;
                                const type = e.currentTarget.dataset.type;
                                const name = e.currentTarget.dataset.name;
                                toggleStar(id, type, name, true); // true means unstar
                            });
                        });
                        starredItemsTableBody.querySelectorAll('.delete-btn').forEach(button => {
                            button.addEventListener('click', (e) => {
                                const id = e.currentTarget.dataset.id;
                                const type = e.currentTarget.dataset.type;
                                deleteItem(id, type);
                            });
                        });
                    }

                    currentStarredPage = page;
                    totalStarredPages = Math.ceil(data.total_items / data.per_page);
                    currentStarredPageSpan.textContent = `Page ${currentStarredPage}`;
                    totalStarredPagesSpan.textContent = totalStarredPages;

                    prevStarredPageBtn.disabled = currentStarredPage === 1;
                    nextStarredPageBtn.disabled = currentStarredPage === totalStarredPages;

                } catch (error) {
                    console.error('Error loading starred items:', error);
                    showNotification('Failed to load starred items.', 'error');
                }
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
                        showNotification(data.message, 'success');
                        loadStarredItems(selectedUserId, currentStarredPage); // Reload current page
                    } else {
                        showNotification('Failed to toggle star: ' + data.message, 'error');
                    }
                } catch (error) {
                    console.error('Error toggling star:', error);
                    showNotification('An error occurred while toggling star.', 'error');
                }
            }

            // Delete Item function (from this page)
            async function deleteItem(id, type) {
                if (!confirm(`Are you sure you want to permanently delete this ${type}?`)) {
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
                        showNotification(data.message, 'success');
                        // Also unstar the item after deletion
                        toggleStar(id, type, '', true); // Unstar it from starred.json, name not needed for unstar
                        loadStarredItems(selectedUserId, currentStarredPage); // Reload current page
                    } else {
                        showNotification('Failed to delete: ' + data.message, 'error');
                    }
                } catch (error) {
                    console.error('Error deleting item:', error);
                    showNotification('An error occurred while deleting item.', 'error');
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

            // Initial load
            loadUsers(currentUserPage);
        });
    </script>
</body>
</html>
