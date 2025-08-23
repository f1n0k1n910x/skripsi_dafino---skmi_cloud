<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? 'SKMI Cloud Storage'; ?> - Huda Version</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
</head>
<body>
    <div class="app-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <img src="../img/logo.png" alt="SKMI Cloud Logo">
                <h2>SKMI Cloud</h2>
            </div>
            
            <nav class="sidebar-menu">
                <ul>
                    <li><a href="?page=dashboard" class="<?php echo ($_GET['page'] ?? 'dashboard') === 'dashboard' ? 'active' : ''; ?>">
                        <i class="fas fa-home"></i> Dashboard
                    </a></li>
                    <li><a href="?page=files" class="<?php echo ($_GET['page'] ?? '') === 'files' ? 'active' : ''; ?>">
                        <i class="fas fa-folder"></i> Files
                    </a></li>
                    <li><a href="?page=recycle_bin" class="<?php echo ($_GET['page'] ?? '') === 'recycle_bin' ? 'active' : ''; ?>">
                        <i class="fas fa-trash"></i> Recycle Bin
                    </a></li>
                    <li><a href="?page=profile" class="<?php echo ($_GET['page'] ?? '') === 'profile' ? 'active' : ''; ?>">
                        <i class="fas fa-user"></i> Profile
                    </a></li>
                    <li><a href="?page=members" class="<?php echo ($_GET['page'] ?? '') === 'members' ? 'active' : ''; ?>">
                        <i class="fas fa-users"></i> Members
                    </a></li>
                </ul>
            </nav>
            
            <!-- Storage Info -->
            <div class="storage-info">
                <h4>Storage Usage</h4>
                <div class="progress-bar-container">
                    <div class="progress-bar" style="width: <?php echo $storagePercentage ?? 0; ?>%">
                        <span class="progress-bar-text"><?php echo $storagePercentage ?? 0; ?>%</span>
                    </div>
                </div>
                <div class="storage-text">
                    <?php echo $usedStorage ?? '0'; ?> GB used of <?php echo $totalStorage ?? '500'; ?> GB
                </div>
            </div>
            
            <!-- User Info -->
            <div class="user-info">
                <div class="user-avatar">
                    <img src="<?php echo $userAvatar ?? '../img/photo_profile_bg_blank.png'; ?>" alt="User Avatar">
                </div>
                <div class="user-details">
                    <span class="user-name"><?php echo $userName ?? 'User'; ?></span>
                    <a href="../logout.php" class="logout-btn">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <header class="content-header">
                <h1><?php echo $pageTitle ?? 'Dashboard'; ?></h1>
                <div class="header-actions">
                    <div class="search-container">
                        <input type="text" id="searchInput" placeholder="Search files..." class="search-input">
                        <button class="search-btn">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                    <button class="upload-btn">
                        <i class="fas fa-upload"></i> Upload
                    </button>
                </div>
            </header>
            
            <div class="content-body">
