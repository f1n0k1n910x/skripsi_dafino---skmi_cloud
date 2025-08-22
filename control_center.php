<?php
include 'config.php';
include 'functions.php'; // Include functions.php for logActivity, formatBytes, etc.

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Define $currentUserRole from session
$currentUserRole = isset($_SESSION['role']) ? $_SESSION['role'] : 'guest'; // Default to 'guest' if not set

// Check if the user has admin or moderator role
if ($currentUserRole !== 'admin' && $currentUserRole !== 'moderator') {
    header("Location: index.php"); // Redirect if not authorized
    exit();
}

$currentUserId = $_SESSION['user_id'];

// Handle form submission for creating new member account
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_member_account'])) {
    $newUsername = trim($_POST['new_username']);
    $newPassword = password_hash(trim($_POST['new_password']), PASSWORD_DEFAULT);
    $newEmail = trim($_POST['new_email']);
    $newFullName = trim($_POST['new_full_name']);
    $newRole = trim($_POST['new_role']); // Should be 'member' or 'user' for new accounts

    // Basic validation
    if (empty($newUsername) || empty($newPassword) || empty($newEmail) || empty($newFullName) || empty($newRole)) {
        $message = "All fields are required.";
        $messageType = "error";
    } elseif (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
        $message = "Invalid email format.";
        $messageType = "error";
    } else {
        // Check if username or email already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $newUsername, $newEmail);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $message = "Username or email already exists.";
            $messageType = "error";
        } else {
            // Insert new user
            $stmt = $conn->prepare("INSERT INTO users (username, password, email, full_name, role) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $newUsername, $newPassword, $newEmail, $newFullName, $newRole);
            if ($stmt->execute()) {
                $message = "Member account created successfully!";
                $messageType = "success";
                logActivity($conn, $currentUserId, 'create_member_account', "Created new member account: " . $newUsername);
            } else {
                $message = "Error creating member account: " . $stmt->error;
                $messageType = "error";
            }
        }
        $stmt->close();
    }
    // Store message in session to display after redirect
    $_SESSION['message'] = $message;
    $_SESSION['message_type'] = $messageType;
    header("Location: control_center.php"); // Redirect to prevent form resubmission
    exit();
}

// Get search query for members
$searchMemberQuery = isset($_GET['search_member']) ? $_GET['search_member'] : '';

// Fetch members based on search query
$members = [];
$sql = "SELECT id, username, email, full_name, role, last_active, last_login FROM users";
$params = [];
$types = "";

if (!empty($searchMemberQuery)) {
    $sql .= " WHERE username LIKE ? OR email LIKE ? OR full_name LIKE ?";
    $searchTerm = '%' . $searchMemberQuery . '%';
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= "sss";
}
$sql .= " ORDER BY username ASC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $isOnline = (strtotime($row['last_active']) > strtotime('-15 minutes'));
    $row['is_online'] = $isOnline;
    $members[] = $row;
}
$stmt->close();

// Simulated data for storage (from index.php)
$totalStorageGB = 500;
$totalStorageBytes = $totalStorageGB * 1024 * 1024 * 1024;

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

// Check for messages from session
$message = '';
$messageType = '';
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $messageType = $_SESSION['message_type'];
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SKMI Cloud Storage - Control Center</title>
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

        /* Perbaikan Animasi Hover dan Active (Diambil dari index.php terbaru) */
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

        .sidebar-menu a i {
            margin-right: 15px;
            font-size: 1.4em;
            width: 25px; /* Fixed width for icons */
            text-align: center;
        }

        /* Storage Info */
        .storage-info {
            padding: 20px;
            border-top: 1px solid rgba(255,255,255,0.1);
            text-align: center;
            font-size: 0.9em;
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
            border-radius: 0; /* MODIFIED: No rounded corners for full width */
            margin: 0; /* MODIFIED: Full width */
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

        /* Control Center Specific Styles */
        .section-title {
            font-size: 1.8em;
            font-weight: 300;
            color: var(--metro-text-color);
            margin-bottom: 20px;
            border-bottom: 1px solid var(--metro-light-gray);
            padding-bottom: 10px;
        }

        .form-section, .member-list-section {
            background-color: #FFFFFF;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .form-section form {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .form-section label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--metro-text-color);
        }

        .form-section input[type="text"],
        .form-section input[type="password"],
        .form-section input[type="email"],
        .form-section select {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--metro-medium-gray);
            border-radius: 3px;
            font-size: 1em;
            color: var(--metro-text-color);
            background-color: #F9F9F9;
            transition: border-color 0.2s ease-out, box-shadow 0.2s ease-out;
        }

        .form-section input[type="text"]:focus,
        .form-section input[type="password"]:focus,
        .form-section input[type="email"]:focus,
        .form-section select:focus {
            border-color: var(--metro-blue);
            box-shadow: 0 0 0 2px rgba(0,120,215,0.3);
            outline: none;
            background-color: #FFFFFF;
        }

        .form-section button[type="submit"] {
            grid-column: 2 / 3; /* Align with the second column */
            justify-self: end; /* Align to the end of the grid cell */
            background-color: var(--metro-blue);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 1.1em;
            transition: background-color 0.2s ease-out, transform 0.1s ease-in-out;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin-top: 10px;
        }

        .form-section button[type="submit"]:hover {
            background-color: var(--metro-dark-blue);
            transform: translateY(-1px);
        }

        .form-section button[type="submit"]:active {
            transform: translateY(0);
        }

        .member-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .member-table th, .member-table td {
            text-align: left;
            padding: 12px 15px;
            border-bottom: 1px solid var(--metro-light-gray);
            font-size: 0.95em;
        }

        .member-table th {
            background-color: var(--metro-bg-color);
            color: var(--metro-dark-gray);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85em;
        }

        .member-table tbody tr:hover {
            background-color: var(--metro-light-gray);
        }

        .member-table .status-indicator {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 8px;
            vertical-align: middle;
        }

        .member-table .status-indicator.online { background-color: var(--metro-success); }
        .member-table .status-indicator.offline { background-color: var(--metro-error); }

        .member-table .action-buttons button {
            background-color: var(--metro-blue);
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 0.9em;
            transition: background-color 0.2s ease-out;
        }

        .member-table .action-buttons button:hover {
            background-color: var(--metro-dark-blue);
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

        /* General button hover/active effects */
        button {
            outline: none;
        }
        button:focus {
            box-shadow: 0 0 0 2px rgba(0,120,215,0.5); /* Focus ring */
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
        .header-main .control-center-title {
            display: block; /* "Control Center" visible on desktop */
        }
        /* .header-main .search-bar-desktop { */
        /*     display: flex; /* Search bar in header on desktop */
        /* } */
        .search-bar-mobile {
            display: none; /* Mobile search bar hidden on desktop */
        }

        /* New styles for desktop search bar placement */
        .member-monitoring-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            border-bottom: 1px solid var(--metro-light-gray);
            padding-bottom: 10px;
        }

        .member-monitoring-header .section-title {
            margin: 0;
            border-bottom: none;
            padding-bottom: 0;
        }

        .member-monitoring-header .search-bar-desktop {
            display: flex; /* Ensure it's visible here */
            width: auto; /* Adjust width as needed */
        }

        /* Class for iPad & Tablet (Landscape: min-width 768px, max-width 1024px) */
        @media (min-width: 768px) and (max-width: 1024px) {
            body.tablet-landscape .sidebar {
                width: 220px; /* Slightly narrower sidebar */
            }
            body.tablet-landscape .main-content {
                padding: 20px;
            }
            body.tablet-landscape .header-main {
                padding: 10px 20px;
                margin: -20px -20px 25px -20px; /* Adjusted margin for full width */
            }
            body.tablet-landscape .header-main h1 {
                font-size: 2em;
            }
            body.tablet-landscape .search-bar input {
                width: 200px;
            }
            body.tablet-landscape .form-section form {
                grid-template-columns: 1fr; /* Stack form fields */
            }
            body.tablet-landscape .form-section button[type="submit"] {
                grid-column: span 1;
                justify-self: stretch; /* Stretch button to full width */
            }
            body.tablet-landscape .member-table th,
            body.tablet-landscape .member-table td {
                padding: 10px 12px;
                font-size: 0.9em;
            }
            body.tablet-landscape .sidebar-menu a {
                font-size: var(--sidebar-font-size-tablet-landscape);
            }
            body.tablet-landscape .member-monitoring-header .search-bar-desktop {
                display: none; /* Hide desktop search bar on tablet landscape */
            }
            body.tablet-landscape .search-bar-mobile {
                display: flex; /* Show mobile search bar */
                margin: 0 auto 20px auto; /* Centered below header */
                width: calc(100% - 40px); /* Adjusted width for padding */
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
            }
            body.tablet-portrait .sidebar.show-mobile-sidebar {
                transform: translateX(0);
            }
            body.tablet-portrait .sidebar-toggle-btn {
                display: block;
                background: none;
                border: none;
                font-size: 1.8em;
                color: var(--metro-text-color);
                cursor: pointer;
                margin-left: 10px;
                order: 0;
            }
            body.tablet-portrait .header-main {
                justify-content: flex-start; /* Align items to start */
                padding: 10px 20px;
                margin: -20px -20px 25px -20px; /* Adjusted margin for full width */
            }
            body.tablet-portrait .header-main h1 {
                font-size: 2em;
                flex-grow: 1;
                text-align: center;
                margin-right: 15px; /* Space for toggle button */
            }
            body.tablet-portrait .header-main .control-center-title {
                display: none;
            }
            body.tablet-portrait .member-monitoring-header .search-bar-desktop {
                display: none; /* Hide desktop search bar */
            }
            body.tablet-portrait .search-bar-mobile {
                display: flex;
                margin: 0 auto 20px auto; /* Centered below header */
                width: calc(100% - 40px); /* Adjusted width for padding */
            }
            body.tablet-portrait .main-content {
                padding: 20px;
            }
            body.tablet-portrait .form-section form {
                grid-template-columns: 1fr;
            }
            body.tablet-portrait .form-section button[type="submit"] {
                grid-column: span 1;
                justify-self: stretch; /* Stretch button to full width */
            }
            body.tablet-portrait .member-table thead {
                display: none; /* Hide table header on mobile for better stacking */
            }
            body.tablet-portrait .member-table tbody tr {
                display: flex;
                flex-wrap: wrap;
                border: 1px solid var(--metro-light-gray);
                margin-bottom: 10px;
                border-radius: 5px;
                background-color: #FFFFFF;
                position: relative;
            }
            body.tablet-portrait .member-table td {
                display: block;
                width: 100%;
                padding: 8px 15px;
                font-size: 0.8em;
                border-bottom: none;
                white-space: normal;
                text-align: left;
            }
            body.tablet-portrait .member-table td:nth-child(1) { /* ID */
                position: absolute;
                top: 8px;
                right: 8px;
                width: auto;
                padding: 0;
                font-weight: bold;
                color: var(--metro-dark-gray);
            }
            body.tablet-portrait .member-table td:nth-child(2) { /* Username */
                padding-top: 15px;
                font-weight: 600;
                font-size: 0.9em;
            }
            body.tablet-portrait .member-table td:nth-child(3)::before { content: "Full Name: "; font-weight: normal; color: var(--metro-dark-gray); }
            body.tablet-portrait .member-table td:nth-child(4)::before { content: "Email: "; font-weight: normal; color: var(--metro-dark-gray); }
            body.tablet-portrait .member-table td:nth-child(5)::before { content: "Role: "; font-weight: normal; color: var(--metro-dark-gray); }
            body.tablet-portrait .member-table td:nth-child(6)::before { content: "Last Login: "; font-weight: normal; color: var(--metro-dark-gray); }
            body.tablet-portrait .member-table td:nth-child(7)::before { content: "Status: "; font-weight: normal; color: var(--metro-dark-gray); }
            body.tablet-portrait .member-table td:nth-child(8)::before { content: "Actions: "; font-weight: normal; color: var(--metro-dark-gray); }
            body.tablet-portrait .member-table td:nth-child(3),
            body.tablet-portrait .member-table td:nth-child(4),
            body.tablet-portrait .member-table td:nth-child(5),
            body.tablet-portrait .member-table td:nth-child(6),
            body.tablet-portrait .member-table td:nth-child(7),
            body.tablet-portrait .member-table td:nth-child(8) {
                display: inline-block;
                width: 100%;
                box-sizing: border-box;
                padding-top: 0;
                padding-bottom: 0;
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
                width: 200px;
                z-index: 100;
                transform: translateX(-100%);
            }
            body.mobile .sidebar.show-mobile-sidebar {
                transform: translateX(0);
            }
            body.mobile .sidebar-toggle-btn {
                display: block;
                background: none;
                border: none;
                font-size: 1.5em;
                color: var(--metro-text-color);
                cursor: pointer;
                margin-left: 10px;
                order: 0;
            }
            body.mobile .header-main {
                justify-content: flex-start; /* Align items to start */
                padding: 10px 15px;
                margin: -15px -15px 20px -15px; /* Adjusted margin for full width */
            }
            body.mobile .header-main h1 {
                font-size: 1.8em;
                flex-grow: 1;
                text-align: center;
                margin-right: 10px; /* Space for toggle button */
            }
            body.mobile .header-main .control-center-title {
                display: none;
            }
            body.mobile .member-monitoring-header .search-bar-desktop {
                display: none; /* Hide desktop search bar */
            }
            body.mobile .search-bar-mobile {
                display: flex;
                margin: 0 auto 20px auto; /* Centered below header */
                width: calc(100% - 30px); /* Adjusted width for padding */
            }
            body.mobile .main-content {
                padding: 15px;
                overflow-x: hidden;
            }
            body.mobile .form-section form {
                grid-template-columns: 1fr;
            }
            body.mobile .form-section button[type="submit"] {
                grid-column: span 1;
                justify-self: stretch; /* Stretch button to full width */
            }
            body.mobile .member-table thead {
                display: none;
            }
            body.mobile .member-table tbody tr {
                display: flex;
                flex-wrap: wrap;
                border: 1px solid var(--metro-light-gray);
                margin-bottom: 10px;
                border-radius: 5px;
                background-color: #FFFFFF;
                position: relative;
            }
            body.mobile .member-table td {
                display: block;
                width: 100%;
                padding: 8px 15px;
                font-size: 0.8em;
                border-bottom: none;
                white-space: normal;
                text-align: left;
            }
            body.mobile .member-table td:nth-child(1) { /* ID */
                position: absolute;
                top: 8px;
                right: 8px;
                width: auto;
                padding: 0;
                font-weight: bold;
                color: var(--metro-dark-gray);
            }
            body.mobile .member-table td:nth-child(2) { /* Username */
                padding-top: 15px;
                font-weight: 600;
                font-size: 0.9em;
            }
            body.mobile .member-table td:nth-child(3)::before { content: "Full Name: "; font-weight: normal; color: var(--metro-dark-gray); }
            body.mobile .member-table td:nth-child(4)::before { content: "Email: "; font-weight: normal; color: var(--metro-dark-gray); }
            body.mobile .member-table td:nth-child(5)::before { content: "Role: "; font-weight: normal; color: var(--metro-dark-gray); }
            body.mobile .member-table td:nth-child(6)::before { content: "Last Login: "; font-weight: normal; color: var(--metro-dark-gray); }
            body.mobile .member-table td:nth-child(7)::before { content: "Status: "; font-weight: normal; color: var(--metro-dark-gray); }
            body.mobile .member-table td:nth-child(8)::before { content: "Actions: "; font-weight: normal; color: var(--metro-dark-gray); }
            body.mobile .member-table td:nth-child(3),
            body.mobile .member-table td:nth-child(4),
            body.mobile .member-table td:nth-child(5),
            body.mobile .member-table td:nth-child(6),
            body.mobile .member-table td:nth-child(7),
            body.mobile .member-table td:nth-child(8) {
                display: inline-block;
                width: 100%;
                box-sizing: border-box;
                padding-top: 0;
                padding-bottom: 0;
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

        /* Modal Styles */
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
        .modal input[type="password"],
        .modal input[type="email"],
        .modal select {
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
        .modal input[type="password"]:focus,
        .modal input[type="email"]:focus,
        .modal select:focus {
            border-color: var(--metro-blue);
            box-shadow: 0 0 0 2px rgba(0,120,215,0.3);
            outline: none;
            background-color: #FFFFFF;
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

        /* Windows 7-like Animations for Modals */
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
    </style>
</head>
<body>
    <div class="sidebar mobile-hidden">
        <div class="sidebar-header">
            <img src="img/logo.png" alt="Dafino Logo">
        </div>
        <ul class="sidebar-menu">
            <?php if ($currentUserRole === 'admin' || $currentUserRole === 'moderator'): ?>
                <li><a href="control_center.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'control_center.php') ? 'active' : ''; ?>"><i class="fas fa-cogs"></i> Control Center</a></li>
            <?php endif; ?>
            <?php if ($currentUserRole === 'admin' || $currentUserRole === 'user' || $currentUserRole === 'member'): ?>
                <li><a href="index.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'index.php') ? 'active' : ''; ?>"><i class="fas fa-folder"></i> My Drive</a></li>
                <li><a href="priority_files.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'priority_files.php') ? 'active' : ''; ?>"><i class="fas fa-star"></i> Priority File</a></li>
                <li><a href="recycle_bin.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'recycle_bin.php') ? 'active' : ''; ?>"><i class="fas fa-trash"></i> Recycle Bin</a></li>
            <?php endif; ?>
            <li><a href="summary.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'summary.php') ? 'active' : ''; ?>"><i class="fas fa-chart-line"></i> Summary</a></li>
            <li><a href="members.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'members.php') ? 'active' : ''; ?>"><i class="fas fa-users"></i> Members</a></li>
            <li><a href="profile.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'profile.php') ? 'active' : ''; ?>"><i class="fas fa-user"></i> Profile</a></li>
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
                <p class="storage-text" style="color: var(--metro-error); font-weight: bold;">Storage Full!</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="main-content">
        <div class="header-main">
            <button class="sidebar-toggle-btn" id="sidebarToggleBtn"><i class="fas fa-bars"></i></button>
            <h1 class="control-center-title">Control Center</h1>
            <!-- Desktop search bar removed from here -->
        </div>

        <?php if (!empty($message)): ?>
            <div id="customNotification" class="notification <?php echo $messageType; ?> show">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <h2 class="section-title">Create New Member Account</h2>
        <div class="form-section">
            <form action="control_center.php" method="POST" id="createMemberForm">
                <div>
                    <label for="new_username">Username:</label>
                    <input type="text" id="new_username" name="new_username" required>
                </div>
                <div>
                    <label for="new_password">Password:</label>
                    <input type="password" id="new_password" name="new_password" required>
                </div>
                <div>
                    <label for="new_email">Email:</label>
                    <input type="email" id="new_email" name="new_email" required>
                </div>
                <div>
                    <label for="new_full_name">Full Name:</label>
                    <input type="text" id="new_full_name" name="new_full_name" required>
                </div>
                <div>
                    <label for="new_role">Role:</label>
                    <select id="new_role" name="new_role" required>
                        <option value="user">User</option>
                        <option value="member">Member</option>
                        <?php if ($currentUserRole === 'admin'): // Only admin can create other admins/moderators ?>
                            <option value="moderator">Moderator</option>
                            <option value="admin">Admin</option>
                        <?php endif; ?>
                    </select>
                </div>
                <button type="submit" name="create_member_account">Create Account</button>
            </form>
        </div>

        <div class="member-monitoring-header">
            <h2 class="section-title">Member Monitoring</h2>
            <div class="search-bar search-bar-desktop">
                <i class="fas fa-search"></i>
                <input type="text" id="searchMemberInputDesktop" placeholder="Search members..." value="<?php echo htmlspecialchars($searchMemberQuery); ?>">
            </div>
        </div>

        <!-- Mobile Search Bar (moved below member monitoring section) -->
        <div class="search-bar search-bar-mobile">
            <i class="fas fa-search"></i>
            <input type="text" id="searchMemberInputMobile" placeholder="Search members..." value="<?php echo htmlspecialchars($searchMemberQuery); ?>">
        </div>

        <div class="member-list-section">
            <table class="member-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Full Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Last Login</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($members)): ?>
                        <?php foreach ($members as $member): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($member['id']); ?></td>
                                <td><?php echo htmlspecialchars($member['username']); ?></td>
                                <td><?php echo htmlspecialchars($member['full_name']); ?></td>
                                <td><?php echo htmlspecialchars(ucfirst($member['role'])); ?></td>
                                <td><?php echo htmlspecialchars($member['email']); ?></td>
                                <td><?php echo !empty($member['last_login']) ? date('Y-m-d H:i', strtotime($member['last_login'])) : 'N/A'; ?></td>
                                <td>
                                    <span class="status-indicator <?php echo $member['is_online'] ? 'online' : 'offline'; ?>"></span>
                                    <?php echo $member['is_online'] ? 'Online' : 'Offline'; ?>
                                </td>
                                <td class="action-buttons">
                                    <button onclick="viewMemberDetails(<?php echo $member['id']; ?>)">View Details</button>
                                    <!-- Add more actions like Edit, Delete if needed -->
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" style="text-align: center;">No members found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>

    <!-- Modal for Member Details (can be reused from members.php or simplified) -->
    <div id="memberDetailsModal" class="modal">
        <div class="modal-content">
            <span class="close-button">&times;</span>
            <h2>Member Details: <span id="memberDetailsUsername"></span></h2>
            <div id="memberDetailsContent">
                <p><strong>ID:</strong> <span id="detailId"></span></p>
                <p><strong>Full Name:</strong> <span id="detailFullName"></span></p>
                <p><strong>Email:</strong> <span id="detailEmail"></span></p>
                <p><strong>Role:</strong> <span id="detailRole"></span></p>
                <p><strong>Last Login:</strong> <span id="detailLastLogin"></span></p>
                <p><strong>Last Active:</strong> <span id="detailLastActive"></span></p>
                <p><strong>Status:</strong> <span id="detailStatus"></span></p>
                <!-- You can add more details here, like total files, storage used by this member, etc. -->
            </div>
        </div>
    </div>

    <div class="overlay" id="mobileOverlay"></div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.querySelector('.sidebar');
            const sidebarToggleBtn = document.getElementById('sidebarToggleBtn');
            const mobileOverlay = document.getElementById('mobileOverlay');
            const searchMemberInputDesktop = document.getElementById('searchMemberInputDesktop'); // Desktop search
            const searchMemberInputMobile = document.getElementById('searchMemberInputMobile'); // Mobile search
            const customNotification = document.getElementById('customNotification');
            const createMemberForm = document.getElementById('createMemberForm');

            // Function to show custom notification
            function showNotification(message, type) {
                customNotification.innerHTML = message;
                customNotification.className = 'notification show ' + type;
                setTimeout(() => {
                    customNotification.classList.remove('show');
                }, 3000);
            }

            // Display message from session if exists
            <?php if (!empty($message)): ?>
                showNotification("<?php echo $message; ?>", "<?php echo $messageType; ?>");
            <?php endif; ?>

            // --- Responsive Class Handling ---
            function applyDeviceClass() {
                const width = window.innerWidth;
                const body = document.body;

                body.classList.remove('mobile', 'tablet-portrait', 'tablet-landscape', 'desktop');

                if (width <= 767) {
                    body.classList.add('mobile');
                    sidebar.classList.add('mobile-hidden');
                } else if (width >= 768 && width <= 1024) {
                    if (window.matchMedia("(orientation: portrait)").matches) {
                        body.classList.add('tablet-portrait');
                        sidebar.classList.add('mobile-hidden');
                    } else {
                        body.classList.add('tablet-landscape');
                        sidebar.classList.remove('mobile-hidden');
                        sidebar.classList.remove('show-mobile-sidebar');
                        mobileOverlay.classList.remove('show');
                    }
                } else {
                    body.classList.add('desktop');
                    sidebar.classList.remove('mobile-hidden');
                    sidebar.classList.remove('show-mobile-sidebar');
                    mobileOverlay.classList.remove('show');
                }
            }

            applyDeviceClass();
            window.addEventListener('resize', applyDeviceClass);
            window.addEventListener('orientationchange', applyDeviceClass);

            // --- Mobile Sidebar Toggle ---
            sidebarToggleBtn.addEventListener('click', () => {
                sidebar.classList.toggle('show-mobile-sidebar');
                mobileOverlay.classList.toggle('show');
            });

            mobileOverlay.addEventListener('click', () => {
                sidebar.classList.remove('show-mobile-sidebar');
                mobileOverlay.classList.remove('show');
            });

            // --- Member Search Functionality (No Reload Page) ---
            function updateMemberList(query) {
                const xhr = new XMLHttpRequest();
                xhr.open('GET', 'control_center.php?search_member=' + encodeURIComponent(query) + '&ajax=1', true);
                xhr.onload = function() {
                    if (this.status === 200) {
                        const parser = new DOMParser();
                        const doc = parser.parseFromString(this.responseText, 'text/html');
                        const newTableBody = doc.querySelector('.member-table tbody');
                        const currentTableBody = document.querySelector('.member-table tbody');
                        if (newTableBody && currentTableBody) {
                            currentTableBody.innerHTML = newTableBody.innerHTML;
                        }
                    }
                };
                xhr.send();
            }

            searchMemberInputDesktop.addEventListener('keyup', function() {
                updateMemberList(this.value);
            });

            searchMemberInputMobile.addEventListener('keyup', function() {
                updateMemberList(this.value);
            });

            // --- Create Member Account (No Reload Page) ---
            createMemberForm.addEventListener('submit', function(e) {
                e.preventDefault(); // Prevent default form submission

                const formData = new FormData(this);
                formData.append('create_member_account', '1'); // Ensure the PHP script recognizes this as a creation request

                fetch('control_center.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.text()) // Get response as text
                .then(text => {
                    // Parse the response to find the message and messageType
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(text, 'text/html');
                    const notificationDiv = doc.getElementById('customNotification');
                    
                    let message = "An unknown error occurred.";
                    let messageType = "error";

                    if (notificationDiv) {
                        message = notificationDiv.textContent.trim();
                        if (notificationDiv.classList.contains('success')) {
                            messageType = 'success';
                        } else if (notificationDiv.classList.contains('error')) {
                            messageType = 'error';
                        }
                    }

                    showNotification(message, messageType);

                    // If successful, clear form and update member list
                    if (messageType === 'success') {
                        createMemberForm.reset();
                        updateMemberList(''); // Refresh member list
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('An error occurred during account creation.', 'error');
                });
            });


            // --- Member Details Modal ---
            const memberDetailsModal = document.getElementById('memberDetailsModal');
            const closeButtons = memberDetailsModal.querySelectorAll('.close-button');

            // Function to open modal
            function openModal(modalElement) {
                modalElement.classList.add('show');
            }

            // Function to close modal
            function closeModal(modalElement) {
                modalElement.classList.remove('show');
            }

            closeButtons.forEach(button => {
                button.addEventListener('click', () => {
                    closeModal(memberDetailsModal);
                });
            });

            window.addEventListener('click', (event) => {
                if (event.target == memberDetailsModal) {
                    closeModal(memberDetailsModal);
                }
            });

            // Function to view member details (simplified for this example)
            window.viewMemberDetails = function(memberId) {
                // In a real application, you would fetch more details via AJAX
                // For now, we'll just find the member in the current list
                const member = <?php echo json_encode($members); ?>.find(m => m.id == memberId);

                if (member) {
                    document.getElementById('memberDetailsUsername').textContent = member.username;
                    document.getElementById('detailId').textContent = member.id;
                    document.getElementById('detailFullName').textContent = member.full_name;
                    document.getElementById('detailEmail').textContent = member.email;
                    document.getElementById('detailRole').textContent = member.role.charAt(0).toUpperCase() + member.role.slice(1);
                    document.getElementById('detailLastLogin').textContent = member.last_login ? new Date(member.last_login.replace(/-/g, '/')).toLocaleString() : 'N/A';
                    document.getElementById('detailLastActive').textContent = member.last_active ? new Date(member.last_active.replace(/-/g, '/')).toLocaleString() : 'N/A';
                    document.getElementById('detailStatus').textContent = member.is_online ? 'Online' : 'Offline';
                    openModal(memberDetailsModal); // Use openModal function
                } else {
                    showNotification('Member details not found.', 'error');
                }
            };

            // Set active class for current page in sidebar
            const sidebarMenuItems = document.querySelectorAll('.sidebar-menu a');
            const currentPage = window.location.pathname.split('/').pop();
            sidebarMenuItems.forEach(item => {
                item.classList.remove('active');
                const itemHref = item.getAttribute('href');
                if (itemHref === currentPage || (currentPage === 'index.php' && itemHref === 'index.php')) {
                    item.classList.add('active');
                }
            });
        });
    </script>
</body>
</html>
