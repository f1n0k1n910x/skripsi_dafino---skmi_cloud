<?php
include 'config.php';
include 'functions.php';

// Pastikan session_start() ada di sini, di awal file
session_start();

// Define $currentUserRole from session
// Ini penting untuk menghindari "Undefined variable" dan untuk logika sidebar
$currentUserRole = isset($_SESSION['role']) ? $_SESSION['role'] : 'guest'; // Default to 'guest' if not set

// Check if user is logged in (assuming session_start() is handled elsewhere or at the top of config.php)
// If not, you might want to redirect or handle unauthorized access.
// For this summary page, we'll assume it's accessible if config.php and functions.php are included.
// Tambahan: Jika halaman summary hanya untuk user login, tambahkan cek ini:
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}


// Check if it's an AJAX request
$isAjax = isset($_GET['ajax']) && $_GET['ajax'] == 1;

// 1. Total Files Uploaded
$totalFiles = 0;
$stmt = $conn->prepare("SELECT COUNT(id) AS total_files FROM files");
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
if ($row['total_files']) {
    $totalFiles = $row['total_files'];
}
$stmt->close();

// 2. Total Folders
$totalFolders = 0;
$stmt = $conn->prepare("SELECT COUNT(id) AS total_folders FROM folders");
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
if ($row['total_folders']) {
    $totalFolders = $row['total_folders'];
}
$stmt->close();

// 3. Total Storage Usage
$totalStorageGB = 500; // Misalnya, total kapasitas penyimpanan 500 GB
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
$usedStorageGB = $usedStorageBytes / (1024 * 1024 * 1024);

$usedPercentage = ($totalStorageBytes > 0) ? ($usedStorageBytes / $totalStorageBytes) * 100 : 0;
if ($usedPercentage > 100) $usedPercentage = 100;
$freeStorageGB = $totalStorageGB - $usedStorageGB;

// Check if storage is full
// Pastikan fungsi isStorageFull() tersedia di functions.php
$isStorageFull = isStorageFull($conn, $totalStorageBytes);


// 4. Last Uploaded Files (e.g., last 5 files)
$lastUploadedFiles = [];
$stmt = $conn->prepare("SELECT file_name, file_size, uploaded_at FROM files ORDER BY uploaded_at DESC LIMIT 5");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $lastUploadedFiles[] = $row;
}
$stmt->close();

// 5. File Type Distribution
$fileTypeDistribution = [];
$stmt = $conn->prepare("SELECT file_type, COUNT(id) as count FROM files GROUP BY file_type");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $fileTypeDistribution[$row['file_type']] = $row['count'];
}
$stmt->close();

// Prepare data for Chart.js (File Type Distribution)
$fileTypeLabels = array_map('strtoupper', array_keys($fileTypeDistribution));
$fileTypeData = array_values($fileTypeDistribution);

// 6. Uploads per Month (Januari - Desember for current year)
$uploadsPerMonth = [];
$currentYear = date('Y');
// Initialize all 12 months with 0 for the current year
for ($m = 1; $m <= 12; $m++) {
    $monthKey = $currentYear . '-' . str_pad($m, 2, '0', STR_PAD_LEFT);
    $uploadsPerMonth[$monthKey] = 0;
}

$stmt = $conn->prepare("SELECT DATE_FORMAT(uploaded_at, '%Y-%m') as month, COUNT(id) as count FROM files WHERE YEAR(uploaded_at) = ? GROUP BY month ORDER BY month ASC");
$stmt->bind_param("i", $currentYear);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $uploadsPerMonth[$row['month']] = $row['count'];
}
$stmt->close();

// Ensure data is ordered correctly for the chart (Januari to Desember)
$allMonthsUploads = [];
for ($m = 1; $m <= 12; $m++) {
    $monthKey = $currentYear . '-' . str_pad($m, 2, '0', STR_PAD_LEFT);
    $allMonthsUploads[$monthKey] = $uploadsPerMonth[$monthKey] ?? 0;
}
$monthLabels = array_map(function($date) {
    return date('M', strtotime($date . '-01')); // Format to "Jan"
}, array_keys($allMonthsUploads));
$monthData = array_values($allMonthsUploads);


// 7. Storage Usage Per Month (Januari - Desember for current year)
$storageUsagePerMonth = [];
// Initialize all 12 months with 0 bytes for the current year
for ($m = 1; $m <= 12; $m++) {
    $month = $currentYear . '-' . str_pad($m, 2, '0', STR_PAD_LEFT);
    $storageUsagePerMonth[$month] = 0;
}

// Query to get total file size uploaded per month for the current year
$stmt = $conn->prepare("SELECT DATE_FORMAT(uploaded_at, '%Y-%m') as month, SUM(file_size) as total_size FROM files WHERE YEAR(uploaded_at) = ? GROUP BY month ORDER BY month ASC");
$stmt->bind_param("i", $currentYear);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $storageUsagePerMonth[$row['month']] = $row['total_size'];
}
$stmt->close();

// Ensure data is ordered correctly for the chart (Januari to Desember)
$allStorageMonths = [];
for ($m = 1; $m <= 12; $m++) {
    $monthKey = $currentYear . '-' . str_pad($m, 2, '0', STR_PAD_LEFT);
    $allStorageMonths[$monthKey] = $storageUsagePerMonth[$monthKey] ?? 0;
}
$storageMonthLabels = array_map(function($date) {
    return date('M', strtotime($date . '-01')); // Format to "Jan"
}, array_keys($allStorageMonths));
$storageMonthData = array_values($allStorageMonths);


// If it's an AJAX request, return JSON data
if ($isAjax) {
    header('Content-Type: application/json');
    echo json_encode([
        'totalFiles' => $totalFiles,
        'totalFolders' => $totalFolders,
        'usedStorageBytes' => $usedStorageBytes,
        'totalStorageGB' => $totalStorageGB,
        'usedPercentage' => round($usedPercentage, 2),
        'lastUploadedFiles' => $lastUploadedFiles,
        'fileTypeLabels' => $fileTypeLabels,
        'fileTypeData' => $fileTypeData,
        'monthLabels' => $monthLabels,
        'monthData' => $monthData,
        'storageMonthLabels' => $storageMonthLabels,
        'storageMonthData' => $storageMonthData,
        'formattedUsedStorage' => formatBytes($usedStorageBytes),
        'formattedTotalStorage' => formatBytes($totalStorageBytes), // Use totalStorageBytes here
        'isStorageFull' => $isStorageFull // Pass storage full status
    ]);
    exit();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Summary - SKMI Cloud Storage</title>
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

        /* Dashboard Specific Styles */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); /* Adjusted minmax for better fit */
            gap: 20px;
            margin-bottom: 30px;
        }

        /* Card styles */
        .card {
            background-color: var(--primary-color); /* Material primary color */
            color: #FFFFFF;
            padding: 25px;
            border-radius: 0; /* Siku-siku */
            box-shadow: none; /* No box-shadow */
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: transform 0.2s ease-out;
            animation: fadeIn 0.5s ease-out forwards;
            opacity: 0;
        }
        .card:nth-child(1) { animation-delay: 0.1s; }
        .card:nth-child(2) { animation-delay: 0.2s; }
        .card:nth-child(3) { animation-delay: 0.3s; }
        .card:nth-child(4) { animation-delay: 0.4s; }

        .card:hover {
            transform: translateY(-5px);
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


        .chart-container {
            background-color: var(--surface-color);
            border-radius: 0; /* Siku-siku */
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: none; /* No box-shadow */
            transition: transform 0.2s ease-out;
            border: 1px solid var(--divider-color); /* Subtle border for container */
        }
        .chart-container:hover {
            transform: translateY(-3px);
        }
        .chart-container h3 {
            color: var(--text-color);
            font-weight: 400;
            font-size: 1.8em;
            margin-top: 0;
            margin-bottom: 20px;
            border-bottom: 1px solid var(--divider-color);
            padding-bottom: 10px;
        }

        .last-uploaded-files {
            background-color: var(--surface-color);
            border-radius: 0; /* Siku-siku */
            padding: 20px;
            box-shadow: none; /* No box-shadow */
            transition: transform 0.2s ease-out;
            border: 1px solid var(--divider-color); /* Subtle border for container */
        }
        .last-uploaded-files:hover {
            transform: translateY(-3px);
        }

        .last-uploaded-files h3 {
            margin-top: 0;
            margin-bottom: 15px;
            color: var(--text-color);
            font-weight: 400;
            font-size: 1.8em;
            border-bottom: 1px solid var(--divider-color);
            padding-bottom: 10px;
        }

        .last-uploaded-files ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .last-uploaded-files li {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid var(--divider-color);
            font-size: 0.95em;
        }

        .last-uploaded-files li:last-child {
            border-bottom: none;
        }

        .last-uploaded-files .file-info {
            display: flex;
            align-items: center;
            flex-grow: 1;
            min-width: 0; /* Allow content to shrink */
        }

        .last-uploaded-files .file-icon {
            margin-right: 12px;
            font-size: 1.3em;
            width: 28px;
            text-align: center;
            flex-shrink: 0;
        }
        .last-uploaded-files .file-name {
            font-weight: 400;
            color: var(--text-color);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 100%; /* Ensure it respects parent width */
            display: block;
        }
        .last-uploaded-files .file-meta {
            font-size: 0.85em;
            color: var(--secondary-text-color);
            flex-shrink: 0; /* Prevent meta from shrinking */
            margin-left: 10px;
        }
        /* Icon colors from index.php for consistency */
        .file-icon.pdf { color: #E81123; } /* Red */
        .file-icon.doc { color: #2B579A; } /* Dark Blue */
        .file-icon.xls { color: #107C10; } /* Dark Green */
        .file-icon.ppt { color: #D24726; } /* Orange-Red */
        .file-icon.jpg, .file-icon.png, .file-icon.gif { color: #8E24AA; } /* Purple */
        .file-icon.zip { color: #F7B500; } /* Amber */
        .file-icon.txt { color: #666666; } /* Dark Gray */
        .file-icon.exe, .file-icon.apk { color: #0078D7; } /* Metro Blue */
        .file-icon.mp3, .file-icon.wav { color: #00B294; } /* Teal */
        .file-icon.mp4, .file-icon.avi { color: #FFB900; } /* Gold */
        .file-icon.html, .file-icon.css, .file-icon.js, .file-icon.php, .file-icon.py, .file-icon.json, .file-icon.sql, .file-icon.java, .file-icon.c { color: #505050; } /* Code files: dark gray */
        .file-icon.folder { color: #FFD700; } /* Gold for folders */
        .file-icon.default { color: #999999; } /* Light Gray */

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
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

        /* ========================================================================== */
        /* Responsive Classes for iPad, Tablet, HP (Android & iOS) */
        /* ========================================================================== */

        /* Default for Desktop */
        .sidebar-toggle-btn {
            display: none; /* Hidden on desktop */
        }
        .sidebar.mobile-hidden {
            display: flex; /* Always visible on desktop */
            transform: translateX(0);
        }
        .header-main .summary-title {
            display: block; /* "Activity Log" visible on desktop */
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

        /* Class for iPad & Tablet (Landscape: min-width 768px, max-width 1024px) */
        @media (min-width: 768px) and (max-width: 1024px) {
            body.tablet-landscape .sidebar {
                width: 220px; /* Slightly narrower sidebar */
            }
            body.tablet-landscape .main-content {
                margin: 0; /* MODIFIED: Full width */
                padding: 20px;
                overflow-x: hidden; /* Tambahan: Mencegah scrollbar horizontal */
            }
            body.tablet-landscape .header-main {
                padding: 10px 20px;
                margin: -20px -20px 20px -20px;
            }
            body.tablet-landscape .header-main h1 {
                font-size: 2em;
            }
            body.tablet-landscape .dashboard-grid {
                grid-template-columns: repeat(2, 1fr); /* Tampilan 2x2 */
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
            body.tablet-landscape .chart-container {
                padding: 15px;
            }
            body.tablet-landscape .chart-container h3,
            body.tablet-landscape .last-uploaded-files h3 {
                font-size: 1.5em;
                margin-bottom: 15px;
            }
            body.tablet-landscape .last-uploaded-files li {
                font-size: 0.9em;
                padding: 8px 0;
            }
            body.tablet-landscape .last-uploaded-files .file-info {
                 min-width: 0;
            }
            body.tablet-landscape .last-uploaded-files .file-icon {
                font-size: 1.2em;
                margin-right: 10px;
                width: 25px;
            }
            body.tablet-landscape .last-uploaded-files .file-meta {
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
                margin: -20px -20px 20px -20px;
            }
            body.tablet-portrait .header-main h1 {
                font-size: 2em;
                flex-grow: 1; /* Allow title to take space */
                text-align: center; /* Center title */
                white-space: nowrap; /* Tambahan: Mencegah teks terlalu panjang */
                overflow: hidden;
                text-overflow: ellipsis;
            }
            body.tablet-portrait .header-main .summary-title {
                display: none; /* Hide "Activity Log" */
            }
            body.tablet-portrait .main-content {
                margin: 0; /* MODIFIED: Full width */
                padding: 20px;
                overflow-x: hidden; /* Tambahan: Mencegah scrollbar horizontal */
            }
            body.tablet-portrait .dashboard-grid {
                grid-template-columns: repeat(2, 1fr); /* Tampilan 2x2 */
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
            /* Charts and Last Uploaded Files vertical stacking */
            body.tablet-portrait .dashboard-grid[style="grid-template-columns: 2fr 1fr;"],
            body.tablet-portrait .dashboard-grid[style="grid-template-columns: 1fr 1fr;"] {
                grid-template-columns: 1fr !important; /* Force vertical stacking */
            }
            body.tablet-portrait .chart-container {
                padding: 15px;
            }
            body.tablet-portrait .chart-container h3,
            body.tablet-portrait .last-uploaded-files h3 {
                font-size: 1.4em;
                margin-bottom: 12px;
            }
            body.tablet-portrait .last-uploaded-files li {
                font-size: 0.85em;
                padding: 7px 0;
            }
            body.tablet-portrait .last-uploaded-files .file-info {
                min-width: 0;
            }
            body.tablet-portrait .last-uploaded-files .file-icon {
                font-size: 1.1em;
                margin-right: 8px;
                width: 22px;
            }
            body.tablet-portrait .last-uploaded-files .file-meta {
                font-size: 0.75em;
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
                color: var(--adminlte-header-text);
                cursor: pointer;
                margin-left: 10px;
                order: 0;
            }
            body.mobile .header-main {
                justify-content: space-between;
                padding: 10px 15px;
                margin: -15px -15px 15px -15px; /* REVISED: Adjusted margins for mobile */
            }
            body.mobile .header-main h1 {
                font-size: 1.8em;
                flex-grow: 1;
                text-align: center;
            }
            body.mobile .header-main .summary-title {
                display: none;
            }
            body.mobile .main-content {
                padding: 15px;
                overflow-x: hidden;
            }
            body.mobile .dashboard-grid {
                grid-template-columns: repeat(2, 1fr) !important; /* Tampilan 2x2 */
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
            /* Charts and Last Uploaded Files vertical stacking */
            body.mobile .dashboard-grid[style="grid-template-columns: 2fr 1fr;"],
            body.mobile .dashboard-grid[style="grid-template-columns: 1fr 1fr;"] {
                grid-template-columns: 1fr !important; /* Force vertical stacking */
            }
            body.mobile .chart-container {
                padding: 12px;
            }
            body.mobile .chart-container h3,
            body.mobile .last-uploaded-files h3 {
                font-size: 1.2em;
                margin-bottom: 10px;
            }
            body.mobile .last-uploaded-files li {
                display: flex; /* ADDED: Ensure list items are flex containers on mobile */
                flex-direction: column; /* ADDED: Stack content vertically */
                align-items: flex-start; /* ADDED: Align content to the left */
                padding: 6px 0;
            }
            body.mobile .last-uploaded-files .file-info {
                display: flex; /* ADDED: Flexbox for icon and file name */
                align-items: center; /* ADDED: Center icon and name vertically */
                width: 100%; /* ADDED: Ensure it takes full width */
                min-width: 0; /* Tambahan: Memastikan item flex bisa mengecil */
            }
            body.mobile .last-uploaded-files .file-icon {
                font-size: 1em;
                margin-right: 8px; /* REVISED: Small margin to separate from file name */
                margin-bottom: 0; /* REVISED: Remove bottom margin */
                width: auto;
            }
            body.mobile .last-uploaded-files .file-name {
                font-size: 0.85em;
                white-space: normal; /* REVISED: Allow wrapping for long filenames */
                text-overflow: ellipsis; /* REVISED: Keep ellipsis if it's too long on a single line */
                overflow: hidden; /* REVISED: Keep overflow hidden to manage long names */
                display: -webkit-box; /* REVISED: For multiline ellipsis */
                -webkit-line-clamp: 2; /* REVISED: Limit to 2 lines */
                -webkit-box-orient: vertical; /* REVISED: For multiline ellipsis */
            }
            body.mobile .last-uploaded-files .file-meta {
                font-size: 0.7em;
                margin-left: 0;
                margin-top: 5px; /* REVISED: Add space between file name and meta info */
                text-align: left; /* REVISED: Align meta info to the left */
                width: 100%;
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
            <h1 class="summary-title" data-lang-key="activityLogTitle">Activity Log</h1>
        </div>

        <div class="dashboard-grid">
            <div class="card">
                <h3 data-lang-key="totalFilesUploaded">Total Files Uploaded</h3>
                <p class="count" id="totalFilesCount"><?php echo $totalFiles; ?></p>
            </div>

            <div class="card green">
                <h3 data-lang-key="totalFolders">Total Folders</h3>
                <p class="count" id="totalFoldersCount"><?php echo $totalFolders; ?></p>
            </div>

            <div class="card orange">
                <h3 data-lang-key="totalStorageUsed">Total Storage Used</h3>
                <p class="count" id="totalStorageUsed"><?php echo formatBytes($usedStorageBytes); ?></p>
                <p class="storage-text-card" id="totalStorageCapacity" data-lang-key="ofUsedText">of 500 GB used</p>
            </div>

             <div class="card red">
                <h3 data-lang-key="storageUsedPercentage">Storage Used (%)</h3>
                <p class="count" id="storageUsedPercentage"><?php echo round($usedPercentage, 2); ?>%</p>
            </div>
        </div>

        <div class="dashboard-grid" style="grid-template-columns: 2fr 1fr;">
            <div class="chart-container">
                <h3 data-lang-key="uploadsPerMonth">Uploads Per Month</h3>
                <canvas id="uploadsPerMonthChart"></canvas>
            </div>

            <div class="chart-container">
                <h3 data-lang-key="fileTypeDistribution">File Type Distribution</h3>
                <canvas id="fileTypeChart"></canvas>
            </div>
        </div>

        <div class="dashboard-grid" style="grid-template-columns: 1fr 1fr;">
            <div class="last-uploaded-files">
                <h3 data-lang-key="lastUploadedFiles">Last Uploaded Files</h3>
                <ul id="lastUploadedFilesList">
                    <?php if (empty($lastUploadedFiles)): ?>
                        <li data-lang-key="noRecentUploads">No recent uploads.</li>
                    <?php else: ?>
                        <?php foreach ($lastUploadedFiles as $file): ?>
                            <li>
                                <div class="file-info">
                                    <i class="fas <?php echo getFontAwesomeIconClass($file['file_name']); ?> file-icon"></i>
                                    <span class="file-name"><?php echo htmlspecialchars($file['file_name']); ?></span>
                                </div>
                                <span class="file-meta"><?php echo formatBytes($file['file_size']); ?> - <?php echo date('Y-m-d H:i', strtotime($file['uploaded_at'])); ?></span>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>

            <div class="chart-container">
                <h3 data-lang-key="storageUsagePerMonth">Storage Usage Per Month</h3>
                <canvas id="storageUsageChart"></canvas>
            </div>
        </div>
    </div>

    <div class="overlay" id="mobileOverlay"></div>

    <script>
        let uploadsPerMonthChart;
        let fileTypeChart;
        let storageUsageChart;

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

            // Summary Dashboard
            'activityLogTitle': { 'id': 'Log Aktivitas', 'en': 'Activity Log' },
            'totalFilesUploaded': { 'id': 'Total File Diunggah', 'en': 'Total Files Uploaded' },
            'totalFolders': { 'id': 'Total Folder', 'en': 'Total Folders' },
            'totalStorageUsed': { 'id': 'Total Penyimpanan Terpakai', 'en': 'Total Storage Used' },
            'ofUsedText': { 'id': 'dari', 'en': 'of' }, // "of X used"
            'storageUsedPercentage': { 'id': 'Penyimpanan Terpakai (%)', 'en': 'Storage Used (%)' },
            'uploadsPerMonth': { 'id': 'Unggahan Per Bulan', 'en': 'Uploads Per Month' },
            'fileTypeDistribution': { 'id': 'Distribusi Tipe File', 'en': 'File Type Distribution' },
            'lastUploadedFiles': { 'id': 'File Terakhir Diunggah', 'en': 'Last Uploaded Files' },
            'noRecentUploads': { 'id': 'Tidak ada unggahan terbaru.', 'en': 'No recent uploads.' },
            'storageUsagePerMonth': { 'id': 'Penggunaan Penyimpanan Per Bulan', 'en': 'Storage Usage Per Month' },
            'filesUploaded': { 'id': 'File Diunggah', 'en': 'Files Uploaded' },
            'numberofFiles': { 'id': 'Jumlah File', 'en': 'Number of Files' },
            'month': { 'id': 'Bulan', 'en': 'Month' },
            'storageUsed': { 'id': 'Penyimpanan Terpakai', 'en': 'Storage Used' },
        };

        let currentLanguage = localStorage.getItem('lang') || 'id'; // Default to Indonesian

        function applyTranslation(lang) {
            document.querySelectorAll('[data-lang-key]').forEach(element => {
                const key = element.getAttribute('data-lang-key');
                if (translations[key] && translations[key][lang]) {
                    element.textContent = translations[key][lang];
                }
            });

            // Special handling for "of X used" text in cards
            const totalStorageCapacityElement = document.getElementById('totalStorageCapacity');
            if (totalStorageCapacityElement) {
                const usedStorage = document.getElementById('totalStorageUsed').textContent;
                const totalStorage = totalStorageCapacityElement.textContent.split(' ')[1]; // Get the total storage value
                if (translations['ofUsedText'] && translations['ofUsedText'][lang]) {
                    totalStorageCapacityElement.textContent = `${translations['ofUsedText'][lang]} ${totalStorage} ${lang === 'id' ? 'terpakai' : 'used'}`;
                }
            }

            // Update sidebar storage text
            const storageTextElement = document.getElementById('storageText');
            if (storageTextElement) {
                const usedStorage = storageTextElement.textContent.split(' ')[0]; // Get the used storage value
                const totalStorage = storageTextElement.textContent.split(' ')[2]; // Get the total storage value
                if (translations['ofUsedText'] && translations['ofUsedText'][lang]) {
                    storageTextElement.textContent = `${usedStorage} ${translations['ofUsedText'][lang]} ${totalStorage} ${lang === 'id' ? 'terpakai' : 'used'}`;
                }
            }

            // Update chart labels if charts are already rendered
            if (uploadsPerMonthChart) {
                uploadsPerMonthChart.data.datasets[0].label = translations['filesUploaded'][lang];
                uploadsPerMonthChart.options.scales.y.title.text = translations['numberofFiles'][lang];
                uploadsPerMonthChart.options.scales.x.title.text = translations['month'][lang];
                uploadsPerMonthChart.update();
            }
            if (fileTypeChart) {
                // Doughnut chart labels are dynamic, no direct translation needed for legend labels
                fileTypeChart.update();
            }
            if (storageUsageChart) {
                storageUsageChart.data.datasets[0].label = translations['storageUsed'][lang];
                storageUsageChart.options.scales.y.title.text = translations['storageUsed'][lang];
                storageUsageChart.options.scales.x.title.text = translations['month'][lang];
                storageUsageChart.update();
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Get language from localStorage
            currentLanguage = localStorage.getItem('lang') || 'id';

            // Initial chart rendering
            renderCharts(
                <?php echo json_encode($monthLabels); ?>,
                <?php echo json_encode($monthData); ?>,
                <?php echo json_encode($fileTypeLabels); ?>,
                <?php echo json_encode($fileTypeData); ?>,
                <?php echo json_encode($storageMonthLabels); ?>,
                <?php echo json_encode($storageMonthData); ?>
            );

            // Fetch and update dashboard data every 30 seconds
            setInterval(updateDashboardData, 30000); // Update every 30 seconds

            // Mobile sidebar elements
            const sidebar = document.querySelector('.sidebar');
            const sidebarToggleBtn = document.getElementById('sidebarToggleBtn');
            const mobileOverlay = document.getElementById('mobileOverlay');
            const mainContent = document.getElementById('mainContent'); // Get main-content for animations

            // Sidebar menu items for active state management
            const sidebarMenuItems = document.querySelectorAll('.sidebar-menu a');

            // --- Responsive Class Handling ---
            function applyDeviceClass() {
                const width = window.innerWidth;
                const body = document.body;

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

            // Initial application of device class
            applyDeviceClass();
            window.addEventListener('resize', applyDeviceClass);
            window.addEventListener('orientationchange', applyDeviceClass);

            // --- Mobile Sidebar Toggle ---
            sidebarToggleBtn.addEventListener('click', () => {
                sidebar.classList.toggle('show-mobile-sidebar');
                mobileOverlay.classList.toggle('show');
            });

            // Close mobile sidebar if overlay is clicked
            mobileOverlay.addEventListener('click', () => {
                if (sidebar.classList.contains('show-mobile-sidebar')) {
                    sidebar.classList.remove('show-mobile-sidebar');
                    mobileOverlay.classList.remove('show');
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

            // Set active class for current page in sidebar
            const currentPage = window.location.pathname.split('/').pop();
            sidebarMenuItems.forEach(item => {
                item.classList.remove('active');
                const itemHref = item.getAttribute('href');
                if (itemHref === currentPage || (currentPage === 'index.php' && itemHref === 'index.php')) {
                    item.classList.add('active');
                }
            });

            // Apply initial translation
            applyTranslation(currentLanguage);
        });

        // Function to get file icon class based on extension (replicated from PHP for client-side rendering)
        // This function should be kept in sync with getFontAwesomeIconClass in functions.php
        function getFileIconClass(fileName) {
            const ext = fileName.split('.').pop().toLowerCase();
            const iconClasses = {
                // Documents
                'pdf': 'fa-file-pdf',
                'doc': 'fa-file-word',
                'docx': 'fa-file-word',
                'xls': 'fa-file-excel',
                'xlsx': 'fa-file-excel',
                'ppt': 'fa-file-powerpoint',
                'pptx': 'fa-file-powerpoint',
                'txt': 'fa-file-alt',
                'rtf': 'fa-file-alt',
                'md': 'fa-file-alt',
                'csv': 'fa-file-csv',
                'odt': 'fa-file-alt',
                'odp': 'fa-file-powerpoint',
                'log': 'fa-file-alt',
                'tex': 'fa-file-alt',

                // Images
                'jpg': 'fa-file-image',
                'jpeg': 'fa-file-image',
                'png': 'fa-file-image',
                'gif': 'fa-file-image',
                'bmp': 'fa-file-image',
                'webp': 'fa-file-image',
                'svg': 'fa-file-image',
                'tiff': 'fa-file-image',

                // Audio
                'mp3': 'fa-file-audio',
                'wav': 'fa-file-audio',
                'ogg': 'fa-file-audio',
                'flac': 'fa-file-audio',
                'aac': 'fa-file-audio',
                'm4a': 'fa-file-audio',
                'alac': 'fa-file-audio',
                'wma': 'fa-file-audio',
                'opus': 'fa-file-audio',
                'amr': 'fa-file-audio',
                'mid': 'fa-file-audio',

                // Video
                'mp4': 'fa-file-video',
                'avi': 'fa-file-video',
                'mov': 'fa-file-video',
                'wmv': 'fa-file-video',
                'flv': 'fa-file-video',
                'webm': 'fa-file-video',
                '3gp': 'fa-file-video',
                'm4v': 'fa-file-video',
                'mpg': 'fa-file-video',
                'mpeg': 'fa-file-video',
                'ts': 'fa-file-video',
                'ogv': 'fa-file-video',

                // Archives
                'zip': 'fa-file-archive',
                'rar': 'fa-file-archive',
                '7z': 'fa-file-archive',
                'tar': 'fa-file-archive',
                'gz': 'fa-file-archive',
                'bz2': 'fa-file-archive',
                'xz': 'fa-file-archive',
                'iso': 'fa-file-archive',
                'cab': 'fa-file-archive',
                'arj': 'fa-file-archive',

                // Code
                'html': 'fa-file-code',
                'htm': 'fa-file-code',
                'css': 'fa-file-code',
                'js': 'fa-file-code',
                'php': 'fa-file-code',
                'py': 'fa-file-code',
                'java': 'fa-file-code',
                'json': 'fa-file-code',
                'xml': 'fa-file-code',
                'ts': 'fa-file-code',
                'tsx': 'fa-file-code',
                'jsx': 'fa-file-code',
                'vue': 'fa-file-code',
                'cpp': 'fa-file-code',
                'c': 'fa-file-code',
                'cs': 'fa-file-code',
                'rb': 'fa-file-code',
                'go': 'fa-file-code',
                'swift': 'fa-file-code',
                'sql': 'fa-database', // SQL uses fa-database in PHP, keep consistent
                'sh': 'fa-file-code',
                'bat': 'fa-file-code',
                'ini': 'fa-file-code',
                'yml': 'fa-file-code',
                'yaml': 'fa-file-code',
                'pl': 'fa-file-code',
                'r': 'fa-file-code',

                // Installation
                'exe': 'fa-box',
                'msi': 'fa-box',
                'apk': 'fa-box',
                'ipa': 'fa-box',
                'jar': 'fa-box',
                'appimage': 'fa-box',
                'dmg': 'fa-box',
                'bin': 'fa-box',

                // P2P
                'torrent': 'fa-magnet',
                'nzb': 'fa-magnet',
                'ed2k': 'fa-magnet',
                'part': 'fa-magnet',
                '!ut': 'fa-magnet',

                // Default
                'default': 'fa-file'
            };

            return iconClasses[ext] || iconClasses['default'];
        }

        // Helper function to format bytes (replicate from PHP's formatBytes)
        function formatBytes(bytes, precision = 2) {
            const units = ['B', 'KB', 'MB', 'GB', 'TB']; // Keep consistent with PHP
            bytes = Math.max(bytes, 0);
            const pow = Math.floor((bytes ? Math.log(bytes) : 0) / Math.log(1024));
            const p = Math.min(pow, units.length - 1);
            bytes /= (1 << (10 * p));
            return bytes.toFixed(precision) + ' ' + units[p];
        }

        function renderCharts(monthLabels, monthData, fileTypeLabels, fileTypeData, storageMonthLabels, storageMonthData) {
            // Destroy existing charts if they exist
            if (uploadsPerMonthChart) uploadsPerMonthChart.destroy();
            if (fileTypeChart) fileTypeChart.destroy();
            if (storageUsageChart) storageUsageChart.destroy();

            // Uploads Per Month Chart
            const uploadsPerMonthCtx = document.getElementById('uploadsPerMonthChart').getContext('2d');
            uploadsPerMonthChart = new Chart(uploadsPerMonthCtx, {
                type: 'bar',
                data: {
                    labels: monthLabels,
                    datasets: [{
                        label: translations['filesUploaded'][currentLanguage] || 'Files Uploaded',
                        data: monthData,
                        backgroundColor: 'var(--primary-color)', // Use Material primary color
                        borderColor: 'var(--primary-color)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: translations['numberofFiles'][currentLanguage] || 'Number of Files',
                                color: 'var(--text-color)'
                            },
                            ticks: {
                                color: 'var(--secondary-text-color)'
                            },
                            grid: {
                                color: 'var(--divider-color)'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: translations['month'][currentLanguage] || 'Month',
                                color: 'var(--text-color)'
                            },
                            ticks: {
                                color: 'var(--secondary-text-color)'
                            },
                            grid: {
                                color: 'var(--divider-color)'
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            labels: {
                                color: 'var(--text-color)'
                            }
                        }
                    }
                }
            });

            // File Type Distribution Chart
            const fileTypeCtx = document.getElementById('fileTypeChart').getContext('2d');
            const fileTypeColors = [
                '#E81123', '#0078D7', '#4CAF50', '#FF8C00', '#8E24AA', /* Material-inspired colors */
                '#F7B500', '#666666', '#00B294', '#D24726', '#2B579A',
                '#107C10', '#FFB900', '#505050', '#999999', '#0056b3',
                '#C8C8C8', '#E1E1E1', '#2D2D30', '#333333', '#F0F0F0'
            ];
            fileTypeChart = new Chart(fileTypeCtx, {
                type: 'doughnut',
                data: {
                    labels: fileTypeLabels,
                    datasets: [{
                        data: fileTypeData,
                        backgroundColor: fileTypeColors,
                        hoverOffset: 4
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: {
                                color: 'var(--text-color)'
                            }
                        },
                        title: {
                            display: false,
                            text: translations['fileTypeDistribution'][currentLanguage] || 'File Type Distribution'
                        }
                    }
                }
            });

            // Storage Usage Per Month Chart
            const storageUsageCtx = document.getElementById('storageUsageChart').getContext('2d');
            storageUsageChart = new Chart(storageUsageCtx, {
                type: 'bar',
                data: {
                    labels: storageMonthLabels,
                    datasets: [{
                        label: translations['storageUsed'][currentLanguage] || 'Storage Used',
                        data: storageMonthData,
                        backgroundColor: 'var(--success-color)', // Use Material success color
                        borderColor: 'var(--success-color)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        x: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: translations['month'][currentLanguage] || 'Month',
                                color: 'var(--text-color)'
                            },
                            ticks: {
                                color: 'var(--secondary-text-color)'
                            },
                            grid: {
                                color: 'var(--divider-color)'
                            }
                        },
                        y: {
                            title: {
                                display: true,
                                text: translations['storageUsed'][currentLanguage] || 'Storage Used',
                                color: 'var(--text-color)'
                            },
                            ticks: {
                                color: 'var(--secondary-text-color)',
                                callback: function(value, index, values) {
                                    return formatBytes(value); // Custom formatting function
                                }
                            },
                            grid: {
                                color: 'var(--divider-color)'
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            labels: {
                                color: 'var(--text-color)'
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    if (context.parsed.y !== null) {
                                        label += formatBytes(context.parsed.y);
                                    }
                                    return label;
                                }
                            }
                        }
                    }
                }
            });
        }

        async function updateDashboardData() {
            try {
                const response = await fetch('summary.php?ajax=1');
                const data = await response.json();

                // Update dashboard cards
                document.getElementById('totalFilesCount').textContent = data.totalFiles;
                document.getElementById('totalFoldersCount').textContent = data.totalFolders;
                document.getElementById('totalStorageUsed').textContent = data.formattedUsedStorage;
                
                const totalStorageCapacityElement = document.getElementById('totalStorageCapacity');
                if (totalStorageCapacityElement) {
                    totalStorageCapacityElement.textContent = `${translations['ofUsedText'][currentLanguage]} ${data.formattedTotalStorage} ${currentLanguage === 'id' ? 'terpakai' : 'used'}`;
                }

                document.getElementById('storageUsedPercentage').textContent = `${data.usedPercentage}%`;

                // Update sidebar storage info
                document.querySelector('.progress-bar').style.width = `${data.usedPercentage}%`;
                document.querySelector('.progress-bar-text').textContent = `${data.usedPercentage}%`;
                
                const storageTextElement = document.getElementById('storageText');
                if (storageTextElement) {
                    storageTextElement.textContent = `${data.formattedUsedStorage} ${translations['ofUsedText'][currentLanguage]} ${data.formattedTotalStorage} ${currentLanguage === 'id' ? 'terpakai' : 'used'}`;
                }
                
                const storageInfoDiv = document.querySelector('.storage-info');
                let storageFullMessage = storageInfoDiv.querySelector('.storage-full-message');

                if (data.isStorageFull) {
                    if (!storageFullMessage) {
                        const p = document.createElement('p');
                        p.className = 'storage-text storage-full-message';
                        p.style.color = 'var(--error-color)';
                        p.style.fontWeight = 'bold';
                        p.setAttribute('data-lang-key', 'storageFull'); // Add data-lang-key for translation
                        storageInfoDiv.appendChild(p);
                    }
                } else {
                    if (storageFullMessage) {
                        storageFullMessage.remove();
                    }
                }


                // Update Last Uploaded Files list
                const lastUploadedFilesList = document.getElementById('lastUploadedFilesList');
                lastUploadedFilesList.innerHTML = '';
                if (data.lastUploadedFiles.length === 0) {
                    const li = document.createElement('li');
                    li.setAttribute('data-lang-key', 'noRecentUploads');
                    li.textContent = translations['noRecentUploads'][currentLanguage] || 'No recent uploads.';
                    lastUploadedFilesList.appendChild(li);
                } else {
                    data.lastUploadedFiles.forEach(file => {
                        const listItem = document.createElement('li');
                        const uploadedDate = new Date(file.uploaded_at);
                        const formattedDate = uploadedDate.getFullYear() + '-' + 
                                            ('0' + (uploadedDate.getMonth()+1)).slice(-2) + '-' + 
                                            ('0' + uploadedDate.getDate()).slice(-2) + ' ' + 
                                            ('0' + uploadedDate.getHours()).slice(-2) + ':' + 
                                            ('0' + uploadedDate.getMinutes()).slice(-2);
                        listItem.innerHTML = `
                            <div class="file-info">
                                <i class="fas ${getFileIconClass(file.file_name)} file-icon"></i>
                                <span class="file-name">${htmlspecialchars(file.file_name)}</span>
                            </div>
                            <span class="file-meta">${formatBytes(file.file_size)} - ${formattedDate}</span>
                        `;
                        lastUploadedFilesList.appendChild(listItem);
                    });
                }

                // Update charts
                renderCharts(
                    data.monthLabels,
                    data.monthData,
                    data.fileTypeLabels,
                    data.fileTypeData,
                    data.storageMonthLabels,
                    data.storageMonthData
                );

                // Re-apply translation after AJAX update
                applyTranslation(currentLanguage);

            } catch (error) {
                console.error('Error fetching dashboard data:', error);
            }
        }

        // Helper function for htmlspecialchars (replicated from PHP)
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
    </script>
</body>
</html>
