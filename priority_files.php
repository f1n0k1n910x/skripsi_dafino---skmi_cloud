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
    <link rel="stylesheet" href="css/priority_file.css">
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
            <p class="storage-text" id="storageText"
                data-used-bytes="<?php echo $usedStorageBytes; ?>"
                data-total-bytes="<?php echo $totalStorageBytes; ?>">
                <?php echo formatBytes($usedStorageBytes); ?> of <?php echo formatBytes($totalStorageBytes); ?> used
            </p>
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
        // Pass PHP variables to JavaScript
        const currentUserRole = "<?php echo $currentUserRole; ?>";
        const usedStorageBytes = <?php echo $usedStorageBytes; ?>;
        const totalStorageBytes = <?php echo $totalStorageBytes; ?>;
    </script>
    <script src="js/priority_file.js"></script>
</body>
</html>
