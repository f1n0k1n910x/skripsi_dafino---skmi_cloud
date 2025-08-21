<?php
/**
 * Dashboard Page - Refactored to include index.php functionality
 * Shows overview of user's files and storage usage with full file management
 */

// Note: config.php and functions.php are already included by the main huda/index.php
// We can use the existing $conn and functions directly

// Start session
session_start();

// Check if user is logged in (should already be checked by main index.php)
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}

// Get user data and storage information
$userId = $_SESSION['user_id'] ?? null;

// Verify database connection exists
if (!isset($conn) || !$conn) {
    die("Database connection not available");
}

// Simple debug - test basic database query
$testQuery = "SELECT COUNT(*) as total FROM files";
$testResult = $conn->query($testQuery);
if ($testResult) {
    $totalFiles = $testResult->fetch_assoc()['total'];
    echo "<!-- Debug: Total files in database: $totalFiles -->";
} else {
    echo "<!-- Debug: Database query failed: " . $conn->error . " -->";
}

// Current folder ID, default to NULL for root
$currentFolderId = $_GET['folder'] ?? null;
$currentFolderName = 'Root';
$currentFolderPath = '';

// Define the base upload directory
$baseUploadDir = '../../uploads/';

// Fetch current folder details for breadcrumbs
$breadcrumbs = [];
if ($currentFolderId) {
    $path = [];
    $tempFolderId = $currentFolderId;
    while ($tempFolderId !== null) {
        $stmt = $conn->prepare("SELECT id, folder_name, parent_id FROM folders WHERE id = ?");
        $stmt->bind_param("i", $tempFolderId);
        $stmt->execute();
        $result = $stmt->get_result();
        $folder = $result->fetch_assoc();
        if ($folder) {
            array_unshift($path, ['id' => $folder['id'], 'name' => $folder['folder_name']]);
            $tempFolderId = $folder['parent_id'];
        } else {
            break;
        }
    }
    $breadcrumbs = $path;
    if (!empty($path)) {
        $currentFolderName = end($path)['name'];
    }
}

// Get search query and filters
$searchQuery = $_GET['search'] ?? '';
$sizeFilter = $_GET['size'] ?? 'none';
$fileTypeFilter = $_GET['file_type'] ?? 'all';

// Define file categories for filtering
$docExt = ['doc','docx','pdf','ppt','pptx','xls','xlsx','txt','odt','odp','rtf','md','log','csv','tex'];
$musicExt = ['mp3','wav','aac','ogg','flac','m4a','alac','wma','opus','amr','mid'];
$videoExt = ['mp4','mkv','avi','mov','wmv','flv','webm','3gp','m4v','mpg','mpeg','ts','ogv'];
$codeExt = ['html','htm','css','js','php','py','java','json','xml','ts','tsx','jsx','vue','cpp','c','cs','rb','go','swift','sql','sh','bat','ini','yml','yaml','md','pl','r'];
$archiveExt = ['zip','rar','7z','tar','gz','bz2','xz','iso','cab','arj'];
$instExt = ['exe','msi','apk','ipa','sh','bat','jar','appimage','dmg','bin'];
$ptpExt = ['torrent','nzb','ed2k','part','!ut'];
$imageExt = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg', 'tiff'];
$cadExt = ['dwg', 'dxf', 'dgn', 'iges', 'igs', 'step', 'stp', 'stl', '3ds', 'obj', 'sldprt', 'sldasm', 'ipt', 'iam', 'catpart', 'catproduct', 'prt', 'asm', 'fcstd', 'skp', 'x_t', 'x_b'];

// Map filter types to actual extensions
$filterExtensions = [];
switch ($fileTypeFilter) {
    case 'document': $filterExtensions = $docExt; break;
    case 'music': $filterExtensions = $musicExt; break;
    case 'video': $filterExtensions = $videoExt; break;
    case 'code': $filterExtensions = $codeExt; break;
    case 'archive': $filterExtensions = $archiveExt; break;
    case 'installation': $filterExtensions = $instExt; break;
    case 'p2p': $filterExtensions = $ptpExt; break;
    case 'image': $filterExtensions = $imageExt; break;
    case 'cad': $filterExtensions = $cadExt; break;
    case 'all': default: $filterExtensions = []; break;
}

// Fetch folders in current directory
$folders = [];
$sqlFolders = "SELECT id, folder_name, created_at, updated_at FROM folders WHERE parent_id <=> ?";
$folderParams = [$currentFolderId];
$folderTypes = "i";

if (!empty($searchQuery)) {
    $sqlFolders .= " AND folder_name LIKE ?";
    $searchTerm = '%' . $searchQuery . '%';
    $folderParams[] = $searchTerm;
    $folderTypes .= "s";
}

$sqlFolders .= " ORDER BY folder_name ASC";

$stmt = $conn->prepare($sqlFolders);
$stmt->bind_param($folderTypes, ...$folderParams);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $folders[] = $row;
}
$stmt->close();

echo "<!-- Debug: Found " . count($folders) . " folders -->";

// Calculate folder sizes for sorting
foreach ($folders as &$folder) {
    try {
        $folderPath = $baseUploadDir . getFolderPath($conn, $folder['id']);
        $folder['calculated_size'] = getFolderSize($folderPath);
        echo "<!-- Debug: Folder {$folder['folder_name']} path: $folderPath, size: {$folder['calculated_size']} -->";
    } catch (Exception $e) {
        $folder['calculated_size'] = 0;
        echo "<!-- Debug: Error calculating folder size: " . $e->getMessage() . " -->";
    }
}
unset($folder);

// Apply size sorting for folders
if ($sizeFilter === 'asc') {
    usort($folders, function($a, $b) {
        return $a['calculated_size'] <=> $b['calculated_size'];
    });
} elseif ($sizeFilter === 'desc') {
    usort($folders, function($a, $b) {
        return $b['calculated_size'] <=> $a['calculated_size'];
    });
}

// Fetch files in current directory
$files = [];
$sqlFiles = "SELECT id, file_name, file_path, file_size, file_type, uploaded_at FROM files WHERE folder_id <=> ?";
$params = [$currentFolderId];
$types = "i";

if (!empty($searchQuery)) {
    $sqlFiles .= " AND file_name LIKE ?";
    $searchTerm = '%' . $searchQuery . '%';
    $params[] = $searchTerm;
    $types .= "s";
}

if (!empty($filterExtensions)) {
    $placeholders = implode(',', array_fill(0, count($filterExtensions), '?'));
    $sqlFiles .= " AND file_type IN ($placeholders)";
    foreach ($filterExtensions as $ext) {
        $params[] = $ext;
        $types .= "s";
    }
}

// Apply size sorting for files
if ($sizeFilter === 'asc') {
    $sqlFiles .= " ORDER BY file_size ASC";
} elseif ($sizeFilter === 'desc') {
    $sqlFiles .= " ORDER BY file_size DESC";
} else {
    $sqlFiles .= " ORDER BY file_name ASC";
}

$stmt = $conn->prepare($sqlFiles);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $files[] = $row;
}
$stmt->close();

echo "<!-- Debug: Found " . count($files) . " files -->";

// Calculate storage usage
$totalStorageGB = 500;
$totalStorageBytes = $totalStorageGB * 1024 * 1024 * 1024;

$stmt = $conn->prepare("SELECT SUM(file_size) as total_size FROM files");
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$usedStorageBytes = $row['total_size'] ?? 0;
$stmt->close();

echo "<!-- Debug: Used storage bytes: $usedStorageBytes -->";

$usedStorageGB = round($usedStorageBytes / (1024 * 1024 * 1024), 2);
$storagePercentage = round(($usedStorageBytes / $totalStorageBytes) * 100, 1);
$freeStorageGB = $totalStorageGB - $usedStorageGB;

echo "<!-- Debug: Used storage GB: $usedStorageGB, Percentage: $storagePercentage% -->";

// Check if storage is full
$isStorageFull = $usedStorageBytes >= $totalStorageBytes;

// Get recent files for dashboard overview
$stmt = $conn->prepare("SELECT * FROM files WHERE user_id = ? ORDER BY uploaded_at DESC LIMIT 5");
$stmt->bind_param("i", $userId);
$stmt->execute();
$recentFiles = $stmt->get_result();
$stmt->close();

echo "<!-- Debug: Recent files count: " . $recentFiles->num_rows . " -->";

// Get file statistics
$stmt = $conn->prepare("SELECT COUNT(*) as total_files FROM files WHERE user_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$totalFiles = $result->fetch_assoc()['total_files'];
$stmt->close();

echo "<!-- Debug: Total files for user: $totalFiles -->";

$stmt = $conn->prepare("SELECT COUNT(*) as total_folders FROM folders WHERE user_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$totalFolders = isset($row['total_folders']) ? (int)$row['total_folders'] : 0;
$stmt->close();

echo "<!-- Debug: Total folders for user: $totalFolders -->";

// Get deleted files count for recycle bin
$stmt = $conn->prepare("SELECT COUNT(*) as deleted_count FROM deleted_files");
$stmt->execute();
$result = $stmt->get_result();
$deletedCount = $result->fetch_assoc()['deleted_count'];
$stmt->close();

echo "<!-- Debug: Deleted files count: $deletedCount -->";

// Set page variables for header
$pageTitle = 'Dashboard - My Drive';
$userName = $_SESSION['username'] ?? 'User';
$userAvatar = '../../img/photo_profile_bg_blank.png';

echo "<!-- Debug: User ID: $userId, Username: $userName -->";

// Utility functions (use the ones from functions.php instead of redefining)
if (!function_exists('getFileIcon')) {
    function getFileIcon($fileType) {
        $iconMap = [
            'pdf' => 'fas fa-file-pdf',
            'doc' => 'fas fa-file-word',
            'docx' => 'fas fa-file-word',
            'xls' => 'fas fa-file-excel',
            'xlsx' => 'fas fa-file-excel',
            'ppt' => 'fas fa-file-powerpoint',
            'pptx' => 'fas fa-file-powerpoint',
            'txt' => 'fas fa-file-alt',
            'zip' => 'fas fa-file-archive',
            'rar' => 'fas fa-file-archive',
            'jpg' => 'fas fa-file-image',
            'jpeg' => 'fas fa-file-image',
            'png' => 'fas fa-file-image',
            'gif' => 'fas fa-file-image',
            'mp3' => 'fas fa-file-audio',
            'mp4' => 'fas fa-file-video'
        ];
        
        return $iconMap[$fileType] ?? 'fas fa-file';
    }
}
?>

<div class="dashboard-container">
    <!-- Storage Overview Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-hdd"></i>
            </div>
            <div class="stat-content">
                <h3>Storage Used</h3>
                <p class="stat-value"><?php echo $usedStorageGB; ?> GB</p>
                <p class="stat-subtitle">of <?php echo $totalStorageGB; ?> GB</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-file"></i>
            </div>
            <div class="stat-content">
                <h3>Total Files</h3>
                <p class="stat-value"><?php echo number_format($totalFiles); ?></p>
                <p class="stat-subtitle">files uploaded</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-folder"></i>
            </div>
            <div class="stat-content">
                <h3>Total Folders</h3>
                <p class="stat-value"><?php echo number_format($totalFolders); ?></p>
                <p class="stat-subtitle">folders created</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-trash"></i>
            </div>
            <div class="stat-content">
                <h3>Recycle Bin</h3>
                <p class="stat-value"><?php echo $deletedCount ?? 0; ?></p>
                <p class="stat-subtitle">deleted items</p>
            </div>
        </div>
    </div>
    
    <!-- Storage Progress -->
    <div class="storage-overview">
        <h2>Storage Overview</h2>
        <div class="storage-progress">
            <div class="progress-info">
                <span>Used: <?php echo $usedStorageGB; ?> GB</span>
                <span>Free: <?php echo $freeStorageGB; ?> GB</span>
            </div>
            <div class="progress-bar-large">
                <div class="progress-fill" style="width: <?php echo $storagePercentage; ?>%"></div>
            </div>
            <div class="progress-percentage"><?php echo $storagePercentage; ?>% used</div>
        </div>
    </div>

    <!-- File Management Section -->
    <div class="file-management-section">
        <div class="section-header">
            <h2>File Management</h2>
            <div class="section-actions">
                <button class="btn-primary" onclick="openUploadModal()" <?php echo $isStorageFull ? 'disabled' : ''; ?>>
                    <i class="fas fa-upload"></i> Upload Files
                </button>
                <button class="btn-secondary" onclick="createFolder()" <?php echo $isStorageFull ? 'disabled' : ''; ?>>
                    <i class="fas fa-folder-plus"></i> New Folder
                </button>
            </div>
        </div>

        <!-- Breadcrumb Navigation -->
        <div class="breadcrumb-nav">
            <a href="?page=dashboard" class="breadcrumb-item">
                <i class="fas fa-home"></i> Home
            </a>
            <?php foreach ($breadcrumbs as $crumb): ?>
                <span class="breadcrumb-separator">
                    <i class="fas fa-chevron-right"></i>
                </span>
                <a href="?page=dashboard&folder=<?php echo $crumb['id']; ?>" class="breadcrumb-item">
                    <?php echo htmlspecialchars($crumb['name']); ?>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- File Management Toolbar -->
        <div class="files-toolbar">
            <div class="toolbar-left">
                <button class="btn-primary" onclick="openUploadModal()" <?php echo $isStorageFull ? 'disabled' : ''; ?>>
                    <i class="fas fa-upload"></i> Upload Files
                </button>
                <button class="btn-secondary" onclick="createFolder()" <?php echo $isStorageFull ? 'disabled' : ''; ?>>
                    <i class="fas fa-folder-plus"></i> New Folder
                </button>
                <button class="btn-danger" onclick="deleteSelected()">
                    <i class="fas fa-trash"></i> Delete Selected
                </button>
            </div>
            <div class="toolbar-right">
                <div class="view-toggle">
                    <button class="view-btn active" data-view="grid" title="Grid View">
                        <i class="fas fa-th"></i>
                    </button>
                    <button class="view-btn" data-view="list" title="List View">
                        <i class="fas fa-list"></i>
                    </button>
                </div>
                <div class="sort-controls">
                    <select id="sortSelect" onchange="sortFiles(this.value)">
                        <option value="name-asc">Name A-Z</option>
                        <option value="name-desc">Name Z-A</option>
                        <option value="date-newest">Date Newest</option>
                        <option value="date-oldest">Date Oldest</option>
                        <option value="size-largest">Size Largest</option>
                        <option value="size-smallest">Size Smallest</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Files and Folders Grid -->
        <div class="files-grid" id="filesGrid">
            <!-- Folders -->
            <?php foreach ($folders as $folder): ?>
                <div class="file-item folder" data-id="<?php echo $folder['id']; ?>" data-type="folder">
                    <div class="item-icon">
                        <i class="fas fa-folder"></i>
                    </div>
                    <div class="item-info">
                        <h4 class="item-name"><?php echo htmlspecialchars($folder['folder_name']); ?></h4>
                        <p class="item-meta">Folder</p>
                        <small><?php echo date('M j, Y', strtotime($folder['created_at'])); ?></small>
                    </div>
                    <div class="item-actions">
                        <button class="btn-icon" onclick="openFolder(<?php echo $folder['id']; ?>)" title="Open Folder">
                            <i class="fas fa-folder-open"></i>
                        </button>
                        <button class="btn-icon" onclick="showFolderMenu(<?php echo $folder['id']; ?>, event)" title="More Options">
                            <i class="fas fa-ellipsis-v"></i>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <!-- Files -->
            <?php foreach ($files as $file): ?>
                <div class="file-item file" data-id="<?php echo $file['id']; ?>" data-type="file">
                    <div class="item-icon">
                        <i class="<?php echo getFileIcon($file['file_type']); ?>"></i>
                    </div>
                    <div class="item-info">
                        <h4 class="item-name"><?php echo htmlspecialchars($file['file_name']); ?></h4>
                        <p class="item-meta">
                            <?php echo formatBytes($file['file_size']); ?> • 
                            <?php echo date('M j, Y', strtotime($file['uploaded_at'])); ?>
                        </p>
                    </div>
                    <div class="item-actions">
                        <button class="btn-icon" onclick="downloadFile(<?php echo $file['id']; ?>)" title="Download">
                            <i class="fas fa-download"></i>
                        </button>
                        <button class="btn-icon" onclick="showFileMenu(<?php echo $file['id']; ?>, event)" title="More Options">
                            <i class="fas fa-ellipsis-v"></i>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <!-- Empty State -->
            <?php if (empty($folders) && empty($files)): ?>
                <div class="empty-state">
                    <i class="fas fa-folder-open"></i>
                    <h3>No files or folders</h3>
                    <p>This folder is empty. Start by uploading files or creating folders.</p>
                    <div class="empty-actions">
                        <button class="btn-primary" onclick="openUploadModal()" <?php echo $isStorageFull ? 'disabled' : ''; ?>>
                            <i class="fas fa-upload"></i> Upload Files
                        </button>
                        <button class="btn-secondary" onclick="createFolder()" <?php echo $isStorageFull ? 'disabled' : ''; ?>>
                            <i class="fas fa-folder-plus"></i> Create Folder
                        </button>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- File Count Summary -->
        <div class="files-summary">
            <p>
                <?php 
                $totalItems = count($folders) + count($files);
                echo $totalItems . ' item' . ($totalItems !== 1 ? 's' : '');
                if (!empty($folders)) {
                    echo ' (' . count($folders) . ' folder' . (count($folders) !== 1 ? 's' : '') . ')';
                }
                if (!empty($files)) {
                    echo ' (' . count($files) . ' file' . (count($files) !== 1 ? 's' : '') . ')';
                }
                ?>
            </p>
        </div>
    </div>

    <!-- Recent Files -->
    <div class="recent-files">
        <h2>Recent Files</h2>
        <?php if ($recentFiles->num_rows > 0): ?>
            <div class="files-grid">
                <?php while ($file = $recentFiles->fetch_assoc()): ?>
                    <div class="file-card">
                        <div class="file-icon">
                            <i class="<?php echo getFileIcon($file['file_type']); ?>"></i>
                        </div>
                        <div class="file-info">
                            <h4><?php echo htmlspecialchars($file['file_name']); ?></h4>
                            <p><?php echo formatBytes($file['file_size']); ?></p>
                            <small><?php echo date('M j, Y', strtotime($file['uploaded_at'])); ?></small>
                        </div>
                        <div class="file-actions">
                            <button class="btn-icon" title="Download">
                                <i class="fas fa-download"></i>
                            </button>
                            <button class="btn-icon" title="Share">
                                <i class="fas fa-share"></i>
                            </button>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-folder-open"></i>
                <h3>No files yet</h3>
                <p>Start by uploading your first file</p>
                <button class="btn-primary" onclick="openUploadModal()">Upload Files</button>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.dashboard-container {
    max-width: 1200px;
    margin: 0 auto;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    padding: 25px;
    border-radius: 15px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    display: flex;
    align-items: center;
    gap: 20px;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.15);
}

.stat-icon {
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
    border-radius: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.5em;
}

.stat-content h3 {
    margin: 0 0 5px 0;
    font-size: 0.9em;
    color: var(--text-secondary);
    font-weight: 500;
}

.stat-value {
    margin: 0 0 5px 0;
    font-size: 1.8em;
    font-weight: 600;
    color: var(--text-primary);
}

.stat-subtitle {
    margin: 0;
    font-size: 0.8em;
    color: var(--text-secondary);
}

.storage-overview {
    background: white;
    padding: 30px;
    border-radius: 15px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    margin-bottom: 30px;
}

.storage-overview h2 {
    margin: 0 0 20px 0;
    color: var(--text-primary);
}

.storage-progress {
    margin-bottom: 20px;
}

.progress-info {
    display: flex;
    justify-content: space-between;
    margin-bottom: 10px;
    font-size: 0.9em;
    color: var(--text-secondary);
}

.progress-bar-large {
    width: 100%;
    height: 12px;
    background-color: var(--bg-secondary);
    border-radius: 6px;
    overflow: hidden;
    margin-bottom: 10px;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--accent-color), var(--primary-color));
    border-radius: 6px;
    transition: width 0.5s ease;
}

.progress-percentage {
    text-align: center;
    font-weight: 600;
    color: var(--primary-color);
}

.file-management-section {
    background: white;
    padding: 30px;
    border-radius: 15px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    margin-bottom: 30px;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
}

.section-header h2 {
    margin: 0;
    color: var(--text-primary);
}

.section-actions {
    display: flex;
    gap: 15px;
}

.breadcrumb-nav {
    display: flex;
    align-items: center;
    margin-bottom: 20px;
    padding: 15px;
    background: var(--bg-secondary);
    border-radius: 10px;
}

.breadcrumb-item {
    color: var(--primary-color);
    text-decoration: none;
    font-weight: 500;
    transition: color 0.3s ease;
}

.breadcrumb-item:hover {
    color: var(--primary-dark);
}

.breadcrumb-separator {
    margin: 0 10px;
    color: var(--text-secondary);
}

.files-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding: 20px;
    background: var(--bg-secondary);
    border-radius: 10px;
}

.toolbar-left {
    display: flex;
    gap: 15px;
}

.toolbar-right {
    display: flex;
    align-items: center;
    gap: 20px;
}

.view-toggle {
    display: flex;
    background: white;
    border-radius: 8px;
    padding: 4px;
}

.view-btn {
    background: none;
    border: none;
    padding: 8px 12px;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.view-btn.active {
    background-color: var(--primary-color);
    color: white;
}

.sort-controls select {
    padding: 8px 12px;
    border: 1px solid var(--border-color);
    border-radius: 6px;
    background: white;
    font-size: 0.9em;
}

.files-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 20px;
}

.file-item {
    background: white;
    border: 1px solid var(--border-color);
    border-radius: 10px;
    padding: 20px;
    transition: all 0.3s ease;
    cursor: pointer;
}

.file-item:hover {
    border-color: var(--primary-color);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}

.file-item.folder .item-icon {
    color: var(--warning-color);
}

.file-item.file .item-icon {
    color: var(--primary-color);
}

.item-icon {
    font-size: 2.5em;
    margin-bottom: 15px;
    text-align: center;
}

.item-info {
    margin-bottom: 15px;
}

.item-name {
    margin: 0 0 5px 0;
    font-size: 1em;
    color: var(--text-primary);
    word-break: break-word;
}

.item-meta {
    margin: 0 0 3px 0;
    font-size: 0.85em;
    color: var(--text-secondary);
}

.item-actions {
    display: flex;
    gap: 8px;
    justify-content: center;
}

.btn-icon {
    width: 35px;
    height: 35px;
    border: none;
    background-color: var(--bg-secondary);
    border-radius: 8px;
    color: var(--text-secondary);
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-icon:hover {
    background-color: var(--primary-color);
    color: white;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: var(--text-secondary);
    grid-column: 1 / -1;
}

.empty-state i {
    font-size: 4em;
    margin-bottom: 20px;
    color: var(--border-color);
}

.empty-state h3 {
    margin: 0 0 10px 0;
    color: var(--text-primary);
}

.empty-state p {
    margin: 0 0 20px 0;
}

.empty-actions {
    display: flex;
    gap: 15px;
    justify-content: center;
}

.files-summary {
    text-align: center;
    padding: 20px;
    background: var(--bg-secondary);
    border-radius: 10px;
}

.files-summary p {
    margin: 0;
    color: var(--text-secondary);
    font-size: 0.9em;
}

.recent-files {
    background: white;
    padding: 30px;
    border-radius: 15px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    margin-bottom: 30px;
}

.recent-files h2 {
    margin: 0 0 20px 0;
    color: var(--text-primary);
}

.files-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 15px;
}

.file-card {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 20px;
    border: 1px solid var(--border-color);
    border-radius: 10px;
    transition: all 0.3s ease;
}

.file-card:hover {
    border-color: var(--primary-color);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.file-card .file-icon {
    width: 50px;
    height: 50px;
    background-color: var(--bg-secondary);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary-color);
    font-size: 1.2em;
}

.file-card .file-info {
    flex-grow: 1;
}

.file-card .file-info h4 {
    margin: 0 0 5px 0;
    font-size: 0.95em;
    color: var(--text-primary);
}

.file-card .file-info p {
    margin: 0 0 3px 0;
    font-size: 0.85em;
    color: var(--text-secondary);
}

.file-card .file-info small {
    font-size: 0.8em;
    color: var(--text-secondary);
}

.file-card .file-actions {
    display: flex;
    gap: 8px;
}

.btn-primary {
    background-color: var(--primary-color);
    color: white;
    border: none;
    padding: 12px 25px;
    border-radius: 25px;
    font-size: 0.9em;
    cursor: pointer;
    transition: background-color 0.3s ease;
    display: flex;
    align-items: center;
    gap: 8px;
}

.btn-primary:hover {
    background-color: var(--primary-dark);
}

.btn-primary:disabled {
    background-color: var(--text-secondary);
    cursor: not-allowed;
}

.btn-secondary {
    background-color: var(--bg-secondary);
    color: var(--text-primary);
    border: 1px solid var(--border-color);
    padding: 12px 25px;
    border-radius: 25px;
    font-size: 0.9em;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 8px;
}

.btn-secondary:hover {
    background-color: var(--border-color);
}

.btn-danger {
    background-color: var(--error-color);
    color: white;
    border: none;
    padding: 12px 25px;
    border-radius: 25px;
    font-size: 0.9em;
    cursor: pointer;
    transition: background-color 0.3s ease;
    display: flex;
    align-items: center;
    gap: 8px;
}

.btn-danger:hover {
    background-color: #c62828;
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
    }
    
    .stat-card {
        padding: 20px;
    }
    
    .stat-icon {
        width: 50px;
        height: 50px;
        font-size: 1.3em;
    }
    
    .section-header {
        flex-direction: column;
        gap: 15px;
        align-items: stretch;
    }
    
    .section-actions {
        justify-content: center;
    }
    
    .files-toolbar {
        flex-direction: column;
        gap: 20px;
        align-items: stretch;
    }
    
    .toolbar-left,
    .toolbar-right {
        justify-content: center;
    }
    
    .files-grid {
        grid-template-columns: 1fr;
    }
    
    .empty-actions {
        flex-direction: column;
        align-items: center;
    }
}
</style>

<script>
// File management functions
function openFolder(folderId) {
    window.location.href = `?page=dashboard&folder=${folderId}`;
}

function downloadFile(fileId) {
    window.location.href = `../../download.php?id=${fileId}`;
}

function createFolder() {
    const folderName = prompt('Enter folder name:');
    if (folderName && folderName.trim()) {
        // Implement folder creation
        console.log('Creating folder:', folderName);
        // You can add AJAX call here to create folder
    }
}

function deleteSelected() {
    const selectedItems = document.querySelectorAll('.file-item input[type="checkbox"]:checked');
    if (selectedItems.length === 0) {
        alert('Please select items to delete');
        return;
    }
    
    if (confirm(`Are you sure you want to delete ${selectedItems.length} selected item(s)?`)) {
        // Implement delete functionality
        console.log('Deleting selected items');
    }
}

function showFileMenu(fileId, event) {
    event.stopPropagation();
    // Implement file context menu
    console.log('Show file menu for:', fileId);
}

function showFolderMenu(folderId, event) {
    event.stopPropagation();
    // Implement folder context menu
    console.log('Show folder menu for:', folderId);
}

function sortFiles(sortType) {
    // Implement file sorting
    console.log('Sorting files by:', sortType);
}

// View toggle functionality
document.addEventListener('DOMContentLoaded', function() {
    const viewBtns = document.querySelectorAll('.view-btn');
    const filesGrid = document.getElementById('filesGrid');
    
    viewBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const view = this.dataset.view;
            
            // Update active button
            viewBtns.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            
            // Update grid view
            if (view === 'list') {
                filesGrid.classList.add('list-view');
            } else {
                filesGrid.classList.remove('list-view');
            }
        });
    });
});

// Utility function for file size formatting
function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}
</script>
