<?php include 'views/partials/header.php'; ?>

<?php 
$currentFolderPath = ''; // To build the full path for uploads and display
$currentFolderId = isset($_GET['folder']) ? (int)$_GET['folder'] : NULL;
?>

    <!-- Sidebar -->
    <?php include 'views/partials/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="header-main">
            <button class="sidebar-toggle-btn" id="sidebarToggleBtn"><i class="fas fa-bars"></i></button>
            <h1 class="my-drive-title">My Drive</h1>
            <div class="search-bar search-bar-desktop">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" placeholder="Search files..." value="<?php echo htmlspecialchars($searchQuery); ?>">
            </div>
            <!-- OLD: Filter buttons were here for mobile/tablet -->
        </div>

        <!-- Mobile Search Bar (moved below header for smaller screens) -->
        <div class="search-bar search-bar-mobile">
            <i class="fas fa-search"></i>
            <input type="text" id="searchInputMobile" placeholder="Search files..." value="<?php echo htmlspecialchars($searchQuery); ?>">
        </div>

        <div class="toolbar">
            <div class="dropdown-container size-filter-dropdown-container" >
                <button id="sizeFilterBtn" class="filter-button">Action</button>
                <div class="dropdown-content size-filter-dropdown-content" style="position: absolute; top: 100%; left: 3%; transform: translateX(-50%); margin-top: 8px; padding:5px 0; border-radius:4px;">
                    <a id="uploadFileBtn" href="#" data-size="desc"><i class="fas fa-upload"></i> Upload File</a>
                    <a id="createFolderBtn" href="#" data-size="asc" ><i class="fas fa-folder-plus"> </i> Create Folder</a>
                    <a id="deleteSelectedBtn" href="#" data-size="none"><i class="fas fa-trash-alt"></i> Delete Selected</a>
                </div>
            </div>
            <div class="toolbar-right">
                <!-- Archive Button with Dropdown (Hidden on mobile/tablet portrait) -->
                <div class="dropdown-container archive-dropdown-container">
                    <button id="archiveSelectedBtn" class="filter-button"><i class="fas fa-archive"></i></button>
                    <div class="dropdown-content archive-dropdown-content">
                        <a href="#" data-format="zip">.zip (PHP Native)</a>
                    </div>
                </div>

                <!-- File Type Filter Button (Hidden on mobile/tablet portrait) -->
                <div class="dropdown-container file-type-filter-dropdown-container">
                    <button id="fileTypeFilterBtn" class="filter-button"><i class="fas fa-filter"></i></button>
                    <div class="dropdown-content file-type-filter-dropdown-content">
                        <a href="#" data-filter="all">All Files</a>
                        <a href="#" data-filter="document">Documents</a>
                        <a href="#" data-filter="image">Images</a>
                        <a href="#" data-filter="music">Music</a>
                        <a href="#" data-filter="video">Videos</a>
                        <a href="#" data-filter="code">Code Files</a>
                        <a href="#" data-filter="archive">Archives</a>
                        <a href="#" data-filter="installation">Installation Files</a>
                        <a href="#" data-filter="p2p">Peer-to-Peer Files</a>
                        <a href="#" data-filter="cad">CAD Files</a>
                    </div>
                </div>

                <!-- Size Filter Button (Replaces Release Date Filter) -->
                <div class="dropdown-container size-filter-dropdown-container">
                    <button id="sizeFilterBtn" class="filter-button"><i class="fas fa-sort-amount-down"></i></button>
                    <div class="dropdown-content size-filter-dropdown-content">
                        <a href="#" data-size="desc">Size (Large to Small)</a>
                        <a href="#" data-size="asc">Size (Small to Large)</a>
                        <a href="#" data-size="none">Default (Alphabetical)</a>
                    </div>
                </div>

                <!-- View Toggle Buttons (Hidden on mobile/tablet portrait) -->
                <div class="view-toggle">
                    <button id="listViewBtn" class="active"><i class="fas fa-list"></i></button>
                    <button id="gridViewBtn"><i class="fas fa-th-large"></i></button>
                </div>
            </div>
        </div>

        <!-- NEW: Filter buttons moved here for mobile/tablet -->
        <div class="toolbar-filter-buttons">
            <!-- Archive Button with Dropdown -->
            <div class="dropdown-container archive-dropdown-container">
                <button id="archiveSelectedBtnHeader" class="filter-button" style="background-color: var(--metro-warning);"><i class="fas fa-archive"></i></button>
                <div class="dropdown-content archive-dropdown-content">
                    <a href="#" data-format="zip">.zip (PHP Native)</a>
                </div>
            </div>

            <!-- File Type Filter Button -->
            <div class="dropdown-container file-type-filter-dropdown-container">
                <button id="fileTypeFilterBtnHeader" class="filter-button"><i class="fas fa-filter"></i></button>
                <div class="dropdown-content file-type-filter-dropdown-content">
                    <a href="#" data-filter="all">All Files</a>
                    <a href="#" data-filter="document">Documents</a>
                    <a href="#" data-filter="image">Images</a>
                    <a href="#" data-filter="music">Music</a>
                    <a href="#" data-filter="video">Videos</a>
                    <a href="#" data-filter="code">Code Files</a>
                    <a href="#" data-filter="archive">Archives</a>
                    <a href="#" data-filter="installation">Installation Files</a>
                    <a href="#" data-filter="p2p">Peer-to-Peer Files</a>
                    <a href="#" data-filter="cad">CAD Files</a>
                </div>
            </div>

            <!-- Size Filter Button (Replaces Release Date Filter) -->
            <div class="dropdown-container size-filter-dropdown-container">
                <button id="sizeFilterBtnHeader" class="filter-button"><i class="fas fa-sort-amount-down"></i></button>
                <div class="dropdown-content size-filter-dropdown-content">
                    <a href="#" data-size="desc">Size (Large to Small)</a>
                    <a href="#" data-size="asc">Size (Small to Large)</a>
                    <a href="#" data-size="none">Default (Alphabetical)</a>
                </div>
            </div>

            <!-- View Toggle Buttons -->
            <div class="view-toggle">
                <button id="listViewBtnHeader" class="active"><i class="fas fa-list"></i></button>
                <button id="gridViewBtnHeader"><i class="fas fa-th-large"></i></button>
            </div>
        </div>

        <div class="breadcrumbs">
            <a href="index.php"><i class="fas fa-home"></i> Root</a>
            <?php foreach ($breadcrumbs as $crumb): ?>
                <span>/</span> <a href="index.php?folder=<?php echo $crumb['id']; ?>"><?php echo htmlspecialchars($crumb['name'] ?? ""); ?></a>
            <?php endforeach; ?>
            <?php if (!empty($searchQuery)): ?>
                <span>/</span> <span>Search results for "<?php echo htmlspecialchars($searchQuery); ?>"</span>
            <?php endif; ?>
        </div>

        <div class="file-list-container">
            <div id="fileListView" class="file-view">
                <div class="file-table-wrapper">
                    <table class="file-table">
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="selectAllCheckbox"></th>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Size</th>
                                <th>Last Modified</th>
                                <th>Actions</th> <!-- Added Actions column header -->
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($folders) && empty($files) && !empty($searchQuery)): ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; padding: 20px;">No files or folders found matching "<?php echo htmlspecialchars($searchQuery); ?>"</td>
                                </tr>
                            <?php elseif (empty($folders) && empty($files) && empty($searchQuery)): ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; padding: 20px;">No files or folders found in this directory.</td>
                                </tr>
                            <?php endif; ?>
                            <?php foreach ($folders as $folder): ?>
                                <tr class="file-item" data-id="<?php echo $folder['id']; ?>" data-type="folder" data-name="<?php echo htmlspecialchars($folder['folder_name']); ?>" data-path="<?php echo htmlspecialchars($baseUploadDir . getFolderPath($conn, $folder['id'])); ?>" tabindex="0">
                                    <td><input type="checkbox" class="file-checkbox" data-id="<?php echo $folder['id']; ?>" data-type="folder"></td>
                                    <td class="file-name-cell">
                                        <i class="fas fa-folder file-icon folder"></i>
                                        <a href="index.php?folder=<?php echo $folder['id']; ?>" class="file-link-clickable" onclick="event.stopPropagation();"><?php echo htmlspecialchars($folder['folder_name']); ?></a>
                                    </td>
                                    <td>Folder</td>
                                    <td>
                                        <?php
                                            // NEW: Calculate and display folder size
                                            // Get the full physical path of the folder
                                            echo formatBytes($folder['calculated_size']);
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                            // Display modification date if available, otherwise creation date
                                            $displayDate = $folder['updated_at'] ?? $folder['created_at'];
                                            echo date('Y-m-d H:i', strtotime($displayDate));
                                        ?>
                                    </td>
                                    <td>
                                        <button class="item-more" aria-haspopup="true" aria-label="More">⋮</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                            <?php foreach ($files as $file): ?>
                                <tr class="file-item" data-id="<?php echo $file['id']; ?>" data-type="file" data-name="<?php echo htmlspecialchars($file['file_name']); ?>" data-path="<?php echo htmlspecialchars($file['file_path']); ?>" data-file-type="<?php echo strtolower($file['file_type'] ?? ""); ?>" tabindex="0">
                                    <td><input type="checkbox" class="file-checkbox" data-id="<?php echo $file['id']; ?>" data-type="file"></td>
                                    <td class="file-name-cell">
                                        <i class="fas <?php echo getFontAwesomeIconClass($file['file_name']); ?> file-icon <?php echo getFileColorClassPhp($file['file_name']); ?>"></i>
                                        <a href="views/pages/file-view.php?file_id=<?php echo $file['id']; ?>" class="file-link-clickable" onclick="event.stopPropagation();"><?php echo htmlspecialchars($file['file_name']); ?></a>
                                        </td>
                                    <td><?php echo strtoupper($file['file_type'] ?? ""); ?></td>
                                    <td><?php echo formatBytes($file['file_size'] ?? ""); ?></td>
                                    <td><?php echo !empty($file['uploaded_at']) ? date('Y-m-d H:i', strtotime($file['uploaded_at'])) : 'N/A'; ?></td>
                                    <td>
                                        <button class="item-more" aria-haspopup="true" aria-label="More">⋮</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div id="fileGridView" class="file-view hidden">
                <div class="file-grid">
                    <?php if (empty($folders) && empty($files) && !empty($searchQuery)): ?>
                        <div style="grid-column: 1 / -1; text-align: center; padding: 20px;">No files or folders found matching "<?php echo htmlspecialchars($searchQuery); ?>"</div>
                    <?php elseif (empty($folders) && empty($files) && empty($searchQuery)): ?>
                        <div style="grid-column: 1 / -1; text-align: center; padding: 20px;">No files or folders found in this directory.</div>
                    <?php endif; ?>

                    <?php foreach ($folders as $folder): ?>
                        <div class="grid-item file-item" data-id="<?php echo $folder['id']; ?>" data-type="folder" data-name="<?php echo htmlspecialchars($folder['folder_name']); ?>" data-path="<?php echo htmlspecialchars($baseUploadDir . getFolderPath($conn, $folder['id'])); ?>" tabindex="0">
                            <input type="checkbox" class="file-checkbox" data-id="<?php echo $folder['id']; ?>" data-type="folder">
                            <div class="grid-thumbnail">
                                <i class="fas fa-folder file-icon folder"></i>
                                <span class="file-type-label">Folder</span>
                            </div>
                            <a href="index.php?folder=<?php echo $folder['id']; ?>" class="file-name file-link-clickable" onclick="event.stopPropagation();"><?php echo htmlspecialchars($folder['folder_name']); ?></a>
                            <span class="file-size">
                                <?php
                                    echo formatBytes($folder['calculated_size']);
                                ?>
                            </span>
                            <button class="item-more" aria-haspopup="true" aria-label="More">⋮</button>
                        </div>
                    <?php endforeach; ?>

                    <?php foreach ($files as $file): ?>
                        <div class="grid-item file-item" data-id="<?php echo $file['id']; ?>" data-type="file" data-name="<?php echo htmlspecialchars($file['file_name']); ?>" data-path="<?php echo htmlspecialchars($file['file_path']); ?>" data-file-type="<?php echo strtolower($file['file_type'] ?? ""); ?>" tabindex="0">
                        <input type="checkbox" class="file-checkbox" data-id="<?php echo $file['id'] ?? ''; ?>" data-type="file">
                        <div class="grid-thumbnail">
                                <?php
                                $fileExt = strtolower(pathinfo($file['file_name'], PATHINFO_EXTENSION) ?? "");
                                $filePath = htmlspecialchars($file['file_path']);
                                $fileName = htmlspecialchars($file['file_name']);

                                if (in_array($fileExt, $imageExt)):
                                ?>
                                    <img src="<?php echo $filePath; ?>" alt="<?php echo $fileName; ?>">
                                <?php elseif (in_array($fileExt, $docExt)):
                                    $content = @file_get_contents($file['file_path'], false, null, 0, 500);
                                    if ($content !== false) {
                                        echo '<pre>' . htmlspecialchars(substr(strip_tags($content), 0, 200)) . '...</pre>';
                                    } else {
                                        echo '<i class="fas ' . getFontAwesomeIconClass($file['file_name']) . ' file-icon ' . getFileColorClassPhp($file['file_name']) . '"></i>';
                                    }
                                ?>
                                <?php elseif (in_array($fileExt, $musicExt)): ?>
                                    <audio controls style='width:100%; height: auto;'><source src='<?php echo $filePath; ?>' type='audio/<?php echo $fileExt; ?>'></audio>
                                <?php elseif (in_array($fileExt, $videoExt)): ?>
                                    <video controls style='width:100%; height:100%;'><source src='<?php echo $filePath; ?>' type='video/<?php echo $fileExt; ?>'></video>
                                <?php elseif (in_array($fileExt, $codeExt)):
                                    $code = @file_get_contents($file['file_path'], false, null, 0, 500);
                                    if ($code !== false) {
                                        echo '<pre>' . htmlspecialchars(substr($code, 0, 200)) . '...</pre>';
                                    } else {
                                        echo '<i class="fas ' . getFontAwesomeIconClass($file['file_name']) . ' file-icon ' . getFileColorClassPhp($file['file_name']) . '"></i>';
                                    }
                                ?>
                                <?php elseif (in_array($fileExt, $cadExt)): // NEW: CAD Thumbnail Preview ?>
                                    <i class="fas <?php echo getFontAwesomeIconClass($file['file_name']); ?> file-icon <?php echo getFileColorClassPhp($file['file_name']); ?>"></i>
                                    <span style="font-size: 0.9em; margin-top: 5px;">CAD File</span>
                                <?php elseif (in_array($fileExt, $archiveExt) || in_array($fileExt, $instExt) || in_array($fileExt, $ptpExt)): ?>
                                    <i class="fas <?php echo getFontAwesomeIconClass($file['file_name']); ?> file-icon <?php echo getFileColorClassPhp($file['file_name']); ?>"></i>
                                    <span style="font-size: 0.9em; margin-top: 5px;"><?php echo strtoupper($fileExt); ?> File</span>
                                <?php else: ?>
                                    <i class="fas <?php echo getFontAwesomeIconClass($file['file_name']); ?> file-icon <?php echo getFileColorClassPhp($file['file_name']); ?>"></i>
                                <?php endif; ?>
                                <span class="file-type-label"><?php echo strtoupper($fileExt); ?></span>
                            </div>
                            <a href="views/pages/file-view.php?file_id=<?php echo $file['id']; ?>" class="file-name file-link-clickable" onclick="event.stopPropagation();"><?php echo $fileName; ?></a>
                            <span class="file-size"><?php echo formatBytes($file['file_size']); ?></span>
                            <button class="item-more" aria-haspopup="true" aria-label="More">⋮</button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal -->
    <div id="uploadFileModal" class="modal">
        <div class="modal-content">
            <span class="close-button">&times;</span>
            <h2>Upload File</h2>
            <form id="uploadForm" enctype="multipart/form-data">
                <input type="hidden" name="current_folder_id" value="<?= htmlspecialchars($currentFolderId ?? '') ?>">
                <input type="hidden" name="current_folder_path" value="<?php echo htmlspecialchars($currentFolderPath); ?>">
                <label for="fileToUpload">Select File(s):</label>
                <input type="file" name="fileToUpload[]" id="fileToUpload" multiple required <?php echo $isStorageFull ? 'disabled' : ''; ?>>
                <button type="submit" id="startUploadBtn" <?php echo $isStorageFull ? 'disabled' : ''; ?>>Upload</button>
            </form>
        </div>
    </div>


    <div id="createFolderModal" class="modal">
        <div class="modal-content">
            <span class="close-button">&times;</span>
            <h2>Create New Folder</h2>
            <form id="createFolderForm" action="services/api/folderCreate.php" method="POST">
                <input type="hidden" name="parent_folder_id" value="<?php echo htmlspecialchars($currentFolderId ?? ""); ?>">
                <input type="hidden" name="parent_folder_path" value="<?php echo htmlspecialchars($currentFolderPath ?? ""); ?>">
                <label for="folderName">Folder Name:</label>
                <input type="text" name="folderName" id="folderName" required <?php echo $isStorageFull ? 'disabled' : ''; ?>>
                <button type="submit" <?php echo $isStorageFull ? 'disabled' : ''; ?>>Create Folder</button>
            </form>
        </div>
    </div>

    <div id="renameModal" class="modal">
        <div class="modal-content">
            <span class="close-button">&times;</span>
            <h2>Rename <span id="renameItemType"></span></h2>
            <form id="renameForm" action="rename.php" method="POST">
                <input type="hidden" name="itemId" id="renameItemId">
                <input type="hidden" name="itemType" id="renameItemActualType">
                <label for="newName">New Name:</label>
                <input type="text" name="newName" id="newName" required>
                <button type="submit">Rename</button>
            </form>
        </div>
    </div>

    <div id="uploadPreviewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <button class="back-button" id="uploadPreviewBackBtn"><i class="fas fa-chevron-left"></i></button>
                <h2>File Upload</h2>
                <span class="close-button" id="closeUploadPreviewBtn">&times;</span>
            </div>
            <div id="uploadPreviewList">
                </div>
            </div>
    </div>

     <!-- Modal untuk Share Link -->
     <div id="shareLinkModal" class="modal">
        <div class="modal-content">
            <span class="close-button">&times;</span>
            <h2>Share Link</h2>
            <p>Here is the shareable link for your file:</p>
            <div class="share-link-container">
                <input type="text" id="shortLinkOutput" value="" readonly>
                <button id="copyShortLinkBtn"><i class="fas fa-copy"></i> Copy</button>
            </div>
            <p class="small-text">Anyone with this link can view the file.</p>
        </div>
    </div>

    <div id="customNotification" class="notification"></div>

    <!-- Custom context menu (shared UI, populated by JS) -->
    <div id="context-menu" class="context-menu" hidden>
        <ul>
            <li data-action="rename"><i class="fas fa-pen"></i> Rename</li>
            <li data-action="download" class="hidden"><i class="fas fa-download"></i> Download</li>
            <li data-action="share" class="hidden"><i class="fas fa-share-alt"></i> Share Link</li>
            <li data-action="extract" class="hidden"><i class="fas fa-file-archive"></i> Extract ZIP</li>
            <li data-action="toggle-star"><i class="fas fa-star"></i> Pin to Priority</li> <!-- Changed data-action to toggle-star -->
            <li class="separator"></li>
            <li data-action="delete"><i class="fas fa-trash"></i> Delete</li>
        </ul>
    </div>

    <!-- Overlay for mobile sidebar -->
    <div class="overlay" id="mobileOverlay"></div>

<?php include 'views/partials/footer.php'; ?>
