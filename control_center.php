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

        /* Control Center Specific Styles */
        .section-title {
            font-size: 1.8em;
            font-weight: 400; /* Lighter font weight */
            color: var(--text-color);
            margin-bottom: 20px;
            border-bottom: 1px solid var(--divider-color); /* Material Design divider */
            padding-bottom: 10px;
        }

        .form-section, .member-list-section {
            background-color: var(--surface-color); /* White background */
            padding: 25px;
            border-radius: 0; /* Siku-siku */
            margin-bottom: 20px; /* Reduced margin */
            box-shadow: none; /* No box-shadow */
            border: 1px solid var(--divider-color); /* Subtle border */
        }

        .form-section form {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px; /* Reduced gap */
        }

        .form-section label {
            display: block;
            margin-bottom: 5px; /* Reduced margin */
            font-weight: 500; /* Medium font weight */
            color: var(--text-color);
            font-size: 0.95em;
        }

        .form-section input[type="text"],
        .form-section input[type="email"],
        .form-section select {
            width: calc(100% - 20px); /* Adjust for padding */
            padding: 10px;
            border: 1px solid var(--divider-color); /* Material Design border */
            border-radius: 0; /* Siku-siku */
            font-size: 0.9em;
            color: var(--text-color);
            background-color: var(--background-color); /* Light grey background */
            transition: border-color 0.2s ease-out, box-shadow 0.2s ease-out;
        }

        /* Style for password input container */
        .password-input-container {
            position: relative;
            width: 100%; /* Ensure it takes full width of its grid cell */
        }

        .password-input-container input[type="password"],
        .password-input-container input[type="text"] { /* Apply to both types */
            width: calc(100% - 50px); /* Adjust for padding and eye button */
            padding: 10px;
            padding-right: 40px; /* Space for the eye icon */
            border: 1px solid var(--divider-color);
            border-radius: 0;
            font-size: 0.9em;
            color: var(--text-color);
            background-color: var(--background-color);
            transition: border-color 0.2s ease-out, box-shadow 0.2s ease-out;
        }

        .password-input-container .toggle-password-btn {
            position: absolute;
            right: 5px; /* Adjust as needed */
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: var(--secondary-text-color);
            font-size: 1em; /* Adjust icon size */
            padding: 5px;
            outline: none;
        }

        .password-input-container .toggle-password-btn:hover {
            color: var(--primary-color);
        }

        .form-section input[type="text"]:focus,
        .form-section input[type="email"]:focus,
        .form-section select:focus,
        .password-input-container input:focus { /* Apply focus style to password input */
            border-color: var(--primary-color); /* Material primary color on focus */
            box-shadow: none; /* No box-shadow */
            outline: none;
            background-color: var(--surface-color); /* White background on focus */
        }

        .form-section button[type="submit"] {
            grid-column: 2 / 3; /* Align with the second column */
            justify-self: end; /* Align to the end of the grid cell */
            background-color: var(--primary-color); /* Material primary color */
            color: white;
            border: none;
            padding: 10px 20px; /* Reduced padding */
            border-radius: 0; /* Siku-siku */
            cursor: pointer;
            font-size: 1em;
            transition: background-color 0.2s ease-out, transform 0.1s ease-in-out;
            box-shadow: none; /* No box-shadow */
            margin-top: 10px;
        }

        .form-section button[type="submit"]:hover {
            background-color: var(--primary-dark-color);
            transform: translateY(0); /* No lift */
        }

        .form-section button[type="submit"]:active {
            transform: translateY(0);
        }

        .member-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px; /* Reduced margin */
            border: none; /* No outer border */
        }

        .member-table th, .member-table td {
            text-align: left;
            padding: 10px 15px; /* Reduced padding */
            border-bottom: 1px solid var(--divider-color); /* Material Design divider */
            font-size: 0.9em;
            color: var(--text-color);
        }

        .member-table th {
            background-color: var(--background-color); /* Light grey background */
            color: var(--secondary-text-color);
            font-weight: 500; /* Medium font weight */
            text-transform: uppercase;
            font-size: 0.8em;
        }

        .member-table tbody tr:hover {
            background-color: #f0f0f0; /* Google Drive hover effect */
        }

        .member-table .status-indicator {
            display: inline-block;
            width: 8px; /* Smaller indicator */
            height: 8px;
            border-radius: 50%;
            margin-right: 6px; /* Reduced margin */
            vertical-align: middle;
        }

        .member-table .status-indicator.online { background-color: var(--success-color); }
        .member-table .status-indicator.offline { background-color: var(--error-color); }

        .member-table .action-buttons button {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 7px 10px; /* Reduced padding */
            border-radius: 0; /* Siku-siku */
            cursor: pointer;
            font-size: 0.85em;
            transition: background-color 0.2s ease-out;
            box-shadow: none; /* No box-shadow */
        }

        .member-table .action-buttons button:hover {
            background-color: var(--primary-dark-color);
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
        .search-bar-mobile {
            display: none; /* Mobile search bar hidden on desktop */
        }

        /* New styles for desktop search bar placement */
        .member-monitoring-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            border-bottom: 1px solid var(--divider-color); /* Material Design divider */
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
                padding: 15px; /* Reduced padding */
            }
            body.tablet-landscape .header-main {
                padding: 10px 15px;
                margin: -15px -15px 15px -15px; /* Adjusted margin for full width */
            }
            body.tablet-landscape .header-main h1 {
                font-size: 1.8em;
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
                padding: 8px 12px; /* Reduced padding */
                font-size: 0.85em;
            }
            body.tablet-landscape .sidebar-menu a {
                font-size: var(--sidebar-font-size-tablet-landscape);
            }
            body.tablet-landscape .member-monitoring-header .search-bar-desktop {
                display: none; /* Hide desktop search bar on tablet landscape */
            }
            body.tablet-landscape .search-bar-mobile {
                display: flex; /* Show mobile search bar */
                margin: 0 auto 15px auto; /* Centered below header */
                width: calc(100% - 30px); /* Adjusted width for padding */
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
                margin: -15px -15px 15px -15px; /* Adjusted margin for full width */
            }
            body.tablet-portrait .header-main h1 {
                font-size: 1.6em;
                flex-grow: 1;
                text-align: center;
                margin-left: -30px; /* Counteract toggle button space */
            }
            body.tablet-portrait .header-main .control-center-title {
                display: none;
            }
            body.tablet-portrait .member-monitoring-header .search-bar-desktop {
                display: none; /* Hide desktop search bar */
            }
            body.tablet-portrait .search-bar-mobile {
                display: flex;
                margin: 0 auto 15px auto; /* Centered below header */
                width: calc(100% - 30px); /* Adjusted width for padding */
            }
            body.tablet-portrait .main-content {
                padding: 15px;
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
                border: 1px solid var(--divider-color); /* Material Design border */
                margin-bottom: 8px; /* Reduced margin */
                border-radius: 0; /* Siku-siku */
                background-color: var(--surface-color);
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
                font-weight: 500; /* Medium font weight */
                color: var(--secondary-text-color);
            }
            body.tablet-portrait .member-table td:nth-child(2) { /* Username */
                padding-top: 15px;
                font-weight: 600;
                font-size: 0.9em;
            }
            body.tablet-portrait .member-table td:nth-child(3)::before { content: "Full Name: "; font-weight: normal; color: var(--secondary-text-color); }
            body.tablet-portrait .member-table td:nth-child(4)::before { content: "Email: "; font-weight: normal; color: var(--secondary-text-color); }
            body.tablet-portrait .member-table td:nth-child(5)::before { content: "Role: "; font-weight: normal; color: var(--secondary-text-color); }
            body.tablet-portrait .member-table td:nth-child(6)::before { content: "Last Login: "; font-weight: normal; color: var(--secondary-text-color); }
            body.tablet-portrait .member-table td:nth-child(7)::before { content: "Status: "; font-weight: normal; color: var(--secondary-text-color); }
            body.tablet-portrait .member-table td:nth-child(8)::before { content: "Actions: "; font-weight: normal; color: var(--secondary-text-color); }
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
                justify-content: flex-start; /* Align items to start */
                padding: 10px 10px;
                margin: -15px -15px 15px -15px;
            }
            body.mobile .header-main h1 {
                font-size: 1.5em;
                flex-grow: 1;
                text-align: center;
                margin-left: -25px; /* Counteract toggle button space */
            }
            body.mobile .header-main .control-center-title {
                display: none;
            }
            body.mobile .member-monitoring-header .search-bar-desktop {
                display: none; /* Hide desktop search bar */
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
                border: 1px solid var(--divider-color);
                margin-bottom: 8px;
                border-radius: 0; /* Siku-siku */
                background-color: var(--surface-color);
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
                font-weight: 500;
                color: var(--secondary-text-color);
            }
            body.mobile .member-table td:nth-child(2) { /* Username */
                padding-top: 15px;
                font-weight: 600;
                font-size: 0.9em;
            }
            body.mobile .member-table td:nth-child(3)::before { content: "Full Name: "; font-weight: normal; color: var(--secondary-text-color); }
            body.mobile .member-table td:nth-child(4)::before { content: "Email: "; font-weight: normal; color: var(--secondary-text-color); }
            body.mobile .member-table td:nth-child(5)::before { content: "Role: "; font-weight: normal; color: var(--secondary-text-color); }
            body.mobile .member-table td:nth-child(6)::before { content: "Last Login: "; font-weight: normal; color: var(--secondary-text-color); }
            body.mobile .member-table td:nth-child(7)::before { content: "Status: "; font-weight: normal; color: var(--secondary-text-color); }
            body.mobile .member-table td:nth-child(8)::before { content: "Actions: "; font-weight: normal; color: var(--secondary-text-color); }
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

        .modal input[type="text"],
        .modal input[type="password"],
        .modal input[type="email"],
        .modal select {
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
        
        .modal input[type="text"]:focus,
        .modal input[type="password"]:focus,
        .modal input[type="email"]:focus,
        .modal select:focus {
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
            'ofUsed': { 'id': 'dari', 'en': 'of' },
            'usedTextId': 'terpakai',
            'usedTextEn': 'used',

            // Control Center Specific
            'controlCenterTitle': { 'id': 'Pusat Kontrol', 'en': 'Control Center' },
            'createNewMemberAccount': { 'id': 'Buat Akun Anggota Baru', 'en': 'Create New Member Account' },
            'username': { 'id': 'Nama Pengguna', 'en': 'Username' },
            'password': { 'id': 'Kata Sandi', 'en': 'Password' },
            'email': { 'id': 'Email', 'en': 'Email' },
            'fullName': { 'id': 'Nama Lengkap', 'en': 'Full Name' },
            'role': { 'id': 'Peran', 'en': 'Role' },
            'userRole': { 'id': 'Pengguna', 'en': 'User' },
            'memberRole': { 'id': 'Anggota', 'en': 'Member' },
            'moderatorRole': { 'id': 'Moderator', 'en': 'Moderator' },
            'adminRole': { 'id': 'Admin', 'en': 'Admin' },
            'createAccount': { 'id': 'Buat Akun', 'en': 'Create Account' },
            'memberMonitoring': { 'id': 'Pemantauan Anggota', 'en': 'Member Monitoring' },
            'searchMembersPlaceholder': { 'id': 'Cari anggota...', 'en': 'Search members...' },
            'id': { 'id': 'ID', 'en': 'ID' },
            'lastLogin': { 'id': 'Login Terakhir', 'en': 'Last Login' },
            'status': { 'id': 'Status', 'en': 'Status' },
            'actions': { 'id': 'Tindakan', 'en': 'Actions' },
            'onlineStatus': { 'id': 'Online', 'en': 'Online' },
            'offlineStatus': { 'id': 'Offline', 'en': 'Offline' },
            'viewDetails': { 'id': 'Lihat Detail', 'en': 'View Details' },
            'noMembersFound': { 'id': 'Tidak ada anggota ditemukan.', 'en': 'No members found.' },
            'memberDetailsTitle': { 'id': 'Detail Anggota', 'en': 'Member Details' },
            'lastActive': { 'id': 'Terakhir Aktif', 'en': 'Last Active' },
            'na': { 'id': 'N/A', 'en': 'N/A' }, // Not Applicable
            'allFieldsRequired': { 'id': 'Semua kolom wajib diisi.', 'en': 'All fields are required.' },
            'invalidEmailFormat': { 'id': 'Format email tidak valid.', 'en': 'Invalid email format.' },
            'usernameOrEmailExists': { 'id': 'Nama pengguna atau email sudah ada.', 'en': 'Username or email already exists.' },
            'accountCreatedSuccess': { 'id': 'Akun anggota berhasil dibuat!', 'en': 'Member account created successfully!' },
            'errorCreatingAccount': { 'id': 'Kesalahan saat membuat akun anggota:', 'en': 'Error creating member account:' },
            'unknownError': { 'id': 'Terjadi kesalahan yang tidak diketahui.', 'en': 'An unknown error occurred.' },
            'memberDetailsNotFound': { 'id': 'Detail anggota tidak ditemukan.', 'en': 'Member details not found.' },
            'errorDuringCreation': { 'id': 'Terjadi kesalahan saat pembuatan akun.', 'en': 'An error occurred during account creation.' },
        };

        let currentLanguage = localStorage.getItem('lang') || 'id'; // Default to 'id'

        function applyTranslation(lang) {
            document.querySelectorAll('[data-lang-key]').forEach(element => {
                const key = element.getAttribute('data-lang-key');
                if (translations[key] && translations[key][lang]) {
                    // For input placeholders
                    if (element.tagName === 'INPUT' && element.hasAttribute('placeholder')) {
                        element.setAttribute('placeholder', translations[key][lang]);
                    } else {
                        element.textContent = translations[key][lang];
                    }
                }
            });

            // Special handling for "of X used" text in storage info
            const storageTextElement = document.getElementById('storageText');
            if (storageTextElement) {
                const usedBytes = <?php echo $usedStorageBytes; ?>;
                const totalBytes = <?php echo $totalStorageBytes; ?>;
                const formattedUsed = formatBytes(usedBytes);
                const formattedTotal = formatBytes(totalBytes);
                
                const ofText = translations['ofUsed'][lang];
                const usedText = translations['usedText' + (lang === 'id' ? 'Id' : 'En')];
                
                storageTextElement.textContent = `${formattedUsed} ${ofText} ${formattedTotal} ${usedText}`;
            }

            // Update N/A for Last Login if applicable
            document.querySelectorAll('.member-table tbody tr').forEach(row => {
                const lastLoginCell = row.querySelector('td:nth-child(6)');
                if (lastLoginCell && lastLoginCell.textContent.trim() === 'N/A') {
                    lastLoginCell.innerHTML = `<span data-lang-key="na">${translations['na'][lang]}</span>`;
                }
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.querySelector('.sidebar');
            const sidebarToggleBtn = document.getElementById('sidebarToggleBtn');
            const mobileOverlay = document.getElementById('mobileOverlay');
            const searchMemberInputDesktop = document.getElementById('searchMemberInputDesktop'); // Desktop search
            const searchMemberInputMobile = document.getElementById('searchMemberInputMobile'); // Mobile search
            const customNotification = document.getElementById('customNotification');
            const createMemberForm = document.getElementById('createMemberForm');
            const mainContent = document.getElementById('mainContent'); // Get main-content for animations

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
                // Translate the message before showing
                let translatedMessage = "<?php echo $message; ?>";
                const messageKeyMap = {
                    "All fields are required.": "allFieldsRequired",
                    "Invalid email format.": "invalidEmailFormat",
                    "Username or email already exists.": "usernameOrEmailExists",
                    "Member account created successfully!": "accountCreatedSuccess",
                    "Error creating member account: ": "errorCreatingAccount", // Partial match
                    "An unknown error occurred.": "unknownError",
                    "Member details not found.": "memberDetailsNotFound",
                    "An error occurred during account creation.": "errorDuringCreation"
                };

                for (const originalMsg in messageKeyMap) {
                    if (translatedMessage.startsWith(originalMsg)) {
                        const key = messageKeyMap[originalMsg];
                        translatedMessage = translations[key][currentLanguage] + translatedMessage.substring(originalMsg.length);
                        break;
                    }
                }
                showNotification(translatedMessage, "<?php echo $messageType; ?>");
            <?php endif; ?>

            // --- Responsive Class Handling ---
            function applyDeviceClass() {
                const width = window.innerWidth;
                const body = document.body;

                body.classList.remove('mobile', 'tablet-portrait', 'tablet-landscape', 'desktop');

                if (width <= 767) {
                    body.classList.add('mobile');
                    sidebar.classList.add('mobile-hidden'); // Ensure sidebar is hidden by default on mobile
                } else if (width >= 768 && width <= 1024) {
                    if (window.matchMedia("(orientation: portrait)").matches) {
                        body.classList.add('tablet-portrait');
                        sidebar.classList.add('mobile-hidden'); // Ensure sidebar is hidden by default on tablet portrait
                    } else {
                        body.classList.add('tablet-landscape');
                        sidebar.classList.remove('mobile-hidden'); // Sidebar visible on tablet landscape
                        sidebar.classList.remove('show-mobile-sidebar');
                        mobileOverlay.classList.remove('show');
                    }
                } else {
                    body.classList.add('desktop');
                    sidebar.classList.remove('mobile-hidden'); // Sidebar visible on desktop
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
                            applyTranslation(currentLanguage); // Apply translation after updating list
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
                    
                    let message = translations['unknownError'][currentLanguage]; // Default unknown error
                    let messageType = "error";

                    if (notificationDiv) {
                        const originalMessage = notificationDiv.textContent.trim();
                        const messageKeyMap = {
                            "All fields are required.": "allFieldsRequired",
                            "Invalid email format.": "invalidEmailFormat",
                            "Username or email already exists.": "usernameOrEmailExists",
                            "Member account created successfully!": "accountCreatedSuccess",
                            "Error creating member account: ": "errorCreatingAccount", // Partial match
                        };

                        let foundTranslation = false;
                        for (const originalMsg in messageKeyMap) {
                            if (originalMessage.startsWith(originalMsg)) {
                                const key = messageKeyMap[originalMsg];
                                message = translations[key][currentLanguage] + originalMessage.substring(originalMsg.length);
                                foundTranslation = true;
                                break;
                            }
                        }
                        if (!foundTranslation) {
                            message = originalMessage; // Fallback to original if no specific translation found
                        }

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
                    showNotification(translations['errorDuringCreation'][currentLanguage], 'error');
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
                    document.getElementById('detailRole').textContent = translations[member.role.toLowerCase() + 'Role'][currentLanguage] || ucfirst(member.role);
                    document.getElementById('detailLastLogin').textContent = member.last_login ? new Date(member.last_login.replace(/-/g, '/')).toLocaleString() : translations['na'][currentLanguage];
                    document.getElementById('detailLastActive').textContent = member.last_active ? new Date(member.last_active.replace(/-/g, '/')).toLocaleString() : translations['na'][currentLanguage];
                    document.getElementById('detailStatus').textContent = member.is_online ? translations['onlineStatus'][currentLanguage] : translations['offlineStatus'][currentLanguage];
                    openModal(memberDetailsModal); // Use openModal function
                } else {
                    showNotification(translations['memberDetailsNotFound'][currentLanguage], 'error');
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

            // Initial translation application
            applyTranslation(currentLanguage);
        });

        // --- Show/Hide Password Function ---
        function togglePassword(fieldId) {
            const passwordField = document.getElementById(fieldId);
            const toggleButton = passwordField.nextElementSibling; // Assuming button is next sibling

            if (passwordField.type === "password") {
                passwordField.type = "text";
                toggleButton.innerHTML = '<i class="fas fa-eye-slash"></i>'; // Change icon to eye-slash
            } else {
                passwordField.type = "password";
                toggleButton.innerHTML = '<i class="fas fa-eye"></i>'; // Change icon back to eye
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

        // Helper function for ucfirst (replicate PHP's ucfirst)
        function ucfirst(str) {
            if (typeof str !== 'string' || str.length === 0) {
                return '';
            }
            return str.charAt(0).toUpperCase() + str.slice(1);
        }
    </script>
</body>
</html>
