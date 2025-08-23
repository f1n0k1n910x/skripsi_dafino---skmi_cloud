<?php
include 'config.php';
include 'functions.php'; // Include functions.php for logActivity, formatBytes, getFolderSize, etc.

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

$currentUserId = $_SESSION['user_id'];
$membersPerPage = 6; // Number of members to display per page in the main table

// Function to fetch all dashboard data
function getDashboardData($conn, $currentUserId) {
    global $membersPerPage;
    $data = [];

    // Total Active Users
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

        /* Dashboard Specific Styles (from summary.php) */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); /* Adjusted minmax for better fit */
            gap: 20px;
            margin-bottom: 30px;
        }

        /* Card styles (from summary.php) */
        .card {
            background-color: var(--primary-color); /* Using primary-color for default card */
            color: #FFFFFF;
            padding: 25px;
            border-radius: 0; /* Siku-siku */
            box-shadow: none; /* No box-shadow */
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
            transform: translateY(0); /* No lift */
            box-shadow: none; /* No box-shadow */
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

        .card.green { background-color: var(--success-color); }
        .card.orange { background-color: var(--warning-color); }
        .card.red { background-color: var(--error-color); }

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

        /* Member Table (Google Drive Style) */
        .section-title {
            font-size: 1.8em;
            font-weight: 400;
            color: var(--text-color);
            margin-bottom: 20px;
            border-bottom: 1px solid var(--divider-color);
            padding-bottom: 10px;
            animation: slideInFromLeft 0.5s ease-out forwards;
            opacity: 0;
            animation-delay: 0.5s;
        }

        .table-container {
            background-color: var(--surface-color);
            border-radius: 0; /* Siku-siku */
            box-shadow: none; /* No box-shadow */
            overflow-x: auto;
            margin-bottom: 30px;
            animation: fadeIn 0.6s ease-out forwards;
            opacity: 0;
            animation-delay: 0.6s;
            border: 1px solid var(--divider-color); /* Subtle border for container */
        }

        .member-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed; /* Kunci utama */
        }

        .member-table th, .member-table td {
            text-align: left;
            padding: 12px 24px; /* Google Drive padding */
            border-bottom: 1px solid #dadce0; /* Google Drive border color */
            font-size: 0.875em;
            color: #3c4043; /* Google Drive text color */
        }

        .member-table th {
            background-color: #f8f9fa; /* Google Drive header background */
            color: #5f6368; /* Google Drive header text */
            font-weight: 500;
            text-transform: none;
            position: sticky;
            top: 0;
            z-index: 1;
        }

        .member-table tbody tr:hover {
            background-color: #f0f0f0; /* Google Drive hover effect */
        }

        .member-table .profile-pic {
            width: 32px; /* Smaller profile pic */
            height: 32px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 10px;
            vertical-align: middle;
            border: 1px solid var(--divider-color);
        }

        .member-table .status-indicator {
            display: inline-block;
            width: 8px; /* Smaller indicator */
            height: 8px;
            border-radius: 50%;
            margin-right: 6px;
            vertical-align: middle;
        }

        .member-table .status-indicator.online { background-color: var(--success-color); }
        .member-table .status-indicator.offline { background-color: var(--error-color); }

        .member-table a {
            color: #3c4043; /* Google Drive text color */
            text-decoration: none;
            transition: color 0.2s ease-out;
        }
        .member-table a:hover {
            text-decoration: underline;
            color: #1a73e8; /* Google Drive blue on hover */
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
            background-color: var(--background-color);
            color: var(--text-color);
            border: 1px solid var(--divider-color);
            padding: 8px 12px;
            border-radius: 0; /* Siku-siku */
            cursor: pointer;
            transition: background-color 0.2s ease-out, color 0.2s ease-out;
            font-size: 0.9em;
            box-shadow: none; /* No box-shadow */
        }

        .pagination-controls button:hover:not(.active) {
            background-color: var(--divider-color);
        }

        .pagination-controls button.active {
            background-color: var(--primary-color);
            color: #FFFFFF;
            border-color: var(--primary-color);
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
            background-color: var(--surface-color);
            padding: 25px;
            border-radius: 0; /* Siku-siku */
            box-shadow: none; /* No box-shadow */
            animation: fadeIn 0.7s ease-out forwards;
            opacity: 0;
            border: 1px solid var(--divider-color); /* Subtle border */
        }
        .chart-card:nth-child(1) { animation-delay: 0.7s; }
        .chart-card:nth-child(2) { animation-delay: 0.8s; }
        .chart-card:nth-child(3) { animation-delay: 0.9s; }

        .chart-card h4 {
            margin-top: 0;
            margin-bottom: 20px;
            font-size: 1.4em;
            font-weight: 400;
            color: var(--text-color);
            border-bottom: 1px solid var(--divider-color);
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
            background-color: var(--surface-color);
            padding: 25px;
            border-radius: 0; /* Siku-siku */
            box-shadow: none; /* No box-shadow */
            animation: fadeIn 1.0s ease-out forwards;
            opacity: 0;
            overflow-y: auto; /* Add scrollbar */
            max-height: 400px; /* Max height for scrollbar */
            border: 1px solid var(--divider-color); /* Subtle border */
        }

        .recent-activities ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .recent-activities li {
            padding: 12px 0;
            border-bottom: 1px solid var(--divider-color);
            font-size: 0.95em;
            color: var(--text-color);
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
            color: var(--secondary-text-color);
            margin-left: auto;
            flex-shrink: 0; /* Prevent timestamp from shrinking */
        }

        /* Mini Profile */
        .mini-profile {
            background-color: var(--surface-color);
            padding: 25px;
            border-radius: 0; /* Siku-siku */
            box-shadow: none; /* No box-shadow */
            animation: fadeIn 1.1s ease-out forwards;
            opacity: 0;
            border: 1px solid var(--divider-color); /* Subtle border */
        }

        .mini-profile h4 {
            margin-top: 0;
            margin-bottom: 20px;
            font-size: 1.4em;
            font-weight: 400;
            color: var(--text-color);
            border-bottom: 1px solid var(--divider-color);
            padding-bottom: 10px;
        }

        .mini-profile p {
            margin: 8px 0;
            font-size: 1em;
            color: var(--text-color);
        }

        .mini-profile p strong {
            color: var(--primary-color);
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
            background-color: var(--surface-color);
            padding: 30px;
            border-radius: 0; /* Siku-siku */
            box-shadow: 0 8px 17px 2px rgba(0,0,0,0.14), 0 3px 14px 2px rgba(0,0,0,0.12), 0 5px 5px -3px rgba(0,0,0,0.2); /* Material Design shadow */
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
            color: var(--secondary-text-color);
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
            color: var(--error-color);
        }

        .modal h2 {
            margin-top: 0;
            margin-bottom: 25px;
            color: var(--text-color);
            font-size: 2em;
            font-weight: 300;
            border-bottom: 1px solid var(--divider-color);
            padding-bottom: 15px;
        }

        /* Custom Scrollbar for Webkit browsers (Chrome, Safari) */
        ::-webkit-scrollbar {
            width: 8px; /* Width of the scrollbar */
            height: 8px; /* Height of horizontal scrollbar */
        }

        ::-webkit-scrollbar-track {
            background: var(--background-color); /* Color of the track */
            border-radius: 0; /* Siku-siku */
        }

        ::-webkit-scrollbar-thumb {
            background: var(--divider-color); /* Color of the scroll thumb */
            border-radius: 0; /* Siku-siku */
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--secondary-text-color); /* Color of the scroll thumb on hover */
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
                margin: -20px -20px 20px -20px; /* Adjust margin to cover full width */
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
                padding: 10px 15px;
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
                box-shadow: 2px 0 5px rgba(0,0,0,0.2); /* Subtle shadow for mobile sidebar */
            }
            body.tablet-portrait .sidebar.show-mobile-sidebar {
                transform: translateX(0); /* Show when active */
            }
            body.tablet-portrait .sidebar-toggle-btn {
                display: block; /* Show toggle button */
                background: none;
                border: none;
                font-size: 1.8em;
                color: var(--adminlte-header-text);
                cursor: pointer;
                margin-left: 10px; /* Space from logo */
                order: 0; /* Place on the left */
            }
            body.tablet-portrait .header-main {
                justify-content: space-between; /* Align items */
                padding: 10px 20px;
                margin: -20px -20px 20px -20px; /* Adjust margin to cover full width */
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
                width: 180px; /* Narrower sidebar for mobile */
                z-index: 100;
                transform: translateX(-100%); /* Hidden by default */
                box-shadow: 2px 0 5px rgba(0,0,0,0.2);
            }
            body.mobile .sidebar.show-mobile-sidebar {
                transform: translateX(0); /* Show when active */
            }
            body.mobile .sidebar-toggle-btn {
                display: block; /* Show toggle button */
                background: none;
                border: none;
                font-size: 1.5em;
                color: var(--adminlte-header-text);
                cursor: pointer;
                margin-left: 10px; /* Space from logo */
                order: 0; /* Place on the left */
            }
            body.mobile .header-main {
                justify-content: space-between; /* Align items */
                padding: 10px 15px;
                margin: -20px -20px 20px -20px; /* Adjust margin to cover full width */
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
                grid-template-columns: 1fr !important; /* Single column layout */
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
                border: 1px solid var(--divider-color);
                margin-bottom: 10px;
                border-radius: 0; /* Siku-siku */
                background-color: var(--surface-color);
                box-shadow: none; /* No box-shadow */
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
                color: var(--secondary-text-color);
            }
            body.mobile .member-table td:nth-child(2) { /* Full Name */
                padding-top: 15px;
                font-weight: 600;
                font-size: 0.9em;
            }
            body.mobile .member-table td:nth-child(3)::before { content: "Username: "; font-weight: normal; color: var(--secondary-text-color); }
            body.mobile .member-table td:nth-child(4)::before { content: "Email: "; font-weight: normal; color: var(--secondary-text-color); }
            body.mobile .member-table td:nth-child(5)::before { content: "Last Login: "; font-weight: normal; color: var(--secondary-text-color); }
            body.mobile .member-table td:nth-child(6)::before { content: "Status: "; font-weight: normal; color: var(--secondary-text-color); }
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
            background-color: var(--background-color);
            color: var(--text-color);
            border: 1px solid var(--divider-color);
            padding: 5px 8px;
            border-radius: 0; /* Siku-siku */
            cursor: pointer;
            transition: background-color 0.2s ease-out, color 0.2s ease-out;
        }
        .modal-pagination-controls button:hover:not(.active) {
            background-color: var(--divider-color);
        }
        .modal-pagination-controls button.active {
            background-color: var(--primary-color);
            color: #FFFFFF;
            border-color: var(--primary-color);
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
                <li><a href="control_center.php"><i class="fas fa-cogs"></i> <span data-lang-key="controlCenter">Control Center</span></a></li>
            <?php endif; ?>
            <?php if (in_array($currentUserRole, ['admin', 'moderator', 'user', 'member'])): ?>
                <li><a href="index.php"><i class="fas fa-folder"></i> <span data-lang-key="myDrive">My Drive</span></a></li>
                <li><a href="priority_files.php"><i class="fas fa-star"></i> <span data-lang-key="priorityFile">Priority File</span></a></li>
                <li><a href="recycle_bin.php"><i class="fas fa-trash"></i> <span data-lang-key="recycleBin">Recycle Bin</span></a></li>
            <?php endif; ?>
            <li><a href="summary.php"><i class="fas fa-chart-line"></i> <span data-lang-key="summary">Summary</span></a></li>
            <li><a href="members.php" class="active"><i class="fas fa-users"></i> <span data-lang-key="members">Members</span></a></li>
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
            <h1 class="members-title" data-lang-key="membersDashboardTitle">Members Dashboard</h1>
        </div>

        <div class="dashboard-grid">
            <div class="card" id="totalMembersCard">
                <h3 data-lang-key="totalMembers">Total Members</h3>
                <p class="count"><span id="totalUsersCount"><?php echo $totalUsers; ?></span> <span data-lang-key="activeUsers">Active Users</span></p>
            </div>
            <div class="card green" id="publicFilesCard">
                <h3 data-lang-key="totalFiles">Total Files</h3>
                <p class="count"><span id="totalPublicFilesCount"><?php echo $totalPublicFiles; ?></span> <span data-lang-key="available">Available</span></p>
            </div>
            <div class="card orange" id="storageUsedCard">
                <h3 data-lang-key="totalStorageUsed">Total Storage Used</h3>
                <p class="count" id="usedStorageBytesCount"><?php echo formatBytes($usedStorageBytes); ?></p>
                <p class="storage-text-card" data-lang-key="ofUsed">of <span id="totalStorageBytesCount"><?php echo formatBytes($totalStorageBytes); ?></span> used</p>
            </div>
            <div class="card red" id="weeklyActivitiesCard">
                <h3 data-lang-key="weeklyActivities">Weekly Activities</h3>
                <p class="count"><span id="weeklyActivitiesCount"><?php echo $weeklyActivities; ?></span> <span data-lang-key="activities">Activities</span></p>
            </div>
        </div>

        <h2 class="section-title" data-lang-key="memberList">Member List</h2>
        <div class="">
            <table class="member-table" id="memberTable">
                <thead>
                    <tr>
                        <th data-lang-key="no">No</th>
                        <th data-lang-key="fullName">Full Name</th>
                        <th data-lang-key="username">Username</th>
                        <th data-lang-key="email">Email</th>
                        <th data-lang-key="lastLoginTime">Last Login Time</th>
                        <th data-lang-key="status">Status</th>
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
                                    <?= !empty($member['last_login']) ? date('Y-m-d H:i:s', strtotime($member['last_login'])) : '<span data-lang-key="neverLoggedIn">Never logged in</span>' ?>
                                </td>
                                <td>
                                    <span class="status-indicator <?= $member['is_online'] ? 'online' : 'offline' ?>"></span>
                                    <span data-lang-key="<?= $member['is_online'] ? 'online' : 'offline' ?>"><?= $member['is_online'] ? 'Online' : 'Offline' ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center" data-lang-key="noMembersFound">No members found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="pagination-controls" id="paginationControls">
            <button id="prevPageBtn" disabled><i class="fas fa-angle-left"></i> <span data-lang-key="previous">Previous</span></button>
            <div id="pageNumbers">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <button class="page-number-btn <?= ($i == 1) ? 'active' : '' ?>" data-page="<?= $i ?>"><?= $i ?></button>
                <?php endfor; ?>
            </div>
            <button id="nextPageBtn" <?= ($totalPages <= 1) ? 'disabled' : '' ?>><span data-lang-key="next">Next</span> <i class="fas fa-angle-right"></i></button>
        </div>

        <h2 class="section-title" data-lang-key="activityOverview">Activity Overview</h2>
        <div class="charts-section">
            <div class="chart-card">
                <h4 data-lang-key="activityDistribution">Activity Distribution</h4>
                <canvas id="activityDistributionChart"></canvas>
            </div>
            <div class="chart-card">
                <h4 data-lang-key="topMembersByTotalFiles">Top Members by Total Files</h4>
                <canvas id="topMembersPublicFilesChart"></canvas>
            </div>
            <div class="chart-card">
                <h4 data-lang-key="dailyActivityTrend">Daily Activity Trend</h4>
                <canvas id="dailyActivityChart"></canvas>
            </div>
        </div>

        <h2 class="section-title" data-lang-key="userActivityProfile">User Activity & Profile</h2>
        <div class="bottom-section">
            <div class="recent-activities" id="recentActivitiesSection">
                <h4 data-lang-key="recentActivities">Recent Activities</h4>
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
                            <span class="activity-text"><strong><?php echo htmlspecialchars($activity['username']); ?></strong> <span data-lang-activity-desc-key="<?= $activity['activity_type'] ?>"><?php echo htmlspecialchars($activity['description']); ?></span></span>
                            <span class="timestamp" data-timestamp="<?= $activity['timestamp'] ?>"></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="mini-profile" id="miniProfileSection">
                <h4 data-lang-key="myMiniProfile">My Mini Profile</h4>
                <p><strong data-lang-key="name">Name:</strong> <span id="miniProfileUsername"><?php echo htmlspecialchars($currentUserProfile['username']); ?></span></p>
                <p><strong data-lang-key="totalFilesMini">Total Files:</strong> <span id="miniProfileTotalFiles"><?php echo $currentUserProfile['total_files']; ?></span></p>
                <p><strong data-lang-key="totalFilesPublicMini">Total Files (Public):</strong> <span id="miniProfilePublicFiles"><?php echo $currentUserProfile['public_files']; ?></span></p>
                <p><strong data-lang-key="storageUsedMini">Storage Used:</strong> <span id="miniProfileStorageUsed"><?php echo $currentUserProfile['storage_used']; ?></span></p>
                <p><strong data-lang-key="weeklyActivitiesMini">Weekly Activities:</strong> <span id="miniProfileWeeklyActivities"><?php echo $currentUserProfile['weekly_activities']; ?></span></p>
            </div>
        </div>

    </div>

    <div id="memberDetailModal" class="modal">
        <div class="modal-content">
            <span class="close-button">&times;</span>
            <h2 id="memberDetailName"></h2>
            <div id="memberDetailContent">
                <p><strong data-lang-key="totalFilesModal">Total Files:</strong> <span id="memberTotalFiles"></span></p>
                <p><strong data-lang-key="totalFilesPublicModal">Total Files (Public):</strong> <span id="memberTotalPublicFiles"></span></p>

                <h4 data-lang-key="recentFilesModal">Recent Files:</h4>
                <ul id="recentFilesList">
                    <!-- Recent files will be loaded here -->
                </ul>
                <div class="modal-pagination-controls" id="recentFilesPagination">
                    <button id="prevFilesPageBtn"><i class="fas fa-angle-left"></i> <span data-lang-key="previous">Previous</span></button>
                    <span id="currentFilesPage">1</span> / <span id="totalFilesPages">1</span>
                    <button id="nextFilesPageBtn"><span data-lang-key="next">Next</span> <i class="fas fa-angle-right"></i></button>
                </div>

                <h4 data-lang-key="recentActivitiesModal">Recent Activities:</h4>
                <ul id="recentActivitiesList">
                    <!-- Recent activities will be loaded here -->
                </ul>
                <div class="modal-pagination-controls" id="recentActivitiesPagination">
                    <button id="prevActivitiesPageBtn"><i class="fas fa-angle-left"></i> <span data-lang-key="previous">Previous</span></button>
                    <span id="currentActivitiesPage">1</span> / <span id="totalActivitiesPages">1</span>
                    <button id="nextActivitiesPageBtn"><span data-lang-key="next">Next</span> <i class="fas fa-angle-right"></i></button>
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

            // Members Dashboard
            'membersDashboardTitle': { 'id': 'Dasbor Anggota', 'en': 'Members Dashboard' },
            'totalMembers': { 'id': 'Total Anggota', 'en': 'Total Members' },
            'activeUsers': { 'id': 'Pengguna Aktif', 'en': 'Active Users' },
            'totalFiles': { 'id': 'Total File', 'en': 'Total Files' },
            'available': { 'id': 'Tersedia', 'en': 'Available' },
            'totalStorageUsed': { 'id': 'Total Penyimpanan Terpakai', 'en': 'Total Storage Used' },
            'ofUsed': { 'id': 'dari', 'en': 'of' }, // "of X used"
            'weeklyActivities': { 'id': 'Aktivitas Mingguan', 'en': 'Weekly Activities' },
            'activities': { 'id': 'Aktivitas', 'en': 'Activities' },

            // Member List
            'memberList': { 'id': 'Daftar Anggota', 'en': 'Member List' },
            'no': { 'id': 'No', 'en': 'No' },
            'fullName': { 'id': 'Nama Lengkap', 'en': 'Full Name' },
            'username': { 'id': 'Nama Pengguna', 'en': 'Username' },
            'email': { 'id': 'Email', 'en': 'Email' },
            'lastLoginTime': { 'id': 'Waktu Login Terakhir', 'en': 'Last Login Time' },
            'status': { 'id': 'Status', 'en': 'Status' },
            'neverLoggedIn': { 'id': 'Belum pernah login', 'en': 'Never logged in' },
            'online': { 'id': 'Online', 'en': 'Online' },
            'offline': { 'id': 'Offline', 'en': 'Offline' },
            'noMembersFound': { 'id': 'Tidak ada anggota ditemukan', 'en': 'No members found' },

            // Pagination
            'previous': { 'id': 'Sebelumnya', 'en': 'Previous' },
            'next': { 'id': 'Berikutnya', 'en': 'Next' },

            // Activity Overview
            'activityOverview': { 'id': 'Ikhtisar Aktivitas', 'en': 'Activity Overview' },
            'activityDistribution': { 'id': 'Distribusi Aktivitas', 'en': 'Activity Distribution' },
            'topMembersByTotalFiles': { 'id': 'Anggota Teratas berdasarkan Total File', 'en': 'Top Members by Total Files' },
            'dailyActivityTrend': { 'id': 'Tren Aktivitas Harian', 'en': 'Daily Activity Trend' },

            // User Activity & Profile
            'userActivityProfile': { 'id': 'Aktivitas Pengguna & Profil', 'en': 'User Activity & Profile' },
            'recentActivities': { 'id': 'Aktivitas Terbaru', 'en': 'Recent Activities' },
            'myMiniProfile': { 'id': 'Profil Mini Saya', 'en': 'My Mini Profile' },
            'name': { 'id': 'Nama', 'en': 'Name' },
            'totalFilesMini': { 'id': 'Total File', 'en': 'Total Files' },
            'totalFilesPublicMini': { 'id': 'Total File (Publik)', 'en': 'Total Files (Public)' },
            'storageUsedMini': { 'id': 'Penyimpanan Terpakai', 'en': 'Storage Used' },
            'weeklyActivitiesMini': { 'id': 'Aktivitas Mingguan', 'en': 'Weekly Activities' },

            // Activity Descriptions (for recent activities)
            'upload_file': { 'id': 'mengunggah file', 'en': 'uploaded a file' },
            'delete_file': { 'id': 'menghapus file', 'en': 'deleted a file' },
            'delete_folder': { 'id': 'menghapus folder', 'en': 'deleted a folder' },
            'rename_file': { 'id': 'mengganti nama file', 'en': 'renamed a file' },
            'rename_folder': { 'id': 'mengganti nama folder', 'en': 'renamed a folder' },
            'create_folder': { 'id': 'membuat folder', 'en': 'created a folder' },
            'archive': { 'id': 'mengarsipkan', 'en': 'archived' },
            'download': { 'id': 'mengunduh', 'en': 'downloaded' },
            'login': { 'id': 'masuk', 'en': 'logged in' },
            'share_link': { 'id': 'membagikan tautan', 'en': 'shared a link' },
            // Add more activity types as needed

            // Member Detail Modal
            'totalFilesModal': { 'id': 'Total File', 'en': 'Total Files' },
            'totalFilesPublicModal': { 'id': 'Total File (Publik)', 'en': 'Total Files (Public)' },
            'recentFilesModal': { 'id': 'File Terbaru', 'en': 'Recent Files' },
            'recentActivitiesModal': { 'id': 'Aktivitas Terbaru', 'en': 'Recent Activities' },
            'invalidMemberId': { 'id': 'ID anggota tidak valid.', 'en': 'Invalid member ID.' },
            'memberNotFound': { 'id': 'Anggota tidak ditemukan.', 'en': 'Member not found.' },
            'loadingRecentFiles': { 'id': 'Memuat file terbaru...', 'en': 'Loading recent files...' },
            'loadingRecentActivities': { 'id': 'Memuat aktivitas terbaru...', 'en': 'Loading recent activities...' },
            'noRecentFiles': { 'id': 'Tidak ada file terbaru.', 'en': 'No recent files.' },
            'noRecentActivities': { 'id': 'Tidak ada aktivitas terbaru.', 'en': 'No recent activities.' },
            'failedToLoadRecentFiles': { 'id': 'Gagal memuat file terbaru.', 'en': 'Failed to load recent files.' },
            'failedToLoadRecentActivities': { 'id': 'Gagal memuat aktivitas terbaru.', 'en': 'Failed to load recent activities.' },
        };

        let currentLanguage = localStorage.getItem('lang') || 'id'; // Default to Indonesian

        function applyTranslation(lang) {
            document.querySelectorAll('[data-lang-key]').forEach(element => {
                const key = element.getAttribute('data-lang-key');
                if (translations[key] && translations[key][lang]) {
                    element.textContent = translations[key][lang];
                }
            });

            // Special handling for "of X used" text
            const ofUsedElement = document.querySelector('.storage-text-card');
            if (ofUsedElement) {
                const totalStorageBytesText = document.getElementById('totalStorageBytesCount').textContent;
                if (translations['ofUsed'] && translations['ofUsed'][lang]) {
                    ofUsedElement.innerHTML = `${translations['ofUsed'][lang]} <span id="totalStorageBytesCount">${totalStorageBytesText}</span> ${translations['ofUsed'][lang === 'id' ? 'usedTextId' : 'usedTextEn'] || (lang === 'id' ? 'terpakai' : 'used')}`;
                }
            }
            // Add specific text for "used" in different languages if needed
            translations['usedTextId'] = 'terpakai';
            translations['usedTextEn'] = 'used';


            // Update dynamic counts and texts
            document.getElementById('totalUsersCount').textContent = <?php echo $totalUsers; ?>;
            document.getElementById('totalPublicFilesCount').textContent = <?php echo $totalPublicFiles; ?>;
            document.getElementById('usedStorageBytesCount').textContent = formatBytes(<?php echo $usedStorageBytes; ?>);
            document.getElementById('totalStorageBytesCount').textContent = formatBytes(<?php echo $totalStorageBytes; ?>);
            document.getElementById('weeklyActivitiesCount').textContent = <?php echo $weeklyActivities; ?>;

            // Update sidebar storage text
            document.getElementById('storageText').textContent = `${formatBytes(<?php echo $usedStorageBytes; ?>)} ${translations['ofUsed'][lang]} ${formatBytes(<?php echo $totalStorageBytes; ?>)} ${translations['usedText' + (lang === 'id' ? 'Id' : 'En')]}`;


            // Update recent activities descriptions
            document.querySelectorAll('[data-lang-activity-desc-key]').forEach(element => {
                const key = element.getAttribute('data-lang-activity-desc-key');
                const originalDescription = element.textContent; // Get the original description
                if (translations[key] && translations[key][lang]) {
                    // Replace the activity type part of the description with the translated one
                    // This assumes the original description starts with the activity type
                    // Example: "uploaded a file: document.pdf" -> "mengunggah file: document.pdf"
                    const username = element.closest('li').querySelector('strong').textContent; // Get username
                    const originalActivityType = username.split(' ')[0]; // Assuming username is "User uploaded"
                    const translatedActivityType = translations[key][lang];
                    
                    // A more robust way would be to store the base description without the activity type
                    // For now, we'll just update the activity type part if it's a simple match
                    let newDescription = originalDescription;
                    if (originalDescription.startsWith(originalActivityType)) {
                        newDescription = originalDescription.replace(originalActivityType, translatedActivityType);
                    } else {
                        // Fallback if the description structure is complex, just use the original
                        newDescription = originalDescription;
                    }
                    element.textContent = newDescription;
                }
            });

            // Update timestamps in recent activities
            document.querySelectorAll('.recent-activities .timestamp').forEach(element => {
                const timestamp = element.getAttribute('data-timestamp');
                if (timestamp) {
                    element.textContent = time_elapsed_string(timestamp, lang);
                }
            });

            // Update member table status
            document.querySelectorAll('.member-table tbody tr').forEach(row => {
                const statusSpan = row.querySelector('.status-indicator + span');
                if (statusSpan) {
                    const isOnline = statusSpan.getAttribute('data-lang-key') === 'online';
                    statusSpan.textContent = translations[isOnline ? 'online' : 'offline'][lang];
                }
                const neverLoggedInSpan = row.querySelector('[data-lang-key="neverLoggedIn"]');
                if (neverLoggedInSpan) {
                    neverLoggedInSpan.textContent = translations['neverLoggedIn'][lang];
                }
            });

            // Update modal content
            const memberDetailName = document.getElementById('memberDetailName');
            if (memberDetailName.textContent.includes("'s Profile")) { // Check if it's an English profile name
                const username = memberDetailName.textContent.replace("'s Profile", "");
                memberDetailName.textContent = `${username}${lang === 'id' ? "'s Profil" : "'s Profile"}`;
            } else if (memberDetailName.textContent.includes(" Profil")) { // Check if it's an Indonesian profile name
                const username = memberDetailName.textContent.replace(" Profil", "");
                memberDetailName.textContent = `${username}${lang === 'id' ? "'s Profil" : "'s Profile"}`;
            }
            
            // Update modal pagination buttons
            document.querySelectorAll('#memberDetailModal .modal-pagination-controls button').forEach(button => {
                const span = button.querySelector('span');
                if (span) {
                    const key = span.getAttribute('data-lang-key');
                    if (translations[key] && translations[key][lang]) {
                        span.textContent = translations[key][lang];
                    }
                }
            });
        }

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

            memberDetailName.textContent = translations['loadingRecentFiles'][currentLanguage] || 'Loading...';
            memberTotalFiles.textContent = '...';
            memberTotalPublicFiles.textContent = '...';
            recentFilesList.innerHTML = `<p>${translations['loadingRecentFiles'][currentLanguage] || 'Loading recent files...'}</p>`;
            recentActivitiesList.innerHTML = `<p>${translations['loadingRecentActivities'][currentLanguage] || 'Loading recent activities...'}</p>`;
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
                    memberDetailName.textContent = `${htmlspecialchars(member.username)}${currentLanguage === 'id' ? "'s Profil" : "'s Profile"}`;
                    memberTotalFiles.textContent = member.total_files;
                    memberTotalPublicFiles.textContent = member.total_public_files;

                    // Render Recent Files
                    recentFilesList.innerHTML = '';
                    if (member.recent_files.length > 0) {
                        member.recent_files.forEach(file => {
                            recentFilesList.innerHTML += `<li><i class="fas ${getFileIconClass(file.file_name)}"></i> ${htmlspecialchars(file.file_name)} (${formatBytes(file.file_size)})</li>`;
                        });
                    } else {
                        recentFilesList.innerHTML = `<li data-lang-key="noRecentFiles">${translations['noRecentFiles'][currentLanguage] || 'No recent files.'}</li>`;
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
                            const activityDescription = translations[activity.activity_type] ? translations[activity.activity_type][currentLanguage] : activity.description;
                            recentActivitiesList.innerHTML += `<li><i class="${icon}"></i> ${htmlspecialchars(activityDescription)} <span class="timestamp">${time_elapsed_string(activity.timestamp, currentLanguage)}</span></li>`;
                        });
                    } else {
                        recentActivitiesList.innerHTML = `<li data-lang-key="noRecentActivities">${translations['noRecentActivities'][currentLanguage] || 'No recent activities.'}</li>`;
                    }
                    updateActivitiesPagination(activitiesPage, member.total_activities_pages);

                } else {
                    memberDetailName.textContent = 'Error';
                    memberTotalFiles.textContent = 'N/A';
                    memberTotalPublicFiles.textContent = 'N/A';
                    recentFilesList.innerHTML = `<p>${translations['failedToLoadRecentFiles'][currentLanguage] || 'Failed to load recent files.'}</p>`;
                    recentActivitiesList.innerHTML = `<p>${translations['failedToLoadRecentActivities'][currentLanguage] || 'Failed to load recent activities.'}</p>`;
                    updateFilesPagination(1, 1); // Reset pagination on error
                    updateActivitiesPagination(1, 1); // Reset pagination on error
                }
            } catch (error) {
                console.error('Error fetching member details:', error);
                memberDetailName.textContent = 'Error';
                memberTotalFiles.textContent = 'N/A';
                memberTotalPublicFiles.textContent = 'N/A';
                recentFilesList.innerHTML = `<p>${translations['failedToLoadRecentFiles'][currentLanguage] || 'Failed to load recent files.'}</p>`;
                recentActivitiesList.innerHTML = `<p>${translations['failedToLoadRecentActivities'][currentLanguage] || 'Failed to load recent activities.'}</p>`;
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
        function time_elapsed_string(datetime, lang = 'en') {
            const now = new Date();
            const then = new Date(datetime.replace(/-/g, '/')); // Handle different date formats
            const seconds = Math.floor((now - then) / 1000);

            let interval;
            let unit;
            let value;

            if (seconds < 60) {
                value = seconds;
                unit = lang === 'id' ? 'detik' : 'second';
            } else if (seconds < 3600) {
                value = Math.floor(seconds / 60);
                unit = lang === 'id' ? 'menit' : 'minute';
            } else if (seconds < 86400) {
                value = Math.floor(seconds / 3600);
                unit = lang === 'id' ? 'jam' : 'hour';
            } else if (seconds < 2592000) { // 30 days
                value = Math.floor(seconds / 86400);
                unit = lang === 'id' ? 'hari' : 'day';
            } else if (seconds < 31536000) { // 365 days
                value = Math.floor(seconds / 2592000);
                unit = lang === 'id' ? 'bulan' : 'month';
            } else {
                value = Math.floor(seconds / 31536000);
                unit = lang === 'id' ? 'tahun' : 'year';
            }

            const plural = (value > 1 && lang === 'en') ? 's' : '';
            const ago = lang === 'id' ? 'yang lalu' : 'ago';

            return `${value} ${unit}${plural} ${ago}`;
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
            document.getElementById('totalUsersCount').textContent = data.totalUsers;
            document.getElementById('totalPublicFilesCount').textContent = data.totalPublicFiles;
            document.getElementById('usedStorageBytesCount').textContent = formatBytes(data.usedStorageBytes);
            document.getElementById('totalStorageBytesCount').textContent = formatBytes(data.totalStorageBytes);
            document.getElementById('weeklyActivitiesCount').textContent = data.weeklyActivities;

            // Update Storage Info in Sidebar
            document.querySelector('.progress-bar').style.width = `${data.usedPercentage.toFixed(2)}%`;
            document.querySelector('.progress-bar-text').textContent = `${data.usedPercentage.toFixed(2)}%`; // Update text inside progress bar
            document.getElementById('storageText').textContent = `${formatBytes(data.usedStorageBytes)} ${translations['ofUsed'][currentLanguage]} ${formatBytes(data.totalStorageBytes)} ${translations['usedText' + (currentLanguage === 'id' ? 'Id' : 'En')]}`;
            
            // Update storage full message in sidebar
            const storageInfoDiv = document.querySelector('.storage-info');
            let storageFullMessage = storageInfoDiv.querySelector('.storage-full-message');
            if (data.isStorageFull) {
                if (!storageFullMessage) {
                    storageFullMessage = document.createElement('p');
                    storageFullMessage.className = 'storage-text storage-full-message';
                    storageFullMessage.style.color = 'var(--error-color)';
                    storageFullMessage.style.fontWeight = 'bold';
                    storageFullMessage.setAttribute('data-lang-key', 'storageFull');
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
                    const activityDescription = translations[activity.activity_type] ? translations[activity.activity_type][currentLanguage] : activity.description;
                    const activityLi = `
                        <li>
                            <i class="${icon}"></i>
                            <span class="activity-text"><strong>${htmlspecialchars(activity.username)}</strong> <span data-lang-activity-desc-key="${activity.activity_type}">${htmlspecialchars(activityDescription)}</span></span>
                            <span class="timestamp" data-timestamp="${activity.timestamp}"></span>
                        </li>
                    `;
                    recentActivitiesUl.innerHTML += activityLi;
                });
            } else {
                recentActivitiesUl.innerHTML = `<li data-lang-key="noRecentActivities">${translations['noRecentActivities'][currentLanguage] || 'No recent activities.'}</li>`;
            }

            // Update Mini Profile
            const miniProfileSection = document.getElementById('miniProfileSection');
            miniProfileSection.querySelector('#miniProfileUsername').textContent = htmlspecialchars(data.currentUserProfile.username);
            miniProfileSection.querySelector('#miniProfileTotalFiles').textContent = data.currentUserProfile.total_files;
            miniProfileSection.querySelector('#miniProfilePublicFiles').textContent = data.currentUserProfile.public_files;
            miniProfileSection.querySelector('#miniProfileStorageUsed').textContent = data.currentUserProfile.storage_used;
            miniProfileSection.querySelector('#miniProfileWeeklyActivities').textContent = data.currentUserProfile.weekly_activities;

            // Re-apply translation after UI update
            applyTranslation(currentLanguage);
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
                    applyTranslation(currentLanguage); // Apply translation after rendering table
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
                                ${member.last_login ? new Date(member.last_login.replace(/-/g, '/')).toLocaleString() : `<span data-lang-key="neverLoggedIn">${translations['neverLoggedIn'][currentLanguage]}</span>`}
                            </td>
                            <td>
                                <span class="status-indicator ${member.is_online ? 'online' : 'offline'}"></span>
                                <span data-lang-key="${member.is_online ? 'online' : 'offline'}">${translations[member.is_online ? 'online' : 'offline'][currentLanguage]}</span>
                            </td>
                        </tr>
                    `;
                    memberTableBody.innerHTML += row;
                });
            } else {
                memberTableBody.innerHTML = `<tr><td colspan="6" class="text-center" data-lang-key="noMembersFound">${translations['noMembersFound'][currentLanguage]}</td></tr>`;
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
            const activityLabels = Object.keys(activityDistribution).map(key => translations[key] ? translations[key][currentLanguage] : key);
            const activityColors = [
                '#3F51B5', '#4CAF50', '#FFC107', '#F44336', '#9C27B0', '#00BCD4', '#FFEB3B', '#607D8B'
            ]; // Material Design colors

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
                                    color: 'var(--text-color)',
                                    font: {
                                        family: 'Roboto'
                                    }
                                }
                            },
                            title: {
                                display: false,
                                text: translations['activityDistribution'][currentLanguage] || 'Activity Distribution'
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
                            label: translations['totalFiles'][currentLanguage] || 'Total Files',
                            data: membersData,
                            backgroundColor: 'var(--primary-color)',
                            borderColor: 'var(--primary-dark-color)',
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
                                text: translations['topMembersByTotalFiles'][currentLanguage] || 'Top Members by Total Files'
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    color: 'var(--text-color)',
                                    font: {
                                        family: 'Roboto'
                                    }
                                }
                            },
                            x: {
                                ticks: {
                                    color: 'var(--text-color)',
                                    font: {
                                        family: 'Roboto'
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
                            label: translations['activities'][currentLanguage] || 'Activities',
                            data: dailyData,
                            borderColor: 'var(--success-color)',
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
                                text: translations['dailyActivityTrend'][currentLanguage] || 'Daily Activity Trend'
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    color: 'var(--text-color)',
                                    font: {
                                        family: 'Roboto'
                                    }
                                }
                            },
                            x: {
                                ticks: {
                                    color: 'var(--text-color)',
                                    font: {
                                        family: 'Roboto'
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
            // Get language from localStorage
            currentLanguage = localStorage.getItem('lang') || 'id';

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
            const mainContent = document.getElementById('mainContent'); // Get main-content for animations

            // --- Mobile Sidebar Toggle ---
            sidebarToggleBtn.addEventListener('click', () => {
                sidebar.classList.toggle('show-mobile-sidebar');
                mobileOverlay.classList.toggle('show');
            });

            // --- Sidebar Menu Navigation with Fly Out Animation ---
            document.querySelectorAll('.sidebar-menu a').forEach(item => {
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
            const currentPagePath = window.location.pathname.split('/').pop();
            document.querySelectorAll('.sidebar-menu a').forEach(item => {
                item.classList.remove('active');
                const itemHref = item.getAttribute('href');
                if (itemHref === currentPagePath) {
                    item.classList.add('active');
                }
            });

            // Apply initial translation
            applyTranslation(currentLanguage);
        });
    </script>
</body>
</html>
