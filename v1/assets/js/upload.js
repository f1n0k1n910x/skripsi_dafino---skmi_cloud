/**
 * SKMI Cloud Storage - Upload Functionality
 * Handles file uploads with drag & drop and progress tracking
 */

class FileUpload {
  constructor() {
    this.uploadQueue = [];
    this.uploading = false;
    this.maxFileSize = 100 * 1024 * 1024; // 100MB
    this.allowedTypes = [
      'image/*',
      'video/*',
      'audio/*',
      'application/pdf',
      'application/msword',
      'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
      'application/vnd.ms-excel',
      'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
      'application/vnd.ms-powerpoint',
      'application/vnd.openxmlformats-officedocument.presentationml.presentation',
      'text/*',
      'application/zip',
      'application/x-rar-compressed'
    ];
    this.init();
  }

  init() {
    this.setupUploadZones();
    this.setupFileInput();
    this.setupUploadModal();
    this.setupProgressTracking();
  }

  setupUploadZones() {
    // Find all drop zones on the page
    const dropZones = document.querySelectorAll('.drop-zone');

    dropZones.forEach(zone => {
      this.setupDropZone(zone);
    });

    // Create global drop zone for the entire page
    this.createGlobalDropZone();
  }

  setupDropZone(zone) {
    zone.addEventListener('dragover', (e) => {
      e.preventDefault();
      zone.classList.add('drag-over');
    });

    zone.addEventListener('dragleave', (e) => {
      e.preventDefault();
      if (!zone.contains(e.relatedTarget)) {
        zone.classList.remove('drag-over');
      }
    });

    zone.addEventListener('drop', (e) => {
      e.preventDefault();
      zone.classList.remove('drag-over');
      const files = Array.from(e.dataTransfer.files);
      this.handleFiles(files);
    });

    // Click to browse
    zone.addEventListener('click', () => {
      this.triggerFileInput();
    });
  }

  createGlobalDropZone() {
    // Create an invisible drop zone that covers the entire page
    const globalZone = document.createElement('div');
    globalZone.className = 'global-drop-zone';
    globalZone.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 9999;
            pointer-events: none;
            display: none;
        `;

    document.body.appendChild(globalZone);

    // Show global zone when dragging over the page
    document.addEventListener('dragenter', (e) => {
      if (e.dataTransfer.types.includes('Files')) {
        globalZone.style.display = 'block';
        globalZone.style.pointerEvents = 'auto';
        globalZone.classList.add('active');
      }
    });

    globalZone.addEventListener('dragover', (e) => {
      e.preventDefault();
      globalZone.classList.add('drag-over');
    });

    globalZone.addEventListener('dragleave', (e) => {
      e.preventDefault();
      if (!globalZone.contains(e.relatedTarget)) {
        globalZone.classList.remove('drag-over');
      }
    });

    globalZone.addEventListener('drop', (e) => {
      e.preventDefault();
      globalZone.classList.remove('drag-over', 'active');
      globalZone.style.display = 'none';
      globalZone.style.pointerEvents = 'none';

      const files = Array.from(e.dataTransfer.files);
      this.handleFiles(files);
    });
  }

  setupFileInput() {
    // Create hidden file input
    this.fileInput = document.createElement('input');
    this.fileInput.type = 'file';
    this.fileInput.multiple = true;
    this.fileInput.accept = this.allowedTypes.join(',');
    this.fileInput.style.display = 'none';

    document.body.appendChild(this.fileInput);

    this.fileInput.addEventListener('change', (e) => {
      const files = Array.from(e.target.files);
      this.handleFiles(files);
      e.target.value = ''; // Reset input
    });
  }

  setupUploadModal() {
    // Create upload modal
    this.uploadModal = document.createElement('div');
    this.uploadModal.className = 'upload-modal';
    this.uploadModal.style.display = 'none';
    this.uploadModal.innerHTML = `
            <div class="modal-overlay">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3>Upload Files</h3>
                        <button class="modal-close">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div class="upload-area">
                            <div class="drop-zone-large">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <h4>Drag & Drop Files Here</h4>
                                <p>or click to browse files</p>
                                <span class="file-limits">Max file size: 100MB</span>
                            </div>
                        </div>
                        <div class="upload-queue" style="display: none;">
                            <h4>Upload Queue</h4>
                            <div class="queue-items"></div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn-secondary" onclick="fileUpload.closeModal()">Cancel</button>
                        <button class="btn-primary upload-btn" onclick="fileUpload.startUpload()" disabled>Upload Files</button>
                    </div>
                </div>
            </div>
        `;

    document.body.appendChild(this.uploadModal);

    // Setup modal events
    this.setupModalEvents();
  }

  setupModalEvents() {
    const closeBtn = this.uploadModal.querySelector('.modal-close');
    const dropZone = this.uploadModal.querySelector('.drop-zone-large');

    closeBtn.addEventListener('click', () => {
      this.closeModal();
    });

    dropZone.addEventListener('click', () => {
      this.triggerFileInput();
    });

    // Setup drop zone in modal
    this.setupDropZone(dropZone);
  }

  setupProgressTracking() {
    // Create progress bar container
    this.progressContainer = document.createElement('div');
    this.progressContainer.className = 'upload-progress-container';
    this.progressContainer.style.display = 'none';
    this.progressContainer.innerHTML = `
            <div class="progress-header">
                <h4>Uploading Files</h4>
                <button class="progress-close">&times;</button>
            </div>
            <div class="progress-items"></div>
        `;

    document.body.appendChild(this.progressContainer);

    // Setup progress events
    const closeBtn = this.progressContainer.querySelector('.progress-close');
    closeBtn.addEventListener('click', () => {
      this.hideProgress();
    });
  }

  triggerFileInput() {
    this.fileInput.click();
  }

  handleFiles(files) {
    // Validate files
    const validFiles = files.filter(file => this.validateFile(file));

    if (validFiles.length === 0) {
      this.showNotification('No valid files to upload', 'warning');
      return;
    }

    // Add files to queue
    validFiles.forEach(file => {
      this.addToQueue(file);
    });

    // Show modal if not already visible
    if (!this.uploadModal.style.display || this.uploadModal.style.display === 'none') {
      this.showModal();
    }

    // Update queue display
    this.updateQueueDisplay();
  }

  validateFile(file) {
    // Check file size
    if (file.size > this.maxFileSize) {
      this.showNotification(`File "${file.name}" is too large. Max size: 100MB`, 'error');
      return false;
    }

    // Check file type
    const isValidType = this.allowedTypes.some(type => {
      if (type.endsWith('/*')) {
        return file.type.startsWith(type.slice(0, -1));
      }
      return file.type === type;
    });

    if (!isValidType) {
      this.showNotification(`File type "${file.type}" is not supported`, 'error');
      return false;
    }

    return true;
  }

  addToQueue(file) {
    const queueItem = {
      id: this.generateId(),
      file: file,
      status: 'pending', // pending, uploading, completed, error
      progress: 0,
      error: null
    };

    this.uploadQueue.push(queueItem);
  }

  updateQueueDisplay() {
    const queueContainer = this.uploadModal.querySelector('.upload-queue');
    const queueItems = this.uploadModal.querySelector('.queue-items');
    const uploadBtn = this.uploadModal.querySelector('.upload-btn');

    if (this.uploadQueue.length > 0) {
      queueContainer.style.display = 'block';
      uploadBtn.disabled = false;

      queueItems.innerHTML = this.uploadQueue.map(item => `
                <div class="queue-item" data-id="${item.id}">
                    <div class="item-info">
                        <i class="fas fa-file"></i>
                        <span class="item-name">${item.file.name}</span>
                        <span class="item-size">${this.formatFileSize(item.file.size)}</span>
                    </div>
                    <div class="item-status">
                        <span class="status-text">${item.status}</span>
                        <button class="remove-item" onclick="fileUpload.removeFromQueue('${item.id}')">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            `).join('');
    } else {
      queueContainer.style.display = 'none';
      uploadBtn.disabled = true;
    }
  }

  removeFromQueue(itemId) {
    const index = this.uploadQueue.findIndex(item => item.id === itemId);
    if (index > -1) {
      this.uploadQueue.splice(index, 1);
      this.updateQueueDisplay();
    }
  }

  startUpload() {
    if (this.uploading || this.uploadQueue.length === 0) return;

    this.uploading = true;
    this.closeModal();
    this.showProgress();

    // Process queue
    this.processQueue();
  }

  async processQueue() {
    for (let i = 0; i < this.uploadQueue.length; i++) {
      const item = this.uploadQueue[i];

      if (item.status === 'pending') {
        item.status = 'uploading';
        this.updateProgressDisplay();

        try {
          await this.uploadFile(item);
          item.status = 'completed';
          item.progress = 100;
        } catch (error) {
          item.status = 'error';
          item.error = error.message;
        }

        this.updateProgressDisplay();
      }
    }

    this.uploading = false;
    this.showUploadComplete();
  }

  async uploadFile(item) {
    return new Promise((resolve, reject) => {
      const formData = new FormData();
      formData.append('file', item.file);
      formData.append('user_id', this.getUserId());

      const xhr = new XMLHttpRequest();

      // Progress tracking
      xhr.upload.addEventListener('progress', (e) => {
        if (e.lengthComputable) {
          item.progress = Math.round((e.loaded / e.total) * 100);
          this.updateProgressDisplay();
        }
      });

      // Response handling
      xhr.addEventListener('load', () => {
        if (xhr.status === 200) {
          try {
            const response = JSON.parse(xhr.responseText);
            if (response.success) {
              resolve();
            } else {
              reject(new Error(response.message || 'Upload failed'));
            }
          } catch (e) {
            reject(new Error('Invalid response from server'));
          }
        } else {
          reject(new Error(`HTTP ${xhr.status}: ${xhr.statusText}`));
        }
      });

      xhr.addEventListener('error', () => {
        reject(new Error('Network error occurred'));
      });

      xhr.addEventListener('abort', () => {
        reject(new Error('Upload was cancelled'));
      });

      // Send request
      xhr.open('POST', '../upload.php');
      xhr.send(formData);
    });
  }

  updateProgressDisplay() {
    const progressItems = this.progressContainer.querySelector('.progress-items');

    progressItems.innerHTML = this.uploadQueue.map(item => `
            <div class="progress-item ${item.status}">
                <div class="item-info">
                    <span class="item-name">${item.file.name}</span>
                    <span class="item-size">${this.formatFileSize(item.file.size)}</span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: ${item.progress}%"></div>
                </div>
                <div class="item-status">
                    <span class="status-text">${item.status}</span>
                    ${item.error ? `<span class="error-text">${item.error}</span>` : ''}
                </div>
            </div>
        `).join('');
  }

  showModal() {
    this.uploadModal.style.display = 'block';
    document.body.style.overflow = 'hidden';
  }

  closeModal() {
    this.uploadModal.style.display = 'none';
    document.body.style.overflow = '';
  }

  showProgress() {
    this.progressContainer.style.display = 'block';
  }

  hideProgress() {
    this.progressContainer.style.display = 'none';
  }

  showUploadComplete() {
    const completed = this.uploadQueue.filter(item => item.status === 'completed').length;
    const errors = this.uploadQueue.filter(item => item.status === 'error').length;

    let message = `Upload completed! ${completed} file(s) uploaded successfully.`;
    if (errors > 0) {
      message += ` ${errors} file(s) failed.`;
    }

    this.showNotification(message, errors > 0 ? 'warning' : 'success');

    // Clear queue after showing completion
    setTimeout(() => {
      this.uploadQueue = [];
      this.hideProgress();

      // Refresh file list if on files page
      if (window.location.search.includes('page=files')) {
        this.refreshFileList();
      }
    }, 3000);
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

  refreshFileList() {
    // Trigger file list refresh
    const event = new CustomEvent('filesUpdated');
    document.dispatchEvent(event);
  }

  // Utility methods
  generateId() {
    return Date.now().toString(36) + Math.random().toString(36).substr(2);
  }

  formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
  }

  getUserId() {
    // Get user ID from session or global variable
    return window.userId || 1;
  }
}

// Initialize upload functionality
let fileUpload;
document.addEventListener('DOMContentLoaded', () => {
  fileUpload = new FileUpload();
});

// Global functions for external access
function openUploadModal() {
  if (fileUpload) {
    fileUpload.showModal();
  }
}

function uploadFiles(files) {
  if (fileUpload) {
    fileUpload.handleFiles(files);
  }
}
