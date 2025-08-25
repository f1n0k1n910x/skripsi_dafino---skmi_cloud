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
    <link rel="stylesheet" href="css/members.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        // Data PHP yang akan digunakan oleh JavaScript
        const phpData = {
            membersPerPage: <?php echo $membersPerPage; ?>,
            totalMembersCount: <?php echo $totalMembersCount; ?>,
            totalPages: <?php echo $totalPages; ?>,
            initialMembers: <?php echo json_encode($members); ?>,
            initialActivityDistribution: <?php echo json_encode($activityDistribution); ?>,
            initialTopMembersPublicFiles: <?php echo json_encode($topMembersPublicFiles); ?>,
            initialDailyActivities: <?php echo json_encode($dailyActivities); ?>,
            initialRecentActivities: <?php echo json_encode($recentActivities); ?>,
            initialCurrentUserProfile: <?php echo json_encode($currentUserProfile); ?>,
            initialTotalUsers: <?php echo $totalUsers; ?>,
            initialTotalPublicFiles: <?php echo $totalPublicFiles; ?>,
            initialUsedStorageBytes: <?php echo $usedStorageBytes; ?>,
            initialTotalStorageBytes: <?php echo $totalStorageBytes; ?>,
            initialWeeklyActivities: <?php echo $weeklyActivities; ?>,
            initialUsedPercentage: <?php echo $usedPercentage; ?>,
            initialIsStorageFull: <?php echo json_encode($isStorageFull); ?>
        };
    </script>
    <script src="js/members.js"></script>
</body>
</html>
