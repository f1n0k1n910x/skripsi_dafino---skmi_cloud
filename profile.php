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

// Current folder ID, default to NULL for root (not directly used in profile.php but kept for consistency)
$currentFolderId = isset($_GET['folder']) ? (int)$_GET['folder'] : NULL;

$user_id = $_SESSION['user_id'];

// Function to fetch all profile data
function getProfileData($conn, $user_id) {
    $data = [];

    // Fetch user information
    $stmt = $conn->prepare("SELECT username, email, full_name, created_at, profile_picture, phone_number, date_of_birth, account_status FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if (!$user) {
        // User not found, this should ideally not happen if session is valid
        // For AJAX, return an error state
        return ['error' => true, 'message' => 'User not found.'];
    }

    // Ensure profile_picture is set, use default if empty or null
    if (empty($user['profile_picture'])) {
        $user['profile_picture'] = 'img/photo_profile.png'; // Default blank profile image
    }

    // Simulate account status if not present in DB
    if (!isset($user['account_status']) || empty($user['account_status'])) {
        $user['account_status'] = 'Active'; // Default status
    }
    $data['user'] = $user;

    // Storage Data (Global Storage for all users, consistent with index.php and summary.php)
    $totalStorageGB = 500; // Total storage capacity 700 GB
    $totalStorageBytes = $totalStorageGB * 1024 * 1024 * 1024; // Convert GB to Bytes

    $usedStorageBytes = 0;
    // Calculate used storage from files table (sum of file_size for ALL files, not just current user)
    $stmt = $conn->prepare("SELECT SUM(file_size) as total_size FROM files");
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    if ($row['total_size']) {
        $usedStorageBytes = $row['total_size'];
    }
    $stmt->close();
    
    $data['totalStorageGB'] = $totalStorageGB;
    $data['totalStorageBytes'] = $totalStorageBytes; // Pass totalStorageBytes
    $data['usedStorageBytes'] = $usedStorageBytes;
    $data['usedPercentage'] = ($totalStorageBytes > 0) ? ($usedStorageBytes / $totalStorageBytes) * 100 : 0;
    if ($data['usedPercentage'] > 100) $data['usedPercentage'] = 100;

    // Check if storage is full (using the function from functions.php)
    $data['isStorageFull'] = isStorageFull($conn, $totalStorageBytes);


    // Activity Logs
    $activity_logs = [];
    $stmt_activities = $conn->prepare("SELECT activity_type, description, timestamp FROM activities WHERE user_id = ? ORDER BY timestamp ASC");
    $stmt_activities->bind_param("i", $user_id);
    $stmt_activities->execute();
    $result_activities = $stmt_activities->get_result();
    while ($row = $result_activities->fetch_assoc()) {
        $activity_logs[] = $row;
    }
    $stmt_activities->close();
    $data['activity_logs'] = $activity_logs;

    // Daily Activity Count for Chart
    $daily_activities_count = [];
    foreach ($activity_logs as $log) {
        $date = date('Y-m-d', strtotime($log['timestamp']));
        if (!isset($daily_activities_count[$date])) {
            $daily_activities_count[$date] = 0;
        }
        $daily_activities_count[$date]++;
    }
    ksort($daily_activities_count);
    $data['chart_labels'] = array_keys($daily_activities_count);
    $data['chart_data'] = array_values($daily_activities_count);

    if (empty($data['chart_labels'])) {
        $data['chart_labels'] = [];
        $data['chart_data'] = [];
        for ($i = 6; $i >= 0; $i--) {
            $data['chart_labels'][] = date('Y-m-d', strtotime("-$i days"));
            $data['chart_data'][] = 0;
        }
    }

    // Additional Emails
    $additional_emails = [];
    $stmt_emails = $conn->prepare("SELECT id, email, is_verified FROM user_emails WHERE user_id = ? ORDER BY created_at ASC");
    $stmt_emails->bind_param("i", $user_id);
    $stmt_emails->execute();
    $result_emails = $stmt_emails->get_result();
    while ($row = $result_emails->fetch_assoc()) {
        $additional_emails[] = $row;
    }
    $stmt_emails->close();
    $data['additional_emails'] = $additional_emails;

    // Total Files for the user
    $total_files = 0;
    $stmt_total_files = $conn->prepare("SELECT COUNT(id) AS total_files FROM files WHERE user_id = ?");
    $stmt_total_files->bind_param("i", $user_id);
    $stmt_total_files->execute();
    $result_total_files = $stmt_total_files->get_result();
    $row_total_files = $result_total_files->fetch_assoc();
    if ($row_total_files) {
        $total_files = $row_total_files['total_files'];
    }
    $stmt_total_files->close();
    $data['total_files'] = $total_files;

    // Account Status Display
    $account_status_display = [
        'Active' => 'Active',
        'Inactive' => 'Inactive',
        'None' => 'None',
        'Lost' => 'Lost'
    ];
    $data['current_account_status'] = $account_status_display[$user['account_status']] ?? 'Unknown';

    return $data;
}

// Handle AJAX request for initial data or refresh
if (isset($_GET['action']) && $_GET['action'] === 'get_profile_data') {
    header('Content-Type: application/json');
    echo json_encode(getProfileData($conn, $user_id));
    $conn->close();
    exit();
}

// Handle form submissions (Password Change, Profile Edit, Add/Delete Email)
$notification_message = '';
$notification_type = '';

// Handle Password Change (This part will now be handled by AJAX from the modal)
// We keep the PHP logic here to process the AJAX request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password_ajax'])) {
    $old_password = $_POST['old_password'];
    $new_password = $_POST['new_password'];
    $confirm_new_password = $_POST['confirm_new_password'];

    // Verify old password
    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $db_password = $result->fetch_assoc()['password'];
    $stmt->close();

    if (!password_verify($old_password, $db_password)) {
        echo json_encode(['success' => false, 'message' => 'Old password is incorrect.']);
    } elseif ($new_password !== $confirm_new_password) {
        echo json_encode(['success' => false, 'message' => 'New password confirmation does not match.']);
    } elseif (strlen($new_password) < 6) { // Example: minimum password length
        echo json_encode(['success' => false, 'message' => 'New password must be at least 6 characters long.']);
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $hashed_password, $user_id);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Password changed successfully.']);
            logActivity($conn, $user_id, 'change_password', 'Changed account password.');
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to change password.']);
        }
        $stmt->close();
    }
    exit(); // Important: exit after AJAX response
}


// Handle Profile Edit (for full_name, phone_number, date_of_birth, profile_picture)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_profile_submit'])) {
    // Re-fetch user data to get current profile picture path
    $stmt_user = $conn->prepare("SELECT profile_picture FROM users WHERE id = ?");
    $stmt_user->bind_param("i", $user_id);
    $stmt_user->execute();
    $current_user_data = $stmt_user->get_result()->fetch_assoc();
    $stmt_user->close();
    $profile_picture = $current_user_data['profile_picture']; // Keep existing if not updated

    $full_name = $_POST['full_name'];
    $phone_number = $_POST['phone_number'];
    $date_of_birth = $_POST['date_of_birth'];
    
    $response = ['success' => false, 'message' => ''];

    // Handle profile picture upload
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $target_dir = "uploads/profile_pictures/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        $file_extension = strtolower(pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
        $max_file_size = 2 * 1024 * 1024; // 2 MB

        if (!in_array($file_extension, $allowed_extensions)) {
            $response['message'] = 'Only JPG, JPEG, PNG, and GIF files are allowed.';
        } elseif ($_FILES['profile_picture']['size'] > $max_file_size) {
            $response['message'] = 'File size is too large. Maximum 2MB.';
        } else {
            $new_file_name = uniqid('profile_') . '.' . $file_extension;
            $target_file = $target_dir . $new_file_name;
            $temp_file_path = $_FILES['profile_picture']['tmp_name'];

            // Check if the uploaded file is a PNG and has transparency
            $is_transparent_png = false;
            if ($file_extension === 'png') {
                $img = imagecreatefrompng($temp_file_path);
                if ($img) {
                    imagesavealpha($img, true);
                    $width = imagesx($img);
                    $height = imagesy($img);
                    for ($x = 0; $x < $width; $x++) {
                        for ($y = 0; $y < $height; $y++) {
                            $rgba = imagecolorat($img, $x, $y);
                            $alpha = ($rgba >> 24) & 0xFF;
                            if ($alpha !== 0 && $alpha !== 127) { // Check for any transparency (not fully opaque or fully transparent)
                                $is_transparent_png = true;
                                break 2; // Exit both loops
                            }
                        }
                    }
                    imagedestroy($img);
                }
            }

            // If it's a transparent PNG, merge with background image
            if ($is_transparent_png) {
                $background_image_path = 'img/photo_profile_bg_blank.png';
                if (file_exists($background_image_path)) {
                    $background_img = imagecreatefrompng($background_image_path);
                    $uploaded_img = imagecreatefrompng($temp_file_path);

                    if ($background_img && $uploaded_img) {
                        // Resize uploaded image to fit background if necessary (assuming background is circular)
                        // For simplicity, we'll just overlay. You might need more complex resizing/cropping.
                        $bg_width = imagesx($background_img);
                        $bg_height = imagesy($background_img);

                        // Create a new true color image with transparency
                        $merged_img = imagecreatetruecolor($bg_width, $bg_height);
                        imagesavealpha($merged_img, true);
                        $transparent_color = imagecolorallocatealpha($merged_img, 0, 0, 0, 127);
                        imagefill($merged_img, 0, 0, $transparent_color);

                        // Copy background image to merged image
                        imagecopy($merged_img, $background_img, 0, 0, 0, 0, $bg_width, $bg_height);

                        // Calculate position to center the uploaded image on the background
                        $uploaded_width = imagesx($uploaded_img);
                        $uploaded_height = imagesy($uploaded_img);

                        // Maintain aspect ratio and fit within the background circle
                        $ratio = min($bg_width / $uploaded_width, $bg_height / $uploaded_height);
                        $new_width = $uploaded_width * $ratio;
                        $new_height = $uploaded_height * $ratio;

                        $dest_x = ($bg_width - $new_width) / 2;
                        $dest_y = ($bg_height - $new_height) / 2;

                        imagecopyresampled($merged_img, $uploaded_img, $dest_x, $dest_y, 0, 0, $new_width, $new_height, $uploaded_width, $uploaded_height);

                        // Save the merged image
                        if (imagepng($merged_img, $target_file)) {
                            // Delete old profile picture if it's not the default one
                            if ($profile_picture && $profile_picture !== 'img/photo_profile.png' && $profile_picture !== 'img/photo_profile.png' && file_exists($profile_picture)) {
                                unlink($profile_picture);
                            }
                            $profile_picture = $target_file;
                        } else {
                            $response['message'] = 'Failed to save merged profile picture.';
                        }
                        imagedestroy($background_img);
                        imagedestroy($uploaded_img);
                        imagedestroy($merged_img);
                    } else {
                        $response['message'] = 'Failed to load images for merging.';
                    }
                } else {
                    $response['message'] = 'Background image not found. Uploading original PNG.';
                    // Fallback to just uploading the original PNG if background not found
                    if (move_uploaded_file($temp_file_path, $target_file)) {
                        if ($profile_picture && $profile_picture !== 'img/default_avatar.png' && $profile_picture !== 'img/photo_profile.png' && file_exists($profile_picture)) {
                            unlink($profile_picture);
                        }
                        $profile_picture = $target_file;
                    } else {
                        $response['message'] = 'Failed to upload profile picture.';
                    }
                }
            } else {
                // If not a transparent PNG, just move the uploaded file
                if (move_uploaded_file($temp_file_path, $target_file)) {
                    // Delete old profile picture if it's not the default one
                    if ($profile_picture && $profile_picture !== 'img/default_avatar.png' && $profile_picture !== 'img/photo_profile.png' && file_exists($profile_picture)) {
                        unlink($profile_picture);
                    }
                    $profile_picture = $target_file;
                } else {
                    $response['message'] = 'Failed to upload profile picture.';
                }
            }
        }
    }

    if (empty($response['message'])) { // Only update if no file upload error
        $stmt = $conn->prepare("UPDATE users SET full_name = ?, profile_picture = ?, phone_number = ?, date_of_birth = ? WHERE id = ?");
        $stmt->bind_param("ssssi", $full_name, $profile_picture, $phone_number, $date_of_birth, $user_id);
        if ($stmt->execute()) {
            $response['success'] = true;
            $response['message'] = 'Profile updated successfully.';
            logActivity($conn, $user_id, 'update_profile', 'Updated profile information.');
        } else {
            $response['message'] = 'Failed to update profile.';
        }
        $stmt->close();
    }
    echo json_encode($response);
    exit(); // Important: exit after AJAX response
}

// Handle Delete Profile Picture
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_profile_picture'])) {
    $response = ['success' => false, 'message' => ''];

    // Re-fetch user data to get current profile picture path
    $stmt_user = $conn->prepare("SELECT profile_picture FROM users WHERE id = ?");
    $stmt_user->bind_param("i", $user_id);
    $stmt_user->execute();
    $current_user_data = $stmt_user->get_result()->fetch_assoc();
    $stmt_user->close();
    $profile_picture = $current_user_data['profile_picture'];

    // Check if there's a picture to delete and it's not the default blank/avatar
    if ($profile_picture && $profile_picture !== 'img/photo_profile.png' && $profile_picture !== 'img/photo_profile.png' && file_exists($profile_picture)) {
        if (unlink($profile_picture)) {
            // Update database to set profile_picture to default blank image
            $new_profile_picture_path = 'img/photo_profile.png';
            $stmt = $conn->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
            $stmt->bind_param("si", $new_profile_picture_path, $user_id);
            if ($stmt->execute()) {
                $response['success'] = true;
                $response['message'] = 'Profile picture deleted successfully.';
                logActivity($conn, $user_id, 'delete_profile_picture', 'Deleted profile picture.');
            } else {
                $response['message'] = 'Failed to update profile picture in database.';
            }
            $stmt->close();
        } else {
            $response['message'] = 'Failed to delete profile picture file.';
        }
    } else {
        $response['message'] = 'No custom profile picture to delete.';
    }
    echo json_encode($response);
    exit();
}


// --- NEW LOGIC FOR MANAGING ADDITIONAL EMAILS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_email'])) {
    $new_email = trim($_POST['new_email']);
    $response = ['success' => false, 'message' => ''];

    if (empty($new_email)) {
        $response['message'] = 'Email cannot be empty.';
    } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        $response['message'] = 'Invalid email format.';
    } else {
        // Re-fetch user's primary email for comparison
        $stmt_primary_email = $conn->prepare("SELECT email FROM users WHERE id = ?");
        $stmt_primary_email->bind_param("i", $user_id);
        $stmt_primary_email->execute();
        $primary_email = $stmt_primary_email->get_result()->fetch_assoc()['email'];
        $stmt_primary_email->close();

        // Check if email is already the primary email for this user
        if ($new_email === $primary_email) {
            $response['message'] = 'This email is already your primary email.';
        } else {
            // Check if email already exists in user_emails table for THIS user
            $stmt_check_for_user = $conn->prepare("SELECT id FROM user_emails WHERE user_id = ? AND email = ?");
            $stmt_check_for_user->bind_param("is", $user_id, $new_email); // Corrected bind_param
            $stmt_check_for_user->execute();
            $result_check_for_user = $stmt_check_for_user->get_result();
            if ($result_check_for_user->num_rows > 0) {
                $response['message'] = 'This email is already registered as your additional email.';
            } else {
                // Check if email is already used as a primary email by ANY user
                $stmt_check_main_email_exists = $conn->prepare("SELECT id FROM users WHERE email = ?");
                $stmt_check_main_email_exists->bind_param("s", $new_email);
                $stmt_check_main_email_exists->execute();
                $result_check_main_email_exists = $stmt_check_main_email_exists->get_result();
                if ($result_check_main_email_exists->num_rows > 0) {
                    $response['message'] = 'This email is already used as a primary email by another account.';
                } else {
                    // Check if email is already used as an additional email by ANY OTHER user
                    $stmt_check_additional_email_exists = $conn->prepare("SELECT id FROM user_emails WHERE email = ? AND user_id != ?");
                    $stmt_check_additional_email_exists->bind_param("si", $new_email, $user_id);
                    $stmt_check_additional_email_exists->execute();
                    $result_check_additional_email_exists = $stmt_check_additional_email_exists->get_result();
                    if ($result_check_additional_email_exists->num_rows > 0) {
                        $response['message'] = 'This email is already used as an additional email by another account.';
                    } else {
                        // Insert new email into database
                        $stmt_insert = $conn->prepare("INSERT INTO user_emails (user_id, email) VALUES (?, ?)");
                        $stmt_insert->bind_param("is", $user_id, $new_email);
                        if ($stmt_insert->execute()) {
                            $response['success'] = true;
                            $response['message'] = 'Email "' . htmlspecialchars($new_email) . '" added successfully!';
                            logActivity($conn, $user_id, 'add_email', 'Added additional email: ' . htmlspecialchars($new_email));
                        } else {
                            $response['message'] = 'Failed to add email: ' . $conn->error;
                        }
                        $stmt_insert->close();
                    }
                    $stmt_check_additional_email_exists->close();
                }
                $stmt_check_main_email_exists->close();
            }
            $stmt_check_for_user->close();
        }
    }
    echo json_encode($response);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_additional_email'])) {
    $email_id_to_delete = (int)$_POST['email_id_to_to_delete'];
    $response = ['success' => false, 'message' => ''];

    $stmt_delete = $conn->prepare("DELETE FROM user_emails WHERE id = ? AND user_id = ?");
    $stmt_delete->bind_param("ii", $email_id_to_delete, $user_id);
    if ($stmt_delete->execute()) {
        if ($stmt_delete->affected_rows > 0) {
            $response['success'] = true;
            $response['message'] = 'Additional email deleted successfully.';
            logActivity($conn, $user_id, 'delete_email', 'Deleted additional email with ID: ' . $email_id_to_delete);
        } else {
            $response['message'] = 'Email not found or not authorized to delete.';
        }
    } else {
        $response['message'] = 'Failed to delete email: ' . $conn->error;
    }
    $stmt_delete->close();
    echo json_encode($response);
    exit();
}

// Initial data load for the first page render
$initial_data = getProfileData($conn, $user_id);
if (isset($initial_data['error'])) {
    // Handle critical error during initial data fetch, e.g., redirect to login
    header("Location: login.php");
    exit();
}

// Extract data for HTML rendering
$user = $initial_data['user'];
$totalStorageGB = $initial_data['totalStorageGB'];
$totalStorageBytes = $initial_data['totalStorageBytes']; // Get totalStorageBytes
$usedStorageBytes = $initial_data['usedStorageBytes'];
$usedPercentage = $initial_data['usedPercentage'];
$isStorageFull = $initial_data['isStorageFull']; // Get isStorageFull
$activity_logs = $initial_data['activity_logs'];
$chart_labels = $initial_data['chart_labels'];
$chart_data = $initial_data['chart_data'];
$additional_emails = $initial_data['additional_emails'];
$total_files = $initial_data['total_files'];
$current_account_status = $initial_data['current_account_status'];

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Profile - SKMI Cloud Storage</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="css/profile.css"> <!-- Tautan ke file CSS eksternal -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            <?php if ($currentUserRole === 'admin' || $currentUserRole === 'user' || $currentUserRole === 'member'): ?>
                <li><a href="index.php"><i class="fas fa-folder"></i> My Drive</a></li>
                <li><a href="priority_files.php"><i class="fas fa-star"></i> Priority File</a></li>
                <li><a href="recycle_bin.php"><i class="fas fa-trash"></i> Recycle Bin</a></li>
            <?php endif; ?>
            <li><a href="summary.php"><i class="fas fa-chart-line"></i> Summary</a></li>
            <li><a href="members.php"><i class="fas fa-users"></i> Members</a></li>
            <li><a href="profile.php" class="active"><i class="fas fa-user"></i> Profile</a></li>
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
        <div class="storage-info">
            <h4>Storage</h4>
            <div class="progress-bar-container">
                <div class="progress-bar" id="sidebarProgressBar" style="width: <?php echo round($usedPercentage, 2); ?>%;">
                    <span class="progress-bar-text" id="sidebarProgressBarText"><?php echo round($usedPercentage, 2); ?>%</span>
                </div>
            </div>
            <p class="storage-text" id="sidebarStorageText"><?php echo formatBytes($usedStorageBytes); ?> of <?php echo formatBytes($totalStorageBytes); ?> used</p>
            <?php if ($isStorageFull): ?>
                <p class="storage-text" id="sidebarStorageFullMessage" style="color: var(--error-color); font-weight: bold;">Storage Full!</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="main-content" id="mainContent">
        <div class="dashboard-header">
            <button class="sidebar-toggle-btn" id="sidebarToggleBtn"><i class="fas fa-bars"></i></button>
            <h1 class="profile-title" data-lang-key="profileDashboardTitle">My Profile Dashboard</h1>
            <div class="user-info">
                <span id="userInfoGreeting" data-lang-key="helloUserGreeting">Hello <?php echo htmlspecialchars($user['full_name'] ?? $user['username']); ?></span>
                <img id="userInfoAvatar" src="<?php echo htmlspecialchars($user['profile_picture'] ?? 'img/photo_profile.png'); ?>" alt="User Avatar">
            </div>
        </div>

        <div class="dashboard-grid">
            <!-- My Profile Card -->
            <div class="card profile-card">
                <div class="profile-header-bg"></div>
                <div class="profile-picture-container">
                    <img id="profileCardPicture" src="<?php echo htmlspecialchars($user['profile_picture'] ?? 'img/photo_profile.png'); ?>" alt="Profile Picture">
                    <div class="edit-profile-picture-overlay" id="editProfilePictureOverlay">
                        <i class="fas fa-camera"></i>
                    </div>
                    <input type="file" id="profilePictureInput" name="profile_picture_upload" accept="image/*" style="display: none;">
                </div>
                <h3 id="profileCardFullName"><?php echo htmlspecialchars($user['full_name'] ?? 'Full Name'); ?></h3>
                <p data-lang-key="welcomeMessage">Welcome to SKMI Cloud Storage</p>

                <div class="profile-info-grid">
                    <div class="profile-info-item">
                        <strong data-lang-key="usernameLabel">Username</strong>
                        <span id="profileInfoUsername"><?php echo htmlspecialchars($user['username'] ?? 'username'); ?></span>
                    </div>
                    <div class="profile-info-item">
                        <strong data-lang-key="emailLabel">Email</strong>
                        <span id="profileInfoEmail"><?php echo htmlspecialchars($user['email'] ?? 'email@example.com'); ?></span>
                    </div>
                    <div class="profile-info-item">
                        <strong data-lang-key="phoneNumberLabel">Phone Number</strong>
                        <span id="profileInfoPhoneNumber"><?php echo htmlspecialchars($user['phone_number'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="profile-info-item">
                        <strong data-lang-key="dateOfBirthLabel">Date of Birth</strong>
                        <span id="profileInfoDateOfBirth"><?php echo htmlspecialchars($user['date_of_birth'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="profile-info-item">
                        <strong data-lang-key="joinDateLabel">Join Date</strong>
                        <span id="profileInfoJoinDate"><?php echo date('d M Y', strtotime($user['created_at'])); ?></span>
                    </div>
                    <div class="profile-info-item">
                        <strong data-lang-key="accountStatusLabel">Account Status</strong>
                        <span id="profileInfoAccountStatus"><?php echo htmlspecialchars($current_account_status); ?></span>
                    </div>
                </div>

                <div class="profile-stats">
                    <div class="profile-stats-item">
                        <strong id="profileStatsTotalFiles"><?php echo $total_files; ?></strong>
                        <span data-lang-key="totalFilesLabel">Total Files</span>
                    </div>
                    <div class="profile-stats-item">
                        <strong id="profileStatsStorageUsed"><?php echo formatBytes($usedStorageBytes); ?></strong>
                        <span data-lang-key="storageUsedLabel">Storage Used</span>
                    </div>
                    <div class="profile-stats-item">
                        <strong id="profileStatsTotalQuota"><?php echo formatBytes($totalStorageBytes); ?></strong>
                        <span data-lang-key="totalQuotaLabel">Total Quota</span>
                    </div>
                </div>

                <!-- Real-time clock section -->
                <div class="real-time-clock">
                    <div class="clock" id="profileClock">00:00:00 A.M.</div>
                    <div class="date" id="profileDate">Loading date...</div>
                </div>

                <div class="profile-actions-buttons">
                    <button class="profile-button" id="editProfileBtn" data-lang-key="editProfileButton"><i class="fas fa-edit"></i> Edit Profile</button>
                    <button class="profile-button" id="changePasswordBtn" data-lang-key="changePasswordButton"><i class="fas fa-key"></i> Change Password</button>
                    <a href="logout.php" class="profile-button" data-lang-key="logoutButton">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>

            <div class="right-column">
                <!-- Translation Buttons -->
                <div class="card translation-buttons-card" style="margin-bottom: 20px;">
                    <div class="card-header">
                        <h2 data-lang-key="languageSelectionTitle">Language Selection</h2>
                    </div>
                    <div class="translation-buttons">
                        <button id="langIdBtn" class="active-lang">Bahasa Indonesia</button>
                        <button id="langEnBtn">English</button>
                    </div>
                </div>

                <!-- Activity History Card -->
                <div class="card activity-card">
                    <div class="card-header">
                        <h2 data-lang-key="activityHistoryTitle">Activity History</h2>
                    </div>
                    <div class="calendar-filter">
                        <div class="form-group">
                            <label for="startDate" data-lang-key="startDateLabel">Start Date:</label>
                            <input type="date" id="startDate">
                        </div>
                        <div class="form-group">
                            <label for="endDate" data-lang-key="endDateLabel">End Date:</label>
                            <input type="date" id="endDate">
                        </div>
                        <button onclick="filterActivityData()" data-lang-key="showDataButton">Show Data</button>
                    </div>
                    <div id="filterResult" style="margin-bottom: 15px;"></div>
                    <div class="chart-container">
                        <canvas id="activityLineChart"></canvas>
                    </div>
                    <!-- Detailed activity list -->
                    <div id="activityList">
                        <h3 data-lang-key="activityDetailsTitle">Activity Details:</h3>
                        <ul id="activityListUl">
                            <?php if (!empty($activity_logs)): ?>
                                <?php foreach ($activity_logs as $log): ?>
                                    <li>
                                        <strong data-lang-activity-type="<?php echo htmlspecialchars($log['activity_type']); ?>"><?php echo htmlspecialchars($log['activity_type']); ?>:</strong>
                                        <span data-lang-activity-desc="<?php echo htmlspecialchars($log['description']); ?>"><?php echo htmlspecialchars($log['description']); ?></span>
                                        <span style="float: right; color: var(--secondary-text-color); font-size: 0.9em;"><?php echo date('d M Y H:i', strtotime($log['timestamp'])); ?></span>
                                    </li>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <li data-lang-key="noActivityHistory">No activity history.</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>

                <!-- Add Email Card -->
                <div class="card add-email-card" style="margin-top: 20px;">
                    <div class="card-header">
                        <h2 data-lang-key="additionalEmailsTitle">Additional Emails</h2>
                    </div>
                    <form id="addEmailForm" class="email-input-group">
                        <input type="email" name="new_email" placeholder="Enter new email" required data-lang-placeholder="enterNewEmail">
                        <button type="submit" name="add_email" data-lang-key="addButton">Add</button>
                    </form>
                    <div class="email-list-container">
                        <ul id="additionalEmailsList" style="list-style: none; padding: 0;">
                            <?php if (!empty($additional_emails)): ?>
                                <?php foreach ($additional_emails as $email_item): ?>
                                    <li class="email-item">
                                        <span><?php echo htmlspecialchars($email_item['email']); ?></span>
                                        <form class="delete-email-form" data-email-id="<?php echo $email_item['id']; ?>">
                                            <input type="hidden" name="email_id_to_delete" value="<?php echo $email_item['id']; ?>">
                                            <button type="submit" name="delete_additional_email" class="delete-email-btn" title="Delete this email" data-lang-title="deleteThisEmail">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </form>
                                    </li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            <!-- Display primary email as well -->
                            <li class="email-item" style="font-weight: bold; background-color: var(--background-color); border-radius: 0; padding: 8px;">
                                <span data-lang-key="primaryEmailLabel"><?php echo htmlspecialchars($user['email']); ?> (Primary Email)</span>
                                <i class="fas fa-check-circle" style="color: var(--success-color);"></i>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="customNotification" class="notification"></div>

    <!-- Change Password Modal -->
    <div id="changePasswordModal" class="modal">
        <div class="modal-content">
            <span class="close-button">&times;</span>
            <h2 data-lang-key="changePasswordModalTitle"><i class="fas fa-key"></i> Change Password</h2>
            <form id="changePasswordForm">
                <input type="hidden" name="change_password_ajax" value="1">
                <div class="form-group">
                    <label for="modal_old_password" data-lang-key="oldPasswordLabel">Old Password:</label>
                    <input type="password" id="modal_old_password" name="old_password" required>
                </div>
                <div class="form-group">
                    <label for="modal_new_password" data-lang-key="newPasswordLabel">New Password:</label>
                    <input type="password" id="modal_new_password" name="new_password" required>
                </div>
                <div class="form-group">
                    <label for="modal_confirm_new_password" data-lang-key="confirmNewPasswordLabel">Confirm New Password:</label>
                    <input type="password" id="modal_confirm_new_password" name="confirm_new_password" required>
                </div>
                <button type="submit" class="profile-button" data-lang-key="saveNewPasswordButton">Save New Password</button>
            </form>
        </div>
    </div>

    <!-- Edit Profile Modal -->
    <div id="editProfileModal" class="modal">
        <div class="modal-content">
            <span class="close-button">&times;</span>
            <h2 data-lang-key="editProfileModalTitle"><i class="fas fa-edit"></i> Edit Profile</h2>
            <form id="editProfileForm" enctype="multipart/form-data">
                <input type="hidden" name="edit_profile_submit" value="1">
                <div class="form-group">
                    <label for="edit_full_name" data-lang-key="fullNameLabel">Full Name:</label>
                    <input type="text" id="edit_full_name" name="full_name" value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label for="edit_phone_number" data-lang-key="phoneNumberLabel">Phone Number:</label>
                    <input type="text" id="edit_phone_number" name="phone_number" value="<?php echo htmlspecialchars($user['phone_number'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="edit_date_of_birth" data-lang-key="dateOfBirthLabel">Date of Birth:</label>
                    <input type="date" id="edit_date_of_birth" name="date_of_birth" value="<?php echo htmlspecialchars($user['date_of_birth'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="edit_profile_picture" data-lang-key="profilePictureLabel">Profile Picture:</label>
                    <input type="file" id="edit_profile_picture" name="profile_picture" accept="image/*">
                    <small data-lang-key="currentLabel">Current: <a id="currentProfilePicLink" href="<?php echo htmlspecialchars($user['profile_picture'] ?? 'img/photo_profile.png'); ?>" target="_blank"><?php echo basename($user['profile_picture'] ?? 'photo_profile.png'); ?></a></small>
                </div>
                <div class="button-group">
                    <button type="button" id="deleteProfilePictureBtn" class="profile-button delete-button" data-lang-key="deleteProfilePictureButton">Delete Profile Picture</button>
                    <button type="submit" class="profile-button" data-lang-key="saveChangesButton">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Overlay for mobile sidebar -->
    <div class="overlay" id="mobileOverlay"></div>

    <script>
        // Data PHP yang akan digunakan oleh JavaScript
        const initialChartLabels = <?php echo json_encode($chart_labels); ?>;
        const initialChartData = <?php echo json_encode($chart_data); ?>;
        const initialActivityLogs = <?php echo json_encode($activity_logs); ?>;
        const initialNotificationMessage = '<?php echo $notification_message; ?>';
        const initialNotificationType = '<?php echo $notification_type; ?>';
    </script>
    <script src="js/profile.js"></script> <!-- Tautan ke file JavaScript eksternal -->
</body>
</html>
