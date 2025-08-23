/**
 * SKMI Cloud Storage - Main JavaScript
 * Core application functionality
 */

class SKMICloudApp {
  constructor() {
    this.init();
  }

  init() {
    this.setupEventListeners();
    this.initializeComponents();
    this.setupStorageMonitoring();
  }

  setupEventListeners() {
    // Sidebar toggle for mobile
    const sidebarToggle = document.querySelector('.sidebar-toggle');
    if (sidebarToggle) {
      sidebarToggle.addEventListener('click', () => {
        this.toggleSidebar();
      });
    }

    // Global click handler for closing modals
    document.addEventListener('click', (e) => {
      if (e.target.classList.contains('modal-overlay')) {
        this.closeModal(e.target);
      }
    });

    // Keyboard shortcuts
    document.addEventListener('keydown', (e) => {
      this.handleKeyboardShortcuts(e);
    });
  }

  initializeComponents() {
    // Initialize tooltips
    this.initTooltips();

    // Initialize file drag and drop
    this.initDragAndDrop();

    // Initialize progress bars
    this.initProgressBars();
  }

  setupStorageMonitoring() {
    // Monitor storage changes
    this.updateStorageInfo();

    // Update storage info every 30 seconds
    setInterval(() => {
      this.updateStorageInfo();
    }, 30000);
  }

  toggleSidebar() {
    const sidebar = document.querySelector('.sidebar');
    const mainContent = document.querySelector('.main-content');

    if (sidebar && mainContent) {
      sidebar.classList.toggle('collapsed');
      mainContent.classList.toggle('expanded');
    }
  }

  initTooltips() {
    const tooltipElements = document.querySelectorAll('[data-tooltip]');

    tooltipElements.forEach(element => {
      element.addEventListener('mouseenter', (e) => {
        this.showTooltip(e.target, e.target.dataset.tooltip);
      });

      element.addEventListener('mouseleave', () => {
        this.hideTooltip();
      });
    });
  }

  showTooltip(element, text) {
    const tooltip = document.createElement('div');
    tooltip.className = 'tooltip';
    tooltip.textContent = text;
    document.body.appendChild(tooltip);

    const rect = element.getBoundingClientRect();
    tooltip.style.left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2) + 'px';
    tooltip.style.top = rect.top - tooltip.offsetHeight - 10 + 'px';

    this.currentTooltip = tooltip;
  }

  hideTooltip() {
    if (this.currentTooltip) {
      this.currentTooltip.remove();
      this.currentTooltip = null;
    }
  }

  initDragAndDrop() {
    const dropZones = document.querySelectorAll('.drop-zone');

    dropZones.forEach(zone => {
      zone.addEventListener('dragover', (e) => {
        e.preventDefault();
        zone.classList.add('drag-over');
      });

      zone.addEventListener('dragleave', () => {
        zone.classList.remove('drag-over');
      });

      zone.addEventListener('drop', (e) => {
        e.preventDefault();
        zone.classList.remove('drag-over');
        this.handleFileDrop(e.dataTransfer.files, zone);
      });
    });
  }

  handleFileDrop(files, dropZone) {
    if (files.length > 0) {
      this.uploadFiles(files);
    }
  }

  uploadFiles(files) {
    const formData = new FormData();

    Array.from(files).forEach(file => {
      formData.append('files[]', file);
    });

    // Show upload progress
    this.showUploadProgress();

    // Send files to server
    fetch('../upload.php', {
      method: 'POST',
      body: formData
    })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          this.showNotification('Files uploaded successfully!', 'success');
          this.updateStorageInfo();
          // Refresh file list if on files page
          if (window.location.search.includes('page=files')) {
            this.refreshFileList();
          }
        } else {
          this.showNotification('Upload failed: ' + data.message, 'error');
        }
      })
      .catch(error => {
        this.showNotification('Upload failed: ' + error.message, 'error');
      })
      .finally(() => {
        this.hideUploadProgress();
      });
  }

  showUploadProgress() {
    const progress = document.createElement('div');
    progress.className = 'upload-progress';
    progress.innerHTML = `
            <div class="progress-content">
                <div class="progress-bar">
                    <div class="progress-fill"></div>
                </div>
                <span class="progress-text">Uploading files...</span>
            </div>
        `;
    document.body.appendChild(progress);

    this.uploadProgress = progress;
  }

  hideUploadProgress() {
    if (this.uploadProgress) {
      this.uploadProgress.remove();
      this.uploadProgress = null;
    }
  }

  initProgressBars() {
    const progressBars = document.querySelectorAll('.progress-bar');

    progressBars.forEach(bar => {
      const fill = bar.querySelector('.progress-fill');
      if (fill) {
        const width = fill.style.width;
        fill.style.width = '0%';

        setTimeout(() => {
          fill.style.width = width;
        }, 100);
      }
    });
  }

  updateStorageInfo() {
    fetch('../get_storage_info.php')
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          this.updateStorageDisplay(data.data);
        }
      })
      .catch(error => {
        console.error('Failed to update storage info:', error);
      });
  }

  updateStorageDisplay(storageData) {
    const progressBar = document.querySelector('.progress-bar');
    const storageText = document.querySelector('.storage-text');

    if (progressBar) {
      const fill = progressBar.querySelector('.progress-fill');
      if (fill) {
        fill.style.width = storageData.percentage + '%';
      }
    }

    if (storageText) {
      storageText.textContent = `${storageData.used} GB used of ${storageData.total} GB`;
    }
  }

  showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.textContent = message;

    document.body.appendChild(notification);

    // Show notification
    setTimeout(() => {
      notification.classList.add('show');
    }, 100);

    // Hide notification after 5 seconds
    setTimeout(() => {
      notification.classList.remove('show');
      setTimeout(() => {
        notification.remove();
      }, 300);
    }, 5000);
  }

  handleKeyboardShortcuts(e) {
    // Ctrl/Cmd + K: Focus search
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
      e.preventDefault();
      const searchInput = document.getElementById('searchInput');
      if (searchInput) {
        searchInput.focus();
      }
    }

    // Ctrl/Cmd + U: Upload files
    if ((e.ctrlKey || e.metaKey) && e.key === 'u') {
      e.preventDefault();
      this.openUploadModal();
    }

    // Escape: Close modals
    if (e.key === 'Escape') {
      this.closeAllModals();
    }
  }

  openUploadModal() {
    // Create and show upload modal
    const modal = document.createElement('div');
    modal.className = 'modal-overlay';
    modal.innerHTML = `
            <div class="modal">
                <div class="modal-header">
                    <h3>Upload Files</h3>
                    <button class="modal-close">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="drop-zone">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <p>Drag and drop files here or click to browse</p>
                        <input type="file" multiple style="display: none;">
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn-secondary" onclick="this.closest('.modal-overlay').remove()">Cancel</button>
                    <button class="btn-primary">Upload</button>
                </div>
            </div>
        `;

    document.body.appendChild(modal);

    // Setup file input
    const fileInput = modal.querySelector('input[type="file"]');
    const dropZone = modal.querySelector('.drop-zone');

    dropZone.addEventListener('click', () => {
      fileInput.click();
    });

    fileInput.addEventListener('change', (e) => {
      if (e.target.files.length > 0) {
        this.uploadFiles(e.target.files);
        modal.remove();
      }
    });

    // Close button
    const closeBtn = modal.querySelector('.modal-close');
    closeBtn.addEventListener('click', () => {
      modal.remove();
    });
  }

  closeModal(modal) {
    modal.remove();
  }

  closeAllModals() {
    const modals = document.querySelectorAll('.modal-overlay');
    modals.forEach(modal => modal.remove());
  }

  refreshFileList() {
    // Trigger file list refresh
    const event = new CustomEvent('filesUpdated');
    document.dispatchEvent(event);
  }
}

// Initialize the application when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
  window.skmiApp = new SKMICloudApp();
});

// Utility functions
function formatFileSize(bytes) {
  if (bytes === 0) return '0 Bytes';
  const k = 1024;
  const sizes = ['Bytes', 'KB', 'MB', 'GB'];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

function formatDate(dateString) {
  const date = new Date(dateString);
  const now = new Date();
  const diffTime = Math.abs(now - date);
  const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

  if (diffDays === 1) {
    return 'Today';
  } else if (diffDays === 2) {
    return 'Yesterday';
  } else if (diffDays < 7) {
    return `${diffDays - 1} days ago`;
  } else {
    return date.toLocaleDateString();
  }
}
