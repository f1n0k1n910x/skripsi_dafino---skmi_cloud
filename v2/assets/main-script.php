
<?php
$usedStorageBytes  = $usedStorageBytes  ?? 0;
$totalStorageBytes = $totalStorageBytes ?? 1; // jangan 0 biar tidak divide by zero
$isStorageFull     = $isStorageFull     ?? false;
$searchQuery = isset($_GET['search']) ? $_GET['search'] : '';
$folders = [];
$currentFolderPath = ''; // To build the full path for uploads and display

?>

<script>
  const BASE_URL = "<?php echo dirname($_SERVER['SCRIPT_NAME']); ?>";

  document.addEventListener('DOMContentLoaded', function() {
      const uploadFileBtn = document.getElementById('uploadFileBtn');
      const createFolderBtn = document.getElementById('createFolderBtn');
      const deleteSelectedBtn = document.getElementById('deleteSelectedBtn');
      
      // Dropdown elements (main toolbar)
      const archiveDropdownContainer = document.querySelector('.toolbar .archive-dropdown-container');
      const archiveSelectedBtn = document.getElementById('archiveSelectedBtn');
      const archiveDropdownContent = document.querySelector('.toolbar .archive-dropdown-content');

      const sizeFilterDropdownContainer = document.querySelector('.toolbar .size-filter-dropdown-container');
      const sizeFilterBtn = document.getElementById('sizeFilterBtn');
      const sizeFilterDropdownContent = document.querySelector('.toolbar .size-filter-dropdown-content');

      const actionDropdownContainer = document.querySelector('.toolbar .action-dropdown-container');
      const actionBtn = document.getElementById('actionBtn');
      const actionDropdownContent = document.querySelector('.toolbar .action-dropdown-content');

      const fileTypeFilterDropdownContainer = document.querySelector('.toolbar .file-type-filter-dropdown-container');
      const fileTypeFilterBtn = document.getElementById('fileTypeFilterBtn');
      const fileTypeFilterDropdownContent = document.querySelector('.toolbar .file-type-filter-dropdown-content');

      // Dropdown elements (header)
      const archiveSelectedBtnHeader = document.getElementById('archiveSelectedBtnHeader');
      const fileTypeFilterBtnHeader = document.getElementById('fileTypeFilterBtnHeader');
      const sizeFilterBtnHeader = document.getElementById('sizeFilterBtnHeader'); // NEW
      const listViewBtnHeader = document.getElementById('listViewBtnHeader'); // NEW
      const gridViewBtnHeader = document.getElementById('gridViewBtnHeader'); // NEW


      const uploadFileModal = document.getElementById('uploadFileModal');
      const createFolderModal = document.getElementById('createFolderModal');
      const renameModal = document.getElementById('renameModal');
      const uploadPreviewModal = document.getElementById('uploadPreviewModal');
      const uploadPreviewList = document.getElementById('uploadPreviewList');
      const fileToUploadInput = document.getElementById('fileToUpload');
      const startUploadBtn = document.getElementById('startUploadBtn');
      const uploadPreviewBackBtn = document.getElementById('uploadPreviewBackBtn');
      const closeUploadPreviewBtn = document.getElementById('closeUploadPreviewBtn');

      const closeButtons = document.querySelectorAll('.close-button');

      const listViewBtn = document.getElementById('listViewBtn');
      const gridViewBtn = document.getElementById('gridViewBtn');
      const fileListView = document.getElementById('fileListView');
      const fileGridView = document.getElementById('fileGridView');
      const selectAllCheckbox = document.getElementById('selectAllCheckbox');
      const searchInput = document.getElementById('searchInput'); // Desktop search
      const searchInputMobile = document.getElementById('searchInputMobile'); // Mobile search
      const customNotification = document.getElementById('customNotification');

      // New elements for Share Link
      const shareLinkModal = document.getElementById('shareLinkModal');
      const shortLinkOutput = document.getElementById('shortLinkOutput');
      const copyShortLinkBtn = document.getElementById('copyShortLinkBtn');

      // Context Menu elements
      const contextMenu = document.getElementById('context-menu'); // Changed from 'contextMenu' to 'context-menu'
      const contextRename = document.querySelector('#context-menu [data-action="rename"]');
      const contextDownload = document.querySelector('#context-menu [data-action="download"]');
      const contextShare = document.querySelector('#context-menu [data-action="share"]');
      const contextExtract = document.querySelector('#context-menu [data-action="extract"]');
      const contextToggleStar = document.querySelector('#context-menu [data-action="toggle-star"]'); // Changed to toggle-star
      const contextDelete = document.querySelector('#context-menu [data-action="delete"]');

      // Mobile sidebar elements
      const sidebar = document.querySelector('.sidebar');
      const sidebarToggleBtn = document.getElementById('sidebarToggleBtn');
      const mobileOverlay = document.getElementById('mobileOverlay');
      const myDriveTitle = document.querySelector('.my-drive-title');
      const desktopSearchBar = document.querySelector('.search-bar-desktop');
      const mobileSearchBar = document.querySelector('.search-bar-mobile');

      let activeUploads = new Map();
      let currentContextItem = null; // To store the item clicked for context menu

      // Variables for long press
      let lpTimer = null;
      let lpStart = null;
      const longPressDuration = 600; // milliseconds
      const longPressMoveThreshold = 10; // pixels

      // Current state variables for AJAX filtering/sorting
      let currentFolderId = <?php echo json_encode($currentFolderId); ?>;
      let currentSearchQuery = <?php echo json_encode($searchQuery); ?>;
      let currentSizeFilter = <?php echo json_encode($sizeFilter); ?>; // Changed from releaseFilter
      let currentFileTypeFilter = <?php echo json_encode($fileTypeFilter); ?>;

      /*** Util helpers ****/
      function debounce(fn, ms=150){ let t; return (...a)=>{ clearTimeout(t); t=setTimeout(()=>fn(...a), ms); }; }
      function closestFileItem(el){ return el && el.closest('.file-item'); }

      /*** Device detection & body class toggling ***/
      function setDeviceClass() {
          const ua = navigator.userAgent || '';
          const isIPad = /iPad/.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
          const w = window.innerWidth;
          document.body.classList.remove('mobile', 'tablet-portrait', 'tablet-landscape', 'desktop'); // Clear all
          if (w <= 767) {
              document.body.classList.add('mobile');
          } else if (w >= 768 && w <= 1024) {
              if (window.matchMedia("(orientation: portrait)").matches) {
                  document.body.classList.add('tablet-portrait');
              } else {
                  document.body.classList.add('tablet-landscape');
              }
          } else {
              document.body.classList.add('desktop');
          }
      }
      window.addEventListener('resize', debounce(setDeviceClass, 150));
      window.addEventListener('orientationchange', setDeviceClass); // Listen for orientation changes
      setDeviceClass(); // init

      // Function to get file icon class based on extension (for JS side, if needed for dynamic elements)
      // This function is now primarily handled by PHP's getFontAwesomeIconClass
      function getFileIconClass(fileName) {
          const extension = fileName.split('.').pop().toLowerCase();
          switch (extension) {
              case 'pdf': return 'fa-file-pdf';
              case 'doc':
              case 'docx': return 'fa-file-word';
              case 'xls':
              case 'xlsx': return 'fa-file-excel';
              case 'ppt':
              case 'pptx': return 'fa-file-powerpoint';
              case 'jpg':
              case 'jpeg':
              case 'png':
              case 'gif':
              case 'bmp':
              case 'webp': return 'fa-file-image';
              case 'zip':
              case 'rar':
              case '7z': return 'fa-file-archive';
              case 'txt':
              case 'log':
              case 'md': return 'fa-file-alt';
              case 'exe':
              case 'apk': return 'fa-box';
              case 'mp3':
              case 'wav':
              case 'flac': return 'fa-file-audio';
              case 'mp4':
              case 'avi':
              case 'mkv': return 'fa-file-video';
              case 'html':
              case 'htm': return 'fa-file-code';
              case 'css': return 'fa-file-code';
              case 'js': return 'fa-file-code';
              case 'php': return 'fa-file-code';
              case 'py': return 'fa-file-code';
              case 'json': return 'fa-file-code';
              case 'sql': return 'fa-database';
              case 'svg': return 'fa-file-image';
              case 'sh':
              case 'bat': return 'fa-file-code';
              case 'ini':
              case 'yml':
              case 'yaml': return 'fa-file-code';
              case 'java': return 'fa-java';
              case 'c':
              case 'cpp': return 'fa-file-code';
              // ... (existing cases) ...
              case 'dwg':
              case 'dxf':
              case 'dgn':
              case 'iges':
              case 'igs':
              case 'step':
              case 'stp':
              case 'stl':
              case '3ds':
              case 'obj':
              case 'sldprt':
              case 'sldasm':
              case 'ipt':
              case 'iam':
              case 'catpart':
              case 'catproduct':
              case 'prt':
              case 'asm':
              case 'fcstd':
              case 'skp':
              case 'x_t':
              case 'x_b': return 'fa-cube'; // NEW: CAD Icon for JS
              default: return 'fa-file';
          }
      }

      // Function to get file color class based on extension (for JS side, if needed for dynamic elements)
      // This function is now primarily handled by PHP's getFileColorClassPhp
      function getFileColorClass(fileName) {
          const extension = fileName.split('.').pop().toLowerCase();
          const colorClasses = {
              'pdf': 'file-color-pdf', 'doc': 'file-color-doc', 'docx': 'file-color-doc',
              'xls': 'file-color-xls', 'xlsx': 'file-color-xls', 'ppt': 'file-color-ppt',
              'pptx': 'file-color-ppt', 'txt': 'file-color-txt', 'rtf': 'file-color-txt',
              'md': 'file-color-txt', 'csv': 'file-color-csv', 'odt': 'file-color-doc',
              'odp': 'file-color-ppt', 'log': 'file-color-txt', 'tex': 'file-color-txt',
              'jpg': 'file-color-image', 'jpeg': 'file-color-image', 'png': 'file-color-image',
              'gif': 'file-color-image', 'bmp': 'file-color-image', 'webp': 'file-color-image',
              'svg': 'file-color-image', 'tiff': 'file-color-image',
              'mp3': 'file-color-audio', 'wav': 'file-color-audio', 'ogg': 'file-color-audio',
              'flac': 'file-color-audio', 'aac': 'file-color-audio', 'm4a': 'file-color-audio',
              'alac': 'file-color-audio', 'wma': 'file-color-audio', 'opus': 'file-color-audio',
              'amr': 'file-color-audio', 'mid': 'file-color-audio',
              'mp4': 'file-color-video', 'avi': 'file-color-video', 'mov': 'file-color-video',
              'wmv': 'file-color-video', 'flv': 'file-color-video', 'webm': 'file-color-video',
              '3gp': 'file-color-video', 'm4v': 'file-color-video', 'mpg': 'file-color-video',
              'mpeg': 'file-color-video', 'ts': 'file-color-video', 'ogv': 'file-color-video',
              'zip': 'file-color-archive', 'rar': 'file-color-archive', '7z': 'file-color-archive',
              'tar': 'file-color-archive', 'gz': 'file-color-archive', 'bz2': 'file-color-archive',
              'xz': 'file-color-archive', 'iso': 'file-color-archive', 'cab': 'file-color-archive',
              'arj': 'file-color-archive',
              'html': 'file-color-code', 'htm': 'file-color-code', 'css': 'file-color-code',
              'js': 'file-color-code', 'php': 'file-color-code', 'py': 'file-color-code',
              'java': 'file-color-code', 'json': 'file-color-code', 'xml': 'file-color-code',
              'ts': 'file-color-code', 'tsx': 'file-color-code', 'jsx': 'file-color-code',
              'vue': 'file-color-code', 'cpp': 'file-color-code', 'c': 'file-color-code',
              'cs': 'file-color-code', 'rb': 'file-color-code', 'go': 'file-color-code',
              'swift': 'file-color-code', 'sql': 'file-color-code', 'sh': 'file-color-code',
              'bat': 'file-color-code', 'ini': 'file-color-code', 'yml': 'file-color-code',
              'yaml': 'file-color-code', 'pl': 'file-color-code', 'r': 'file-color-code',
              'exe': 'file-color-exe', 'msi': 'file-color-exe', 'apk': 'file-color-exe',
              'ipa': 'file-color-exe', 'jar': 'file-color-exe', 'appimage': 'file-color-exe',
              'dmg': 'file-color-exe', 'bin': 'file-color-exe',
              'torrent': 'file-color-default', 'nzb': 'file-color-default', 'ed2k': 'file-color-default',
              'part': 'file-color-default', '!ut': 'file-color-default',
              'dwg': 'file-color-cad', 'dxf': 'file-color-cad', 'dgn': 'file-color-cad',
              'iges': 'file-color-cad', 'igs': 'file-color-cad', 'step': 'file-color-cad',
              'stp': 'file-color-cad', 'stl': 'file-color-cad', '3ds': 'file-color-cad',
              'obj': 'file-color-cad', 'sldprt': 'file-color-cad', 'sldasm': 'file-color-cad',
              'ipt': 'file-color-cad', 'iam': 'file-color-cad', 'catpart': 'file-color-cad',
              'catproduct': 'file-color-cad', 'prt': 'file-color-cad', 'asm': 'file-color-cad',
              'fcstd': 'file-color-cad', 'skp': 'file-color-cad', 'x_t': 'file-color-cad',
              'x_b': 'file-color-cad',
              'default': 'file-color-default'
          };
          return colorClasses[extension] || colorClasses['default'];
      }


      // Function to show custom notification
      function showNotification(message, type) {
          customNotification.innerHTML = message; // Use innerHTML to allow HTML tags in message
          customNotification.className = 'notification show ' + type;
          setTimeout(() => {
              customNotification.classList.remove('show');
          }, 3000);
      }

      // --- Modal Open/Close Logic ---
      function openModal(modalElement) {
          modalElement.classList.add('show');
      }

      function closeModal(modalElement) {
          modalElement.classList.remove('show');
          // Reset form if it's a form modal
          const form = modalElement.querySelector('form');
          if (form) {
              form.reset();
          }
      }

      uploadFileBtn.addEventListener('click', () => {
          // Check if storage is full before opening modal
          if (uploadFileBtn.disabled) {
              showNotification('Storage is full. Cannot upload more files.', 'error');
              return;
          }
          openModal(uploadFileModal);
          fileToUploadInput.value = ''; 
          uploadPreviewList.innerHTML = '';
          startUploadBtn.style.display = 'none';
      });

      createFolderBtn.addEventListener('click', () => {
          // Check if storage is full before opening modal
          if (createFolderBtn.disabled) {
              showNotification('Storage is full. Cannot create more folders.', 'error');
              return;
          }
          openModal(createFolderModal);
      });

      closeButtons.forEach(button => {
          button.addEventListener('click', () => {
              closeModal(uploadFileModal);
              closeModal(createFolderModal);
              closeModal(renameModal);
              closeModal(uploadPreviewModal);
              closeModal(shareLinkModal);
              activeUploads.forEach(controller => controller.abort());
              activeUploads.clear();
          });
      });

      uploadPreviewBackBtn.addEventListener('click', () => {
          closeModal(uploadPreviewModal);
          openModal(uploadFileModal);
          activeUploads.forEach(controller => controller.abort());
          activeUploads.clear();
      });

      window.addEventListener('click', (event) => {
          if (event.target == uploadFileModal) {
              closeModal(uploadFileModal);
          }
          if (event.target == createFolderModal) {
              closeModal(createFolderModal);
          }
          if (event.target == renameModal) {
              closeModal(renameModal);
          }
          if (event.target == uploadPreviewModal) {
              closeModal(uploadPreviewModal);
              activeUploads.forEach(controller => controller.abort());
              activeUploads.clear();
          }
          if (event.target == shareLinkModal) {
              closeModal(shareLinkModal);
          }
          // Close all dropdowns if clicked outside
          // Main toolbar dropdowns
          if (archiveDropdownContainer && !archiveDropdownContainer.contains(event.target)) {
              archiveDropdownContainer.classList.remove('show');
          }
          // REMOVED: releaseFilterDropdownContainer check
          // REMOVED: sortOrderDropdownContainer check
          if (sizeFilterDropdownContainer && !sizeFilterDropdownContainer.contains(event.target)) {
              sizeFilterDropdownContainer.classList.remove('show');
          }
          if (fileTypeFilterDropdownContainer && !fileTypeFilterDropdownContainer.contains(event.target)) {
              fileTypeFilterDropdownContainer.classList.remove('show');
          }

          // Header toolbar dropdowns (now toolbar-filter-buttons)
          const headerArchiveDropdownContainer = document.querySelector('.toolbar-filter-buttons .archive-dropdown-container');
          const headerFileTypeDropdownContainer = document.querySelector('.toolbar-filter-buttons .file-type-filter-dropdown-container');
          // REMOVED: headerReleaseDropdownContainer
          // REMOVED: headerSortOrderDropdownContainer
          const headerSizeFilterDropdownContainer = document.querySelector('.toolbar-filter-buttons .size-filter-dropdown-container'); // NEW
          const headerViewToggle = document.querySelector('.toolbar-filter-buttons .view-toggle'); // NEW

          if (headerArchiveDropdownContainer && !headerArchiveDropdownContainer.contains(event.target)) {
              headerArchiveDropdownContainer.classList.remove('show');
          }
          if (headerFileTypeDropdownContainer && !headerFileTypeDropdownContainer.contains(event.target)) {
              headerFileTypeDropdownContainer.classList.remove('show');
          }
          if (headerSizeFilterDropdownContainer && !headerSizeFilterDropdownContainer.contains(event.target)) { // NEW
              headerSizeFilterDropdownContainer.classList.remove('show');
          }
          // NEW: Close view toggle dropdown if clicked outside
          if (headerViewToggle && !headerViewToggle.contains(event.target)) {
              // No specific class to remove, just ensure it's not active if it has one
          }


          // Close context menu if clicked outside
          if (!contextMenu.contains(event.target)) {
              hideContextMenu(); // Use the new hide function
          }
          // Close mobile sidebar if overlay is clicked
          if (event.target == mobileOverlay && sidebar.classList.contains('show-mobile-sidebar')) {
              sidebar.classList.remove('show-mobile-sidebar');
              mobileOverlay.classList.remove('show');
          }
      });

      // --- View Toggle Logic ---
      function setupViewToggle(listViewBtnElement, gridViewBtnElement) {
          listViewBtnElement.addEventListener('click', () => {
              listViewBtnElement.classList.add('active');
              gridViewBtnElement.classList.remove('active');
              fileListView.classList.remove('hidden');
              fileGridView.classList.add('hidden');
              localStorage.setItem('fileView', 'list');
          });

          gridViewBtnElement.addEventListener('click', () => {
              gridViewBtnElement.classList.add('active');
              listViewBtnElement.classList.remove('active');
              fileGridView.classList.remove('hidden');
              fileListView.classList.add('hidden');
              localStorage.setItem('fileView', 'grid');
          });
      }

      setupViewToggle(listViewBtn, gridViewBtn); // For main toolbar
      setupViewToggle(listViewBtnHeader, gridViewBtnHeader); // For header toolbar

      const savedView = localStorage.getItem('fileView');
      if (savedView === 'grid') {
          gridViewBtn.click(); // Simulate click to activate
          gridViewBtnHeader.click(); // Simulate click for header button
      } else {
          listViewBtn.click(); // Simulate click to activate
          listViewBtnHeader.click(); // Simulate click for header button
      }

      // --- Select All Checkbox Logic ---
      function updateSelectAllCheckboxListener() {
          const fileCheckboxes = document.querySelectorAll('.file-checkbox');
          selectAllCheckbox.checked = false;
          selectAllCheckbox.removeEventListener('change', handleSelectAllChange);
          selectAllCheckbox.addEventListener('change', handleSelectAllChange);

          fileCheckboxes.forEach(checkbox => {
              checkbox.removeEventListener('change', handleIndividualCheckboxChange);
              checkbox.addEventListener('change', handleIndividualCheckboxChange);
          });
      }

      function handleSelectAllChange() {
          const fileCheckboxes = document.querySelectorAll('.file-checkbox');
          fileCheckboxes.forEach(checkbox => {
              checkbox.checked = this.checked;
          });
      }

      function handleIndividualCheckboxChange() {
          const fileCheckboxes = document.querySelectorAll('.file-checkbox');
          if (!this.checked) {
              selectAllCheckbox.checked = false;
          } else {
              const allChecked = Array.from(fileCheckboxes).every(cb => cb.checked);
              selectAllCheckbox.checked = allChecked;
          }
      }

      updateSelectAllCheckboxListener();

      // --- Delete Selected Files/Folders ---
      deleteSelectedBtn.addEventListener('click', async () => {
          const checkboxes = document.querySelectorAll('.file-checkbox:checked');
          const seen = new Set();
            const selectedItems = Array.from(checkboxes).reduce((acc, cb) => {
            const key = `${cb.dataset.type}-${cb.dataset.id}`;
            if (!seen.has(key)) {
                seen.add(key);
                acc.push({ id: cb.dataset.id, type: cb.dataset.type });
            }
            return acc;
            }, []);

          if (selectedItems.length === 0) {
              showNotification('Please select at least one file or folder to delete!', 'error');
              return;
          }

          if (!confirm('Are you sure you want to delete the selected items? This will delete all files and subfolders within them!')) {
              return;
          }

          try {
              const response = await fetch(`${BASE_URL}/services/api/deleteSelected.php`, {
                  method: 'POST',
                  headers: {
                      'Content-Type': 'application/json',
                  },
                  body: JSON.stringify({ items: selectedItems })
              });
              const data = await response.json();
              if (data.success) {
                  showNotification('Items deleted successfully!', 'success');
                  updateFileListAndFolders(); // Update content without full reload
              } else {
                  showNotification('Failed to delete items: ' + data.message, 'error');
              }
          } catch (error) {
              console.error('Error:', error);
              showNotification('An error occurred while deleting items.', 'error');
          }
      });

      // --- Archive Selected Files/Folders ---
      function setupArchiveDropdown(buttonId, dropdownContentSelector) {
          const button = document.getElementById(buttonId);
          const dropdownContent = document.querySelector(dropdownContentSelector);
          const dropdownContainer = button.closest('.dropdown-container');

          if (!button || !dropdownContent || !dropdownContainer) return;

          button.addEventListener('click', (event) => {
              event.stopPropagation();
              dropdownContainer.classList.toggle('show');
          });

          dropdownContent.querySelectorAll('a').forEach(link => {
              link.addEventListener('click', async (event) => {
                  event.preventDefault();
                  dropdownContainer.classList.remove('show');

                  const format = event.target.dataset.format;
                  const checkboxes = document.querySelectorAll('.file-checkbox:checked');
                  const selectedItems = Array.from(checkboxes).map(cb => {
                      return { id: cb.dataset.id, type: cb.dataset.type };
                  });

                  if (selectedItems.length === 0) {
                      showNotification('Please select at least one file or folder to archive!', 'error');
                      return;
                  }

                  if (!confirm(`Are you sure you want to archive the selected items to ${format.toUpperCase()} format?`)) {
                      return;
                  }

                  showNotification('Starting archive process...', 'info');

                  try {
                      const response = await fetch('compress.php', {
                          method: 'POST',
                          headers: {
                              'Content-Type': 'application/json',
                          },
                          body: JSON.stringify({ 
                              items: selectedItems, 
                              format: format,
                              current_folder_id: currentFolderId
                          })
                      });
                      const data = await response.json();
                      if (data.success) {
                          showNotification(data.message, 'success');
                          updateFileListAndFolders();
                      } else {
                          showNotification('Failed to archive: ' + data.message, 'error');
                      }
                  } catch (error) {
                      console.error('Error:', error);
                      showNotification('An error occurred while archiving items.', 'error');
                  }
              });
          });
      }

      setupArchiveDropdown('archiveSelectedBtn', '.toolbar .archive-dropdown-content');
      setupArchiveDropdown('archiveSelectedBtnHeader', '.toolbar-filter-buttons .archive-dropdown-content');


      // --- File Type Filter ---
      function setupFileTypeFilterDropdown(buttonId, dropdownContentSelector) {
          const button = document.getElementById(buttonId);
          const dropdownContent = document.querySelector(dropdownContentSelector);
          const dropdownContainer = button.closest('.dropdown-container');

          if (!button || !dropdownContent || !dropdownContainer) return;

          button.addEventListener('click', (event) => {
              event.stopPropagation();
              dropdownContainer.classList.toggle('show');
          });

          dropdownContent.querySelectorAll('a').forEach(link => {
              link.addEventListener('click', (event) => {
                  event.preventDefault();
                  dropdownContainer.classList.remove('show');
                  currentFileTypeFilter = event.target.dataset.filter;
                  updateFileListAndFolders();
              });
          });
      }

      setupFileTypeFilterDropdown('fileTypeFilterBtn', '.toolbar .file-type-filter-dropdown-content');
      setupFileTypeFilterDropdown('fileTypeFilterBtnHeader', '.toolbar-filter-buttons .file-type-filter-dropdown-content');


      // --- Size Filter (Replaces Release Date and Sort Order) ---
      function setupSizeFilterDropdown(buttonId, dropdownContentSelector) {
          const button = document.getElementById(buttonId);
          const dropdownContent = document.querySelector(dropdownContentSelector);
          const dropdownContainer = button.closest('.dropdown-container');

          if (!button || !dropdownContent || !dropdownContainer) return;

          button.addEventListener('click', (event) => {
              event.stopPropagation();
              dropdownContainer.classList.toggle('show');
          });

          dropdownContent.querySelectorAll('a').forEach(link => {
              link.addEventListener('click', (event) => {
                  event.preventDefault();
                  dropdownContainer.classList.remove('show');
                  currentSizeFilter = event.target.dataset.size;
                  updateFileListAndFolders();
              });
          });
      }

      setupSizeFilterDropdown('sizeFilterBtn', '.toolbar .size-filter-dropdown-content');
      setupSizeFilterDropdown('actionBtn', '.toolbar .size-filter-dropdown-content');
      setupSizeFilterDropdown('sizeFilterBtnHeader', '.toolbar-filter-buttons .size-filter-dropdown-content');


      // --- Rename File/Folder ---
      function renameFile(id) {
          const item = document.querySelector(`.file-item[data-id="${CSS.escape(id)}"]`);
          if (!item) return;

          const itemType = item.dataset.type;
          const itemName = item.dataset.name;

          document.getElementById('renameItemId').value = id;
          document.getElementById('renameItemActualType').value = itemType;
          document.getElementById('newName').value = itemName;
          document.getElementById('renameItemType').textContent = itemType.charAt(0).toUpperCase() + itemType.slice(1);

          openModal(renameModal);
      }

      // --- Download File ---
      function downloadFile(id) {
          const item = document.querySelector(`.file-item[data-id="${CSS.escape(id)}"]`);
          if (!item) return;
          const filePath = item.dataset.path;
          const fileName = item.dataset.name;
          const link = document.createElement('a');
          link.href = filePath;
          link.download = fileName;
          document.body.appendChild(link);
          link.click();
          document.body.removeChild(link);
      }

      // --- Individual Delete File/Folder ---
      async function deleteFile(id) {
          const item = document.querySelector(`.file-item[data-id="${CSS.escape(id)}"]`);
          if (!item) return;
          const type = item.dataset.type;
          const confirmMessage = type === 'file'
              ? 'Are you sure you want to permanently delete this file?'
              : 'Are you sure you want to permanently delete this folder and all its contents?';

          if (confirm(confirmMessage)) {
              try {
                  const response = await fetch('delete.php', {
                      method: 'POST',
                      headers: {
                          'Content-Type': 'application/json'
                      },
                      body: JSON.stringify({ id: id, type: type })
                  });
                  const data = await response.json();
                  if (data.success) {
                      showNotification(data.message, 'success');
                      updateFileListAndFolders(); // Update content without full reload
                  } else {
                      showNotification(data.message, 'error');
                  }
              } catch (error) {
                  console.error('Error:', error);
                  showNotification('An error occurred while contacting the server for deletion.', 'error');
              }
          }
      }

      // --- Extract ZIP File ---
      async function extractZipFile(id) {
          const item = document.querySelector(`.file-item[data-id="${CSS.escape(id)}"]`);
          if (!item) return;
          const filePath = item.dataset.path; // Path relatif dari file ZIP

          if (!confirm('Are you sure you want to extract this ZIP file? It will be extracted to a new folder named after the ZIP file in the current directory.')) {
              return;
          }

          showNotification('Extracting ZIP file...', 'info');

          try {
              const response = await fetch('extract.php', { // Endpoint baru untuk ekstrak
                  method: 'POST',
                  headers: {
                      'Content-Type': 'application/json',
                  },
                  body: JSON.stringify({ file_id: id, file_path: filePath })
              });
              const data = await response.json();
              if (data.success) {
                  showNotification(data.message, 'success');
                  updateFileListAndFolders(); // Refresh list to show new folder if extracted to current view
              } else {
                  showNotification('Extraction failed: ' + data.message, 'error');
              }
          } catch (error) {
              console.error('Error:', error);
              showNotification('An error occurred during extraction.', 'error');
          }
      }

      // --- Toggle Star (Pin to Priority) ---
      async function toggleStar(id, type) {
          const item = document.querySelector(`.file-item[data-id="${CSS.escape(id)}"]`);
          if (!item) return;
          const itemName = item.dataset.name;

          try {
              const response = await fetch('toggle_star.php', {
                  method: 'POST',
                  headers: {
                      'Content-Type': 'application/json',
                  },
                  body: JSON.stringify({ id: id, type: type, name: itemName }) // Pass item name
              });
              const data = await response.json();
              if (data.success) {
                  showNotification(data.message, 'success');
                  // No need to update UI here, as it's just a star/unstar action
                  // The priority_files.php page will handle its own loading
              } else {
                  showNotification('Failed to toggle star: ' + data.message, 'error');
              }
          } catch (error) {
              console.error('Error toggling star:', error);
              showNotification('An error occurred while toggling star.', 'error');
          }
      }

      // --- Form Submissions for Create Folder and Rename ---
      const createFolderForm = document.getElementById('createFolderForm');
      const renameForm = document.getElementById('renameForm');

      createFolderForm.addEventListener('submit', async function(e) {
          e.preventDefault();
          const formData = new FormData(this);
          try {
              const response = await fetch(this.action, {
                  method: 'POST',
                  body: formData
              });
              const data = await response.json();
              if (data.success) {
                  showNotification(data.message, 'success');
                  closeModal(createFolderModal);
                  updateFileListAndFolders(); // Update content without full reload
              } else {
                  showNotification(data.message, 'error');
              }
          } catch (error) {
              console.error('Error:', error);
              showNotification('An error occurred while creating the folder.', 'error');
          }
      });

      renameForm.addEventListener('submit', async function(e) {
          e.preventDefault();
          const formData = new FormData(this);
          try {
              const response = await fetch(this.action, {
                  method: 'POST',
                  body: formData
              });
              const data = await response.json();
              if (data.success) {
                  showNotification(data.message, 'success');
                  closeModal(renameModal);
                  updateFileListAndFolders(); // Update content without full reload
              } else {
                  showNotification(data.message, 'error');
              }
          } catch (error) {
              console.error('Error:', error);
              showNotification('An error occurred while renaming.', 'error');
          }
      });

      // --- Search Functionality ---
      function performSearch(query) {
          currentSearchQuery = query.trim();
          updateFileListAndFolders();
      }

      searchInput.addEventListener('keypress', function(e) {
          if (e.key === 'Enter') {
              performSearch(this.value);
          }
      });

      searchInputMobile.addEventListener('keypress', function(e) {
          if (e.key === 'Enter') {
              performSearch(this.value);
          }
      });

      // --- File Upload Preview and Handling ---
      fileToUploadInput.addEventListener('change', function() {
          uploadPreviewList.innerHTML = '';
          startUploadBtn.style.display = 'block';
          activeUploads.clear();

          if (this.files.length > 0) {
              Array.from(this.files).forEach((file, index) => {
                  const fileId = `upload-item-${index}-${Date.now()}`;
                  // Use JS functions for dynamic elements if needed, or rely on PHP for initial render
                  const iconClass = getFileIconClass(file.name); // Assuming getFileIconClass is defined in JS
                  const colorClass = getFileColorClass(file.name); // Assuming getFileColorClass is defined in JS
                  
                  const uploadItemHtml = `
                      <div class="upload-item" id="${fileId}">
                          <i class="fas ${iconClass} file-icon ${colorClass}"></i>
                          <div class="upload-item-info">
                              <strong>${file.name}</strong>
                              <div class="upload-progress-container">
                                  <div class="upload-progress-bar" style="width: 0%;"></div>
                              </div>
                          </div>
                          <span class="upload-status-icon processing"><i class="fas fa-spinner fa-spin"></i></span>
                          <button class="upload-action-button cancel-upload-btn" data-file-id="${fileId}"><i class="fas fa-times"></i></button>
                      </div>
                  `;
                  uploadPreviewList.insertAdjacentHTML('beforeend', uploadItemHtml);

                  const fileElement = document.getElementById(fileId);
                  activeUploads.set(fileId, { file: file, element: fileElement, controller: null });
              });
          } else {
              startUploadBtn.style.display = 'none';
          }
      });

      document.getElementById('startUploadBtn').addEventListener('click', function(e) {
          e.preventDefault();
          if (fileToUploadInput.files.length === 0) {
              showNotification('Please select files to upload first.', 'error');
              return;
          }
          // Check if storage is full before starting upload
          if (this.disabled) {
              showNotification('Storage is full. Cannot upload more files.', 'error');
              return;
          }

          closeModal(uploadFileModal);
          openModal(uploadPreviewModal);

          let allUploadsCompleted = 0;
          const totalUploads = activeUploads.size;

          activeUploads.forEach((item, fileId) => {
              const controller = new AbortController();
              item.controller = controller;
              uploadFile(item.file, fileId, controller.signal).then(() => {
                  allUploadsCompleted++;
                  if (allUploadsCompleted === totalUploads) {
                      // All uploads finished, refresh the file list
                      setTimeout(() => {
                          updateFileListAndFolders();
                          closeModal(uploadPreviewModal);
                      }, 1000); // Give a small delay for visual feedback
                  }
              });
          });
      });

      async function uploadFile(file, fileId, signal) {
          const currentFolderId = document.querySelector('input[name="current_folder_id"]').value;
          const currentFolderPath = document.querySelector('input[name="current_folder_path"]').value;

          const formData = new FormData();
          formData.append('fileToUpload[]', file);
          formData.append('current_folder_id', currentFolderId);
          formData.append('current_folder_path', currentFolderPath);

          const uploadItemElement = document.getElementById(fileId);
          const progressBar = uploadItemElement.querySelector('.upload-progress-bar');
          const statusIcon = uploadItemElement.querySelector('.upload-status-icon');
          const cancelButton = uploadItemElement.querySelector('.cancel-upload-btn');

          statusIcon.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
          statusIcon.classList.remove('success', 'error', 'cancelled');
          statusIcon.classList.add('processing');
          cancelButton.style.display = 'block';

          try {
              const response = await fetch("/skripsi_dafino---skmi_cloud/v2/services/api/uploadFiles.php", {
                    method: "POST",
                    body: formData,
                    signal,
                });

              if (!response.ok) {
                  throw new Error(`Server responded with status ${response.status}`);
              }

              const data = await response.json();

              if (data.success) {
                  progressBar.style.width = '100%';
                  progressBar.style.backgroundColor = 'var(--metro-success)';
                  statusIcon.innerHTML = '<i class="fas fa-check"></i>';
                  statusIcon.classList.remove('processing', 'error', 'cancelled');
                  statusIcon.classList.add('success');
                  uploadItemElement.classList.add('complete');
                  showNotification(`File '${file.name}' uploaded successfully.`, 'success');
              } else {
                  throw new Error(data.message || 'Unknown error during upload.');
              }
          } catch (error) {
              if (error.name === 'AbortError') {
                  progressBar.style.width = '100%';
                  progressBar.style.backgroundColor = 'var(--metro-warning)';
                  statusIcon.innerHTML = '<i class="fas fa-times-circle"></i>';
                  statusIcon.classList.remove('processing', 'success', 'error');
                  statusIcon.classList.add('cancelled');
                  showNotification(`Upload for '${file.name}' cancelled.`, 'error');
              } else {
                  progressBar.style.width = '100%';
                  progressBar.style.backgroundColor = 'var(--metro-error)';
                  statusIcon.innerHTML = '<i class="fas fa-times-circle"></i>';
                  statusIcon.classList.remove('processing', 'success', 'cancelled');
                  statusIcon.classList.add('error');
                  showNotification(`Failed to upload '${file.name}': ${error.message}`, 'error');
              }
              uploadItemElement.classList.add('complete');
          } finally {
              cancelButton.style.display = 'none';
              activeUploads.delete(fileId);
          }
      }

      uploadPreviewList.addEventListener('click', function(event) {
          if (event.target.closest('.cancel-upload-btn')) {
              const button = event.target.closest('.cancel-upload-btn');
              const fileId = button.dataset.fileId;
              const uploadItem = activeUploads.get(fileId);

              if (uploadItem && uploadItem.controller) {
                  uploadItem.controller.abort();
                  activeUploads.delete(fileId);
                  const uploadItemElement = document.getElementById(fileId);
                  const progressBar = uploadItemElement.querySelector('.upload-progress-bar');
                  const statusIcon = uploadItemElement.querySelector('.upload-status-icon');

                  progressBar.style.width = '100%';
                  progressBar.style.backgroundColor = 'var(--metro-warning)';
                  statusIcon.innerHTML = '<i class="fas fa-times-circle"></i>';
                  statusIcon.classList.remove('processing', 'success', 'error');
                  statusIcon.classList.add('cancelled');
                  button.style.display = 'none';
                  uploadItemElement.classList.add('complete');
                  showNotification(`Upload for '${uploadItem.file.name}' manually cancelled.`, 'error');
                  
                  if (activeUploads.size === 0) {
                      setTimeout(() => {
                          updateFileListAndFolders();
                          closeModal(uploadPreviewModal);
                      }, 1000);
                  }
              }
          }
      });

      // --- Share Link Functionality ---
      async function shareFileLink(id) {
          const item = document.querySelector(`.file-item[data-id="${CSS.escape(id)}"]`);
          if (!item) return;
          const itemType = item.dataset.type; // Will always be 'file' for this button

          if (itemType !== 'file') {
              showNotification('Only files can be shared via shortlink.', 'error');
              return;
          }

          showNotification('Generating share link...', 'info');

          try {
              const response = await fetch('generate_share_link.php', {
                  method: 'POST',
                  headers: {
                      'Content-Type': 'application/x-www-form-urlencoded', // Important for $_POST
                  },
                  body: `file_id=${id}`
              });

              const data = await response.json();

              if (data.success) {
                  shortLinkOutput.value = data.shortlink;
                  openModal(shareLinkModal);
                  showNotification('Share link generated!', 'success');
              } else {
                  showNotification('Failed to generate share link: ' + data.message, 'error');
              }
          } catch (error) {
              console.error('Error:', error);
              showNotification('An error occurred while generating the share link.', 'error');
          }
      }

      copyShortLinkBtn.addEventListener('click', () => {
          shortLinkOutput.select();
          shortLinkOutput.setSelectionRange(0, 99999); // For mobile devices
          document.execCommand('copy');
          showNotification('Link copied to clipboard!', 'success');
      });

      /*** Context menu element ***/
      function showContextMenuFor(fileEl, x, y) {
          if (!fileEl) return;
          // attach target id
          contextMenu.dataset.targetId = fileEl.dataset.id;
          contextMenu.dataset.targetType = fileEl.dataset.type;
          contextMenu.dataset.targetName = fileEl.dataset.name; // Pass item name to context menu
          contextMenu.dataset.targetFileType = fileEl.dataset.fileType || ''; // For files

          // Show/hide options based on item type
          const itemType = fileEl.dataset.type;
          const fileType = fileEl.dataset.fileType;

          if (itemType === 'folder') {
              contextDownload.classList.add('hidden');
              contextShare.classList.add('hidden');
              contextExtract.classList.add('hidden');
          } else if (itemType === 'file') {
              contextDownload.classList.remove('hidden');
              contextShare.classList.remove('hidden');
              if (fileType === 'zip') {
                  contextExtract.classList.remove('hidden');
              } else {
                  contextExtract.classList.add('hidden');
              }
          }

          // position - keep inside viewport
          const rect = contextMenu.getBoundingClientRect();
          const menuWidth = rect.width || 200;
          const menuHeight = rect.height || 220;

          let finalLeft = x;
          let finalTop = y;

          // If menu too close to right edge, shift left
          if (x + menuWidth > window.innerWidth) {
              finalLeft = window.innerWidth - menuWidth - 10; // 10px padding from right edge
          }

          // If menu too close to bottom edge, shift up
          if (y + menuHeight > window.innerHeight) {
              finalTop = window.innerHeight - menuHeight - 10; // 10px padding from bottom edge
          }

          contextMenu.style.left = finalLeft + 'px';
          contextMenu.style.top = finalTop + 'px';
          contextMenu.classList.add('visible');
          contextMenu.hidden = false;
          // prevent immediate click opening file
          suppressOpenClickTemporarily();
      }

      function hideContextMenu(){ 
          contextMenu.classList.remove('visible'); 
          contextMenu.hidden = true; 
          contextMenu.dataset.targetId = '';
          contextMenu.dataset.targetType = '';
          contextMenu.dataset.targetName = ''; // Clear item name
          contextMenu.dataset.targetFileType = '';
      }

      /*** Prevent immediate click after open context (so right-click/long-press doesn't also open file) */
      let _suppressOpenUntil = 0;
      function suppressOpenClickTemporarily(ms=350){
          _suppressOpenUntil = Date.now() + ms;
      }

      /*** Open file action (example) */
      function openFileById(id){
          if (Date.now() < _suppressOpenUntil) { return; } // Suppress if context menu just opened
          const fileItem = document.querySelector(`.file-item[data-id="${CSS.escape(id)}"]`);
          if (fileItem && fileItem.dataset.type === 'file') {
              window.location.href = `views/pages/file-view.php?file_id=${id}`;
          } else if (fileItem && fileItem.dataset.type === 'folder') {
              window.location.href = `index.php?folder=${id}`;
          }
      }

      /*** Delegated click: open on click (but blocked if context menu just opened) */
      document.addEventListener('click', function(e){
          // item-more button: open menu (works across devices)
          const moreBtn = e.target.closest('.item-more');
          if (moreBtn) {
              const file = closestFileItem(moreBtn);
              const r = moreBtn.getBoundingClientRect();
              showContextMenuFor(file, r.right - 5, r.bottom + 5);
              e.stopPropagation(); // Prevent click from bubbling to document and closing menu
              return;
          }

          // normal click to open file (only if not suppressed)
          const file = closestFileItem(e.target);
          // MODIFIED: Only open file/folder if the click is NOT on the checkbox
          if (file && !e.target.classList.contains('file-checkbox')) {
              openFileById(file.dataset.id);
          } else {
              // click outside => close menu
              hideContextMenu();
          }
      });

      /*** Desktop right-click (contextmenu) */
      document.addEventListener('contextmenu', function(e){
          if (! (document.body.classList.contains('desktop') || document.body.classList.contains('tablet-landscape')) ) return; // only desktop and tablet landscape
          const file = closestFileItem(e.target);
          if (file) {
              e.preventDefault();
              showContextMenuFor(file, e.clientX, e.clientY);
          } else {
              hideContextMenu();
          }
      });

      /*** Long-press for touch devices (iPad/tablet/phone)
          Implementation: listen pointerdown, if pointerType touch and hold >600ms => show menu.
          Cancel if pointer moves > threshold or pointerup/cancel before timer.
      ***/
      document.addEventListener('pointerdown', function(e){
          if (! (document.body.classList.contains('mobile') ||
              document.body.classList.contains('tablet-portrait') ||
              document.body.classList.contains('device-ipad')) ) return; // Only for mobile and tablet portrait

          const file = closestFileItem(e.target);
          if (!file) return;
          // MODIFIED: Do not trigger long press if the target is the checkbox
          if (e.target.classList.contains('file-checkbox')) return;

          if (e.pointerType !== 'touch') return; // only touch long-press

          const startX = e.clientX, startY = e.clientY;
          lpStart = file;
          lpTimer = setTimeout(()=> {
              showContextMenuFor(file, startX, startY);
              lpTimer = null;
              // Prevent default click behavior after long press
              suppressOpenClickTemporarily(); 
          }, longPressDuration);

          function onMove(ev){
              if (Math.hypot(ev.clientX - startX, ev.clientY - startY) > longPressMoveThreshold) {
                  clearLongPress();
              }
          }
          function clearLongPress(){
              if (lpTimer) clearTimeout(lpTimer);
              lpTimer = null;
              lpStart = null;
              file.removeEventListener('pointermove', onMove);
              file.removeEventListener('pointerup', clearLongPress);
              file.removeEventListener('pointercancel', clearLongPress);
          }
          file.addEventListener('pointermove', onMove);
          file.addEventListener('pointerup', clearLongPress);
          file.addEventListener('pointercancel', clearLongPress);
      });

      /*** Keyboard support: Enter opens, ContextMenu key / Shift+F10 opens menu for focused item */
      document.addEventListener('keydown', function(e){
          const focused = document.activeElement && document.activeElement.closest && document.activeElement.closest('.file-item');
          if (!focused) return;
          if (e.key === 'Enter' || e.key === ' ') {
              e.preventDefault();
              // MODIFIED: Only open file/folder if the focused element is not a checkbox
              if (!document.activeElement.classList.contains('file-checkbox')) {
                  openFileById(focused.dataset.id);
              }
          } else if (e.key === 'ContextMenu' || (e.shiftKey && e.key === 'F10')) {
              e.preventDefault();
              const rect = focused.getBoundingClientRect();
              showContextMenuFor(focused, rect.left + 8, rect.bottom + 8);
          }
      });

      /*** Click inside context menu => execute actions */
      contextMenu.addEventListener('click', function(e){
          const li = e.target.closest('[data-action]');
          if (!li) return;
          const action = li.dataset.action;
          const targetId = contextMenu.dataset.targetId;
          const targetType = contextMenu.dataset.targetType;
          const targetName = contextMenu.dataset.targetName; // Get item name

          if (action === 'toggle-star') { // Handle toggle-star action
              toggleStar(targetId, targetType, targetName);
          } else {
              handleMenuAction(action, targetId);
          }
          hideContextMenu();
      });

      /*** Hide menu on outside clicks/touch */
      document.addEventListener('click', function(e){ 
          if (!e.target.closest('#context-menu') && !e.target.closest('.item-more')) { // Exclude item-more button
              hideContextMenu(); 
          }
      });
      window.addEventListener('blur', hideContextMenu);

      /*** Menu handlers (placeholders - ganti sesuai API/backend) */
      function handleMenuAction(action, id){
          switch(action){
              case 'rename': renameFile(id); break;
              case 'download': downloadFile(id); break;
              case 'share': shareFileLink(id); break;
              case 'extract': extractZipFile(id); break; // Added extract
              case 'delete': deleteFile(id); break;
              default: console.log('Unknown action', action);
          }
      }

      // --- AJAX Content Update Function ---
      async function updateFileListAndFolders() {
          const params = new URLSearchParams();
          if (currentFolderId !== null) {
              params.set('folder', currentFolderId);
          }
          if (currentSearchQuery) {
              params.set('search', currentSearchQuery);
          }
          if (currentSizeFilter && currentSizeFilter !== 'none') { // Changed from releaseFilter
              params.set('size', currentSizeFilter); // Changed from release
          }
          if (currentFileTypeFilter && currentFileTypeFilter !== 'all') {
              params.set('file_type', currentFileTypeFilter);
          }

          const url = `index.php?${params.toString()}&ajax=1`; // Add ajax=1 to indicate AJAX request

          try {
              const response = await fetch(url);
              const html = await response.text();

              // Create a temporary div to parse the HTML
              const tempDiv = document.createElement('div');
              tempDiv.innerHTML = html;

              // Extract the relevant parts
              const newFileListView = tempDiv.querySelector('#fileListView table tbody').innerHTML;
              const newFileGridView = tempDiv.querySelector('#fileGridView .file-grid').innerHTML;
              const newBreadcrumbs = tempDiv.querySelector('.breadcrumbs').innerHTML;
              const newStorageInfo = tempDiv.querySelector('.storage-info').innerHTML;

              // Update the DOM
              document.querySelector('#fileListView table tbody').innerHTML = newFileListView;
              document.querySelector('#fileGridView .file-grid').innerHTML = newFileGridView;
              document.querySelector('.breadcrumbs').innerHTML = newBreadcrumbs;
              document.querySelector('.storage-info').innerHTML = newStorageInfo;

              // Re-attach event listeners to new elements
              updateSelectAllCheckboxListener(); // Re-attach select all listener

              // Update URL in browser history without reloading
              history.pushState(null, '', `index.php?${params.toString()}`);

          } catch (error) {
              console.error('Error updating file list:', error);
              showNotification('Failed to update file list. Please refresh the page.', 'error');
          }
      }

      // Event listener for breadcrumbs (folder navigation)
      document.querySelector('.breadcrumbs').addEventListener('click', function(event) {
          if (event.target.tagName === 'A') {
              event.preventDefault();
              const href = event.target.getAttribute('href');
              const url = new URL(href, window.location.origin);
              const folderId = url.searchParams.get('folder');
              currentFolderId = folderId ? parseInt(folderId) : null;
              currentSearchQuery = ''; // Reset search when navigating folders
              searchInput.value = ''; // Clear desktop search input
              searchInputMobile.value = ''; // Clear mobile search input
              updateFileListAndFolders();
          }
      });

      // --- Mobile Sidebar Toggle ---
      sidebarToggleBtn.addEventListener('click', () => {
          sidebar.classList.toggle('show-mobile-sidebar');
          mobileOverlay.classList.toggle('show');
      });

      // Initial call to attach listeners
      updateSelectAllCheckboxListener();

      // Check if the request is an AJAX request
      const urlParams = new URLSearchParams(window.location.search);
      if (urlParams.has('ajax') && urlParams.get('ajax') === '1') {
          // If it's an AJAX request, only output the content needed for AJAX update
          // This part should ideally be handled by a separate PHP file that returns JSON or HTML fragments
          // For this example, we'll assume index.php can conditionally render.
          // In a real application, you'd have a dedicated API endpoint.
          // Since the current PHP code already renders the full HTML, we'll just let it render.
          // The JS will then parse it.
      }
  });
</script>