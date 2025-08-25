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

// // Check if the user has admin or moderator role
// if ($currentUserRole !== 'admin' && $currentUserRole !== 'moderator') {
//     header("Location: index.php"); // Redirect if not authorized
//     exit();
// }

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
    // No header redirect here if using AJAX for form submission
    // The JavaScript will handle updating the list and showing notifications
    // If not using AJAX, the redirect below would be active:
    // header("Location: control_center.php"); // Redirect to prevent form resubmission
    // exit();
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

// If this is an AJAX request for member list, only output the table body
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    ob_clean(); // Clean any previous output
    ?>
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
    <?php
    exit(); // Exit after sending the table body
}


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
    <link rel="stylesheet" href="css/control_center.css">
</head>
<body>
    <div class="sidebar mobile-hidden">
        <div class="sidebar-header">
            <img src="img/logo.png" alt="Dafino Logo">
        </div>
        <ul class="sidebar-menu">
            <?php if ($currentUserRole === 'admin' || $currentUserRole === 'moderator'): ?>
                <li><a href="control_center.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'control_center.php') ? 'active' : ''; ?>"><i class="fas fa-cogs"></i> <span data-lang-key="controlCenter">Control Center</span></a></li>
            <?php endif; ?>
            <?php if ($currentUserRole === 'admin' || $currentUserRole === 'user' || $currentUserRole === 'member'): ?>
                <li><a href="index.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'index.php') ? 'active' : ''; ?>"><i class="fas fa-folder"></i> <span data-lang-key="myDrive">My Drive</span></a></li>
                <li><a href="priority_files.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'priority_files.php') ? 'active' : ''; ?>"><i class="fas fa-star"></i> <span data-lang-key="priorityFile">Priority File</span></a></li>
                <li><a href="recycle_bin.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'recycle_bin.php') ? 'active' : ''; ?>"><i class="fas fa-trash"></i> <span data-lang-key="recycleBin">Recycle Bin</span></a></li>
            <?php endif; ?>
            <li><a href="summary.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'summary.php') ? 'active' : ''; ?>"><i class="fas fa-chart-line"></i> <span data-lang-key="summary">Summary</span></a></li>
            <li><a href="members.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'members.php') ? 'active' : ''; ?>"><i class="fas fa-users"></i> <span data-lang-key="members">Members</span></a></li>
            <li><a href="profile.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'profile.php') ? 'active' : ''; ?>"><i class="fas fa-user"></i> <span data-lang-key="profile">Profile</span></a></li>
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
            <h1 class="control-center-title" data-lang-key="controlCenterTitle">Control Center</h1>
            <!-- Desktop search bar removed from here -->
        </div>

        <?php if (!empty($message)): ?>
            <div id="customNotification" class="notification <?php echo $messageType; ?> show">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <h2 class="section-title" data-lang-key="createNewMemberAccount">Create New Member Account</h2>
        <div class="form-section">
            <form action="control_center.php" method="POST" id="createMemberForm">
                <div>
                    <label for="new_username" data-lang-key="username">Username:</label>
                    <input type="text" id="new_username" name="new_username" required>
                </div>
                <div>
                    <label for="new_password" data-lang-key="password">Password:</label>
                    <div class="password-input-container">
                        <input type="password" id="new_password" name="new_password" required>
                        <button type="button" class="toggle-password-btn" onclick="togglePassword('new_password')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                <div>
                    <label for="new_email" data-lang-key="email">Email:</label>
                    <input type="email" id="new_email" name="new_email" required>
                </div>
                <div>
                    <label for="new_full_name" data-lang-key="fullName">Full Name:</label>
                    <input type="text" id="new_full_name" name="new_full_name" required>
                </div>
                <div>
                    <label for="new_role" data-lang-key="role">Role:</label>
                    <select id="new_role" name="new_role" required>
                        <option value="user" data-lang-key="userRole">User</option>
                        <option value="member" data-lang-key="memberRole">Member</option>
                        <?php if ($currentUserRole === 'admin'): // Only admin can create other admins/moderators ?>
                            <option value="moderator" data-lang-key="moderatorRole">Moderator</option>
                            <option value="admin" data-lang-key="adminRole">Admin</option>
                        <?php endif; ?>
                    </select>
                </div>
                <button type="submit" name="create_member_account" data-lang-key="createAccount">Create Account</button>
            </form>
            <?php if (isset($message)): ?>
            <div id="customNotification" class="<?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
            <?php endif; ?>

        </div>

        <div class="member-monitoring-header">
            <h2 class="section-title" data-lang-key="memberMonitoring">Member Monitoring</h2>
            <div class="search-bar search-bar-desktop">
                <i class="fas fa-search"></i>
                <input type="text" id="searchMemberInputDesktop" placeholder="Search members..." value="<?php echo htmlspecialchars($searchMemberQuery); ?>" data-lang-key="searchMembersPlaceholder">
            </div>
        </div>

        <!-- Mobile Search Bar (moved below member monitoring section) -->
        <div class="search-bar search-bar-mobile">
            <i class="fas fa-search"></i>
            <input type="text" id="searchMemberInputMobile" placeholder="Search members..." value="<?php echo htmlspecialchars($searchMemberQuery); ?>" data-lang-key="searchMembersPlaceholder">
        </div>

        <div class="member-list-section">
            <table class="member-table">
                <thead>
                    <tr>
                        <th data-lang-key="id">ID</th>
                        <th data-lang-key="username">Username</th>
                        <th data-lang-key="fullName">Full Name</th>
                        <th data-lang-key="role">Role</th>
                        <th data-lang-key="email">Email</th>
                        <th data-lang-key="lastLogin">Last Login</th>
                        <th data-lang-key="status">Status</th>
                        <th data-lang-key="actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($members)): ?>
                        <?php foreach ($members as $member): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($member['id']); ?></td>
                                <td><?php echo htmlspecialchars($member['username']); ?></td>
                                <td><?php echo htmlspecialchars($member['full_name']); ?></td>
                                <td><span data-lang-key="<?php echo strtolower($member['role']); ?>Role"><?php echo htmlspecialchars(ucfirst($member['role'])); ?></span></td>
                                <td><?php echo htmlspecialchars($member['email']); ?></td>
                                <td><?php echo !empty($member['last_login']) ? date('Y-m-d H:i', strtotime($member['last_login'])) : '<span data-lang-key="na">N/A</span>'; ?></td>
                                <td>
                                    <span class="status-indicator <?php echo $member['is_online'] ? 'online' : 'offline'; ?>"></span>
                                    <span data-lang-key="<?php echo $member['is_online'] ? 'onlineStatus' : 'offlineStatus'; ?>"><?php echo $member['is_online'] ? 'Online' : 'Offline'; ?></span>
                                </td>
                                <td class="action-buttons">
                                    <button onclick="viewMemberDetails(<?php echo $member['id']; ?>)" data-lang-key="viewDetails">View Details</button>
                                    <!-- Add more actions like Edit, Delete if needed -->
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" style="text-align: center;" data-lang-key="noMembersFound">No members found.</td>
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
            <h2 data-lang-key="memberDetailsTitle">Member Details: <span id="memberDetailsUsername"></span></h2>
            <div id="memberDetailsContent">
                <p><strong data-lang-key="id">ID:</strong> <span id="detailId"></span></p>
                <p><strong data-lang-key="fullName">Full Name:</strong> <span id="detailFullName"></span></p>
                <p><strong data-lang-key="email">Email:</strong> <span id="detailEmail"></span></p>
                <p><strong data-lang-key="role">Role:</strong> <span id="detailRole"></span></p>
                <p><strong data-lang-key="lastLogin">Last Login:</strong> <span id="detailLastLogin"></span></p>
                <p><strong data-lang-key="lastActive">Last Active:</strong> <span id="detailLastActive"></span></p>
                <p><strong data-lang-key="status">Status:</strong> <span id="detailStatus"></span></p>
                <!-- You can add more details here, like total files, storage used by this member, etc. -->
            </div>
        </div>
    </div>

    <div class="overlay" id="mobileOverlay"></div>

    <script>
        // Data PHP yang dibutuhkan oleh JavaScript
        const phpData = {
            members: <?php echo json_encode($members); ?>,
            usedStorageBytes: <?php echo $usedStorageBytes; ?>,
            totalStorageBytes: <?php echo $totalStorageBytes; ?>,
            message: <?php echo json_encode($message); ?>,
            messageType: <?php echo json_encode($messageType); ?>
        };
    </script>
    <script src="js/control_center.js"></script>
</body>
</html>
