<?php
/**
 * Files Page
 * Shows user's files and folders with management options
 */

// Get user data and storage information
$app = $GLOBALS['app'];
$userId = $app->getUserId();
$conn = $app->getConnection();

// Get current folder (if any)
$currentFolder = $_GET['folder'] ?? null;

// Get files and folders for current user and folder
$files = [];
$folders = [];

if ($currentFolder) {
    // Get files in specific folder
    $stmt = $conn->prepare("SELECT * FROM files WHERE user_id = ? AND folder_id = ? ORDER BY file_name ASC");
    $stmt->bind_param("ii", $userId, $currentFolder);
} else {
    // Get files in root directory
    $stmt = $conn->prepare("SELECT * FROM files WHERE user_id = ? AND (folder_id IS NULL OR folder_id = 0) ORDER BY file_name ASC");
    $stmt->bind_param("i", $userId);
}

$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $files[] = $row;
}
$stmt->close();

// Get folders
if ($currentFolder) {
    $stmt = $conn->prepare("SELECT * FROM folders WHERE user_id = ? AND parent_id = ? ORDER BY folder_name ASC");
    $stmt->bind_param("ii", $userId, $currentFolder);
} else {
    $stmt = $conn->prepare("SELECT * FROM folders WHERE user_id = ? AND (parent_id IS NULL OR parent_id = 0) ORDER BY folder_name ASC");
    $stmt->bind_param("i", $userId);
}

$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $folders[] = $row;
}
$stmt->close();

// Get breadcrumb navigation
$breadcrumbs = [];
if ($currentFolder) {
    $stmt = $conn->prepare("SELECT id, folder_name, parent_id FROM folders WHERE id = ?");
    $stmt->bind_param("i", $currentFolder);
    $stmt->execute();
    $result = $stmt->get_result();
    $folder = $result->fetch_assoc();
    $stmt->close();
    
    if ($folder) {
        $breadcrumbs[] = ['id' => $folder['id'], 'name' => $folder['folder_name']];
        
        // Build breadcrumb trail
        $parentId = $folder['parent_id'];
        while ($parentId) {
            $stmt = $conn->prepare("SELECT id, folder_name, parent_id FROM folders WHERE id = ?");
            $stmt->bind_param("i", $parentId);
            $stmt->execute();
            $result = $stmt->get_result();
            $parentFolder = $result->fetch_assoc();
            $stmt->close();
            
            if ($parentFolder) {
                array_unshift($breadcrumbs, ['id' => $parentFolder['id'], 'name' => $parentFolder['folder_name']]);
                $parentId = $parentFolder['parent_id'];
            } else {
                break;
            }
        }
    }
}

// Set page variables for header
$pageTitle = 'Files';
$storagePercentage = 0; // Will be calculated in dashboard
$usedStorage = 0;
$totalStorage = 500;
$userName = $app->getUserData()['username'] ?? 'User';
$userAvatar = $app->getUserData()['profile_picture'] ?? '../img/photo_profile_bg_blank.png';
?>

<div class="files-container">
    <!-- Breadcrumb Navigation -->
    <div class="breadcrumb-nav">
        <a href="?page=files" class="breadcrumb-item">
            <i class="fas fa-home"></i> Home
        </a>
        <?php foreach ($breadcrumbs as $crumb): ?>
            <span class="breadcrumb-separator">
                <i class="fas fa-chevron-right"></i>
            </span>
            <a href="?page=files&folder=<?php echo $crumb['id']; ?>" class="breadcrumb-item">
                <?php echo htmlspecialchars($crumb['name']); ?>
            </a>
        <?php endforeach; ?>
    </div>
    
    <!-- File Management Toolbar -->
    <div class="files-toolbar">
        <div class="toolbar-left">
            <button class="btn-primary" onclick="openUploadModal()">
                <i class="fas fa-upload"></i> Upload Files
            </button>
            <button class="btn-secondary" onclick="createFolder()">
                <i class="fas fa-folder-plus"></i> New Folder
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
                        <?php echo formatFileSize($file['file_size']); ?> • 
                        <?php echo date('M j, Y', strtotime($file['upload_date'])); ?>
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
                    <button class="btn-primary" onclick="openUploadModal()">
                        <i class="fas fa-upload"></i> Upload Files
                    </button>
                    <button class="btn-secondary" onclick="createFolder()">
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

<style>
.files-container {
    max-width: 1200px;
    margin: 0 auto;
}

.breadcrumb-nav {
    display: flex;
    align-items: center;
    margin-bottom: 20px;
    padding: 15px;
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
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
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
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
    background: var(--bg-secondary);
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
    background: var(--primary-color);
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
    margin: 0;
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
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}

.files-summary p {
    margin: 0;
    color: var(--text-secondary);
    font-size: 0.9em;
}

/* List View */
.files-grid.list-view {
    grid-template-columns: 1fr;
}

.files-grid.list-view .file-item {
    display: flex;
    align-items: center;
    gap: 20px;
}

.files-grid.list-view .item-icon {
    margin-bottom: 0;
    font-size: 1.5em;
}

.files-grid.list-view .item-info {
    flex-grow: 1;
    margin-bottom: 0;
}

.files-grid.list-view .item-actions {
    justify-content: flex-end;
}

@media (max-width: 768px) {
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
    window.location.href = `?page=files&folder=${folderId}`;
}

function downloadFile(fileId) {
    window.location.href = `../download.php?id=${fileId}`;
}

function createFolder() {
    const folderName = prompt('Enter folder name:');
    if (folderName && folderName.trim()) {
        // Implement folder creation
        console.log('Creating folder:', folderName);
        // You can add AJAX call here to create folder
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

// Utility function for file icons
function getFileIcon(fileType) {
    const iconMap = {
        'pdf': 'fas fa-file-pdf',
        'doc': 'fas fa-file-word',
        'docx': 'fas fa-file-word',
        'xls': 'fas fa-file-excel',
        'xlsx': 'fas fa-file-excel',
        'ppt': 'fas fa-file-powerpoint',
        'pptx': 'fas fa-file-powerpoint',
        'txt': 'fas fa-file-alt',
        'zip': 'fas fa-file-archive',
        'rar': 'fas fa-file-archive',
        'jpg': 'fas fa-file-image',
        'jpeg': 'fas fa-file-image',
        'png': 'fas fa-file-image',
        'gif': 'fas fa-file-image',
        'mp3': 'fas fa-file-audio',
        'mp4': 'fas fa-file-video'
    };
    
    return iconMap[fileType] || 'fas fa-file';
}

// Utility function for file size formatting
function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}
</script>
