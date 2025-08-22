<?php
include 'config.php';
include 'functions.php'; // Include functions.php file

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
            $stmt_check_for_user->bind_param("is", $user_id, $new_email);
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
            /* box-shadow: none; */ /* Removed box-shadow */
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

        /* Main Content Area */
        .main-content {
            flex-grow: 1;
            padding: 30px;
            display: flex;
            flex-direction: column;
            overflow-y: auto; /* Enable scrolling for content */
            background-color: #FFFFFF; /* White background for content area */
            border-radius: 0px;
            margin: 0; /* MODIFIED: Full width */
            /* box-shadow: none; */ /* Removed box-shadow */
        }

        /* Dashboard Header */
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px; /* Adjusted margin-bottom */
            padding-bottom: 15px;
            border-bottom: 1px solid var(--metro-light-gray);
            background-color: #FFFFFF; /* White header */
            padding: 15px 30px; /* Add padding for header */
            margin: -30px -30px 25px -30px; /* Adjust margin to cover full width */
            border-radius: 0; /* MODIFIED: No rounded top corners for full width */
            /* box-shadow: none; */ /* Removed box-shadow */
        }

        .dashboard-header h1 {
            margin: 0;
            color: var(--metro-text-color);
            font-size: 2.5em;
            font-weight: 300;
        }

        .dashboard-header .user-info {
            display: flex;
            align-items: center;
        }

        .dashboard-header .user-info span {
            margin-right: 15px;
            font-size: 1.1em;
            color: var(--metro-text-color); /* Changed to metro-text-color */
        }

        .dashboard-header .user-info img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--metro-light-gray); /* Changed to metro-light-gray */
            /* box-shadow: none; */ /* Removed box-shadow */
        }

        /* Dashboard Grid Layout */
        .dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr; /* Adjust as needed for responsiveness */
            gap: 25px;
        }

        .card {
            background-color: white;
            border-radius: 8px; /* Softer rounded corners, consistent with index.php */
            /* box-shadow: none; */ /* Removed box-shadow */
            padding: 30px;
            overflow: hidden; /* For image in profile card */
            transition: transform 0.2s ease-out; /* Removed box-shadow from transition */
        }
        .card:hover { /* Added hover effect */
            transform: translateY(-3px);
            /* box-shadow: none; */ /* Removed box-shadow */
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
            height: 150px; /* Height for the background image */
            background: linear-gradient(to right, #0078D7, #4CAF50); /* Metro-inspired gradient */
            position: absolute;
            top: 0;
            left: 0;
            border-top-left-radius: 8px; /* Consistent with card border-radius */
            border-top-right-radius: 8px; /* Consistent with card border-radius */
        }

        .profile-card .profile-picture-container {
            position: relative;
            margin-top: 50px; /* Push image down to show background */
            z-index: 1; /* Ensure image is above background */
            cursor: pointer; /* Indicate it's clickable */
        }

        .profile-card img {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 5px solid #fff; /* White border around profile picture */
            /* box-shadow: none; */ /* Removed box-shadow */
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
            font-size: 2em;
        }

        .profile-card h3 {
            margin-top: 20px;
            margin-bottom: 5px;
            font-size: 1.8em;
            color: var(--metro-text-color); /* Consistent color */
        }

        .profile-card p {
            font-size: 1em;
            color: var(--metro-dark-gray); /* Consistent color */
            margin-bottom: 20px;
        }

        .profile-info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr; /* Two columns for info */
            gap: 10px 20px; /* Row and column gap */
            width: 100%;
            padding: 20px;
            box-sizing: border-box;
            text-align: left; /* Align text within grid items */
        }

        .profile-info-item strong {
            display: block;
            font-size: 0.9em;
            color: var(--metro-dark-gray); /* Consistent color */
            margin-bottom: 3px;
        }

        .profile-info-item span {
            font-size: 1.1em;
            color: var(--metro-text-color); /* Consistent color */
        }

        .profile-info-item.full-width {
            grid-column: span 2; /* Make this item span both columns */
        }

        .profile-stats {
            display: flex;
            justify-content: space-around;
            width: 100%;
            padding: 20px;
            border-top: 1px solid var(--metro-light-gray); /* Consistent border */
            margin-top: 20px;
            box-sizing: border-box;
        }

        .profile-stats-item {
            text-align: center;
        }

        .profile-stats-item strong {
            display: block;
            font-size: 1.5em;
            color: var(--metro-text-color); /* Consistent color */
            margin-bottom: 5px;
        }

        .profile-stats-item span {
            font-size: 0.9em;
            color: var(--metro-dark-gray); /* Consistent color */
        }

        /* Updated Profile Actions Buttons with Metro Design */
        .profile-actions-buttons {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 8px; /* Smaller gap between buttons */
            padding: 15px; /* Smaller padding */
            border-top: 1px solid var(--metro-light-gray);
            margin-top: 20px;
        }

        .profile-actions-buttons .profile-button {
            background-color: var(--metro-blue);
            color: white;
            border: none;
            padding: 8px 15px; /* Smaller padding */
            border-radius: 3px;
            cursor: pointer;
            font-size: 0.9em; /* Smaller font size */
            transition: all 0.2s ease-out;
            margin: 0;
            min-width: 120px; /* Consistent minimum width */
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }

        .profile-actions-buttons .profile-button i {
            font-size: 0.9em;
        }

        /* Different colors for each button */
        .profile-actions-buttons .profile-button:nth-child(1) {
            background-color: #4CAF50; /* Green for Edit Profile */
        }
        .profile-actions-buttons .profile-button:nth-child(2) {
            background-color: #2196F3; /* Blue for Change Password */
        }
        .profile-actions-buttons .profile-button:nth-child(3) {
            background-color: #9C27B0; /* Purple for Logout */
        }
        /* Removed .profile-button:nth-child(4) for Delete Account */

        /* Hover effects */
        .profile-actions-buttons .profile-button:hover {
            transform: translateY(-2px);
            opacity: 0.9;
        }

        .profile-actions-buttons .profile-button:active {
            transform: translateY(0);
        }

        /* Real-time clock styles */
        .real-time-clock {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-top: 15px;
            padding: 10px;
            background-color: var(--metro-bg-color);
            border-radius: 5px;
        }

        .real-time-clock .clock {
            font-size: 1.2em;
            font-weight: bold;
            color: var(--metro-text-color);
        }

        .real-time-clock .date {
            font-size: 0.9em;
            margin-top: 5px;
            color: var(--metro-dark-gray);
        }

        /* Activity History Card */
        .activity-card .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            border-bottom: 1px solid var(--metro-light-gray); /* Consistent border */
            padding-bottom: 10px; /* Consistent padding */
        }

        .activity-card .card-header h2 {
            font-size: 1.8em; /* Consistent font-size */
            margin: 0;
            color: var(--metro-text-color); /* Consistent color */
            font-weight: 300; /* Consistent font-weight */
        }

        .activity-card .chart-container {
            position: relative;
            height: 250px; /* Set a fixed height for the chart */
            width: 100%;
            margin-bottom: 20px;
        }

        /* Calendar Filter */
        .calendar-filter {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 20px;
            align-items: flex-end;
        }

        .calendar-filter .form-group {
            margin-bottom: 0; /* Remove default form-group margin */
            flex: 1; /* Allow items to grow */
            min-width: 150px; /* Minimum width for date inputs */
        }

        .calendar-filter label {
            font-weight: 600; /* Consistent font-weight */
            color: var(--metro-text-color); /* Consistent color */
            margin-bottom: 8px; /* Consistent margin */
            display: block;
        }

        .calendar-filter input[type="date"] {
            width: calc(100% - 20px); /* Consistent width */
            padding: 12px; /* Consistent padding */
            border: 1px solid var(--metro-medium-gray); /* Consistent border */
            border-radius: 3px; /* Small border-radius */
            font-size: 1em; /* Consistent font-size */
            box-sizing: border-box; /* Include padding in width */
            background-color: #F9F9F9; /* Consistent background */
            transition: border-color 0.2s ease-out, box-shadow 0.2s ease-out; /* Consistent transition */
        }
        .calendar-filter input[type="date"]:focus { /* Consistent focus */
            border-color: var(--metro-blue);
            /* box-shadow: none; */ /* Removed box-shadow */
            outline: none;
            background-color: #FFFFFF;
        }

        .calendar-filter button {
            background-color: var(--metro-blue); /* Consistent button style */
            color: white;
            border: none;
            padding: 12px 25px; /* Consistent padding */
            border-radius: 3px; /* Small border-radius */
            cursor: pointer;
            font-size: 1.1em; /* Consistent font-size */
            transition: background-color 0.2s ease-out, transform 0.1s ease-out; /* Consistent transition */
            /* box-shadow: none; */ /* Removed box-shadow */
        }

        .calendar-filter button:hover {
            background-color: var(--metro-dark-blue); /* Consistent hover */
            transform: translateY(-1px);
        }
        .calendar-filter button:active {
            transform: translateY(0); /* Consistent active */
            /* box-shadow: none; */ /* Removed box-shadow */
        }

        #filterResult {
            margin-top: 10px;
            font-weight: 600; /* Consistent font-weight */
            color: var(--metro-text-color); /* Consistent color */
        }

        /* Activity Details Sticky and Full Background */
        #activityList {
            position: sticky;
            bottom: 0; /* Stick to the bottom of its parent container */
            width: 100%;
            background-color: white; /* Full background */
            border-top: 1px solid var(--metro-light-gray);
            padding-top: 15px;
            padding-bottom: 15px; /* Add padding to the bottom */
            z-index: 10; /* Ensure it stays above other content when scrolling */
            /*box-shadow: 0 -2px 5px rgba(0,0,0,0.05); */
            /* Subtle shadow to indicate stickiness */
            margin-top: 20px; /* Keep original margin-top */
            border-radius: 0 0 8px 8px; /* Rounded bottom corners if card has them */
            box-sizing: border-box; /* Include padding in width/height */
        }

        #activityList h3 {
            margin-top: 0;
            margin-bottom: 10px;
            padding-left: 10px; /* Align with list items */
            color: var(--metro-text-color);
        }

        #activityList ul {
            list-style: none;
            padding: 0 10px; /* Add horizontal padding to list items */
            margin: 0;
            max-height: 150px; /* Keep max height for scrollbar */
            overflow-y: auto; /* Enable vertical scrollbar */
        }

        #activityList ul li {
            margin-bottom: 8px;
            padding: 5px;
            background-color: var(--metro-bg-color);
            border-radius: 5px;
        }


        /* Add Email Card */
        .add-email-card .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            border-bottom: 1px solid var(--metro-light-gray); /* Consistent border */
            padding-bottom: 10px; /* Consistent padding */
        }

        .add-email-card .card-header h2 {
            font-size: 1.8em; /* Consistent font-size */
            margin: 0;
            color: var(--metro-text-color); /* Consistent color */
            font-weight: 300; /* Consistent font-weight */
        }

        .add-email-card .email-input-group {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        .add-email-card .email-input-group input[type="email"] {
            flex-grow: 1;
            width: calc(100% - 20px); /* Consistent width */
            padding: 12px; /* Consistent padding */
            border: 1px solid var(--metro-medium-gray); /* Consistent border */
            border-radius: 3px; /* Small border-radius */
            font-size: 1em; /* Consistent font-size */
            background-color: #F9F9F9; /* Consistent background */
            transition: border-color 0.2s ease-out, box-shadow 0.2s ease-out; /* Consistent transition */
        }
        .add-email-card .email-input-group input[type="email"]:focus { /* Consistent focus */
            border-color: var(--metro-blue);
            /* box-shadow: none; */ /* Removed box-shadow */
            outline: none;
            background-color: #FFFFFF;
        }

        .add-email-card .email-input-group button {
            background-color: var(--metro-blue); /* Consistent button style */
            color: white;
            border: none;
            padding: 12px 25px; /* Consistent padding */
            border-radius: 3px; /* Small border-radius */
            cursor: pointer;
            font-size: 1.1em; /* Consistent font-size */
            transition: background-color 0.2s ease-out, transform 0.1s ease-out; /* Consistent transition */
            /* box-shadow: none; */ /* Removed box-shadow */
        }

        .add-email-card .email-input-group button:hover {
            background-color: var(--metro-dark-blue); /* Consistent hover */
            transform: translateY(-1px);
        }
        .add-email-card .email-input-group button:active {
            transform: translateY(0); /* Consistent active */
            /* box-shadow: none; */ /* Removed box-shadow */
        }

        .add-email-card .email-list-container {
            max-height: 200px; /* Max height for scrollbar */
            overflow-y: auto; /* Enable vertical scrollbar */
            border: 1px solid var(--metro-light-gray); /* Consistent border */
            border-radius: 8px;
            padding: 10px;
            background-color: var(--metro-bg-color); /* Consistent background */
        }

        .add-email-card .email-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid var(--metro-light-gray); /* Consistent border */
        }

        .add-email-card .email-item:last-child {
            border-bottom: none;
        }

        .add-email-card .email-item span {
            font-size: 1em;
            color: var(--metro-text-color); /* Consistent color */
        }

        .add-email-card .email-item .delete-email-btn {
            background: none;
            border: none;
            color: var(--metro-error); /* Consistent color */
            cursor: pointer;
            font-size: 1.1em;
            transition: color 0.2s ease-out; /* Consistent transition */
        }

        .add-email-card .email-item .delete-email-btn:hover {
            color: #C4001A; /* Darker red on hover */
        }

        /* Notification Styles */
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 25px;
            border-radius: 5px; /* Consistent border-radius */
            color: white;
            font-weight: bold;
            z-index: 1001;
            /* box-shadow: none; */ /* Removed box-shadow */
            display: none;
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
            background-color: var(--metro-success);
        }

        .notification.error {
            background-color: var(--metro-error);
        }

        /* Additional sections for Password Change and Delete Account */
        .profile-section {
            background-color: white;
            border-radius: 8px; /* Consistent border-radius */
            /* box-shadow: none; */ /* Removed box-shadow */
            padding: 30px;
            margin-top: 25px; /* Spacing between cards */
            transition: transform 0.2s ease-out; /* Removed box-shadow from transition */
        }
        .profile-section:hover { /* Added hover effect */
            transform: translateY(-3px);
            /* box-shadow: none; */ /* Removed box-shadow */
        }

        .profile-section h2 {
            font-size: 1.8em; /* Consistent font-size */
            color: var(--metro-text-color); /* Consistent color */
            margin-top: 0;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            font-weight: 300; /* Consistent font-weight */
            border-bottom: 1px solid var(--metro-light-gray); /* Consistent border */
            padding-bottom: 10px; /* Consistent padding */
        }

        .profile-section h2 i {
            margin-right: 10px;
            color: var(--metro-blue); /* Consistent color */
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600; /* Consistent font-weight */
            color: var(--metro-text-color); /* Consistent color */
            font-size: 1.05em; /* Consistent font-size */
        }

        .form-group input[type="text"],
        .form-group input[type="email"],
        .form-group input[type="password"],
        .form-group input[type="file"],
        .form-group input[type="date"] {
            width: calc(100% - 20px); /* Consistent width */
            padding: 12px; /* Consistent padding */
            border: 1px solid var(--metro-medium-gray); /* Consistent border */
            border-radius: 3px; /* Small border-radius */
            font-size: 1em; /* Consistent font-size */
            background-color: #F9F9F9; /* Consistent background */
            transition: border-color 0.2s ease-out, box-shadow 0.2s ease-out; /* Consistent transition */
        }
        .form-group input[type="text"]:focus,
        .form-group input[type="email"]:focus,
        .form-group input[type="password"]:focus,
        .form-group input[type="date"]:focus { /* Consistent focus */
            border-color: var(--metro-blue);
            /* box-shadow: none; */ /* Removed box-shadow */
            outline: none;
            background-color: #FFFFFF;
        }

        .form-group input[type="file"] {
            border: none;
            padding-left: 0;
            background-color: transparent; /* Ensure no background */
        }

        .profile-button {
            background-color: var(--metro-blue); /* Consistent button style */
            color: white;
            border: none;
            padding: 12px 25px; /* Consistent padding */
            border-radius: 3px; /* Small border-radius */
            cursor: pointer;
            font-size: 1.1em; /* Consistent font-size */
            transition: background-color 0.2s ease-out, transform 0.1s ease-out; /* Consistent transition */
            /* box-shadow: none; */ /* Removed box-shadow */
            margin-top: 10px;
        }

        .profile-button:hover {
            background-color: var(--metro-dark-blue); /* Consistent hover */
            transform: translateY(-1px);
        }
        .profile-button:active {
            transform: translateY(0); /* Consistent active */
            /* box-shadow: none; */ /* Removed box-shadow */
        }

        .delete-button {
            background-color: var(--metro-error); /* Consistent error color */
        }

        .delete-button:hover {
            background-color: #C4001A; /* Darker red on hover */
        }

        .button-group {
            display: flex;
            justify-content: flex-end; /* Align buttons to the right */
            gap: 10px;
            margin-top: 20px;
        }

        /* New styles for aligning Change Password and Account Actions */
        .profile-actions-grid {
            display: grid;
            grid-template-columns: 1fr 1fr; /* Two columns, equal width */
            gap: 25px;
            margin-top: 25px;
        }

        @media (max-width: 768px) {
            .profile-actions-grid {
                grid-template-columns: 1fr; /* Stack on smaller screens */
            }
        }

        /* Modal Styles (Copied from index.php and adapted for profile.php) */
        .modal {
            display: none;
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
            transition: opacity 0.3s ease-out;
        }

        .modal.show {
            display: flex;
            opacity: 1;
        }

        .modal-content {
            background-color: #FFFFFF; /* Consistent background */
            padding: 30px;
            border-radius: 5px; /* Consistent border-radius */
            /* box-shadow: none; */ /* Removed box-shadow */
            width: 90%;
            max-width: 550px; /* Consistent max-width */
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
            color: var(--metro-dark-gray); /* Consistent color */
            position: absolute;
            top: 15px;
            right: 20px;
            font-size: 30px; /* Consistent font-size */
            font-weight: normal; /* Consistent font-weight */
            cursor: pointer;
            transition: color 0.2s ease-out;
        }

        .close-button:hover,
        .close-button:focus {
            color: var(--metro-error); /* Consistent hover color */
        }

        .modal h2 {
            margin-top: 0;
            margin-bottom: 25px; /* Consistent margin */
            color: var(--metro-text-color); /* Consistent color */
            font-size: 2em; /* Consistent font-size */
            font-weight: 300; /* Consistent font-weight */
            border-bottom: 1px solid var(--metro-light-gray); /* Consistent border */
            padding-bottom: 15px; /* Consistent padding */
        }

        .modal label {
            display: block;
            margin-bottom: 10px; /* Consistent margin */
            font-weight: 600; /* Consistent font-weight */
            color: var(--metro-text-color); /* Consistent color */
            font-size: 1.05em; /* Consistent font-size */
        }

        .modal input[type="text"],
        .modal input[type="file"],
        .modal input[type="password"],
        .modal input[type="date"] {
            width: calc(100% - 20px); /* Consistent width */
            padding: 12px; /* Consistent padding */
            margin-bottom: 20px; /* Consistent margin */
            border: 1px solid var(--metro-medium-gray); /* Consistent border */
            border-radius: 3px; /* Small border-radius */
            font-size: 1em; /* Consistent font-size */
            color: var(--metro-text-color); /* Consistent color */
            background-color: #F9F9F9; /* Consistent background */
            transition: border-color 0.2s ease-out, box-shadow 0.2s ease-out; /* Consistent transition */
        }
        
        .modal input[type="text"]:focus,
        .modal input[type="password"]:focus,
        .modal input[type="date"]:focus { /* Consistent focus */
            border-color: var(--metro-blue);
            /* box-shadow: none; */ /* Removed box-shadow */
            outline: none;
            background-color: #FFFFFF;
        }
        .modal input[type="file"] {
            border: 1px solid var(--metro-medium-gray); /* Keep border for file input */
            padding: 10px;
            background-color: transparent; /* Ensure no background */
        }

        .modal button {
            background-color: var(--metro-blue); /* Consistent button style */
            color: white;
            border: none;
            padding: 12px 25px; /* Consistent padding */
            border-radius: 3px; /* Small border-radius */
            cursor: pointer;
            font-size: 1.1em; /* Consistent font-size */
            transition: background-color 0.2s ease-out, transform 0.1s ease-out; /* Consistent transition */
            /* box-shadow: none; */ /* Removed box-shadow */
        }

        .modal button:hover {
            background-color: var(--metro-dark-blue); /* Consistent hover */
            transform: translateY(-1px);
        }
        .modal button:active {
            transform: translateY(0); /* Consistent active */
            /* box-shadow: none; */ /* Removed box-shadow */
        }

        /* Windows 7-like Animations */
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

        /* General button hover/active effects */
        button {
            outline: none;
        }
        button:focus {
            /* box-shadow: none; */ /* Removed box-shadow */
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
        .dashboard-header .profile-title { /* Specific class for this page's title */
            display: block; /* "My Profile Dashboard" visible on desktop */
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
            body.tablet-landscape .dashboard-header {
                padding: 10px 20px;
                margin: -20px -20px 20px -20px;
                border-radius: 0; /* Sudut siku-siku */
            }
            body.tablet-landscape .dashboard-header h1 {
                font-size: 2em;
            }
            body.tablet-landscape .dashboard-grid {
                grid-template-columns: 1fr; /* Stack columns vertically */
                gap: 20px;
            }
            body.tablet-landscape .profile-card {
                padding: 20px;
            }
            body.tablet-landscape .profile-card h3 {
                font-size: 1.5em;
            }
            /* Keep profile-info-grid as 2 columns on tablet landscape */
            body.tablet-landscape .profile-info-grid {
                grid-template-columns: 1fr 1fr; /* Tetap 2 kolom */
                padding: 15px;
            }
            body.tablet-landscape .profile-info-item.full-width {
                grid-column: span 2; /* Tetap span 2 kolom */
            }
            body.tablet-landscape .profile-stats {
                padding: 15px;
            }
            body.tablet-landscape .profile-stats-item strong {
                font-size: 1.2em;
            }
            body.tablet-landscape .profile-actions-buttons .profile-button {
                padding: 6px 12px;
                font-size: 0.8em;
                min-width: 100px;
            }
            body.tablet-landscape .activity-card .card-header h2,
            body.tablet-landscape .add-email-card .card-header h2 {
                font-size: 1.5em;
            }
            body.tablet-landscape .calendar-filter input[type="date"],
            body.tablet-landscape .add-email-card .email-input-group input[type="email"] {
                padding: 10px;
                font-size: 0.9em;
            }
            body.tablet-landscape .calendar-filter button,
            body.tablet-landscape .add-email-card .email-input-group button {
                padding: 10px 20px;
                font-size: 1em;
            }
            body.tablet-landscape .activity-card .chart-container {
                height: 200px;
            }
            body.tablet-landscape .activity-card #activityList {
                max-height: 150px;
            }
            body.tablet-landscape .add-email-card .email-list-container {
                max-height: 150px;
            }
            body.tablet-landscape .modal-content {
                padding: 25px;
            }
            body.tablet-landscape .modal h2 {
                font-size: 1.8em;
            }
            body.tablet-landscape .modal label {
                font-size: 0.95em;
            }
            body.tablet-landscape .modal input[type="text"],
            body.tablet-landscape .modal input[type="password"],
            body.tablet-landscape .modal input[type="date"],
            body.tablet-landscape .modal input[type="file"] {
                padding: 10px;
                font-size: 0.9em;
            }
            body.tablet-landscape .modal button {
                padding: 10px 20px;
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
                /* box-shadow: none; */ /* Removed box-shadow */
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
            body.tablet-portrait .dashboard-header {
                justify-content: space-between; /* Align items */
                padding: 10px 20px;
                margin: -20px -20px 20px -20px;
                border-radius: 0; /* Sudut siku-siku */
            }
            body.tablet-portrait .dashboard-header h1 {
                font-size: 2em;
                flex-grow: 1; /* Allow title to take space */
                text-align: center; /* Center title */
                white-space: nowrap; /* Prevent text from wrapping */
                overflow: hidden;
                text-overflow: ellipsis;
            }
            body.tablet-portrait .dashboard-header .profile-title {
                display: none; /* Hide "My Profile Dashboard" */
            }
            body.tablet-portrait .main-content {
                margin: 0; /* MODIFIED: Full width */
                padding: 20px;
                overflow-x: hidden; /* Prevent horizontal scrollbar */
            }
            body.tablet-portrait .dashboard-grid {
                grid-template-columns: 1fr; /* Force vertical stacking */
                gap: 20px;
            }
            body.tablet-portrait .profile-card {
                padding: 18px;
            }
            body.tablet-portrait .profile-card h3 {
                font-size: 1.4em;
            }
            /* Keep profile-info-grid as 2 columns on tablet portrait */
            body.tablet-portrait .profile-info-grid {
                grid-template-columns: 1fr 1fr; /* Tetap 2 kolom */
                padding: 12px;
            }
            body.tablet-portrait .profile-info-item.full-width {
                grid-column: span 2; /* Tetap span 2 kolom */
            }
            body.tablet-portrait .profile-stats {
                padding: 12px;
            }
            body.tablet-portrait .profile-stats-item strong {
                font-size: 1.1em;
            }
            body.tablet-portrait .profile-actions-buttons .profile-button {
                padding: 5px 10px;
                font-size: 0.75em;
                min-width: 90px;
            }
            body.tablet-portrait .activity-card .card-header h2,
            body.tablet-portrait .add-email-card .card-header h2 {
                font-size: 1.4em;
            }
            body.tablet-portrait .calendar-filter input[type="date"],
            body.tablet-portrait .add-email-card .email-input-group input[type="email"] {
                padding: 8px;
                font-size: 0.85em;
            }
            body.tablet-portrait .calendar-filter button,
            body.tablet-portrait .add-email-card .email-input-group button {
                padding: 8px 15px;
                font-size: 0.9em;
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
                padding: 20px;
            }
            body.tablet-portrait .modal h2 {
                font-size: 1.6em;
            }
            body.tablet-portrait .modal label {
                font-size: 0.9em;
            }
            body.tablet-portrait .modal input[type="text"],
            body.tablet-portrait .modal input[type="password"],
            body.tablet-portrait .modal input[type="date"],
            body.tablet-portrait .modal input[type="file"] {
                padding: 8px;
                font-size: 0.85em;
            }
            body.tablet-portrait .modal button {
                padding: 8px 15px;
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
                /* box-shadow: none; */ /* Removed box-shadow */
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
            body.mobile .dashboard-header {
                justify-content: space-between; /* Align items */
                padding: 10px 15px;
                margin: -15px -15px 15px -15px; /* Adjusted margins for mobile */
                border-radius: 0; /* Sudut siku-siku */
            }
            body.mobile .dashboard-header h1 {
                font-size: 1.8em;
                flex-grow: 1; /* Allow title to take space */
                text-align: center; /* Center title */
            }
            body.mobile .dashboard-header .profile-title {
                display: none; /* Hide "My Profile Dashboard" */
            }
            body.mobile .main-content {
                margin: 0; /* MODIFIED: Full width */
                padding: 15px;
                overflow-x: hidden; /* Prevent horizontal scrollbar */
            }
            body.mobile .dashboard-grid {
                grid-template-columns: 1fr !important; /* Force vertical stacking */
                gap: 15px;
            }
            body.mobile .card {
                padding: 15px;
            }
            body.mobile .profile-card h3 {
                font-size: 1.2em;
            }
            /* Keep profile-info-grid as 2 columns on mobile */
            body.mobile .profile-info-grid {
                grid-template-columns: 1fr 1fr; /* Tetap 2 kolom */
                padding: 10px;
            }
            body.mobile .profile-info-item strong {
                font-size: 0.8em;
            }
            body.mobile .profile-info-item span {
                font-size: 0.9em;
            }
            body.mobile .profile-stats {
                padding: 10px;
            }
            body.mobile .profile-stats-item strong {
                font-size: 1em;
            }
            body.mobile .profile-actions-buttons .profile-button {
                padding: 4px 8px;
                font-size: 0.7em;
                min-width: 80px;
            }
            body.mobile .activity-card .card-header h2,
            body.mobile .add-email-card .card-header h2 {
                font-size: 1.2em;
            }
            body.mobile .calendar-filter {
                flex-direction: column;
                gap: 10px;
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
                padding: 6px 12px;
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
                padding: 15px;
            }
            body.mobile .modal h2 {
                font-size: 1.4em;
            }
            body.mobile .modal label {
                font-size: 0.85em;
            }
            body.mobile .modal input[type="text"],
            body.mobile .modal input[type="password"],
            body.mobile .modal input[type="date"],
            body.mobile .modal input[type="file"] {
                padding: 6px;
                font-size: 0.8em;
            }
            body.mobile .modal button {
                padding: 6px 12px;
                font-size: 0.9em;
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
                <p class="storage-text" id="sidebarStorageFullMessage" style="color: var(--metro-error); font-weight: bold;">Storage Full!</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="main-content">
        <div class="dashboard-header">
            <button class="sidebar-toggle-btn" id="sidebarToggleBtn"><i class="fas fa-bars"></i></button>
            <h1 class="profile-title">My Profile Dashboard</h1>
            <div class="user-info">
                <span id="userInfoGreeting">Hello <?php echo htmlspecialchars($user['full_name'] ?? $user['username']); ?></span>
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
                <p>Welcome to SKMI Cloud Storage</p>

                <div class="profile-info-grid">
                    <div class="profile-info-item">
                        <strong>Username</strong>
                        <span id="profileInfoUsername"><?php echo htmlspecialchars($user['username'] ?? 'username'); ?></span>
                    </div>
                    <div class="profile-info-item">
                        <strong>Email</strong>
                        <span id="profileInfoEmail"><?php echo htmlspecialchars($user['email'] ?? 'email@example.com'); ?></span>
                    </div>
                    <div class="profile-info-item">
                        <strong>Phone Number</strong>
                        <span id="profileInfoPhoneNumber"><?php echo htmlspecialchars($user['phone_number'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="profile-info-item">
                        <strong>Date of Birth</strong>
                        <span id="profileInfoDateOfBirth"><?php echo htmlspecialchars($user['date_of_birth'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="profile-info-item">
                        <strong>Join Date</strong>
                        <span id="profileInfoJoinDate"><?php echo date('d M Y', strtotime($user['created_at'])); ?></span>
                    </div>
                    <div class="profile-info-item">
                        <strong>Account Status</strong>
                        <span id="profileInfoAccountStatus"><?php echo htmlspecialchars($current_account_status); ?></span>
                    </div>
                </div>

                <div class="profile-stats">
                    <div class="profile-stats-item">
                        <strong id="profileStatsTotalFiles"><?php echo $total_files; ?></strong>
                        <span>Total Files</span>
                    </div>
                    <div class="profile-stats-item">
                        <strong id="profileStatsStorageUsed"><?php echo formatBytes($usedStorageBytes); ?></strong>
                        <span>Storage Used</span>
                    </div>
                    <div class="profile-stats-item">
                        <strong id="profileStatsTotalQuota"><?php echo formatBytes($totalStorageBytes); ?></strong>
                        <span>Total Quota</span>
                    </div>
                </div>

                <!-- Real-time clock section -->
                <div class="real-time-clock">
                    <div class="clock" id="profileClock">00:00:00 A.M.</div>
                    <div class="date" id="profileDate">Loading date...</div>
                </div>

                <div class="profile-actions-buttons">
                    <button class="profile-button" id="editProfileBtn"><i class="fas fa-edit"></i> Edit Profile</button>
                    <button class="profile-button" id="changePasswordBtn"><i class="fas fa-key"></i> Change Password</button>
                    <a href="logout.php" class="profile-button">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                    <!-- Removed Delete Account Button -->
                </div>
            </div>

            <div class="right-column">
                <!-- Activity History Card -->
                <div class="card activity-card">
                    <div class="card-header">
                        <h2>Activity History</h2>
                    </div>
                    <div class="calendar-filter">
                        <div class="form-group">
                            <label for="startDate">Start Date:</label>
                            <input type="date" id="startDate">
                        </div>
                        <div class="form-group">
                            <label for="endDate">End Date:</label>
                            <input type="date" id="endDate">
                        </div>
                        <button onclick="filterActivityData()">Show Data</button>
                    </div>
                    <div id="filterResult" style="margin-bottom: 20px;"></div>
                    <div class="chart-container">
                        <canvas id="activityLineChart"></canvas>
                    </div>
                    <!-- Detailed activity list -->
                    <div id="activityList">
                        <h3>Activity Details:</h3>
                        <ul id="activityListUl">
                            <?php if (!empty($activity_logs)): ?>
                                <?php foreach ($activity_logs as $log): ?>
                                    <li>
                                        <strong><?php echo htmlspecialchars($log['activity_type']); ?>:</strong>
                                        <?php echo htmlspecialchars($log['description']); ?>
                                        <span style="float: right; color: var(--metro-dark-gray); font-size: 0.9em;"><?php echo date('d M Y H:i', strtotime($log['timestamp'])); ?></span>
                                    </li>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <li>No activity history.</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>

                <!-- Add Email Card -->
                <div class="card add-email-card" style="margin-top: 25px;">
                    <div class="card-header">
                        <h2>Additional Emails</h2>
                    </div>
                    <form id="addEmailForm" class="email-input-group">
                        <input type="email" name="new_email" placeholder="Enter new email" required>
                        <button type="submit" name="add_email">Add</button>
                    </form>
                    <div class="email-list-container">
                        <ul id="additionalEmailsList" style="list-style: none; padding: 0;">
                            <?php if (!empty($additional_emails)): ?>
                                <?php foreach ($additional_emails as $email_item): ?>
                                    <li class="email-item">
                                        <span><?php echo htmlspecialchars($email_item['email']); ?></span>
                                        <form class="delete-email-form" data-email-id="<?php echo $email_item['id']; ?>">
                                            <input type="hidden" name="email_id_to_delete" value="<?php echo $email_item['id']; ?>">
                                            <button type="submit" name="delete_additional_email" class="delete-email-btn" title="Delete this email">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </form>
                                    </li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            <!-- Display primary email as well -->
                            <li class="email-item" style="font-weight: bold; background-color: var(--metro-light-gray); border-radius: 5px; padding: 10px;">
                                <span><?php echo htmlspecialchars($user['email']); ?> (Primary Email)</span>
                                <i class="fas fa-check-circle" style="color: var(--metro-success);"></i>
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
            <h2><i class="fas fa-key"></i> Change Password</h2>
            <form id="changePasswordForm">
                <input type="hidden" name="change_password_ajax" value="1">
                <div class="form-group">
                    <label for="modal_old_password">Old Password:</label>
                    <input type="password" id="modal_old_password" name="old_password" required>
                </div>
                <div class="form-group">
                    <label for="modal_new_password">New Password:</label>
                    <input type="password" id="modal_new_password" name="new_password" required>
                </div>
                <div class="form-group">
                    <label for="modal_confirm_new_password">Confirm New Password:</label>
                    <input type="password" id="modal_confirm_new_password" name="confirm_new_password" required>
                </div>
                <button type="submit" class="profile-button">Save New Password</button>
            </form>
        </div>
    </div>

    <!-- Edit Profile Modal -->
    <div id="editProfileModal" class="modal">
        <div class="modal-content">
            <span class="close-button">&times;</span>
            <h2><i class="fas fa-edit"></i> Edit Profile</h2>
            <form id="editProfileForm" enctype="multipart/form-data">
                <input type="hidden" name="edit_profile_submit" value="1">
                <div class="form-group">
                    <label for="edit_full_name">Full Name:</label>
                    <input type="text" id="edit_full_name" name="full_name" value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label for="edit_phone_number">Phone Number:</label>
                    <input type="text" id="edit_phone_number" name="phone_number" value="<?php echo htmlspecialchars($user['phone_number'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="edit_date_of_birth">Date of Birth:</label>
                    <input type="date" id="edit_date_of_birth" name="date_of_birth" value="<?php echo htmlspecialchars($user['date_of_birth'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="edit_profile_picture">Profile Picture:</label>
                    <input type="file" id="edit_profile_picture" name="profile_picture" accept="image/*">
                    <small>Current: <a id="currentProfilePicLink" href="<?php echo htmlspecialchars($user['profile_picture'] ?? 'img/photo_profile.png'); ?>" target="_blank"><?php echo basename($user['profile_picture'] ?? 'photo_profile.png'); ?></a></small>
                </div>
                <div class="button-group">
                    <button type="button" id="deleteProfilePictureBtn" class="profile-button delete-button">Delete Profile Picture</button>
                    <button type="submit" class="profile-button">Save Changes</button>
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
                            borderColor: 'var(--metro-blue)',
                            backgroundColor: 'rgba(0, 120, 215, 0.1)',
                            tension: 0.3,
                            fill: true,
                            pointBackgroundColor: 'var(--metro-blue)',
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
                                    color: 'var(--metro-text-color)'
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
                                    color: 'var(--metro-text-color)'
                                },
                                ticks: {
                                    precision: 0,
                                    color: 'var(--metro-dark-gray)'
                                },
                                grid: {
                                    color: 'var(--metro-light-gray)'
                                }
                            },
                            x: {
                                title: {
                                    display: true,
                                    text: 'Date',
                                    color: 'var(--metro-text-color)'
                                },
                                ticks: {
                                    color: 'var(--metro-dark-gray)'
                                },
                                grid: {
                                    color: 'var(--metro-light-gray)'
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
                    p.style.color = 'var(--metro-error)';
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
                        <strong>${log.activity_type}:</strong>
                        ${log.description}
                        <span style="float: right; color: var(--metro-dark-gray); font-size: 0.9em;">${new Date(log.timestamp).toLocaleDateString('en-US', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })}</span>
                    `;
                    activityListUl.appendChild(li);
                });
            } else {
                activityListUl.innerHTML = '<li>No activity history.</li>';
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
                            <button type="submit" name="delete_additional_email" class="delete-email-btn" title="Delete this email">
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
            primaryLi.style.backgroundColor = 'var(--metro-light-gray)';
            primaryLi.style.borderRadius = '5px';
            primaryLi.style.padding = '10px';
            primaryLi.innerHTML = `
                <span>${user.email} (Primary Email)</span>
                <i class="fas fa-check-circle" style="color: var(--metro-success);"></i>
            `;
            additionalEmailsList.appendChild(primaryLi);

            // Update Edit Profile Modal fields
            document.getElementById('edit_full_name').value = user.full_name || '';
            document.getElementById('edit_phone_number').value = user.phone_number || '';
            document.getElementById('edit_date_of_birth').value = user.date_of_birth || '';
            document.getElementById('currentProfilePicLink').href = user.profile_picture || 'img/photo_profile.png';
            document.getElementById('currentProfilePicLink').textContent = (user.profile_picture ? user.profile_picture.split('/').pop() : 'photo_profile.png');
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
                    showNotification(data.message, 'error');
                    // Optionally redirect if user not found
                    // window.location.href = 'login.php';
                    return;
                }
                updateProfileUI(data);
                // Store all activity logs for client-side filtering
                window.allActivityLogs = data.activity_logs;
            } catch (error) {
                console.error("Could not fetch profile data:", error);
                showNotification('Failed to load profile data.', 'error');
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
            // const deleteAccountBtn = document.getElementById('deleteAccountBtn'); // Removed
            const addEmailForm = document.getElementById('addEmailForm');
            const additionalEmailsList = document.getElementById('additionalEmailsList');
            const deleteProfilePictureBtn = document.getElementById('deleteProfilePictureBtn');

            // Mobile sidebar elements
            const sidebar = document.querySelector('.sidebar');
            const sidebarToggleBtn = document.getElementById('sidebarToggleBtn');
            const mobileOverlay = document.getElementById('mobileOverlay');

            // Notification display from initial PHP load
            <?php if (!empty($notification_message)): ?>
                showNotification('<?php echo $notification_message; ?>', '<?php echo $notification_type; ?>');
            <?php endif; ?>

            // Open Edit Profile Modal
            document.getElementById('editProfileBtn').addEventListener('click', () => {
                editProfileModal.classList.add('show');
                // Populate fields are handled by updateProfileUI on initial load and refresh
            });

            // Open Change Password Modal
            document.getElementById('changePasswordBtn').addEventListener('click', () => {
                changePasswordModal.classList.add('show');
                changePasswordForm.reset(); // Clear form fields when opening
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
                        showNotification(result.message, 'success');
                        changePasswordModal.classList.remove('show');
                        this.reset();
                    } else {
                        showNotification(result.message, 'error');
                    }
                } catch (error) {
                    console.error('Error changing password:', error);
                    showNotification('An error occurred while changing password.', 'error');
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
                        showNotification(result.message, 'success');
                        editProfileModal.classList.remove('show');
                        fetchProfileData(); // Refresh UI after successful update
                    } else {
                        showNotification(result.message, 'error');
                    }
                } catch (error) {
                    console.error('Error updating profile:', error);
                    showNotification('An error occurred while updating profile.', 'error');
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
                        showNotification(result.message, 'success');
                        fetchProfileData(); // Refresh UI after successful update
                    } else {
                        showNotification(result.message, 'error');
                    }
                } catch (error) {
                    console.error('Error uploading profile picture:', error);
                    showNotification('An error occurred while uploading profile picture.', 'error');
                }
            });

            // Handle Delete Profile Picture
            deleteProfilePictureBtn.addEventListener('click', async () => {
                if (confirm('Are you sure you want to delete your profile picture? This will revert to the default blank image.')) {
                    const formData = new FormData();
                    formData.append('delete_profile_picture', '1');
                    try {
                        const response = await fetch('profile.php', {
                            method: 'POST',
                            body: formData
                        });
                        const result = await response.json();
                        if (result.success) {
                            showNotification(result.message, 'success');
                            fetchProfileData(); // Refresh UI after successful deletion
                        } else {
                            showNotification(result.message, 'error');
                        }
                    } catch (error) {
                        console.error('Error deleting profile picture:', error);
                        showNotification('An error occurred while deleting profile picture.', 'error');
                    }
                }
            });

            // Removed Delete Account button event listener and PHP handling
            // deleteAccountBtn.addEventListener('click', async () => { ... });

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
                        showNotification(result.message, 'success');
                        this.reset(); // Clear the input field
                        fetchProfileData(); // Refresh UI to show new email
                    } else {
                        showNotification(result.message, 'error');
                    }
                } catch (error) {
                    console.error('Error adding email:', error);
                    showNotification('An error occurred while adding email.', 'error');
                }
            });

            // Handle Delete Additional Email via Event Delegation
            additionalEmailsList.addEventListener('submit', async function(e) {
                if (e.target.classList.contains('delete-email-form')) {
                    e.preventDefault();
                    if (confirm('Are you sure you want to delete this email?')) {
                        const formData = new FormData(e.target);
                        formData.append('delete_additional_email', '1'); // Explicitly set delete_additional_email flag
                        try {
                            const response = await fetch('profile.php', {
                                method: 'POST',
                                body: formData
                            });
                            const result = await response.json();
                            if (result.success) {
                                showNotification(result.message, 'success');
                                fetchProfileData(); // Refresh UI to remove deleted email
                            } else {
                                showNotification(result.message, 'error');
                            }
                        } catch (error) {
                            console.error('Error deleting email:', error);
                            showNotification('An error occurred while deleting email.', 'error');
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
                    showNotification("Please select both dates!", "error");
                    return;
                }

                const startDateTime = new Date(startDate + 'T00:00:00');
                const endDateTime = new Date(endDate + 'T23:59:59'); // End of the day

                if (startDateTime > endDateTime) {
                    showNotification("Start date cannot be later than end date.", "error");
                    return;
                }

                filterResultDiv.textContent = `Showing data from ${startDate} to ${endDate}`;

                const filteredLogs = window.allActivityLogs.filter(log => {
                    const logTimestamp = new Date(log.timestamp);
                    return logTimestamp >= startDateTime && logTimestamp <= endDateTime;
                });

                const filteredChartData = processLogsForChart(filteredLogs);
                updateActivityChart(filteredChartData.labels, filteredChartData.data);

                // Update detailed activity list
                activityListUl.innerHTML = ''; // Clear existing list
                if (filteredLogs.length === 0) {
                    activityListUl.innerHTML = '<li>No activity within this date range.</li>';
                } else {
                    filteredLogs.sort((a, b) => new Date(b.timestamp) - new Date(a.timestamp)); // Sort newest to oldest
                    filteredLogs.forEach(log => {
                        const li = document.createElement('li');
                        li.innerHTML = `
                            <strong>${log.activity_type}:</strong>
                            ${log.description}
                            <span style="float: right; color: var(--metro-dark-gray); font-size: 0.9em;">${new Date(log.timestamp).toLocaleDateString('en-US', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })}</span>
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

            // Set active class for current page in sidebar
            const sidebarMenuItems = document.querySelectorAll('.sidebar-menu a');
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
