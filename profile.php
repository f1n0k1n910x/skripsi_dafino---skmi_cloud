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
    $email_id_to_delete = (int)$_POST['email_id_to_delete'];
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
            visibility: hidden; /* Hide body initially to prevent FOUC/white blink */
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
        .dashboard-header { /* Renamed from .header-main for profile.php */
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

        .dashboard-header h1 { /* Renamed from .header-main h1 */
            margin: 0;
            color: var(--adminlte-header-text);
            font-size: 2em; /* Slightly smaller title */
            font-weight: 400; /* Lighter font weight */
        }

        .dashboard-header .user-info {
            display: flex;
            align-items: center;
        }

        .dashboard-header .user-info span {
            margin-right: 15px;
            font-size: 1.1em;
            color: var(--text-color);
        }

        .dashboard-header .user-info img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--divider-color);
            box-shadow: none;
        }

        /* Dashboard Grid Layout */
        .dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr; /* Adjust as needed for responsiveness */
            gap: 20px; /* Reduced gap */
        }

        .card {
            background-color: var(--surface-color);
            border-radius: 0; /* Siku-siku */
            box-shadow: none; /* No box-shadow */
            padding: 20px; /* Reduced padding */
            overflow: hidden; /* For image in profile card */
            transition: transform 0.2s ease-out;
            border: 1px solid var(--divider-color); /* Subtle border for cards */
        }
        .card:hover {
            transform: translateY(0); /* No lift */
        }

        /* My Profile Card */
        .profile-card {
            display: flex;
            flex-direction: column;
            align-items: center; /* Center content horizontally */
            text-align: center;
            position: relative; /* For the background image */
            padding-top: 0; /* Remove initial padding to allow bg to go to top */
        }

        .profile-header-bg {
            width: 100%;
            height: 120px; /* Height for the background image */
            background: linear-gradient(to right, var(--primary-color), var(--success-color)); /* Material-inspired gradient */
            position: absolute;
            top: 0;
            left: 0;
            border-top-left-radius: 0; /* Siku-siku */
            border-top-right-radius: 0; /* Siku-siku */
        }

        .profile-card .profile-picture-container {
            position: relative;
            margin-top: 40px; /* Push image down to show background */
            z-index: 1; /* Ensure image is above background */
            cursor: pointer; /* Indicate it's clickable */
        }

        .profile-card img {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #fff; /* White border around profile picture */
            box-shadow: none; /* No box-shadow */
        }

        .profile-card .edit-profile-picture-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border-radius: 50%;
            background-color: rgba(0, 0, 0, 0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .profile-card .profile-picture-container:hover .edit-profile-picture-overlay {
            opacity: 1;
        }

        .profile-card .edit-profile-picture-overlay i {
            color: white;
            font-size: 1.8em;
        }

        .profile-card h3 {
            margin-top: 15px;
            margin-bottom: 5px;
            font-size: 1.6em;
            color: var(--text-color);
        }

        .profile-card p {
            font-size: 0.9em;
            color: var(--secondary-text-color);
            margin-bottom: 15px;
        }

        .profile-info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr; /* Two columns for info */
            gap: 8px 15px; /* Reduced gap */
            width: 100%;
            padding: 15px;
            box-sizing: border-box;
            text-align: left; /* Align text within grid items */
        }

        .profile-info-item strong {
            display: block;
            font-size: 0.85em;
            color: var(--secondary-text-color);
            margin-bottom: 2px;
        }

        .profile-info-item span {
            font-size: 1em;
            color: var(--text-color);
        }

        .profile-info-item.full-width {
            grid-column: span 2; /* Make this item span both columns */
        }

        .profile-stats {
            display: flex;
            justify-content: space-around;
            width: 100%;
            padding: 15px;
            border-top: 1px solid var(--divider-color);
            margin-top: 15px;
            box-sizing: border-box;
        }

        .profile-stats-item {
            text-align: center;
        }

        .profile-stats-item strong {
            display: block;
            font-size: 1.3em;
            color: var(--text-color);
            margin-bottom: 3px;
        }

        .profile-stats-item span {
            font-size: 0.8em;
            color: var(--secondary-text-color);
        }

        /* Updated Profile Actions Buttons with Material Design */
        .profile-actions-buttons {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 6px; /* Smaller gap between buttons */
            padding: 10px; /* Smaller padding */
            border-top: 1px solid var(--divider-color);
            margin-top: 15px;
        }

        .profile-actions-buttons .profile-button {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 7px 12px; /* Smaller padding */
            border-radius: 0; /* Siku-siku */
            cursor: pointer;
            font-size: 0.85em; /* Smaller font size */
            transition: background-color 0.2s ease-out;
            margin: 0;
            min-width: 100px; /* Consistent minimum width */
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            box-shadow: none; /* No box-shadow */
        }

        .profile-actions-buttons .profile-button i {
            font-size: 0.85em;
        }

        /* Different colors for each button */
        .profile-actions-buttons .profile-button:nth-child(1) {
            background-color: var(--success-color); /* Green for Edit Profile */
        }
        .profile-actions-buttons .profile-button:nth-child(2) {
            background-color: #2196F3; /* Blue for Change Password */
        }
        .profile-actions-buttons .profile-button:nth-child(3) {
            background-color: #9C27B0; /* Purple for Logout */
        }

        /* Hover effects */
        .profile-actions-buttons .profile-button:hover {
            background-color: var(--primary-dark-color);
        }
        .profile-actions-buttons .profile-button:nth-child(1):hover {
            background-color: #388E3C; /* Darker green */
        }
        .profile-actions-buttons .profile-button:nth-child(2):hover {
            background-color: #1976D2; /* Darker blue */
        }
        .profile-actions-buttons .profile-button:nth-child(3):hover {
            background-color: #7B1FA2; /* Darker purple */
        }

        /* Real-time clock styles */
        .real-time-clock {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-top: 15px;
            padding: 10px;
            background-color: var(--background-color);
            border-radius: 0; /* Siku-siku */
            border: 1px solid var(--divider-color);
        }

        .real-time-clock .clock {
            font-size: 1.1em;
            font-weight: bold;
            color: var(--text-color);
        }

        .real-time-clock .date {
            font-size: 0.85em;
            margin-top: 5px;
            color: var(--secondary-text-color);
        }

        /* Activity History Card */
        .activity-card .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            border-bottom: 1px solid var(--divider-color);
            padding-bottom: 10px;
        }

        .activity-card .card-header h2 {
            font-size: 1.6em;
            margin: 0;
            color: var(--text-color);
            font-weight: 400;
        }

        .activity-card .chart-container {
            position: relative;
            height: 200px; /* Set a fixed height for the chart */
            width: 100%;
            margin-bottom: 15px;
        }

        /* Calendar Filter */
        .calendar-filter {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 15px;
            align-items: flex-end;
        }

        .calendar-filter .form-group {
            margin-bottom: 0;
            flex: 1;
            min-width: 120px;
        }

        .calendar-filter label {
            font-weight: 500;
            color: var(--text-color);
            margin-bottom: 6px;
            display: block;
            font-size: 0.9em;
        }

        .calendar-filter input[type="date"] {
            width: calc(100% - 16px);
            padding: 8px;
            border: 1px solid var(--divider-color);
            border-radius: 0; /* Siku-siku */
            font-size: 0.9em;
            box-sizing: border-box;
            background-color: var(--background-color);
            transition: border-color 0.2s ease-out;
        }
        .calendar-filter input[type="date"]:focus {
            border-color: var(--primary-color);
            outline: none;
            background-color: var(--surface-color);
        }

        .calendar-filter button {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 0; /* Siku-siku */
            cursor: pointer;
            font-size: 0.9em;
            transition: background-color 0.2s ease-out;
            box-shadow: none;
        }

        .calendar-filter button:hover {
            background-color: var(--primary-dark-color);
        }

        #filterResult {
            margin-top: 8px;
            font-weight: 500;
            color: var(--text-color);
            font-size: 0.85em;
        }

        /* Activity Details Sticky and Full Background */
        #activityList {
            position: relative; /* Changed from sticky to relative for simpler layout */
            width: 100%;
            background-color: var(--surface-color);
            border-top: 1px solid var(--divider-color);
            padding-top: 10px;
            padding-bottom: 10px;
            z-index: 1;
            margin-top: 15px;
            border-radius: 0; /* Siku-siku */
            box-sizing: border-box;
        }

        #activityList h3 {
            margin-top: 0;
            margin-bottom: 8px;
            padding-left: 5px;
            color: var(--text-color);
            font-size: 1.1em;
            font-weight: 500;
        }

        #activityList ul {
            list-style: none;
            padding: 0 5px;
            margin: 0;
            max-height: 120px; /* Keep max height for scrollbar */
            overflow-y: auto; /* Enable vertical scrollbar */
        }

        #activityList ul li {
            margin-bottom: 6px;
            padding: 4px;
            background-color: var(--background-color);
            border-radius: 0; /* Siku-siku */
            font-size: 0.85em;
            color: var(--text-color);
        }


        /* Add Email Card */
        .add-email-card .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            border-bottom: 1px solid var(--divider-color);
            padding-bottom: 10px;
        }

        .add-email-card .card-header h2 {
            font-size: 1.6em;
            margin: 0;
            color: var(--text-color);
            font-weight: 400;
        }

        .add-email-card .email-input-group {
            display: flex;
            gap: 8px;
            margin-bottom: 15px;
        }

        .add-email-card .email-input-group input[type="email"] {
            flex-grow: 1;
            width: calc(100% - 16px);
            padding: 8px;
            border: 1px solid var(--divider-color);
            border-radius: 0; /* Siku-siku */
            font-size: 0.9em;
            background-color: var(--background-color);
            transition: border-color 0.2s ease-out;
        }
        .add-email-card .email-input-group input[type="email"]:focus {
            border-color: var(--primary-color);
            outline: none;
            background-color: var(--surface-color);
        }

        .add-email-card .email-input-group button {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 0; /* Siku-siku */
            cursor: pointer;
            font-size: 0.9em;
            transition: background-color 0.2s ease-out;
            box-shadow: none;
        }

        .add-email-card .email-input-group button:hover {
            background-color: var(--primary-dark-color);
        }

        .add-email-card .email-list-container {
            max-height: 150px; /* Max height for scrollbar */
            overflow-y: auto; /* Enable vertical scrollbar */
            border: 1px solid var(--divider-color);
            border-radius: 0; /* Siku-siku */
            padding: 8px;
            background-color: var(--background-color);
        }

        .add-email-card .email-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid var(--divider-color);
            font-size: 0.9em;
            color: var(--text-color);
        }

        .add-email-card .email-item:last-child {
            border-bottom: none;
        }

        .add-email-card .email-item .delete-email-btn {
            background: none;
            border: none;
            color: var(--error-color);
            cursor: pointer;
            font-size: 1em;
            transition: color 0.2s ease-out;
        }

        .add-email-card .email-item .delete-email-btn:hover {
            color: #D32F2F;
        }

        /* Notification Styles */
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 12px 20px;
            border-radius: 0; /* Siku-siku */
            color: white;
            font-weight: 500;
            z-index: 1001;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
            opacity: 0;
            transform: translateY(-20px);
            transition: opacity 0.3s ease-out, transform 0.3s ease-out;
        }

        .notification.show {
            display: block;
            opacity: 1;
            transform: translateY(0);
        }

        .notification.success {
            background-color: var(--success-color);
        }

        .notification.error {
            background-color: var(--error-color);
        }

        /* Additional sections for Password Change and Delete Account */
        .profile-section {
            background-color: var(--surface-color);
            border-radius: 0; /* Siku-siku */
            box-shadow: none; /* No box-shadow */
            padding: 20px;
            margin-top: 20px; /* Spacing between cards */
            transition: transform 0.2s ease-out;
            border: 1px solid var(--divider-color); /* Subtle border for cards */
        }
        .profile-section:hover {
            transform: translateY(0); /* No lift */
        }

        .profile-section h2 {
            font-size: 1.6em;
            color: var(--text-color);
            margin-top: 0;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            font-weight: 400;
            border-bottom: 1px solid var(--divider-color);
            padding-bottom: 10px;
        }

        .profile-section h2 i {
            margin-right: 10px;
            color: var(--primary-color);
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--text-color);
            font-size: 0.95em;
        }

        .form-group input[type="text"],
        .form-group input[type="email"],
        .form-group input[type="password"],
        .form-group input[type="file"],
        .form-group input[type="date"] {
            width: calc(100% - 16px);
            padding: 8px;
            border: 1px solid var(--divider-color);
            border-radius: 0; /* Siku-siku */
            font-size: 0.9em;
            background-color: var(--background-color);
            transition: border-color 0.2s ease-out;
        }
        .form-group input[type="text"]:focus,
        .form-group input[type="email"]:focus,
        .form-group input[type="password"]:focus,
        .form-group input[type="date"]:focus {
            border-color: var(--primary-color);
            outline: none;
            background-color: var(--surface-color);
        }

        .form-group input[type="file"] {
            border: none;
            padding-left: 0;
            background-color: transparent;
        }

        .profile-button {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 0; /* Siku-siku */
            cursor: pointer;
            font-size: 0.9em;
            transition: background-color 0.2s ease-out;
            box-shadow: none;
            margin-top: 10px;
        }

        .profile-button:hover {
            background-color: var(--primary-dark-color);
        }

        .delete-button {
            background-color: var(--error-color);
        }

        .delete-button:hover {
            background-color: #D32F2F;
        }

        .button-group {
            display: flex;
            justify-content: flex-end; /* Align buttons to the right */
            gap: 8px;
            margin-top: 15px;
        }

        /* New styles for aligning Change Password and Account Actions */
        .profile-actions-grid {
            display: grid;
            grid-template-columns: 1fr 1fr; /* Two columns, equal width */
            gap: 20px;
            margin-top: 20px;
        }

        @media (max-width: 768px) {
            .profile-actions-grid {
                grid-template-columns: 1fr; /* Stack on smaller screens */
            }
        }

        /* Modal Styles (Copied from index.php and adapted for profile.php) */
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
        .modal input[type="email"],
        .modal input[type="password"],
        .modal input[type="file"],
        .modal input[type="date"] {
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
        .modal input[type="email"]:focus,
        .modal input[type="password"]:focus,
        .modal input[type="date"]:focus {
            border-color: var(--primary-color);
            box-shadow: none; /* No box-shadow */
            outline: none;
            background-color: var(--surface-color);
        }
        .modal input[type="file"] {
            border: 1px solid var(--divider-color); /* Keep border for file input */
            padding: 10px;
            background-color: transparent;
        }

        .modal button {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 10px 20px; /* Reduced padding */
            border-radius: 0; /* Siku-siku */
            cursor: pointer;
            font-size: 1em;
            transition: background-color 0.2s ease-out;
            box-shadow: none;
        }

        .modal button:hover {
            background-color: var(--primary-dark-color);
        }

        /* Animations */
        @keyframes fadeInScale {
            from {
                opacity: 0;
                transform: scale(0.95);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }

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

        /* General button focus effects */
        button {
            outline: none;
        }
        button:focus {
            box-shadow: 0 0 0 2px rgba(63,81,181,0.5); /* Material Design focus ring */
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
        .dashboard-header .profile-title {
            display: block;
        }
        .dashboard-header .user-info {
            display: flex;
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

        /* Tablet Landscape */
        @media (min-width: 768px) and (max-width: 1024px) {
            body.tablet-landscape .sidebar {
                width: 200px; /* Narrower sidebar */
            }
            body.tablet-landscape .sidebar-header img {
                width: 100px;
            }
            body.tablet-landscape .main-content {
                padding: 15px;
            }
            body.tablet-landscape .dashboard-header {
                padding: 10px 15px;
                margin: -15px -15px 15px -15px;
            }
            body.tablet-landscape .dashboard-header h1 {
                font-size: 1.8em;
            }
            body.tablet-landscape .dashboard-grid {
                grid-template-columns: 1fr; /* Stack columns vertically */
                gap: 15px;
            }
            body.tablet-landscape .card {
                padding: 15px;
            }
            body.tablet-landscape .profile-card h3 {
                font-size: 1.4em;
            }
            body.tablet-landscape .profile-info-grid {
                grid-template-columns: 1fr 1fr;
                padding: 10px;
            }
            body.tablet-landscape .profile-stats {
                padding: 10px;
            }
            body.tablet-landscape .profile-stats-item strong {
                font-size: 1.1em;
            }
            body.tablet-landscape .profile-actions-buttons .profile-button {
                padding: 6px 10px;
                font-size: 0.8em;
                min-width: 90px;
            }
            body.tablet-landscape .activity-card .card-header h2,
            body.tablet-landscape .add-email-card .card-header h2 {
                font-size: 1.4em;
            }
            body.tablet-landscape .calendar-filter input[type="date"],
            body.tablet-landscape .add-email-card .email-input-group input[type="email"] {
                padding: 7px;
                font-size: 0.85em;
            }
            body.tablet-landscape .calendar-filter button,
            body.tablet-landscape .add-email-card .email-input-group button {
                padding: 7px 12px;
                font-size: 0.85em;
            }
            body.tablet-landscape .activity-card .chart-container {
                height: 180px;
            }
            body.tablet-landscape .activity-card #activityList {
                max-height: 120px;
            }
            body.tablet-landscape .add-email-card .email-list-container {
                max-height: 120px;
            }
            body.tablet-landscape .modal-content {
                max-width: 500px;
                padding: 25px;
            }
            body.tablet-landscape .sidebar-menu a {
                font-size: var(--sidebar-font-size-tablet-landscape);
            }
        }

        /* Tablet Portrait */
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
            body.tablet-portrait .dashboard-header {
                justify-content: flex-start; /* Align items to start */
                padding: 10px 15px;
                margin: -15px -15px 15px -15px;
            }
            body.tablet-portrait .dashboard-header h1 {
                font-size: 1.6em;
                flex-grow: 1;
                text-align: center;
                margin-left: -30px; /* Counteract toggle button space */
            }
            body.tablet-portrait .dashboard-header .profile-title {
                display: none;
            }
            body.tablet-portrait .dashboard-header .user-info {
                display: none;
            }
            body.tablet-portrait .main-content {
                padding: 15px;
            }
            body.tablet-portrait .dashboard-grid {
                grid-template-columns: 1fr; /* Force vertical stacking */
                gap: 15px;
            }
            body.tablet-portrait .card {
                padding: 15px;
            }
            body.tablet-portrait .profile-card h3 {
                font-size: 1.4em;
            }
            body.tablet-portrait .profile-info-grid {
                grid-template-columns: 1fr 1fr;
                padding: 10px;
            }
            body.tablet-portrait .profile-stats {
                padding: 10px;
            }
            body.tablet-portrait .profile-stats-item strong {
                font-size: 1.1em;
            }
            body.tablet-portrait .profile-actions-buttons .profile-button {
                padding: 6px 10px;
                font-size: 0.8em;
                min-width: 90px;
            }
            body.tablet-portrait .activity-card .card-header h2,
            body.tablet-portrait .add-email-card .card-header h2 {
                font-size: 1.4em;
            }
            body.tablet-portrait .calendar-filter {
                flex-direction: column;
                gap: 10px;
            }
            body.tablet-portrait .calendar-filter .form-group {
                min-width: unset;
                width: 100%;
            }
            body.tablet-portrait .calendar-filter input[type="date"],
            body.tablet-portrait .add-email-card .email-input-group input[type="email"] {
                padding: 7px;
                font-size: 0.85em;
            }
            body.tablet-portrait .calendar-filter button,
            body.tablet-portrait .add-email-card .email-input-group button {
                padding: 7px 12px;
                font-size: 0.85em;
            }
            body.tablet-portrait .activity-card .chart-container {
                height: 180px;
            }
            body.tablet-portrait .activity-card #activityList {
                max-height: 120px;
            }
            body.tablet-portrait .add-email-card .email-list-container {
                max-height: 120px;
            }
            body.tablet-portrait .modal-content {
                max-width: 500px;
                padding: 25px;
            }
            body.tablet-portrait .sidebar-menu a {
                font-size: var(--sidebar-font-size-tablet-portrait);
            }
        }

        /* Mobile */
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
            body.mobile .dashboard-header {
                justify-content: flex-start;
                padding: 10px 10px;
                margin: -15px -15px 15px -15px;
            }
            body.mobile .dashboard-header h1 {
                font-size: 1.5em;
                flex-grow: 1;
                text-align: center;
                margin-left: -25px; /* Counteract toggle button space */
            }
            body.mobile .dashboard-header .profile-title {
                display: none;
            }
            body.mobile .dashboard-header .user-info {
                display: none;
            }
            body.mobile .main-content {
                padding: 10px;
                overflow-x: hidden;
            }
            body.mobile .dashboard-grid {
                grid-template-columns: 1fr !important; /* Force vertical stacking */
                gap: 10px;
            }
            body.mobile .card {
                padding: 10px;
            }
            body.mobile .profile-card h3 {
                font-size: 1.2em;
            }
            body.mobile .profile-info-grid {
                grid-template-columns: 1fr; /* Single column on mobile */
                padding: 8px;
            }
            body.mobile .profile-info-item.full-width {
                grid-column: span 1; /* Single column on mobile */
            }
            body.mobile .profile-stats {
                padding: 8px;
            }
            body.mobile .profile-stats-item strong {
                font-size: 1em;
            }
            body.mobile .profile-actions-buttons {
                flex-direction: column;
                gap: 5px;
            }
            body.mobile .profile-actions-buttons .profile-button {
                padding: 5px 8px;
                font-size: 0.75em;
                min-width: unset;
            }
            body.mobile .activity-card .card-header h2,
            body.mobile .add-email-card .card-header h2 {
                font-size: 1.2em;
            }
            body.mobile .calendar-filter {
                flex-direction: column;
                gap: 8px;
            }
            body.mobile .calendar-filter .form-group {
                min-width: unset;
                width: 100%;
            }
            body.mobile .calendar-filter input[type="date"],
            body.mobile .add-email-card .email-input-group input[type="email"] {
                padding: 6px;
                font-size: 0.8em;
            }
            body.mobile .calendar-filter button,
            body.mobile .add-email-card .email-input-group button {
                padding: 6px 10px;
                font-size: 0.8em;
            }
            body.mobile .add-email-card .email-input-group {
                flex-direction: column;
            }
            body.mobile .activity-card .chart-container {
                height: 150px;
            }
            body.mobile .activity-card #activityList {
                max-height: 100px;
            }
            body.mobile .add-email-card .email-list-container {
                max-height: 100px;
            }
            body.mobile .modal-content {
                width: 95%;
                padding: 20px;
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

        /* Styles for translation buttons */
        .translation-buttons {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-bottom: 20px;
            padding: 10px;
            border-bottom: 1px solid var(--divider-color);
        }

        .translation-buttons button {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 0;
            cursor: pointer;
            font-size: 0.9em;
            transition: background-color 0.2s ease-out;
            box-shadow: none;
        }

        .translation-buttons button:hover {
            background-color: var(--primary-dark-color);
        }

        .translation-buttons button.active-lang {
            background-color: var(--success-color);
            font-weight: bold;
        }
        .translation-buttons button.active-lang:hover {
            background-color: #388E3C;
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
                    <input disabled type="file" id="profilePictureInput" name="profile_picture_upload" accept="image/*" style="display: none;">
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
        // Global variable for Chart.js instance
        let activityChart;

        // Helper function to format bytes (replicate from PHP's formatBytes)
        function formatBytes(bytes, precision = 2) {
            const units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
            bytes = Math.max(bytes, 0);
            const pow = Math.floor((bytes ? Math.log(bytes) : 0) / Math.log(1024));
            const p = Math.min(pow, units.length - 1);
            bytes /= (1 << (10 * p));
            return bytes.toFixed(precision) + ' ' + units[p];
        }

        // Function to show custom notification
        function showNotification(message, type) {
            const customNotification = document.getElementById('customNotification');
            customNotification.textContent = message;
            customNotification.className = 'notification show ' + type;
            setTimeout(() => {
                customNotification.classList.remove('show');
            }, 3000);
        }

        // Function to initialize or update the chart
        function updateActivityChart(labels, data) {
            const ctx = document.getElementById('activityLineChart').getContext('2d');
            if (activityChart) {
                activityChart.data.labels = labels;
                activityChart.data.datasets[0].data = data;
                activityChart.update();
            } else {
                activityChart = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Number of Activities',
                            data: data,
                            borderColor: 'var(--primary-color)',
                            backgroundColor: 'rgba(63, 81, 181, 0.1)',
                            tension: 0.3,
                            fill: true,
                            pointBackgroundColor: 'var(--primary-color)',
                            pointBorderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: true,
                                labels: {
                                    color: 'var(--text-color)'
                                }
                            },
                            tooltip: {
                                mode: 'index',
                                intersect: false
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: 'Count',
                                    color: 'var(--text-color)'
                                },
                                ticks: {
                                    precision: 0,
                                    color: 'var(--secondary-text-color)'
                                },
                                grid: {
                                    color: 'var(--divider-color)'
                                }
                            },
                            x: {
                                title: {
                                    display: true,
                                    text: 'Date',
                                    color: 'var(--text-color)'
                                },
                                ticks: {
                                    color: 'var(--secondary-text-color)'
                                },
                                grid: {
                                    color: 'var(--divider-color)'
                                }
                            }
                        }
                    }
                });
            }
        }

        // Function to process log data into chart format
        function processLogsForChart(logs) {
            const dailyCounts = {};
            logs.forEach(log => {
                const date = new Date(log.timestamp).toISOString().split('T')[0]; // Format YYYY-MM-DD
                dailyCounts[date] = (dailyCounts[date] || 0) + 1;
            });

            const sortedDates = Object.keys(dailyCounts).sort();
            const labels = sortedDates;
            const data = sortedDates.map(date => dailyCounts[date]);

            return { labels, data };
        }

        // Function to update all UI elements with new data
        function updateProfileUI(data) {
            const user = data.user;

            // Update Dashboard Header
            document.getElementById('userInfoGreeting').textContent = `Hello ${user.full_name || user.username}`;
            document.getElementById('userInfoAvatar').src = user.profile_picture || 'img/photo_profile.png';

            // Update Sidebar Storage Info
            document.getElementById('sidebarProgressBar').style.width = `${data.usedPercentage.toFixed(2)}%`;
            document.getElementById('sidebarProgressBarText').textContent = `${data.usedPercentage.toFixed(2)}%`;
            document.getElementById('sidebarStorageText').textContent = `${formatBytes(data.usedStorageBytes)} of ${formatBytes(data.totalStorageBytes)} used`;
            
            const sidebarStorageFullMessage = document.getElementById('sidebarStorageFullMessage');
            if (data.isStorageFull) {
                if (!sidebarStorageFullMessage) {
                    const p = document.createElement('p');
                    p.id = 'sidebarStorageFullMessage';
                    p.className = 'storage-text';
                    p.style.color = 'var(--error-color)';
                    p.style.fontWeight = 'bold';
                    p.textContent = 'Storage Full!';
                    document.querySelector('.storage-info').appendChild(p);
                }
            } else {
                if (sidebarStorageFullMessage) {
                    sidebarStorageFullMessage.remove();
                }
            }

            // Update Profile Card
            document.getElementById('profileCardPicture').src = user.profile_picture || 'img/photo_profile.png';
            document.getElementById('profileCardFullName').textContent = user.full_name || 'Full Name';
            document.getElementById('profileInfoUsername').textContent = user.username;
            document.getElementById('profileInfoEmail').textContent = user.email;
            document.getElementById('profileInfoPhoneNumber').textContent = user.phone_number || 'N/A';
            document.getElementById('profileInfoDateOfBirth').textContent = user.date_of_birth || 'N/A';
            document.getElementById('profileInfoJoinDate').textContent = new Date(user.created_at).toLocaleDateString('en-US', { day: '2-digit', month: 'short', year: 'numeric' });
            document.getElementById('profileInfoAccountStatus').textContent = data.current_account_status;

            document.getElementById('profileStatsTotalFiles').textContent = data.total_files;
            document.getElementById('profileStatsStorageUsed').textContent = formatBytes(data.usedStorageBytes);
            document.getElementById('profileStatsTotalQuota').textContent = formatBytes(data.totalStorageBytes);

            // Update Activity Chart
            updateActivityChart(data.chart_labels, data.chart_data);

            // Update Detailed Activity List
            const activityListUl = document.getElementById('activityListUl');
            activityListUl.innerHTML = ''; // Clear existing list
            if (data.activity_logs.length > 0) {
                data.activity_logs.forEach(log => {
                    const li = document.createElement('li');
                    li.innerHTML = `
                        <strong data-lang-activity-type="${log.activity_type}">${log.activity_type}:</strong>
                        <span data-lang-activity-desc="${log.description}">${log.description}</span>
                        <span style="float: right; color: var(--secondary-text-color); font-size: 0.9em;">${new Date(log.timestamp).toLocaleDateString('en-US', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })}</span>
                    `;
                    activityListUl.appendChild(li);
                });
            } else {
                const li = document.createElement('li');
                li.setAttribute('data-lang-key', 'noActivityHistory');
                li.textContent = 'No activity history.';
                activityListUl.appendChild(li);
            }

            // Update Additional Emails List
            const additionalEmailsList = document.getElementById('additionalEmailsList');
            additionalEmailsList.innerHTML = ''; // Clear existing list
            if (data.additional_emails.length > 0) {
                data.additional_emails.forEach(email_item => {
                    const li = document.createElement('li');
                    li.className = 'email-item';
                    li.innerHTML = `
                        <span>${email_item.email}</span>
                        <form class="delete-email-form" data-email-id="${email_item.id}">
                            <input type="hidden" name="email_id_to_delete" value="${email_item.id}">
                            <button type="submit" name="delete_additional_email" class="delete-email-btn" title="Delete this email" data-lang-title="deleteThisEmail">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </form>
                    `;
                    additionalEmailsList.appendChild(li);
                });
            }
            // Always display primary email
            const primaryLi = document.createElement('li');
            primaryLi.className = 'email-item';
            primaryLi.style.fontWeight = 'bold';
            primaryLi.style.backgroundColor = 'var(--background-color)';
            primaryLi.style.borderRadius = '0'; // Siku-siku
            primaryLi.style.padding = '8px';
            primaryLi.innerHTML = `
                <span data-lang-key="primaryEmailLabel">${user.email} (Primary Email)</span>
                <i class="fas fa-check-circle" style="color: var(--success-color);"></i>
            `;
            additionalEmailsList.appendChild(primaryLi);

            // Update Edit Profile Modal fields
            document.getElementById('edit_full_name').value = user.full_name || '';
            document.getElementById('edit_phone_number').value = user.phone_number || '';
            document.getElementById('edit_date_of_birth').value = user.date_of_birth || '';
            document.getElementById('currentProfilePicLink').href = user.profile_picture || 'img/photo_profile.png';
            document.getElementById('currentProfilePicLink').textContent = (user.profile_picture ? user.profile_picture.split('/').pop() : 'photo_profile.png');

            // Re-apply translation after UI update
            applyTranslation(currentLanguage);
        }

        // Function to fetch profile data via AJAX
        async function fetchProfileData() {
            try {
                const response = await fetch('profile.php?action=get_profile_data');
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                const data = await response.json();
                if (data.error) {
                    // showNotification(data.message, 'error'); // Dihapus agar tidak mengganggu saat menerjemahkan
                    // Optionally redirect if user not found
                    // window.location.href = 'login.php';
                    return;
                }
                updateProfileUI(data);
                // Store all activity logs for client-side filtering
                window.allActivityLogs = data.activity_logs;
            } catch (error) {
                console.error("Could not fetch profile data:", error);
                // showNotification('Failed to load profile data.', 'error'); // Dihapus agar tidak mengganggu saat menerjemahkan
            }
        }

        // Function to update the real-time clock
        function updateClock() {
            const now = new Date();
            
            let hours = now.getHours();
            const minutes = now.getMinutes();
            const seconds = now.getSeconds();
            const ampm = hours >= 12 ? 'P.M.' : 'A.M.';
            
            hours = hours % 12;
            hours = hours ? hours : 12; // Convert 0 to 12
            
            const timeString = `${pad(hours)}:${pad(minutes)}:${pad(seconds)} ${ampm}`;
            document.getElementById("profileClock").innerText = timeString;
            
            const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
            const months = [
                'January', 'February', 'March', 'April', 'May', 'June',
                'July', 'August', 'September', 'October', 'November', 'December'
            ];
            
            const dayName = days[now.getDay()];
            const day = now.getDate();
            const month = months[now.getMonth()];
            const year = now.getFullYear();
            
            const dateString = `${dayName}, ${day} ${month} ${year}`;
            document.getElementById("profileDate").innerText = dateString;
        }
        
        function pad(n) {
            return n < 10 ? '0' + n : n;
        }

        // --- Responsive Class Handling ---
        function applyDeviceClass() {
            const width = window.innerWidth;
            const body = document.body;
            const sidebar = document.querySelector('.sidebar');
            const mobileOverlay = document.getElementById('mobileOverlay');
            const profileTitle = document.querySelector('.profile-title'); // Get the title element
            const userInfo = document.querySelector('.user-info'); // Get user-info element

            // Remove all previous device classes
            body.classList.remove('mobile', 'tablet-portrait', 'tablet-landscape', 'desktop');

            if (width <= 767) {
                body.classList.add('mobile');
                sidebar.classList.add('mobile-hidden'); // Ensure sidebar is hidden by default
                profileTitle.style.display = 'none'; // Hide title on mobile
                userInfo.style.display = 'none'; // Hide user info on mobile
            } else if (width >= 768 && width <= 1024) {
                if (window.matchMedia("(orientation: portrait)").matches) {
                    body.classList.add('tablet-portrait');
                    sidebar.classList.add('mobile-hidden'); // Ensure sidebar is hidden by default
                    profileTitle.style.display = 'none'; // Hide title on tablet portrait
                    userInfo.style.display = 'none'; // Hide user info on tablet portrait
                } else {
                    body.classList.add('tablet-landscape');
                    sidebar.classList.remove('mobile-hidden'); // Show sidebar
                    sidebar.classList.remove('show-mobile-sidebar'); // Ensure mobile sidebar is closed
                    mobileOverlay.classList.remove('show'); // Hide overlay
                    profileTitle.style.display = 'block'; // Show title on tablet landscape
                    userInfo.style.display = 'flex'; // Show user info on tablet landscape
                }
            } else {
                body.classList.add('desktop');
                sidebar.classList.remove('mobile-hidden'); // Show sidebar
                sidebar.classList.remove('show-mobile-sidebar'); // Ensure mobile sidebar is closed
                mobileOverlay.classList.remove('show'); // Hide overlay
                profileTitle.style.display = 'block'; // Show title on desktop
                userInfo.style.display = 'flex'; // Show user info on desktop
            }
        }

        // --- Translation Logic ---
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

            // Dashboard Header
            'profileDashboardTitle': { 'id': 'Dasbor Profil Saya', 'en': 'My Profile Dashboard' },
            'helloUserGreeting': { 'id': 'Halo', 'en': 'Hello' }, // Will be combined with username

            // Profile Card
            'welcomeMessage': { 'id': 'Selamat datang di SKMI Cloud Storage', 'en': 'Welcome to SKMI Cloud Storage' },
            'usernameLabel': { 'id': 'Nama Pengguna', 'en': 'Username' },
            'emailLabel': { 'id': 'Email', 'en': 'Email' },
            'phoneNumberLabel': { 'id': 'Nomor Telepon', 'en': 'Phone Number' },
            'dateOfBirthLabel': { 'id': 'Tanggal Lahir', 'en': 'Date of Birth' },
            'joinDateLabel': { 'id': 'Tanggal Bergabung', 'en': 'Join Date' },
            'accountStatusLabel': { 'id': 'Status Akun', 'en': 'Account Status' },
            'totalFilesLabel': { 'id': 'Total File', 'en': 'Total Files' },
            'storageUsedLabel': { 'id': 'Penyimpanan Terpakai', 'en': 'Storage Used' },
            'totalQuotaLabel': { 'id': 'Total Kuota', 'en': 'Total Quota' },
            'editProfileButton': { 'id': 'Edit Profil', 'en': 'Edit Profile' },
            'changePasswordButton': { 'id': 'Ubah Kata Sandi', 'en': 'Change Password' },
            'logoutButton': { 'id': 'Keluar', 'en': 'Logout' },

            // Right Column - Translation Buttons Card
            'languageSelectionTitle': { 'id': 'Pilihan Bahasa', 'en': 'Language Selection' },

            // Activity History Card
            'activityHistoryTitle': { 'id': 'Riwayat Aktivitas', 'en': 'Activity History' },
            'startDateLabel': { 'id': 'Tanggal Mulai:', 'en': 'Start Date:' },
            'endDateLabel': { 'id': 'Tanggal Akhir:', 'en': 'End Date:' },
            'showDataButton': { 'id': 'Tampilkan Data', 'en': 'Show Data' },
            'activityDetailsTitle': { 'id': 'Detail Aktivitas:', 'en': 'Activity Details:' },
            'noActivityHistory': { 'id': 'Tidak ada riwayat aktivitas.', 'en': 'No activity history.' },
            'noActivityWithinRange': { 'id': 'Tidak ada aktivitas dalam rentang tanggal ini.', 'en': 'No activity within this date range.' },
            'selectBothDates': { 'id': 'Mohon pilih kedua tanggal!', 'en': 'Please select both dates!' },
            'startDateCannotBeLater': { 'id': 'Tanggal mulai tidak boleh lebih lambat dari tanggal akhir.', 'en': 'Start date cannot be later than end date.' },

            // Activity Types (dynamic content)
            'change_password': { 'id': 'Ubah Kata Sandi', 'en': 'Change Password' },
            'update_profile': { 'id': 'Perbarui Profil', 'en': 'Update Profile' },
            'delete_profile_picture': { 'id': 'Hapus Foto Profil', 'en': 'Delete Profile Picture' },
            'add_email': { 'id': 'Tambah Email', 'en': 'Add Email' },
            'delete_email': { 'id': 'Hapus Email', 'en': 'Delete Email' },
            // Add more activity types as needed

            // Additional Emails Card
            'additionalEmailsTitle': { 'id': 'Email Tambahan', 'en': 'Additional Emails' },
            'enterNewEmail': { 'id': 'Masukkan email baru', 'en': 'Enter new email' },
            'addButton': { 'id': 'Tambah', 'en': 'Add' },
            'deleteThisEmail': { 'id': 'Hapus email ini', 'en': 'Delete this email' },
            'primaryEmailLabel': { 'id': ' (Email Utama)', 'en': ' (Primary Email)' }, // Will be appended to email address

            // Change Password Modal
            'changePasswordModalTitle': { 'id': 'Ubah Kata Sandi', 'en': 'Change Password' },
            'oldPasswordLabel': { 'id': 'Kata Sandi Lama:', 'en': 'Old Password:' },
            'newPasswordLabel': { 'id': 'Kata Sandi Baru:', 'en': 'New Password:' },
            'confirmNewPasswordLabel': { 'id': 'Konfirmasi Kata Sandi Baru:', 'en': 'Confirm New Password:' },
            'saveNewPasswordButton': { 'id': 'Simpan Kata Sandi Baru', 'en': 'Save New Password' },

            // Edit Profile Modal
            'editProfileModalTitle': { 'id': 'Edit Profil', 'en': 'Edit Profile' },
            'fullNameLabel': { 'id': 'Nama Lengkap:', 'en': 'Full Name:' },
            'profilePictureLabel': { 'id': 'Foto Profil:', 'en': 'Profile Picture:' },
            'currentLabel': { 'id': 'Saat Ini:', 'en': 'Current:' },
            'deleteProfilePictureButton': { 'id': 'Hapus Foto Profil', 'en': 'Delete Profile Picture' },
            'saveChangesButton': { 'id': 'Simpan Perubahan', 'en': 'Save Changes' },

            // Notifications (dynamic messages, but can have base translations)
            'failedToLoadProfileData': { 'id': 'Gagal memuat data profil.', 'en': 'Failed to load profile data.' },
            'errorChangingPassword': { 'id': 'Terjadi kesalahan saat mengubah kata sandi.', 'en': 'An error occurred while changing password.' },
            'errorUpdatingProfile': { 'id': 'Terjadi kesalahan saat memperbarui profil.', 'en': 'An error occurred while updating profile.' },
            'errorUploadingProfilePicture': { 'id': 'Terjadi kesalahan saat mengunggah foto profil.', 'en': 'An error occurred while uploading profile picture.' },
            'errorDeletingProfilePicture': { 'id': 'Terjadi kesalahan saat menghapus foto profil.', 'en': 'An error occurred while deleting profile picture.' },
            'errorAddingEmail': { 'id': 'Terjadi kesalahan saat menambahkan email.', 'en': 'An error occurred while adding email.' },
            'errorDeletingEmail': { 'id': 'Terjadi kesalahan saat menghapus email.', 'en': 'An error occurred while deleting email.' },
            'profilePictureDeletedSuccess': { 'id': 'Foto profil berhasil dihapus.', 'en': 'Profile picture deleted successfully.' },
            'passwordChangedSuccess': { 'id': 'Kata sandi berhasil diubah.', 'en': 'Password changed successfully.' },
            'profileUpdatedSuccess': { 'id': 'Profil berhasil diperbarui.', 'en': 'Profile updated successfully.' },
            'emailAddedSuccess': { 'id': 'Email berhasil ditambahkan!', 'en': 'Email added successfully!' },
            'emailDeletedSuccess': { 'id': 'Email tambahan berhasil dihapus.', 'en': 'Additional email deleted successfully.' },
            'oldPasswordIncorrect': { 'id': 'Kata sandi lama salah.', 'en': 'Old password is incorrect.' },
            'newPasswordMismatch': { 'id': 'Konfirmasi kata sandi baru tidak cocok.', 'en': 'New password confirmation does not match.' },
            'passwordTooShort': { 'id': 'Kata sandi baru harus minimal 6 karakter.', 'en': 'New password must be at least 6 characters long.' },
            'invalidEmailFormat': { 'id': 'Format email tidak valid.', 'en': 'Invalid email format.' },
            'emailAlreadyPrimary': { 'id': 'Email ini sudah menjadi email utama Anda.', 'en': 'This email is already your primary email.' },
            'emailAlreadyRegisteredUser': { 'id': 'Email ini sudah terdaftar sebagai email tambahan Anda.', 'en': 'This email is already registered as your additional email.' },
            'emailUsedByOtherPrimary': { 'id': 'Email ini sudah digunakan sebagai email utama oleh akun lain.', 'en': 'This email is already used as a primary email by another account.' },
            'emailUsedByOtherAdditional': { 'id': 'Email ini sudah digunakan sebagai email tambahan oleh akun lain.', 'en': 'This email is already used as an additional email by another account.' },
            'noCustomProfilePicture': { 'id': 'Tidak ada foto profil kustom untuk dihapus.', 'en': 'No custom profile picture to delete.' },
            'emailNotFoundOrUnauthorized': { 'id': 'Email tidak ditemukan atau tidak diizinkan untuk dihapus.', 'en': 'Email not found or not authorized to delete.' },
            'fileTypeNotAllowed': { 'id': 'Hanya file JPG, JPEG, PNG, dan GIF yang diizinkan.', 'en': 'Only JPG, JPEG, PNG, and GIF files are allowed.' },
            'fileSizeTooLarge': { 'id': 'Ukuran file terlalu besar. Maksimal 2MB.', 'en': 'File size is too large. Maximum 2MB.' },
            'failedToSaveMergedImage': { 'id': 'Gagal menyimpan foto profil yang digabungkan.', 'en': 'Failed to save merged profile picture.' },
            'failedToLoadImagesForMerging': { 'id': 'Gagal memuat gambar untuk penggabungan.', 'en': 'Failed to load images for merging.' },
            'backgroundNotFoundUploadOriginal': { 'id': 'Gambar latar belakang tidak ditemukan. Mengunggah PNG asli.', 'en': 'Background image not found. Uploading original PNG.' },
            'failedToUploadProfilePicture': { 'id': 'Gagal mengunggah foto profil.', 'en': 'Failed to upload profile picture.' },
            'failedToUpdateProfilePictureDB': { 'id': 'Gagal memperbarui foto profil di database.', 'en': 'Failed to update profile picture in database.' },
            'failedToDeleteProfilePictureFile': { 'id': 'Gagal menghapus file foto profil.', 'en': 'Failed to delete profile picture file.' },
            'emailCannotBeEmpty': { 'id': 'Email tidak boleh kosong.', 'en': 'Email cannot be empty.' },
            'deleteProfilePictureButtonConfirm': { 'id': 'Apakah Anda yakin ingin menghapus foto profil Anda? Ini akan kembali ke gambar kosong default.', 'en': 'Are you sure you want to delete your profile picture? This will revert to the default blank image.' },
            'deleteThisEmailConfirm': { 'id': 'Apakah Anda yakin ingin menghapus email ini?', 'en': 'Are you sure you want to delete this email?' },
        };

        let currentLanguage = localStorage.getItem('lang') || 'id'; // Default to Indonesian

        function applyTranslation(lang) {
            const mainContent = document.getElementById('mainContent');
            mainContent.style.opacity = '0'; // Hide content before translation

            document.querySelectorAll('[data-lang-key]').forEach(element => {
                const key = element.getAttribute('data-lang-key');
                if (translations[key] && translations[key][lang]) {
                    // Handle special cases for dynamic text
                    if (key === 'helloUserGreeting') {
                        const username = document.getElementById('userInfoGreeting').textContent.split(' ')[1]; // Get current username
                        element.textContent = `${translations[key][lang]} ${username}`;
                    } else if (key === 'primaryEmailLabel') {
                        const email = element.textContent.split(' ')[0]; // Get current email
                        element.textContent = `${email}${translations[key][lang]}`;
                    } else {
                        element.textContent = translations[key][lang];
                    }
                }
            });

            // Handle placeholders
            document.querySelectorAll('[data-lang-placeholder]').forEach(element => {
                const key = element.getAttribute('data-lang-placeholder');
                if (translations[key] && translations[key][lang]) {
                    element.placeholder = translations[key][lang];
                }
            });

            // Handle titles
            document.querySelectorAll('[data-lang-title]').forEach(element => {
                const key = element.getAttribute('data-lang-title');
                if (translations[key] && translations[key][lang]) {
                    element.title = translations[key][lang];
                }
            });

            // Handle activity log types and descriptions
            document.querySelectorAll('#activityListUl li').forEach(li => {
                const strongElement = li.querySelector('strong[data-lang-activity-type]');
                // const spanElement = li.querySelector('span[data-lang-activity-desc]'); // Description is dynamic, no direct translation needed here

                if (strongElement) {
                    const activityTypeKey = strongElement.getAttribute('data-lang-activity-type');
                    if (translations[activityTypeKey] && translations[activityTypeKey][lang]) {
                        strongElement.textContent = `${translations[activityTypeKey][lang]}:`;
                    }
                }
            });

            // Update sidebar menu items
            document.querySelectorAll('.sidebar-menu a').forEach(link => {
                const href = link.getAttribute('href');
                let key;
                if (href.includes('control_center.php')) key = 'controlCenter';
                else if (href.includes('index.php')) key = 'myDrive';
                else if (href.includes('priority_files.php')) key = 'priorityFile';
                else if (href.includes('recycle_bin.php')) key = 'recycleBin';
                else if (href.includes('summary.php')) key = 'summary';
                else if (href.includes('members.php')) key = 'members';
                else if (href.includes('profile.php')) key = 'profile';
                else if (href.includes('logout.php')) key = 'logout';

                if (key && translations[key] && translations[key][lang]) {
                    const icon = link.querySelector('i');
                    link.innerHTML = ''; // Clear existing content
                    if (icon) link.appendChild(icon);
                    link.appendChild(document.createTextNode(` ${translations[key][lang]}`));
                }
            });

            // Update sidebar storage info
            const sidebarStorageFullMessage = document.getElementById('sidebarStorageFullMessage');
            if (sidebarStorageFullMessage) {
                const key = 'storageFull';
                if (translations[key] && translations[key][lang]) {
                    sidebarStorageFullMessage.textContent = translations[key][lang];
                }
            }
            const storageTitle = document.querySelector('.storage-info h4');
            if (storageTitle) {
                const key = 'storage';
                if (translations[key] && translations[key][lang]) {
                    storageTitle.textContent = translations[key][lang];
                }
            }

            // Update active language button
            document.getElementById('langIdBtn').classList.remove('active-lang');
            document.getElementById('langEnBtn').classList.remove('active-lang');
            if (lang === 'id') {
                document.getElementById('langIdBtn').classList.add('active-lang');
            } else {
                document.getElementById('langEnBtn').classList.add('active-lang');
            }

            // Update notification messages if any are currently displayed
            const currentNotification = document.getElementById('customNotification');
            if (currentNotification.classList.contains('show')) {
                const messageKey = currentNotification.getAttribute('data-lang-key-notification');
                if (messageKey && translations[messageKey] && translations[messageKey][lang]) {
                    currentNotification.textContent = translations[messageKey][lang];
                }
            }

            // After applying all translations, make content visible
            mainContent.style.opacity = '1';
        }

        // Override showNotification to handle translation keys
        const originalShowNotification = showNotification;
        showNotification = function(message, type, langKey = null) {
            const customNotification = document.getElementById('customNotification');
            if (langKey && translations[langKey] && translations[langKey][currentLanguage]) {
                customNotification.textContent = translations[langKey][currentLanguage];
                customNotification.setAttribute('data-lang-key-notification', langKey); // Store key for re-translation
            } else {
                customNotification.textContent = message;
                customNotification.removeAttribute('data-lang-key-notification');
            }
            customNotification.className = 'notification show ' + type;
            setTimeout(() => {
                customNotification.classList.remove('show');
                customNotification.removeAttribute('data-lang-key-notification');
            }, 3000);
        };


        document.addEventListener('DOMContentLoaded', function() {
            // Initial UI update with data from PHP (server-side rendered)
            // This ensures the page is not blank while AJAX loads
            updateActivityChart(
                <?php echo json_encode($chart_labels); ?>,
                <?php echo json_encode($chart_data); ?>
            );
            window.allActivityLogs = <?php echo json_encode($activity_logs); ?>;

            // Initialize real-time clock
            updateClock();
            setInterval(updateClock, 1000);

            // Elements for modals and forms
            const changePasswordModal = document.getElementById('changePasswordModal');
            const changePasswordForm = document.getElementById('changePasswordForm');
            const editProfileModal = document.getElementById('editProfileModal');
            const editProfileForm = document.getElementById('editProfileForm');
            const closeButtons = document.querySelectorAll('.close-button');
            const profilePictureContainer = document.querySelector('.profile-picture-container');
            const profilePictureInput = document.getElementById('profilePictureInput');
            const addEmailForm = document.getElementById('addEmailForm');
            const additionalEmailsList = document.getElementById('additionalEmailsList');
            const deleteProfilePictureBtn = document.getElementById('deleteProfilePictureBtn');

            // Mobile sidebar elements
            const sidebar = document.querySelector('.sidebar');
            const sidebarToggleBtn = document.getElementById('sidebarToggleBtn');
            const mobileOverlay = document.getElementById('mobileOverlay');
            const mainContent = document.getElementById('mainContent'); // Get main-content for animations

            // Notification display from initial PHP load
            <?php if (!empty($notification_message)): ?>
                showNotification('<?php echo $notification_message; ?>', '<?php echo $notification_type; ?>');
            <?php endif; ?>

            // Apply initial translation based on stored preference
            applyTranslation(currentLanguage);
            document.body.style.visibility = 'visible'; // Make body visible after initial translation

            // Event listeners for translation buttons
            document.getElementById('langIdBtn').addEventListener('click', () => {
                currentLanguage = 'id';
                localStorage.setItem('lang', 'id');
                applyTranslation('id');
            });

            document.getElementById('langEnBtn').addEventListener('click', () => {
                currentLanguage = 'en';
                localStorage.setItem('lang', 'en');
                applyTranslation('en');
            });

            // Open Edit Profile Modal
            document.getElementById('editProfileBtn').addEventListener('click', () => {
                editProfileModal.classList.add('show');
                // Populate fields are handled by updateProfileUI on initial load and refresh
                applyTranslation(currentLanguage); // Re-apply translation to modal content
            });

            // Open Change Password Modal
            document.getElementById('changePasswordBtn').addEventListener('click', () => {
                changePasswordModal.classList.add('show');
                changePasswordForm.reset(); // Clear form fields when opening
                applyTranslation(currentLanguage); // Re-apply translation to modal content
            });

            // Close Modals
            closeButtons.forEach(button => {
                button.addEventListener('click', () => {
                    changePasswordModal.classList.remove('show');
                    editProfileModal.classList.remove('show');
                });
            });

            window.addEventListener('click', (event) => {
                if (event.target == changePasswordModal) {
                    changePasswordModal.classList.remove('show');
                }
                if (event.target == editProfileModal) {
                    editProfileModal.classList.remove('show');
                }
                // Close mobile sidebar if overlay is clicked
                if (event.target == mobileOverlay && sidebar.classList.contains('show-mobile-sidebar')) {
                    sidebar.classList.remove('show-mobile-sidebar');
                    mobileOverlay.classList.remove('show');
                }
            });

            // Handle Change Password Form Submission via AJAX
            changePasswordForm.addEventListener('submit', async function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                try {
                    const response = await fetch('profile.php', {
                        method: 'POST',
                        body: formData
                    });
                    const result = await response.json();
                    if (result.success) {
                        showNotification(result.message, 'success', 'passwordChangedSuccess');
                        changePasswordModal.classList.remove('show');
                        this.reset();
                    } else {
                        // Map specific error messages to translation keys
                        let langKey = 'errorChangingPassword';
                        if (result.message.includes('Old password is incorrect')) {
                            langKey = 'oldPasswordIncorrect';
                        } else if (result.message.includes('New password confirmation does not match')) {
                            langKey = 'newPasswordMismatch';
                        } else if (result.message.includes('New password must be at least 6 characters long')) {
                            langKey = 'passwordTooShort';
                        }
                        showNotification(result.message, 'error', langKey);
                    }
                } catch (error) {
                    console.error('Error changing password:', error);
                    showNotification('An error occurred while changing password.', 'error', 'errorChangingPassword');
                }
            });

            // Handle Edit Profile Form Submission via AJAX
            editProfileForm.addEventListener('submit', async function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                try {
                    const response = await fetch('profile.php', {
                        method: 'POST',
                        body: formData
                    });
                    const result = await response.json();
                    if (result.success) {
                        showNotification(result.message, 'success', 'profileUpdatedSuccess');
                        editProfileModal.classList.remove('show');
                        fetchProfileData(); // Refresh UI after successful update
                    } else {
                        let langKey = 'errorUpdatingProfile';
                        if (result.message.includes('Only JPG, JPEG, PNG, and GIF files are allowed')) {
                            langKey = 'fileTypeNotAllowed';
                        } else if (result.message.includes('File size is too large')) {
                            langKey = 'fileSizeTooLarge';
                        } else if (result.message.includes('Failed to save merged profile picture')) {
                            langKey = 'failedToSaveMergedImage';
                        } else if (result.message.includes('Failed to load images for merging')) {
                            langKey = 'failedToLoadImagesForMerging';
                        } else if (result.message.includes('Background image not found')) {
                            langKey = 'backgroundNotFoundUploadOriginal';
                        } else if (result.message.includes('Failed to upload profile picture')) {
                            langKey = 'failedToUploadProfilePicture';
                        }
                        showNotification(result.message, 'error', langKey);
                    }
                } catch (error) {
                    console.error('Error updating profile:', error);
                    showNotification('An error occurred while updating profile.', 'error', 'errorUpdatingProfile');
                }
            });

            // Handle profile picture click to trigger file input
            profilePictureContainer.addEventListener('click', () => {
                profilePictureInput.click();
            });

            // Handle file input change for profile picture upload (directly from profile card)
            profilePictureInput.addEventListener('change', async function() {
                if (this.files.length === 0) {
                    return;
                }
                const file = this.files[0];
                const formData = new FormData();
                formData.append('profile_picture', file);
                formData.append('edit_profile_submit', '1');
                // Send current full name, phone number, and date of birth to avoid overwriting
                // These values are fetched from the current UI state, which should be up-to-date
                formData.append('full_name', document.getElementById('edit_full_name').value);
                formData.append('phone_number', document.getElementById('edit_phone_number').value);
                formData.append('date_of_birth', document.getElementById('edit_date_of_birth').value);

                try {
                    const response = await fetch('profile.php', {
                        method: 'POST',
                        body: formData
                    });
                    const result = await response.json();
                    if (result.success) {
                        showNotification(result.message, 'success', 'profileUpdatedSuccess');
                        fetchProfileData(); // Refresh UI after successful update
                    } else {
                        let langKey = 'errorUploadingProfilePicture';
                        if (result.message.includes('Only JPG, JPEG, PNG, and GIF files are allowed')) {
                            langKey = 'fileTypeNotAllowed';
                        } else if (result.message.includes('File size is too large')) {
                            langKey = 'fileSizeTooLarge';
                        } else if (result.message.includes('Failed to save merged profile picture')) {
                            langKey = 'failedToSaveMergedImage';
                        } else if (result.message.includes('Failed to load images for merging')) {
                            langKey = 'failedToLoadImagesForMerging';
                        } else if (result.message.includes('Background image not found')) {
                            langKey = 'backgroundNotFoundUploadOriginal';
                        } else if (result.message.includes('Failed to upload profile picture')) {
                            langKey = 'failedToUploadProfilePicture';
                        }
                        showNotification(result.message, 'error', langKey);
                    }
                } catch (error) {
                    console.error('Error uploading profile picture:', error);
                    showNotification('An error occurred while uploading profile picture.', 'error', 'errorUploadingProfilePicture');
                }
            });

            // Handle Delete Profile Picture
            deleteProfilePictureBtn.addEventListener('click', async () => {
                if (confirm(translations['deleteProfilePictureButtonConfirm'][currentLanguage] || 'Are you sure you want to delete your profile picture? This will revert to the default blank image.')) {
                    const formData = new FormData();
                    formData.append('delete_profile_picture', '1');
                    try {
                        const response = await fetch('profile.php', {
                            method: 'POST',
                            body: formData
                        });
                        const result = await response.json();
                        if (result.success) {
                            showNotification(result.message, 'success', 'profilePictureDeletedSuccess');
                            fetchProfileData(); // Refresh UI after successful deletion
                        } else {
                            let langKey = 'errorDeletingProfilePicture';
                            if (result.message.includes('No custom profile picture to delete')) {
                                langKey = 'noCustomProfilePicture';
                            } else if (result.message.includes('Failed to update profile picture in database')) {
                                langKey = 'failedToUpdateProfilePictureDB';
                            } else if (result.message.includes('Failed to delete profile picture file')) {
                                langKey = 'failedToDeleteProfilePictureFile';
                            }
                            showNotification(result.message, 'error', langKey);
                        }
                    } catch (error) {
                        console.error('Error deleting profile picture:', error);
                        showNotification('An error occurred while deleting profile picture.', 'error', 'errorDeletingProfilePicture');
                    }
                }
            });

            // Handle Add Email Form Submission via AJAX
            addEmailForm.addEventListener('submit', async function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                formData.append('add_email', '1'); // Explicitly set add_email flag
                try {
                    const response = await fetch('profile.php', {
                        method: 'POST',
                        body: formData
                    });
                    const result = await response.json();
                    if (result.success) {
                        showNotification(result.message, 'success', 'emailAddedSuccess');
                        this.reset(); // Clear the input field
                        fetchProfileData(); // Refresh UI to show new email
                    } else {
                        let langKey = 'errorAddingEmail';
                        if (result.message.includes('Email cannot be empty')) {
                            langKey = 'emailCannotBeEmpty';
                        } else if (result.message.includes('Invalid email format')) {
                            langKey = 'invalidEmailFormat';
                        } else if (result.message.includes('This email is already your primary email')) {
                            langKey = 'emailAlreadyPrimary';
                        } else if (result.message.includes('This email is already registered as your additional email')) {
                            langKey = 'emailAlreadyRegisteredUser';
                        } else if (result.message.includes('This email is already used as a primary email by another account')) {
                            langKey = 'emailUsedByOtherPrimary';
                        } else if (result.message.includes('This email is already used as an additional email by another account')) {
                            langKey = 'emailUsedByOtherAdditional';
                        }
                        showNotification(result.message, 'error', langKey);
                    }
                } catch (error) {
                    console.error('Error adding email:', error);
                    showNotification('An error occurred while adding email.', 'error', 'errorAddingEmail');
                }
            });

            // Handle Delete Additional Email via Event Delegation
            additionalEmailsList.addEventListener('submit', async function(e) {
                if (e.target.classList.contains('delete-email-form')) {
                    e.preventDefault();
                    if (confirm(translations['deleteThisEmailConfirm'][currentLanguage] || 'Are you sure you want to delete this email?')) {
                        const formData = new FormData(e.target);
                        formData.append('delete_additional_email', '1'); // Explicitly set delete_additional_email flag
                        try {
                            const response = await fetch('profile.php', {
                                method: 'POST',
                                body: formData
                            });
                            const result = await response.json();
                            if (result.success) {
                                showNotification(result.message, 'success', 'emailDeletedSuccess');
                                fetchProfileData(); // Refresh UI to remove deleted email
                            } else {
                                let langKey = 'errorDeletingEmail';
                                if (result.message.includes('Email not found or not authorized to delete')) {
                                    langKey = 'emailNotFoundOrUnauthorized';
                                }
                                showNotification(result.message, 'error', langKey);
                            }
                        } catch (error) {
                            console.error('Error deleting email:', error);
                            showNotification('An error occurred while deleting email.', 'error', 'errorDeletingEmail');
                        }
                    }
                }
            });

            // Calendar filter function (client-side filtering)
            window.filterActivityData = function() {
                const startDateInput = document.getElementById('startDate');
                const endDateInput = document.getElementById('endDate');
                const filterResultDiv = document.getElementById('filterResult');
                const activityListUl = document.getElementById('activityListUl');

                const startDate = startDateInput.value;
                const endDate = endDateInput.value;

                if (!startDate || !endDate) {
                    showNotification(translations['selectBothDates'][currentLanguage] || "Please select both dates!", "error", 'selectBothDates');
                    return;
                }

                const startDateTime = new Date(startDate + 'T00:00:00');
                const endDateTime = new Date(endDate + 'T23:59:59'); // End of the day

                if (startDateTime > endDateTime) {
                    showNotification(translations['startDateCannotBeLater'][currentLanguage] || "Start date cannot be later than end date.", "error", 'startDateCannotBeLater');
                    return;
                }

                filterResultDiv.textContent = `${translations['showDataButton'][currentLanguage] || 'Showing data'} from ${startDate} to ${endDate}`;

                const filteredLogs = window.allActivityLogs.filter(log => {
                    const logTimestamp = new Date(log.timestamp);
                    return logTimestamp >= startDateTime && logTimestamp <= endDateTime;
                });

                const filteredChartData = processLogsForChart(filteredLogs);
                updateActivityChart(filteredChartData.labels, filteredChartData.data);

                // Update detailed activity list
                activityListUl.innerHTML = ''; // Clear existing list
                if (filteredLogs.length === 0) {
                    const li = document.createElement('li');
                    li.setAttribute('data-lang-key', 'noActivityWithinRange');
                    li.textContent = translations['noActivityWithinRange'][currentLanguage] || 'No activity within this date range.';
                    activityListUl.appendChild(li);
                } else {
                    filteredLogs.sort((a, b) => new Date(b.timestamp) - new Date(a.timestamp)); // Sort newest to oldest
                    filteredLogs.forEach(log => {
                        const li = document.createElement('li');
                        li.innerHTML = `
                            <strong data-lang-activity-type="${log.activity_type}">${translations[log.activity_type]?.[currentLanguage] || log.activity_type}:</strong>
                            <span data-lang-activity-desc="${log.description}">${log.description}</span>
                            <span style="float: right; color: var(--secondary-text-color); font-size: 0.9em;">${new Date(log.timestamp).toLocaleDateString('en-US', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })}</span>
                        `;
                        activityListUl.appendChild(li);
                    });
                }
            };

            // Initial application of device class
            applyDeviceClass();
            window.addEventListener('resize', applyDeviceClass);
            window.addEventListener('orientationchange', applyDeviceClass);

            // --- Mobile Sidebar Toggle ---
            sidebarToggleBtn.addEventListener('click', () => {
                sidebar.classList.toggle('show-mobile-sidebar');
                mobileOverlay.classList.toggle('show');
            });

            // --- Sidebar Menu Navigation with Fly Out Animation ---
            const sidebarMenuItems = document.querySelectorAll('.sidebar-menu a');
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

            // Set active class for current page in sidebar
            const currentPage = window.location.pathname.split('/').pop();
            sidebarMenuItems.forEach(item => {
                item.classList.remove('active');
                const itemHref = item.getAttribute('href');
                if (itemHref === currentPage || (currentPage === 'profile.php' && itemHref === 'profile.php')) {
                    item.classList.add('active');
                }
            });

            // Refresh data periodically (e.g., every 30 seconds)
            setInterval(fetchProfileData, 30000);
        });
    </script>
</body>
</html>
