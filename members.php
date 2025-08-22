<?php
include 'config.php';
include 'functions.php'; // Include functions.php for logActivity, formatBytes, getFolderSize, etc.

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

$currentUserId = $_SESSION['user_id'];
$membersPerPage = 6; // Number of members to display per page in the main table

// Function to fetch all dashboard data
function getDashboardData($conn, $currentUserId) {
    global $membersPerPage;
    $data = [];

    // Total Active Users
    // MODIFIKASI: Menghapus WHERE is_member = 1 agar semua user terhitung
    $stmt = $conn->prepare("SELECT COUNT(id) AS total_users FROM users");
    $stmt->execute();
    $result = $stmt->get_result();
    $data['totalUsers'] = $result->fetch_assoc()['total_users'];
    $stmt->close();

    // Total Public Files
    $stmt = $conn->prepare("SELECT COUNT(id) AS total_public_files FROM files");
    $stmt->execute();
    $result = $stmt->get_result();
    $data['totalPublicFiles'] = $result->fetch_assoc()['total_public_files'];
    $stmt->close();

    // Total Storage Used
    $totalStorageGB = 500; // Example: total storage capacity 700 GB
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
    $data['usedStorageBytes'] = $usedStorageBytes;
    $data['totalStorageGB'] = $totalStorageGB;
    $data['totalStorageBytes'] = $totalStorageBytes; // Pass totalStorageBytes
    $data['usedPercentage'] = ($totalStorageBytes > 0) ? ($usedStorageBytes / $totalStorageBytes) * 100 : 0;
    if ($data['usedPercentage'] > 100) $data['usedPercentage'] = 100;

    // Check if storage is full
    $data['isStorageFull'] = isStorageFull($conn, $totalStorageBytes);

    // Weekly Activity (last 7 days)
    $stmt = $conn->prepare("SELECT COUNT(id) AS weekly_activities FROM activities WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $stmt->execute();
    $result = $stmt->get_result();
    $data['weeklyActivities'] = $result->fetch_assoc()['weekly_activities'];
    $stmt->close();

    // Member List (Paginated - Initial Load)
    $members = [];
    $offset = 0;
    // MODIFIKASI: Menghapus WHERE is_member = 1 agar semua user terhitung
    $stmt = $conn->prepare("SELECT id, username, email, full_name, last_active, last_login FROM users ORDER BY username ASC LIMIT ? OFFSET ?");
    $stmt->bind_param("ii", $membersPerPage, $offset);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        // Online status check (active in last 15 minutes)
        $isOnline = (strtotime($row['last_active']) > strtotime('-15 minutes'));

        $members[] = [
            'id' => $row['id'],
            'username' => $row['username'],
            'full_name' => $row['full_name'],
            'email' => $row['email'],
            'last_active' => $row['last_active'],
            'last_login' => $row['last_login'],
            'is_online' => $isOnline
        ];
    }
    $stmt->close();
    $data['members'] = $members;

    // Activity Distribution (for Pie Chart)
    $activityDistribution = [];
    $stmt = $conn->prepare("SELECT activity_type, COUNT(id) as count FROM activities GROUP BY activity_type");
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $activityDistribution[$row['activity_type']] = $row['count'];
    }
    $stmt->close();
    $data['activityDistribution'] = $activityDistribution;

    // Top Members by Public Files (for Bar Chart)
    $topMembersPublicFiles = [];
    $stmt = $conn->prepare("
        SELECT u.username, COUNT(f.id) AS public_files_count
        FROM users u
        JOIN files f ON u.id = f.user_id
        GROUP BY u.username
        ORDER BY public_files_count DESC
        LIMIT 5
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $topMembersPublicFiles[] = $row;
    }
    $stmt->close();
    $data['topMembersPublicFiles'] = $topMembersPublicFiles;

    // Daily Activity (for Line Chart)
    $dailyActivities = [];
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $dailyActivities[$date] = 0;
    }
    $stmt = $conn->prepare("SELECT DATE(timestamp) as activity_date, COUNT(id) as count FROM activities WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY) GROUP BY activity_date ORDER BY activity_date ASC");
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $dailyActivities[$row['activity_date']] = $row['count'];
    }
    $stmt->close();
    $data['dailyActivities'] = $dailyActivities;

    // Recent Activities
    $recentActivities = [];
    $stmt = $conn->prepare("
        SELECT a.activity_type, a.description, u.username, a.timestamp
        FROM activities a
        JOIN users u ON a.user_id = u.id
        ORDER BY a.timestamp DESC
        LIMIT 10
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $recentActivities[] = $row;
    }
    $stmt->close();
    $data['recentActivities'] = $recentActivities;

    // Current User's Mini Profile
    $currentUserProfile = [];
    $stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
    $stmt->bind_param("i", $currentUserId);
    $stmt->execute();
    $result = $stmt->get_result();
    $currentUserProfile['username'] = $result->fetch_assoc()['username'];
    $stmt->close();

    $stmt = $conn->prepare("SELECT COUNT(id) AS total_files FROM files WHERE user_id = ?");
    $stmt->bind_param("i", $currentUserId);
    $stmt->execute();
    $result = $stmt->get_result();
    $profileData = $result->fetch_assoc();
    $currentUserProfile['total_files'] = $profileData['total_files'];
    $stmt->close();

    $stmt = $conn->prepare("SELECT COUNT(id) AS public_files FROM files WHERE user_id = ?");
    $stmt->bind_param("i", $currentUserId);
    $stmt->execute();
    $result = $stmt->get_result();
    $currentUserProfile['public_files'] = $result->fetch_assoc()['public_files'];
    $stmt->close();

    $currentUserProfile['storage_used'] = formatBytes($usedStorageBytes); // Use the globally calculated usedStorageBytes

    $stmt = $conn->prepare("SELECT COUNT(id) AS weekly_activities FROM activities WHERE user_id = ? AND timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $stmt->bind_param("i", $currentUserId);
    $stmt->execute();
    $result = $stmt->get_result();
    $currentUserProfile['weekly_activities'] = $result->fetch_assoc()['weekly_activities'];
    $stmt->close();
    $data['currentUserProfile'] = $currentUserProfile;

    return $data;
}

// Function to get paginated members data
function getPaginatedMembers($conn, $page, $membersPerPage) {
    $offset = ($page - 1) * $membersPerPage;
    $members = [];

    // MODIFIKASI: Menghapus WHERE is_member = 1 agar semua user terhitung
    $stmt = $conn->prepare("SELECT id, username, email, full_name, last_active, last_login FROM users ORDER BY username ASC LIMIT ? OFFSET ?");
    $stmt->bind_param("ii", $membersPerPage, $offset);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        // Online status check (active in last 15 minutes)
        $isOnline = (strtotime($row['last_active']) > strtotime('-15 minutes'));

        $members[] = [
            'id' => $row['id'],
            'username' => $row['username'],
            'full_name' => $row['full_name'],
            'email' => $row['email'],
            'last_active' => $row['last_active'],
            'last_login' => $row['last_login'],
            'is_online' => $isOnline
        ];
    }
    $stmt->close();
    return $members;
}

// Handle AJAX request for dashboard data
if (isset($_GET['action']) && $_GET['action'] === 'get_dashboard_data') {
    header('Content-Type: application/json');
    echo json_encode(getDashboardData($conn, $currentUserId));
    $conn->close();
    exit();
}

// Handle AJAX request for paginated members
if (isset($_GET['action']) && $_GET['action'] === 'get_members') {
    header('Content-Type: application/json');
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $totalMembersCount = 0;
    // MODIFIKASI: Menghapus WHERE is_member = 1 agar semua user terhitung
    $stmt = $conn->prepare("SELECT COUNT(id) AS total_members FROM users");
    $stmt->execute();
    $result = $stmt->get_result();
    $totalMembersCount = $result->fetch_assoc()['total_members'];
    $stmt->close();

    $membersData = getPaginatedMembers($conn, $page, $membersPerPage);

    echo json_encode([
        'success' => true,
        'members' => $membersData,
        'totalMembers' => $totalMembersCount,
        'membersPerPage' => $membersPerPage,
        'totalPages' => ceil($totalMembersCount / $membersPerPage)
    ]);
    $conn->close();
    exit();
}

// Handle AJAX request for paginated member details (Recent Files and Activities)
if (isset($_GET['action']) && $_GET['action'] === 'get_member_details_paginated') {
    header('Content-Type: application/json');

    $memberId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $filesPage = isset($_GET['files_page']) ? (int)$_GET['files_page'] : 1;
    $activitiesPage = isset($_GET['activities_page']) ? (int)$_GET['activities_page'] : 1;
    $itemsPerPage = 5; // 5 items per page for recent files and activities

    if ($memberId === 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid member ID.']);
        exit();
    }

    $memberDetails = [];

    // Fetch member username
    $stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
    $stmt->bind_param("i", $memberId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Member not found.']);
        exit();
    }

    $memberDetails['username'] = $user['username'];

    // Fetch total files for this member (not just public)
    $stmt = $conn->prepare("SELECT COUNT(id) AS total_files FROM files WHERE user_id = ?");
    $stmt->bind_param("i", $memberId);
    $stmt->execute();
    $result = $stmt->get_result();
    $memberDetails['total_files'] = $result->fetch_assoc()['total_files'];
    $stmt->close();

    // Fetch total public files for this member
    $stmt = $conn->prepare("SELECT COUNT(id) AS total_public_files FROM files WHERE user_id = ? AND folder_id IS NULL");
    $stmt->bind_param("i", $memberId);
    $stmt->execute();
    $result = $stmt->get_result();
    $memberDetails['total_public_files'] = $result->fetch_assoc()['total_public_files'];
    $stmt->close();

    // Fetch paginated recent files
    $filesOffset = ($filesPage - 1) * $itemsPerPage;
    $recentFiles = [];
    $stmt = $conn->prepare("SELECT file_name, file_size, file_type FROM files WHERE user_id = ? ORDER BY uploaded_at DESC LIMIT ? OFFSET ?");
    $stmt->bind_param("iii", $memberId, $itemsPerPage, $filesOffset);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $recentFiles[] = $row;
    }
    $memberDetails['recent_files'] = $recentFiles;
    $stmt->close();

    // Count total files for pagination
    $stmt = $conn->prepare("SELECT COUNT(id) AS total_count FROM files WHERE user_id = ?");
    $stmt->bind_param("i", $memberId);
    $stmt->execute();
    $result = $stmt->get_result();
    $totalFilesCount = $result->fetch_assoc()['total_count'];
    $memberDetails['total_files_pages'] = ceil($totalFilesCount / $itemsPerPage);
    $stmt->close();

    // Fetch paginated recent activities
    $activitiesOffset = ($activitiesPage - 1) * $itemsPerPage;
    $recentActivities = [];
    $stmt = $conn->prepare("SELECT activity_type, description, timestamp FROM activities WHERE user_id = ? ORDER BY timestamp DESC LIMIT ? OFFSET ?");
    $stmt->bind_param("iii", $memberId, $itemsPerPage, $activitiesOffset);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $recentActivities[] = $row;
    }
    $memberDetails['recent_activities'] = $recentActivities;
    $stmt->close();

    // Count total activities for pagination
    $stmt = $conn->prepare("SELECT COUNT(id) AS total_count FROM activities WHERE user_id = ?");
    $stmt->bind_param("i", $memberId);
    $stmt->execute();
    $result = $stmt->get_result();
    $totalActivitiesCount = $result->fetch_assoc()['total_count'];
    $memberDetails['total_activities_pages'] = ceil($totalActivitiesCount / $itemsPerPage);
    $stmt->close();

    echo json_encode(['success' => true, 'member' => $memberDetails]);
    $conn->close();
    exit();
}


// Initial data load for the first page render
$dashboardData = getDashboardData($conn, $currentUserId);

// Extract data for HTML rendering
$totalUsers = $dashboardData['totalUsers'];
$totalPublicFiles = $dashboardData['totalPublicFiles'];
$usedStorageBytes = $dashboardData['usedStorageBytes'];
$totalStorageGB = $dashboardData['totalStorageGB'];
$totalStorageBytes = $dashboardData['totalStorageBytes']; // Get totalStorageBytes
$usedPercentage = $dashboardData['usedPercentage'];
$isStorageFull = $dashboardData['isStorageFull']; // Get isStorageFull
$weeklyActivities = $dashboardData['weeklyActivities'];
$members = $dashboardData['members'];
$activityDistribution = $dashboardData['activityDistribution'];
$topMembersPublicFiles = $dashboardData['topMembersPublicFiles'];
$dailyActivities = $dashboardData['dailyActivities'];
$recentActivities = $dashboardData['recentActivities'];
$currentUserProfile = $dashboardData['currentUserProfile'];

// Get total member count for pagination
$totalMembersCount = 0;
// MODIFIKASI: Menghapus WHERE is_member = 1 agar semua user terhitung
$stmt = $conn->prepare("SELECT COUNT(id) AS total_members FROM users");
$stmt->execute();
$result = $stmt->get_result();
$totalMembersCount = $result->fetch_assoc()['total_members'];
$stmt->close();
$totalPages = ceil($totalMembersCount / $membersPerPage);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SKMI Cloud Storage - Members Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

        * {
            box-sizing: border-box;
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
            border-radius: 0; /* MODIFIED: No rounded corners for full width */
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
            /*box-shadow: 0 2px 5px rgba(0,0,0,0.05); */
        }

        .header-main h1 {
            margin: 0;
            color: var(--metro-text-color);
            font-size: 2.5em;
            font-weight: 300;
        }

        /* Dashboard Specific Styles (from summary.php) */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); /* Adjusted minmax for better fit */
            gap: 20px;
            margin-bottom: 30px;
        }

        /* Card styles (from summary.php) */
        .card {
            background-color: var(--metro-blue);
            color: #FFFFFF;
            padding: 25px;
            border-radius: 5px;
            /*box-shadow: 0 4px 12px rgba(0,0,0,0.15);*/
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: transform 0.2s ease-out, box-shadow 0.2s ease-out;
            animation: fadeIn 0.5s ease-out forwards;
            opacity: 0;
        }
        .card:nth-child(1) { animation-delay: 0.1s; }
        .card:nth-child(2) { animation-delay: 0.2s; }
        .card:nth-child(3) { animation-delay: 0.3s; }
        .card:nth-child(4) { animation-delay: 0.4s; }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 18px rgba(0,0,0,0.2);
        }

        .card h3 {
            margin: 0 0 10px 0;
            font-size: 1.1em;
            font-weight: 400;
            opacity: 0.8;
        }

        .card p {
            margin: 0;
            font-size: 2.2em;
            font-weight: 600;
        }

        .card.green { background-color: var(--metro-success); }
        .card.orange { background-color: var(--metro-warning); }
        .card.red { background-color: var(--metro-error); }

        /* Adjust .card p for the count and storage text */
        .card p.count {
            font-size: 2.2em; /* Match .card p */
            font-weight: 600;
            color: #FFFFFF; /* White text for counts on colored cards */
            margin-top: 5px;
        }
        .card p.storage-text-card { /* New class for the "of X used" text */
            font-size: 0.9em;
            color: rgba(255,255,255,0.8);
            margin-top: 5px;
        }

        /* Member Table */
        .section-title {
            font-size: 1.8em;
            font-weight: 300;
            color: var(--metro-text-color);
            margin-bottom: 20px;
            border-bottom: 1px solid var(--metro-light-gray);
            padding-bottom: 10px;
            animation: slideInFromLeft 0.5s ease-out forwards;
            opacity: 0;
            animation-delay: 0.5s;
        }

        /* MODIFIED: Renamed .member-table-container to .table-container */
        .table-container {
            background-color: #FFFFFF;
            border-radius: 8px;
            /* Removed box-shadow to eliminate shadow */
            overflow-x: auto;
            margin-bottom: 30px;
            animation: fadeIn 0.6s ease-out forwards;
            opacity: 0;
            animation-delay: 0.6s;
        }

        .member-table {
            width: 100%;
            border-collapse: collapse;
        }

        .member-table th, .member-table td {
            text-align: left;
            padding: 15px 20px;
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

        .member-table .profile-pic {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 10px;
            vertical-align: middle;
            border: 2px solid var(--metro-medium-gray);
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

        .member-table a {
            color: var(--metro-blue);
            text-decoration: none;
            transition: color 0.2s ease-out;
        }
        .member-table a:hover {
            text-decoration: underline;
            color: var(--metro-dark-blue);
        }

        /* Pagination Controls */
        .pagination-controls {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 20px;
            margin-bottom: 10px;
            animation: fadeIn 0.6s ease-out forwards;
            opacity: 0;
            animation-delay: 0.7s;
        }

        .pagination-controls button {
            background-color: var(--metro-bg-color);
            color: var(--metro-text-color);
            border: 1px solid var(--metro-light-gray);
            padding: 8px 12px;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.2s ease-out, color 0.2s ease-out;
            font-size: 0.9em;
        }

        .pagination-controls button:hover:not(.active) {
            background-color: var(--metro-light-gray);
        }

        .pagination-controls button.active {
            background-color: var(--metro-blue);
            color: #FFFFFF;
            border-color: var(--metro-blue);
            pointer-events: none; /* Disable click on active button */
        }
        
        .pagination-controls button:disabled {
            cursor: not-allowed;
            opacity: 0.5;
        }

        /* Charts Section */
        .charts-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin-bottom: 30px;
        }

        .chart-card {
            background-color: #FFFFFF;
            padding: 25px;
            border-radius: 8px;
            /* Removed box-shadow to eliminate shadow */
            animation: fadeIn 0.7s ease-out forwards;
            opacity: 0;
        }
        .chart-card:nth-child(1) { animation-delay: 0.7s; }
        .chart-card:nth-child(2) { animation-delay: 0.8s; }
        .chart-card:nth-child(3) { animation-delay: 0.9s; }

        .chart-card h4 {
            margin-top: 0;
            margin-bottom: 20px;
            font-size: 1.4em;
            font-weight: 400;
            color: var(--metro-text-color);
            border-bottom: 1px solid var(--metro-light-gray);
            padding-bottom: 10px;
        }

        /* Recent Activities & Mini Profile Section */
        .bottom-section {
            display: grid;
            grid-template-columns: 1fr 1fr; /* Two columns, equal width */
            gap: 30px;
            margin-bottom: 30px;
        }

        /* Recent Activities */
        .recent-activities {
            background-color: #FFFFFF;
            padding: 25px;
            border-radius: 8px;
            /* Removed box-shadow to eliminate shadow */
            animation: fadeIn 1.0s ease-out forwards;
            opacity: 0;
            overflow-y: auto; /* Add scrollbar */
            max-height: 400px; /* Max height for scrollbar */
        }

        .recent-activities ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .recent-activities li {
            padding: 12px 0;
            border-bottom: 1px solid var(--metro-light-gray);
            font-size: 0.95em;
            color: var(--metro-text-color);
            display: flex;
            align-items: center;
            white-space: nowrap; /* Prevent text wrapping */
            overflow: hidden; /* Hide overflow */
            text-overflow: ellipsis; /* Add ellipsis for long text */
        }

        .recent-activities li:last-child {
            border-bottom: none;
        }

        .recent-activities li i {
            margin-right: 10px;
            font-size: 1.1em;
            width: 20px;
            text-align: center;
            flex-shrink: 0; /* Prevent icon from shrinking */
        }

        .recent-activities li .activity-text {
            flex-grow: 1; /* Allow text to take available space */
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .recent-activities li .timestamp {
            font-size: 0.85em;
            color: var(--metro-dark-gray);
            margin-left: auto;
            flex-shrink: 0; /* Prevent timestamp from shrinking */
        }

        /* Mini Profile */
        .mini-profile {
            background-color: #FFFFFF;
            padding: 25px;
            border-radius: 8px;
            /* Removed box-shadow to eliminate shadow */
            animation: fadeIn 1.1s ease-out forwards;
            opacity: 0;
            /* Adjust height to match recent activities if needed, or let content define */
        }

        .mini-profile h4 {
            margin-top: 0;
            margin-bottom: 20px;
            font-size: 1.4em;
            font-weight: 400;
            color: var(--metro-text-color);
            border-bottom: 1px solid var(--metro-light-gray);
            padding-bottom: 10px;
        }

        .mini-profile p {
            margin: 8px 0;
            font-size: 1em;
            color: var(--metro-text-color);
        }

        .mini-profile p strong {
            color: var(--metro-blue);
        }

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes slideInFromLeft {
            from { opacity: 0; transform: translateX(-50px); }
            to { opacity: 1; transform: translateX(0); }
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
        .header-main .members-title { /* Specific class for this page's title */
            display: block; /* "Members Dashboard" visible on desktop */
        }

        /* Class for iPad & Tablet (Landscape: min-width 768px, max-width 1024px) */
        @media (min-width: 768px) and (max-width: 1024px) {
            body.tablet-landscape .sidebar {
                width: 220px; /* Slightly narrower sidebar */
            }
            body.tablet-landscape .main-content {
                margin: 0; /* MODIFIED: Full width */
                padding: 20px;
                overflow-x: hidden; /* Prevent horizontal scrollbar */
            }
            body.tablet-landscape .header-main {
                padding: 10px 20px;
                margin: 0; /* MODIFIED: Full width */
            }
            body.tablet-landscape .header-main h1 {
                font-size: 2em;
            }
            body.tablet-landscape .dashboard-grid {
                grid-template-columns: repeat(2, 1fr); /* 2x2 layout */
                gap: 15px;
            }
            body.tablet-landscape .card {
                padding: 20px;
            }
            body.tablet-landscape .card h3 {
                font-size: 1em;
            }
            body.tablet-landscape .card p.count {
                font-size: 1.8em;
            }
            body.tablet-landscape .section-title {
                font-size: 1.5em;
                margin-bottom: 15px;
            }
            body.tablet-landscape .member-table th,
            body.tablet-landscape .member-table td {
                padding: 12px 15px;
                font-size: 0.9em; /* Slightly smaller font for table cells */
            }
            body.tablet-landscape .charts-section {
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); /* Adjust for 2 or 3 columns */
                gap: 20px;
            }
            body.tablet-landscape .chart-card h4 {
                font-size: 1.2em;
                margin-bottom: 15px;
            }
            body.tablet-landscape .bottom-section {
                grid-template-columns: 1fr 1fr; /* Keep 2 columns */
                gap: 20px;
            }
            body.tablet-landscape .recent-activities li,
            body.tablet-landscape .mini-profile p {
                font-size: 0.9em;
            }
            body.tablet-landscape .pagination-controls button {
                padding: 6px 10px;
                font-size: 0.8em;
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
                white-space: nowrap; /* Prevent text from wrapping */
                overflow: hidden;
                text-overflow: ellipsis;
            }
            body.tablet-portrait .header-main .members-title {
                display: none; /* Hide "Members Dashboard" */
            }
            body.tablet-portrait .main-content {
                margin: 0; /* MODIFIED: Full width */
                padding: 20px;
                overflow-x: hidden; /* Prevent horizontal scrollbar */
            }
            body.tablet-portrait .dashboard-grid {
                grid-template-columns: repeat(2, 1fr); /* 2x2 layout */
                gap: 15px;
            }
            body.tablet-portrait .card {
                padding: 18px;
            }
            body.tablet-portrait .card h3 {
                font-size: 0.95em;
            }
            body.tablet-portrait .card p.count {
                font-size: 1.6em;
            }
            body.tablet-portrait .section-title {
                font-size: 1.4em;
                margin-bottom: 12px;
            }
            body.tablet-portrait .member-table th,
            body.tablet-portrait .member-table td {
                padding: 10px 12px;
                font-size: 0.85em; /* Smaller font for table cells */
            }
            body.tablet-portrait .charts-section {
                grid-template-columns: 1fr !important; /* Force vertical stacking */
                gap: 20px;
            }
            body.tablet-portrait .chart-card h4 {
                font-size: 1.1em;
                margin-bottom: 12px;
            }
            body.tablet-portrait .bottom-section {
                grid-template-columns: 1fr !important; /* Force vertical stacking */
                gap: 20px;
            }
            body.tablet-portrait .recent-activities li,
            body.tablet-portrait .mini-profile p {
                font-size: 0.85em;
            }
            body.tablet-portrait .pagination-controls button {
                padding: 6px 10px;
                font-size: 0.8em;
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
            body.mobile .header-main .members-title {
                display: none; /* Hide "Members Dashboard" */
            }
            body.mobile .main-content {
                margin: 0; /* MODIFIED: Full width */
                padding: 15px;
                overflow-x: hidden; /* Prevent horizontal scrollbar */
            }
            body.mobile .dashboard-grid {
                grid-template-columns: repeat(2, 1fr) !important; /* 2x2 layout */
                gap: 10px;
            }
            body.mobile .card {
                padding: 15px;
            }
            body.mobile .card h3 {
                font-size: 0.9em;
            }
            body.mobile .card p.count {
                font-size: 1.4em;
            }
            body.mobile .section-title {
                font-size: 1.2em;
                margin-bottom: 10px;
            }
            body.mobile .member-table thead {
                display: none; /* Hide table header on mobile for better stacking */
            }
            body.mobile .member-table tbody tr {
                display: flex;
                flex-wrap: wrap;
                border: 1px solid var(--metro-light-gray);
                margin-bottom: 10px;
                border-radius: 5px;
                background-color: #FFFFFF;
                /*box-shadow: 0 2px 5px rgba(0,0,0,0.05);*/
                position: relative;
            }
            body.mobile .member-table td {
                display: block;
                width: 100%;
                padding: 8px 15px; /* Reduced padding */
                font-size: 0.8em; /* Smaller font for table cells */
                border-bottom: none; /* Remove individual cell borders */
                white-space: normal; /* Allow text to wrap */
                text-align: left;
            }
            body.mobile .member-table td:nth-child(1) { /* No */
                position: absolute;
                top: 8px;
                right: 8px;
                width: auto;
                padding: 0;
                font-weight: bold;
                color: var(--metro-dark-gray);
            }
            body.mobile .member-table td:nth-child(2) { /* Full Name */
                padding-top: 15px;
                font-weight: 600;
                font-size: 0.9em;
            }
            body.mobile .member-table td:nth-child(3)::before { content: "Username: "; font-weight: normal; color: var(--metro-dark-gray); }
            body.mobile .member-table td:nth-child(4)::before { content: "Email: "; font-weight: normal; color: var(--metro-dark-gray); }
            body.mobile .member-table td:nth-child(5)::before { content: "Last Login: "; font-weight: normal; color: var(--metro-dark-gray); }
            body.mobile .member-table td:nth-child(6)::before { content: "Status: "; font-weight: normal; color: var(--metro-dark-gray); }
            body.mobile .member-table td:nth-child(3),
            body.mobile .member-table td:nth-child(4),
            body.mobile .member-table td:nth-child(5),
            body.mobile .member-table td:nth-child(6) {
                display: inline-block;
                width: 100%; /* Each on its own line */
                box-sizing: border-box;
                padding-top: 0;
                padding-bottom: 0;
            }
            body.mobile .charts-section {
                grid-template-columns: 1fr !important; /* Force vertical stacking */
                gap: 15px;
            }
            body.mobile .chart-card h4 {
                font-size: 1em;
                margin-bottom: 10px;
            }
            body.mobile .bottom-section {
                grid-template-columns: 1fr !important; /* Force vertical stacking */
                gap: 15px;
            }
            body.mobile .recent-activities li {
                flex-direction: column; /* Stack content vertically */
                align-items: flex-start; /* Align content to the left */
                padding: 6px 0;
            }
            body.mobile .recent-activities li i {
                margin-right: 8px;
                margin-bottom: 0;
                width: auto;
            }
            body.mobile .recent-activities li .activity-text {
                font-size: 0.85em;
                white-space: normal; /* Allow wrapping for long text */
                text-overflow: ellipsis;
                overflow: hidden;
                display: -webkit-box;
                -webkit-line-clamp: 2;
                -webkit-box-orient: vertical;
            }
            body.mobile .recent-activities li .timestamp {
                font-size: 0.7em;
                margin-left: 0;
                margin-top: 5px; /* Add space between text and timestamp */
                text-align: left;
                width: 100%;
            }
            body.mobile .mini-profile p {
                font-size: 0.8em;
            }
            body.mobile .pagination-controls button {
                padding: 4px 8px;
                font-size: 0.7em;
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

        /* Styles for pagination within modal */
        .modal-pagination-controls {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 5px;
            margin-top: 15px;
            font-size: 0.85em;
        }
        .modal-pagination-controls button {
            background-color: var(--metro-bg-color);
            color: var(--metro-text-color);
            border: 1px solid var(--metro-light-gray);
            padding: 5px 8px;
            border-radius: 3px;
            cursor: pointer;
            transition: background-color 0.2s ease-out, color 0.2s ease-out;
        }
        .modal-pagination-controls button:hover:not(.active) {
            background-color: var(--metro-light-gray);
        }
        .modal-pagination-controls button.active {
            background-color: var(--metro-blue);
            color: #FFFFFF;
            border-color: var(--metro-blue);
            pointer-events: none;
        }
        .modal-pagination-controls button:disabled {
            cursor: not-allowed;
            opacity: 0.5;
        }

        /* NEW: Styles for text-overflow: ellipsis in modal */
        .modal-content #recentFilesList li,
        .modal-content #recentActivitiesList li {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
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
                <li><a href="recycle_bin.php"><i class="fas fa-trash"></i> Recycle Bin</a></li>
            <?php endif; ?>
            <li><a href="summary.php"><i class="fas fa-chart-line"></i> Summary</a></li>
            <li><a href="members.php" class="active"><i class="fas fa-users"></i> Members</a></li>
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
            <p class="storage-text" id="storageText"><?php echo formatBytes($usedStorageBytes); ?> of <?php echo formatBytes($totalStorageBytes); ?> used</p>
            <?php if ($isStorageFull): ?>
                <p class="storage-text storage-full-message" style="color: var(--metro-error); font-weight: bold;">Storage Full!</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="main-content">
        <div class="header-main">
            <button class="sidebar-toggle-btn" id="sidebarToggleBtn"><i class="fas fa-bars"></i></button>
            <h1 class="members-title">Members Dashboard</h1>
        </div>

        <div class="dashboard-grid">
            <div class="card" id="totalMembersCard">
                <h3>Total Members</h3>
                <p class="count"><?php echo $totalUsers; ?> Active Users</p>
            </div>
            <div class="card green" id="publicFilesCard">
                <h3>Total Files</h3>
                <p class="count"><?php echo $totalPublicFiles; ?> Available</p>
            </div>
            <div class="card orange" id="storageUsedCard">
                <h3>Total Storage Used</h3>
                <p class="count"><?php echo formatBytes($usedStorageBytes); ?></p>
                <p class="storage-text-card">of <?php echo formatBytes($totalStorageBytes); ?></p>
            </div>
            <div class="card red" id="weeklyActivitiesCard">
                <h3>Weekly Activities</h3>
                <p class="count"><?php echo $weeklyActivities; ?> Activities</p>
            </div>
        </div>

        <h2 class="section-title">Member List</h2>
        <div class="">
        <!--<div class="table-container">-->
            <table class="member-table" id="memberTable">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Full Name</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Last Login Time</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($members)): ?>
                        <?php foreach ($members as $index => $member): ?>
                            <tr data-member-id="<?= $member['id'] ?>">
                                <td><?= $index + 1 ?></td>
                                <td><?= htmlspecialchars($member['full_name']) ?></td>
                                <td><?= htmlspecialchars($member['username']) ?></td>
                                <td><?= htmlspecialchars($member['email']) ?></td>
                                <td>
                                    <?= !empty($member['last_login']) ? date('Y-m-d H:i:s', strtotime($member['last_login'])) : 'Never logged in' ?>
                                </td>
                                <td>
                                    <span class="status-indicator <?= $member['is_online'] ? 'online' : 'offline' ?>"></span>
                                    <?= $member['is_online'] ? 'Online' : 'Offline' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center">No members found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="pagination-controls" id="paginationControls">
            <button id="prevPageBtn" disabled>&laquo; Previous</button>
            <div id="pageNumbers">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <button class="page-number-btn <?= ($i == 1) ? 'active' : '' ?>" data-page="<?= $i ?>"><?= $i ?></button>
                <?php endfor; ?>
            </div>
            <button id="nextPageBtn" <?= ($totalPages <= 1) ? 'disabled' : '' ?>>Next &raquo;</button>
        </div>

        <h2 class="section-title">Activity Overview</h2>
        <div class="charts-section">
            <div class="chart-card">
                <h4>Activity Distribution</h4>
                <canvas id="activityDistributionChart"></canvas>
            </div>
            <div class="chart-card">
                <h4>Top Members by Total Files</h4>
                <canvas id="topMembersPublicFilesChart"></canvas>
            </div>
            <div class="chart-card">
                <h4>Daily Activity Trend</h4>
                <canvas id="dailyActivityChart"></canvas>
            </div>
        </div>

        <h2 class="section-title">User Activity & Profile</h2>
        <div class="bottom-section">
            <div class="recent-activities" id="recentActivitiesSection">
                <h4>Recent Activities</h4>
                <ul>
                    <?php foreach ($recentActivities as $activity): ?>
                        <li>
                            <?php
                                $icon = 'fas fa-info-circle';
                                switch ($activity['activity_type']) {
                                    case 'upload_file': $icon = 'fas fa-upload'; break;
                                    case 'delete_file': $icon = 'fas fa-trash'; break;
                                    case 'delete_folder': $icon = 'fas fa-trash'; break;
                                    case 'rename_file': $icon = 'fas fa-pen'; break;
                                    case 'rename_folder': $icon = 'fas fa-pen'; break;
                                    case 'create_folder': $icon = 'fas fa-folder-plus'; break;
                                    case 'archive': $icon = 'fas fa-archive'; break;
                                    case 'download': $icon = 'fas fa-download'; break;
                                    case 'login': $icon = 'fas fa-sign-in-alt'; break;
                                    case 'share_link': $icon = 'fas fa-share-alt'; break;
                                }
                            ?>
                            <i class="<?php echo $icon; ?>"></i>
                            <span class="activity-text"><strong><?php echo htmlspecialchars($activity['username']); ?></strong> <?php echo htmlspecialchars($activity['description']); ?></span>
                            <span class="timestamp"><?php echo time_elapsed_string($activity['timestamp']); ?> ago</span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="mini-profile" id="miniProfileSection">
                <h4>My Mini Profile</h4>
                <p><strong>Name:</strong> <?php echo htmlspecialchars($currentUserProfile['username']); ?></p>
                <p><strong>Total Files:</strong> <?php echo $currentUserProfile['total_files']; ?></p>
                <p><strong>Total Files (Public):</strong> <?php echo $currentUserProfile['public_files']; ?></p>
                <p><strong>Storage Used:</strong> <?php echo $currentUserProfile['storage_used']; ?></p>
                <p><strong>Weekly Activities:</strong> <?php echo $currentUserProfile['weekly_activities']; ?></p>
            </div>
        </div>

    </div>

    <div id="memberDetailModal" class="modal">
        <div class="modal-content">
            <span class="close-button">&times;</span>
            <h2 id="memberDetailName"></h2>
            <div id="memberDetailContent">
                <p><strong>Total Files:</strong> <span id="memberTotalFiles"></span></p>
                <p><strong>Total Files (Public):</strong> <span id="memberTotalPublicFiles"></span></p>

                <h4>Recent Files:</h4>
                <ul id="recentFilesList">
                    <!-- Recent files will be loaded here -->
                </ul>
                <div class="modal-pagination-controls" id="recentFilesPagination">
                    <button id="prevFilesPageBtn">&laquo; Previous</button>
                    <span id="currentFilesPage">1</span> / <span id="totalFilesPages">1</span>
                    <button id="nextFilesPageBtn">Next &raquo;</button>
                </div>

                <h4>Recent Activities:</h4>
                <ul id="recentActivitiesList">
                    <!-- Recent activities will be loaded here -->
                </ul>
                <div class="modal-pagination-controls" id="recentActivitiesPagination">
                    <button id="prevActivitiesPageBtn">&laquo; Previous</button>
                    <span id="currentActivitiesPage">1</span> / <span id="totalActivitiesPages">1</span>
                    <button id="nextActivitiesPageBtn">Next &raquo;</button>
                </div>
            </div>
        </div>
    </div>

    <div class="overlay" id="mobileOverlay"></div>

    <script>
        // Global Chart instances to allow for updates
        let activityChartInstance;
        let membersChartInstance;
        let dailyChartInstance;
        let currentPage = 1;
        const membersPerPage = <?php echo $membersPerPage; ?>;
        const totalMembers = <?php echo $totalMembersCount; ?>;
        const totalPages = <?php echo $totalPages; ?>;

        // Variables for member detail modal pagination
        let currentMemberId = 0;
        let currentFilesPage = 1;
        let currentActivitiesPage = 1;
        const itemsPerPageModal = 5; // 5 items per page for recent files/activities

        // Function to open modal
        function openModal(modalElement) {
            modalElement.classList.add('show');
        }

        // Function to close modal
        function closeModal(modalElement) {
            modalElement.classList.remove('show');
        }

        // Close buttons for modals
        document.querySelectorAll('.close-button').forEach(button => {
            button.addEventListener('click', () => {
                closeModal(document.getElementById('memberDetailModal'));
            });
        });

        // Close modal when clicking outside content
        window.addEventListener('click', (event) => {
            const memberDetailModal = document.getElementById('memberDetailModal');
            if (event.target == memberDetailModal) {
                closeModal(memberDetailModal);
            }
            // Close mobile sidebar if overlay is clicked
            const sidebar = document.querySelector('.sidebar');
            const mobileOverlay = document.getElementById('mobileOverlay');
            if (event.target == mobileOverlay && sidebar.classList.contains('show-mobile-sidebar')) {
                sidebar.classList.remove('show-mobile-sidebar');
                mobileOverlay.classList.remove('show');
            }
        });

        // Function to show member detail
        async function showMemberDetail(memberId) {
            currentMemberId = memberId; // Set the global member ID
            currentFilesPage = 1; // Reset to first page for files
            currentActivitiesPage = 1; // Reset to first page for activities

            const memberDetailModal = document.getElementById('memberDetailModal');
            const memberDetailName = document.getElementById('memberDetailName');
            const memberTotalFiles = document.getElementById('memberTotalFiles');
            const memberTotalPublicFiles = document.getElementById('memberTotalPublicFiles');
            const recentFilesList = document.getElementById('recentFilesList');
            const recentActivitiesList = document.getElementById('recentActivitiesList');

            memberDetailName.textContent = 'Loading...';
            memberTotalFiles.textContent = '...';
            memberTotalPublicFiles.textContent = '...';
            recentFilesList.innerHTML = '<p>Loading recent files...</p>';
            recentActivitiesList.innerHTML = '<p>Loading recent activities...</p>';
            openModal(memberDetailModal);

            // Load initial data for both paginated sections
            await fetchMemberDetailsPaginated(memberId, currentFilesPage, currentActivitiesPage);
        }

        // Function to fetch paginated member details
        async function fetchMemberDetailsPaginated(memberId, filesPage, activitiesPage) {
            const memberDetailName = document.getElementById('memberDetailName');
            const memberTotalFiles = document.getElementById('memberTotalFiles');
            const memberTotalPublicFiles = document.getElementById('memberTotalPublicFiles');
            const recentFilesList = document.getElementById('recentFilesList');
            const recentActivitiesList = document.getElementById('recentActivitiesList');

            try {
                const response = await fetch(`members.php?action=get_member_details_paginated&id=${memberId}&files_page=${filesPage}&activities_page=${activitiesPage}`);
                const data = await response.json();

                if (data.success) {
                    const member = data.member;
                    memberDetailName.textContent = htmlspecialchars(member.username) + "'s Profile";
                    memberTotalFiles.textContent = member.total_files;
                    memberTotalPublicFiles.textContent = member.total_public_files;

                    // Render Recent Files
                    recentFilesList.innerHTML = '';
                    if (member.recent_files.length > 0) {
                        member.recent_files.forEach(file => {
                            recentFilesList.innerHTML += `<li><i class="fas ${getFileIconClass(file.file_name)}"></i> ${htmlspecialchars(file.file_name)} (${formatBytes(file.file_size)})</li>`;
                        });
                    } else {
                        recentFilesList.innerHTML = `<li>No recent files.</li>`;
                    }
                    updateFilesPagination(filesPage, member.total_files_pages);

                    // Render Recent Activities
                    recentActivitiesList.innerHTML = '';
                    if (member.recent_activities.length > 0) {
                        member.recent_activities.forEach(activity => {
                            let icon = 'fas fa-info-circle';
                            switch (activity.activity_type) {
                                case 'upload_file': icon = 'fas fa-upload'; break;
                                case 'delete_file': icon = 'fas fa-trash'; break;
                                case 'delete_folder': icon = 'fas fa-trash'; break;
                                case 'rename_file': icon = 'fas fa-pen'; break;
                                case 'rename_folder': icon = 'fas fa-pen'; break;
                                case 'create_folder': icon = 'fas fa-folder-plus'; break;
                                case 'archive': icon = 'fas fa-archive'; break;
                                case 'download': icon = 'fas fa-download'; break;
                                case 'login': icon = 'fas fa-sign-in-alt'; break;
                                case 'share_link': icon = 'fas fa-share-alt'; break;
                                default: icon = 'fas fa-info-circle'; break;
                            }
                            recentActivitiesList.innerHTML += `<li><i class="${icon}"></i> ${htmlspecialchars(activity.description)} <span class="timestamp">${time_elapsed_string(activity.timestamp)} ago</span></li>`;
                        });
                    } else {
                        recentActivitiesList.innerHTML = `<li>No recent activities.</li>`;
                    }
                    updateActivitiesPagination(activitiesPage, member.total_activities_pages);

                } else {
                    memberDetailName.textContent = 'Error';
                    memberTotalFiles.textContent = 'N/A';
                    memberTotalPublicFiles.textContent = 'N/A';
                    recentFilesList.innerHTML = `<p>${data.message}</p>`;
                    recentActivitiesList.innerHTML = `<p>${data.message}</p>`;
                    updateFilesPagination(1, 1); // Reset pagination on error
                    updateActivitiesPagination(1, 1); // Reset pagination on error
                }
            } catch (error) {
                console.error('Error fetching member details:', error);
                memberDetailName.textContent = 'Error';
                memberTotalFiles.textContent = 'N/A';
                memberTotalPublicFiles.textContent = 'N/A';
                recentFilesList.innerHTML = '<p>Failed to load recent files.</p>';
                recentActivitiesList.innerHTML = '<p>Failed to load recent activities.</p>';
                updateFilesPagination(1, 1); // Reset pagination on error
                updateActivitiesPagination(1, 1); // Reset pagination on error
            }
        }

        // Pagination controls for Recent Files
        document.getElementById('prevFilesPageBtn').addEventListener('click', () => {
            if (currentFilesPage > 1) {
                currentFilesPage--;
                fetchMemberDetailsPaginated(currentMemberId, currentFilesPage, currentActivitiesPage);
            }
        });
        document.getElementById('nextFilesPageBtn').addEventListener('click', () => {
            const totalPages = parseInt(document.getElementById('totalFilesPages').textContent);
            if (currentFilesPage < totalPages) {
                currentFilesPage++;
                fetchMemberDetailsPaginated(currentMemberId, currentFilesPage, currentActivitiesPage);
            }
        });

        // Pagination controls for Recent Activities
        document.getElementById('prevActivitiesPageBtn').addEventListener('click', () => {
            if (currentActivitiesPage > 1) {
                currentActivitiesPage--;
                fetchMemberDetailsPaginated(currentMemberId, currentFilesPage, currentActivitiesPage);
            }
        });
        document.getElementById('nextActivitiesPageBtn').addEventListener('click', () => {
            const totalPages = parseInt(document.getElementById('totalActivitiesPages').textContent);
            if (currentActivitiesPage < totalPages) {
                currentActivitiesPage++;
                fetchMemberDetailsPaginated(currentMemberId, currentFilesPage, currentActivitiesPage);
            }
        });

        function updateFilesPagination(currentPage, totalPages) {
            document.getElementById('currentFilesPage').textContent = currentPage;
            document.getElementById('totalFilesPages').textContent = totalPages;
            document.getElementById('prevFilesPageBtn').disabled = currentPage === 1;
            document.getElementById('nextFilesPageBtn').disabled = currentPage === totalPages;
        }

        function updateActivitiesPagination(currentPage, totalPages) {
            document.getElementById('currentActivitiesPage').textContent = currentPage;
            document.getElementById('totalActivitiesPages').textContent = totalPages;
            document.getElementById('prevActivitiesPageBtn').disabled = currentPage === 1;
            document.getElementById('nextActivitiesPageBtn').disabled = currentPage === totalPages;
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

        // Helper function for time elapsed string (replicate PHP's time_elapsed_string)
        function time_elapsed_string(datetime) {
            const now = new Date();
            const then = new Date(datetime.replace(/-/g, '/')); // Handle different date formats
            const seconds = Math.floor((now - then) / 1000);

            let interval = seconds / 31536000;
            if (interval > 1) return Math.floor(interval) + " years";
            interval = seconds / 2592000;
            if (interval > 1) return Math.floor(interval) + " months";
            interval = seconds / 86400;
            if (interval > 1) return Math.floor(interval) + " days";
            interval = seconds / 3600;
            if (interval > 1) return Math.floor(interval) + " hours";
            interval = seconds / 60;
            if (interval > 1) return Math.floor(interval) + " minutes";
            return Math.floor(seconds) + " seconds";
        }

        // Function to get file icon class based on extension (replicate PHP's getFileIconClassPhp)
        function getFileIconClass(fileName) {
            const ext = fileName.split('.').pop().toLowerCase();
            switch (ext) {
                case 'pdf': return 'fa-file-pdf';
                case 'doc': case 'docx': return 'fa-file-word';
                case 'xls': case 'xlsx': return 'fa-file-excel';
                case 'ppt': case 'pptx': return 'fa-file-powerpoint';
                case 'txt': case 'md': case 'log': case 'csv': case 'tex': return 'fa-file-alt';
                case 'jpg': case 'jpeg': case 'png': case 'gif': case 'bmp': case 'webp': case 'svg': case 'tiff': return 'fa-file-image';
                case 'zip': case 'rar': case '7z': case 'tar': case 'gz': case 'bz2': case 'xz': case 'iso': case 'dmg': case 'cab': case 'arj': return 'fa-file-archive';
                case 'mp3': case 'wav': case 'ogg': case 'flac': case 'aac': case 'm4a': case 'alac': case 'wma': case 'opus': case 'amr': case 'mid': return 'fa-file-audio';
                case 'mp4': case 'avi': case 'mov': case 'wmv': case 'flv': case 'mkv': case 'webm': case '3gp': case 'm4v': case 'mpg': case 'mpeg': case 'ts': case 'ogv': return 'fa-file-video';
                case 'html': case 'htm': case 'css': case 'js': case 'php': case 'py': case 'java': case 'c': case 'cpp': case 'h': case 'json': case 'xml': case 'sql': case 'sh': case 'ts': case 'tsx': case 'jsx': case 'vue': case 'cs': case 'rb': case 'go': case 'swift': case 'bat': case 'ini': case 'yml': case 'yaml': case 'pl': case 'r': return 'fa-file-code';
                case 'exe': case 'msi': case 'apk': case 'ipa': case 'jar': case 'appimage': case 'bin': return 'fa-box';
                case 'torrent': case 'nzb': case 'ed2k': case 'part': case '!ut': return 'fa-magnet';
                default: return 'fa-file';
            }
        }

        // Helper function for formatBytes (replicate PHP's formatBytes)
        function formatBytes(bytes, precision = 2) {
            const units = ['B', 'KB', 'MB', 'GB', 'TB'];
            bytes = Math.max(bytes, 0);
            const pow = Math.floor((bytes ? Math.log(bytes) : 0) / Math.log(1024));
            const unitIndex = Math.min(pow, units.length - 1);
            bytes /= (1 << (10 * unitIndex));
            return bytes.toFixed(precision) + ' ' + units[unitIndex];
        }

        // Function to update dashboard UI with new data
        function updateDashboardUI(data) {
            // Update Summary Statistics Cards
            document.getElementById('totalMembersCard').querySelector('p.count').textContent = `${data.totalUsers} Active Users`;
            document.getElementById('publicFilesCard').querySelector('p.count').textContent = `${data.totalPublicFiles} Available`;
            document.getElementById('storageUsedCard').querySelector('p.count').textContent = `${formatBytes(data.usedStorageBytes)}`;
            document.getElementById('storageUsedCard').querySelector('p.storage-text-card').textContent = `of ${formatBytes(data.totalStorageBytes)}`;
            document.getElementById('weeklyActivitiesCard').querySelector('p.count').textContent = `${data.weeklyActivities} Activities`;

            // Update Storage Info in Sidebar
            document.querySelector('.progress-bar').style.width = `${data.usedPercentage.toFixed(2)}%`;
            document.querySelector('.progress-bar-text').textContent = `${data.usedPercentage.toFixed(2)}%`; // Update text inside progress bar
            document.getElementById('storageText').textContent = `${formatBytes(data.usedStorageBytes)} of ${formatBytes(data.totalStorageBytes)} used`;
            
            // Update storage full message in sidebar
            const storageInfoDiv = document.querySelector('.storage-info');
            let storageFullMessage = storageInfoDiv.querySelector('.storage-full-message');
            if (data.isStorageFull) {
                if (!storageFullMessage) {
                    storageFullMessage = document.createElement('p');
                    storageFullMessage.className = 'storage-text storage-full-message';
                    storageFullMessage.style.color = 'var(--metro-error)';
                    storageFullMessage.style.fontWeight = 'bold';
                    storageFullMessage.textContent = 'Storage Full!';
                    storageInfoDiv.appendChild(storageFullMessage);
                }
            } else {
                if (storageFullMessage) {
                    storageFullMessage.remove();
                }
            }

            // Update Charts
            updateCharts(data.activityDistribution, data.topMembersPublicFiles, data.dailyActivities);

            // Update Recent Activities
            const recentActivitiesUl = document.querySelector('#recentActivitiesSection ul');
            recentActivitiesUl.innerHTML = ''; // Clear existing activities
            if (data.recentActivities.length > 0) {
                data.recentActivities.forEach(activity => {
                    let icon = 'fas fa-info-circle';
                    switch (activity.activity_type) {
                        case 'upload_file': icon = 'fas fa-upload'; break;
                        case 'delete_file': icon = 'fas fa-trash'; break;
                        case 'delete_folder': icon = 'fas fa-trash'; break;
                        case 'rename_file': icon = 'fas fa-pen'; break;
                        case 'rename_folder': icon = 'fas fa-pen'; break;
                        case 'create_folder': icon = 'fas fa-folder-plus'; break;
                        case 'archive': icon = 'fas fa-archive'; break;
                        case 'download': icon = 'fas fa-download'; break;
                        case 'login': icon = 'fas fa-sign-in-alt'; break;
                        case 'share_link': icon = 'fas fa-share-alt'; break;
                        default: icon = 'fas fa-info-circle'; break;
                    }
                    const activityLi = `
                        <li>
                            <i class="${icon}"></i>
                            <span class="activity-text"><strong>${htmlspecialchars(activity.username)}</strong> ${htmlspecialchars(activity.description)}</span>
                            <span class="timestamp">${time_elapsed_string(activity.timestamp)} ago</span>
                        </li>
                    `;
                    recentActivitiesUl.innerHTML += activityLi;
                });
            } else {
                recentActivitiesUl.innerHTML = '<li>No recent activities.</li>';
            }

            // Update Mini Profile
            const miniProfileSection = document.getElementById('miniProfileSection');
            miniProfileSection.querySelector('p:nth-child(2)').innerHTML = `<strong>Name:</strong> ${htmlspecialchars(data.currentUserProfile.username)}`;
            miniProfileSection.querySelector('p:nth-child(3)').innerHTML = `<strong>Total Files:</strong> ${data.currentUserProfile.total_files}`;
            miniProfileSection.querySelector('p:nth-child(4)').innerHTML = `<strong>Total Files (Public):</strong> ${data.currentUserProfile.public_files}`;
            miniProfileSection.querySelector('p:nth-child(5)').innerHTML = `<strong>Storage Used:</strong> ${data.currentUserProfile.storage_used}`;
            miniProfileSection.querySelector('p:nth-child(6)').innerHTML = `<strong>Weekly Activities:</strong> ${data.currentUserProfile.weekly_activities}`;
        }
        
        // --- PAGINATION FUNCTIONS ---
        async function fetchMembers(page) {
            try {
                const response = await fetch(`members.php?action=get_members&page=${page}`);
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                const data = await response.json();
                if (data.success) {
                    currentPage = page;
                    renderMemberTable(data.members);
                    updatePaginationControls();
                }
            } catch (error) {
                console.error("Could not fetch members data:", error);
            }
        }
        
        function renderMemberTable(members) {
            const memberTableBody = document.querySelector('#memberTable tbody');
            memberTableBody.innerHTML = ''; // Clear existing rows
            if (members.length > 0) {
                const startNum = (currentPage - 1) * membersPerPage;
                members.forEach((member, index) => {
                    const row = `
                        <tr data-member-id="${member.id}">
                            <td>${startNum + index + 1}</td>
                            <td>${htmlspecialchars(member.full_name)}</td>
                            <td>${htmlspecialchars(member.username)}</td>
                            <td>${htmlspecialchars(member.email)}</td>
                            <td>
                                ${member.last_login ? new Date(member.last_login.replace(/-/g, '/')).toLocaleString() : 'Never logged in'}
                            </td>
                            <td>
                                <span class="status-indicator ${member.is_online ? 'online' : 'offline'}"></span>
                                ${member.is_online ? 'Online' : 'Offline'}
                            </td>
                        </tr>
                    `;
                    memberTableBody.innerHTML += row;
                });
            } else {
                memberTableBody.innerHTML = `<tr><td colspan="6" class="text-center">No members found</td></tr>`;
            }
            attachMemberRowClickListeners();
        }
        
        function setupPagination() {
            const paginationContainer = document.getElementById('pageNumbers');
            paginationContainer.innerHTML = ''; // Clear existing page buttons
            for (let i = 1; i <= totalPages; i++) {
                const button = document.createElement('button');
                button.className = 'page-number-btn';
                button.textContent = i;
                button.dataset.page = i;
                if (i === currentPage) {
                    button.classList.add('active');
                }
                button.addEventListener('click', () => {
                    fetchMembers(i);
                });
                paginationContainer.appendChild(button);
            }
        }
        
        function updatePaginationControls() {
            const prevBtn = document.getElementById('prevPageBtn');
            const nextBtn = document.getElementById('nextPageBtn');
            
            prevBtn.disabled = currentPage === 1;
            nextBtn.disabled = currentPage === totalPages;
            
            document.querySelectorAll('.page-number-btn').forEach(btn => {
                btn.classList.remove('active');
                if (parseInt(btn.dataset.page) === currentPage) {
                    btn.classList.add('active');
                }
            });
        }
        
        // --- END PAGINATION FUNCTIONS ---

        // Function to update Chart.js instances
        function updateCharts(activityDistribution, topMembersPublicFiles, dailyActivities) {
            // Activity Distribution Pie Chart
            const activityCtx = document.getElementById('activityDistributionChart').getContext('2d');
            const activityData = Object.values(activityDistribution);
            const activityLabels = Object.keys(activityDistribution);
            const activityColors = [
                '#0078D7', '#4CAF50', '#FF8C00', '#E81123', '#8E24AA', '#00B294', '#FFB900', '#505050'
            ];

            if (activityChartInstance) {
                activityChartInstance.data.labels = activityLabels;
                activityChartInstance.data.datasets[0].data = activityData;
                activityChartInstance.data.datasets[0].backgroundColor = activityColors;
                activityChartInstance.update();
            } else {
                activityChartInstance = new Chart(activityCtx, {
                    type: 'pie',
                    data: {
                        labels: activityLabels,
                        datasets: [{
                            data: activityData,
                            backgroundColor: activityColors,
                            hoverOffset: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: {
                                position: 'right',
                                labels: {
                                    color: 'var(--metro-text-color)',
                                    font: {
                                        family: 'Segoe UI'
                                    }
                                }
                            },
                            title: {
                                display: false,
                                text: 'Activity Distribution'
                            }
                        }
                    }
                });
            }

            // Top Members by Public Files Bar Chart
            const membersCtx = document.getElementById('topMembersPublicFilesChart').getContext('2d');
            const membersLabels = topMembersPublicFiles.map(m => m.username);
            const membersData = topMembersPublicFiles.map(m => m.public_files_count);

            if (membersChartInstance) {
                membersChartInstance.data.labels = membersLabels;
                membersChartInstance.data.datasets[0].data = membersData;
                membersChartInstance.update();
            } else {
                membersChartInstance = new Chart(membersCtx, {
                    type: 'bar',
                    data: {
                        labels: membersLabels,
                        datasets: [{
                            label: 'Total Files',
                            data: membersData,
                            backgroundColor: 'var(--metro-blue)',
                            borderColor: 'var(--metro-dark-blue)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: {
                                display: false
                            },
                            title: {
                                display: false,
                                text: 'Top Members by Total Files'
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    color: 'var(--metro-text-color)',
                                    font: {
                                        family: 'Segoe UI'
                                    }
                                }
                            },
                            x: {
                                ticks: {
                                    color: 'var(--metro-text-color)',
                                    font: {
                                        family: 'Segoe UI'
                                    }
                                }
                            }
                        }
                    }
                });
            }

            // Daily Activity Line Chart
            const dailyCtx = document.getElementById('dailyActivityChart').getContext('2d');
            const dailyLabels = Object.keys(dailyActivities);
            const dailyData = Object.values(dailyActivities);

            if (dailyChartInstance) {
                dailyChartInstance.data.labels = dailyLabels;
                dailyChartInstance.data.datasets[0].data = dailyData;
                dailyChartInstance.update();
            } else {
                dailyChartInstance = new Chart(dailyCtx, {
                    type: 'line',
                    data: {
                        labels: dailyLabels,
                        datasets: [{
                            label: 'Activities',
                            data: dailyData,
                            borderColor: 'var(--metro-success)',
                            backgroundColor: 'rgba(76, 175, 80, 0.2)',
                            tension: 0.3,
                            fill: true
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: {
                                display: false
                            },
                            title: {
                                display: false,
                                text: 'Daily Activity Trend'
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    color: 'var(--metro-text-color)',
                                    font: {
                                        family: 'Segoe UI'
                                    }
                                }
                            },
                            x: {
                                ticks: {
                                    color: 'var(--metro-text-color)',
                                    font: {
                                        family: 'Segoe UI'
                                    }
                                }
                            }
                        }
                    }
                });
            }
        }

        // Function to fetch dashboard data via AJAX
        async function fetchDashboardData() {
            try {
                const response = await fetch('members.php?action=get_dashboard_data');
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                const data = await response.json();
                updateDashboardUI(data);
            } catch (error) {
                console.error("Could not fetch dashboard data:", error);
                // Optionally display an error message to the user
            }
        }

        // Attach click listeners to member table rows
        function attachMemberRowClickListeners() {
            document.querySelectorAll('#memberTable tbody tr').forEach(row => {
                row.addEventListener('click', function() {
                    const memberId = this.dataset.memberId;
                    if (memberId) {
                        showMemberDetail(memberId);
                    }
                });
            });
        }

        // --- Responsive Class Handling ---
        function applyDeviceClass() {
            const width = window.innerWidth;
            const body = document.body;
            const sidebar = document.querySelector('.sidebar');
            const mobileOverlay = document.getElementById('mobileOverlay');

            // Remove all previous device classes
            body.classList.remove('mobile', 'tablet-portrait', 'tablet-landscape', 'desktop');

            if (width <= 767) {
                body.classList.add('mobile');
                sidebar.classList.add('mobile-hidden'); // Ensure sidebar is hidden by default
            } else if (width >= 768 && width <= 1024) {
                if (window.matchMedia("(orientation: portrait)").matches) {
                    body.classList.add('tablet-portrait');
                    sidebar.classList.add('mobile-hidden'); // Ensure sidebar is hidden by default
                } else {
                    body.classList.add('tablet-landscape');
                    sidebar.classList.remove('mobile-hidden'); // Show sidebar
                    sidebar.classList.remove('show-mobile-sidebar'); // Ensure mobile sidebar is closed
                    mobileOverlay.classList.remove('show'); // Hide overlay
                }
            } else {
                body.classList.add('desktop');
                sidebar.classList.remove('mobile-hidden'); // Show sidebar
                sidebar.classList.remove('show-mobile-sidebar'); // Ensure mobile sidebar is closed
                mobileOverlay.classList.remove('show'); // Hide overlay
            }
        }

        // Initial load and setup
        document.addEventListener('DOMContentLoaded', function() {
            // Initial chart rendering with data from PHP
            updateCharts(
                <?php echo json_encode($activityDistribution); ?>,
                <?php echo json_encode($topMembersPublicFiles); ?>,
                <?php echo json_encode($dailyActivities); ?>
            );

            // PAGINATION SETUP
            renderMemberTable(<?php echo json_encode($members); ?>);
            setupPagination();
            updatePaginationControls();

            // Pagination button event listeners
            document.getElementById('prevPageBtn').addEventListener('click', () => {
                if (currentPage > 1) {
                    fetchMembers(currentPage - 1);
                }
            });

            document.getElementById('nextPageBtn').addEventListener('click', () => {
                if (currentPage < totalPages) {
                    fetchMembers(currentPage + 1);
                }
            });


            // Attach click listeners to member table rows
            attachMemberRowClickListeners();

            // Fetch and update data every 30 seconds (example)
            setInterval(fetchDashboardData, 30000); // Refresh every 30 seconds

            // Initial application of device class
            applyDeviceClass();
            window.addEventListener('resize', applyDeviceClass);
            window.addEventListener('orientationchange', applyDeviceClass);

            // Mobile sidebar elements
            const sidebar = document.querySelector('.sidebar');
            const sidebarToggleBtn = document.getElementById('sidebarToggleBtn');
            const mobileOverlay = document.getElementById('mobileOverlay');

            // --- Mobile Sidebar Toggle ---
            sidebarToggleBtn.addEventListener('click', () => {
                sidebar.classList.toggle('show-mobile-sidebar');
                mobileOverlay.classList.toggle('show');
            });

            // Set active class for current page in sidebar
            const currentPagePath = window.location.pathname.split('/').pop();
            document.querySelectorAll('.sidebar-menu a').forEach(item => {
                item.classList.remove('active');
                const itemHref = item.getAttribute('href');
                if (itemHref === currentPagePath) {
                    item.classList.add('active');
                }
            });
        });
    </script>
</body>
</html>
