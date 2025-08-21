/**
 * SKMI Cloud Storage - Search Functionality
 * Handles file and folder search
 */

class FileSearch {
  constructor() {
    this.searchInput = null;
    this.searchResults = [];
    this.currentSearchTerm = '';
    this.searchTimeout = null;
    this.init();
  }

  init() {
    this.setupSearchInput();
    this.setupSearchFilters();
    this.setupSearchResults();
  }

  setupSearchInput() {
    this.searchInput = document.getElementById('searchInput');
    if (!this.searchInput) return;

    // Debounced search
    this.searchInput.addEventListener('input', (e) => {
      clearTimeout(this.searchTimeout);
      this.searchTimeout = setTimeout(() => {
        this.performSearch(e.target.value);
      }, 300);
    });

    // Search on Enter
    this.searchInput.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        e.preventDefault();
        this.performSearch(e.target.value);
      }
    });

    // Clear search on Escape
    this.searchInput.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        e.target.value = '';
        this.clearSearch();
      }
    });
  }

  setupSearchFilters() {
    // Add search filters if they exist
    const filterContainer = document.querySelector('.search-filters');
    if (filterContainer) {
      this.setupFilterButtons(filterContainer);
    }
  }

  setupFilterButtons(container) {
    const filters = [
      { name: 'all', label: 'All', icon: 'fas fa-th' },
      { name: 'documents', label: 'Documents', icon: 'fas fa-file-alt' },
      { name: 'images', label: 'Images', icon: 'fas fa-image' },
      { name: 'videos', label: 'Videos', icon: 'fas fa-video' },
      { name: 'music', label: 'Music', icon: 'fas fa-music' },
      { name: 'archives', label: 'Archives', icon: 'fas fa-archive' }
    ];

    filters.forEach(filter => {
      const button = document.createElement('button');
      button.className = 'filter-btn';
      button.dataset.filter = filter.name;
      button.innerHTML = `<i class="${filter.icon}"></i> ${filter.label}`;

      button.addEventListener('click', () => {
        this.setActiveFilter(filter.name);
      });

      container.appendChild(button);
    });

    // Set default active filter
    this.setActiveFilter('all');
  }

  setActiveFilter(filterName) {
    // Update active filter button
    const filterBtns = document.querySelectorAll('.filter-btn');
    filterBtns.forEach(btn => {
      btn.classList.remove('active');
      if (btn.dataset.filter === filterName) {
        btn.classList.add('active');
      }
    });

    // Update search results with filter
    if (this.currentSearchTerm) {
      this.performSearch(this.currentSearchTerm, filterName);
    }
  }

  setupSearchResults() {
    // Create search results container if it doesn't exist
    if (!document.querySelector('.search-results')) {
      const resultsContainer = document.createElement('div');
      resultsContainer.className = 'search-results';
      resultsContainer.style.display = 'none';

      const contentBody = document.querySelector('.content-body');
      if (contentBody) {
        contentBody.appendChild(resultsContainer);
      }
    }
  }

  async performSearch(query, filter = 'all') {
    if (!query.trim()) {
      this.clearSearch();
      return;
    }

    this.currentSearchTerm = query;

    try {
      // Show loading state
      this.showLoadingState();

      // Perform search
      const results = await this.searchFiles(query, filter);
      this.displaySearchResults(results, query);

    } catch (error) {
      console.error('Search failed:', error);
      this.showSearchError('Search failed. Please try again.');
    }
  }

  async searchFiles(query, filter) {
    const params = new URLSearchParams({
      search: query,
      filter: filter,
      user_id: this.getUserId()
    });

    const response = await fetch(`../search_files.php?${params}`);
    const data = await response.json();

    if (!data.success) {
      throw new Error(data.message || 'Search failed');
    }

    return data.results;
  }

  displaySearchResults(results, query) {
    const resultsContainer = document.querySelector('.search-results');
    if (!resultsContainer) return;

    if (results.length === 0) {
      resultsContainer.innerHTML = this.getNoResultsHTML(query);
    } else {
      resultsContainer.innerHTML = this.getResultsHTML(results, query);
    }

    resultsContainer.style.display = 'block';

    // Hide other content
    this.hideOtherContent();

    // Setup result interactions
    this.setupResultInteractions();
  }

  getNoResultsHTML(query) {
    return `
            <div class="no-results">
                <i class="fas fa-search"></i>
                <h3>No results found</h3>
                <p>No files or folders found matching "<strong>${this.escapeHtml(query)}</strong>"</p>
                <div class="search-suggestions">
                    <h4>Suggestions:</h4>
                    <ul>
                        <li>Check your spelling</li>
                        <li>Try different keywords</li>
                        <li>Use more general terms</li>
                        <li>Check file and folder names</li>
                    </ul>
                </div>
            </div>
        `;
  }

  getResultsHTML(results, query) {
    const resultsCount = results.length;
    const highlightedQuery = this.escapeHtml(query);

    let html = `
            <div class="search-header">
                <h3>Search Results for "${highlightedQuery}"</h3>
                <p>Found ${resultsCount} result${resultsCount !== 1 ? 's' : ''}</p>
                <button class="btn-secondary" onclick="fileSearch.clearSearch()">
                    <i class="fas fa-times"></i> Clear Search
                </button>
            </div>
            <div class="search-results-grid">
        `;

    results.forEach(result => {
      html += this.getResultItemHTML(result, query);
    });

    html += '</div>';
    return html;
  }

  getResultItemHTML(result, query) {
    const icon = this.getFileIcon(result.type, result.is_folder);
    const name = this.highlightSearchTerm(result.name, query);
    const size = result.is_folder ? '' : this.formatFileSize(result.size);
    const date = this.formatDate(result.modified_date);

    return `
            <div class="search-result-item" data-id="${result.id}" data-type="${result.is_folder ? 'folder' : 'file'}">
                <div class="result-icon">
                    <i class="${icon}"></i>
                </div>
                <div class="result-info">
                    <h4 class="result-name">${name}</h4>
                    <p class="result-path">${this.escapeHtml(result.path)}</p>
                    <div class="result-meta">
                        ${size ? `<span class="result-size">${size}</span>` : ''}
                        <span class="result-date">${date}</span>
                    </div>
                </div>
                <div class="result-actions">
                    ${result.is_folder ?
        `<button class="btn-icon" onclick="fileSearch.openFolder('${result.id}')" title="Open Folder">
                            <i class="fas fa-folder-open"></i>
                         </button>` :
        `<button class="btn-icon" onclick="fileSearch.downloadFile('${result.id}')" title="Download">
                            <i class="fas fa-download"></i>
                         </button>`
      }
                    <button class="btn-icon" onclick="fileSearch.showFileMenu('${result.id}', event)" title="More Options">
                        <i class="fas fa-ellipsis-v"></i>
                    </button>
                </div>
            </div>
        `;
  }

  getFileIcon(type, isFolder) {
    if (isFolder) return 'fas fa-folder';

    const iconMap = {
      'document': 'fas fa-file-alt',
      'image': 'fas fa-image',
      'video': 'fas fa-video',
      'music': 'fas fa-music',
      'archive': 'fas fa-archive',
      'code': 'fas fa-code',
      'pdf': 'fas fa-file-pdf'
    };

    return iconMap[type] || 'fas fa-file';
  }

  highlightSearchTerm(text, query) {
    if (!query) return this.escapeHtml(text);

    const regex = new RegExp(`(${this.escapeRegex(query)})`, 'gi');
    return this.escapeHtml(text).replace(regex, '<mark>$1</mark>');
  }

  setupResultInteractions() {
    // Add click handlers for result items
    const resultItems = document.querySelectorAll('.search-result-item');
    resultItems.forEach(item => {
      item.addEventListener('click', (e) => {
        if (!e.target.closest('.result-actions')) {
          this.openResult(item);
        }
      });

      // Double click to open
      item.addEventListener('dblclick', () => {
        this.openResult(item);
      });
    });
  }

  openResult(item) {
    const itemId = item.dataset.id;
    const itemType = item.dataset.type;

    if (itemType === 'folder') {
      this.openFolder(itemId);
    } else {
      this.openFile(itemId);
    }
  }

  openFolder(folderId) {
    // Navigate to folder
    window.location.href = `?page=files&folder=${folderId}`;
  }

  openFile(fileId) {
    // Open file viewer or download
    window.open(`../view.php?id=${fileId}`, '_blank');
  }

  downloadFile(fileId) {
    // Trigger file download
    window.location.href = `../download.php?id=${fileId}`;
  }

  showFileMenu(fileId, event) {
    event.stopPropagation();

    // Create context menu
    const menu = document.createElement('div');
    menu.className = 'context-menu';
    menu.innerHTML = `
            <ul>
                <li onclick="fileSearch.downloadFile('${fileId}')">
                    <i class="fas fa-download"></i> Download
                </li>
                <li onclick="fileSearch.shareFile('${fileId}')">
                    <i class="fas fa-share"></i> Share
                </li>
                <li onclick="fileSearch.renameFile('${fileId}')">
                    <i class="fas fa-edit"></i> Rename
                </li>
                <li onclick="fileSearch.moveFile('${fileId}')">
                    <i class="fas fa-folder-open"></i> Move
                </li>
                <li onclick="fileSearch.deleteFile('${fileId}')">
                    <i class="fas fa-trash"></i> Delete
                </li>
            </ul>
        `;

    // Position and show menu
    document.body.appendChild(menu);

    const rect = event.target.getBoundingClientRect();
    menu.style.left = rect.left + 'px';
    menu.style.top = rect.bottom + 5 + 'px';

    // Hide menu on outside click
    setTimeout(() => {
      document.addEventListener('click', () => {
        menu.remove();
      }, { once: true });
    }, 100);
  }

  clearSearch() {
    this.currentSearchTerm = '';

    // Clear search input
    if (this.searchInput) {
      this.searchInput.value = '';
    }

    // Hide search results
    const resultsContainer = document.querySelector('.search-results');
    if (resultsContainer) {
      resultsContainer.style.display = 'none';
    }

    // Show other content
    this.showOtherContent();
  }

  hideOtherContent() {
    // Hide dashboard content when showing search results
    const dashboardContent = document.querySelector('.dashboard-container');
    if (dashboardContent) {
      dashboardContent.style.display = 'none';
    }
  }

  showOtherContent() {
    // Show dashboard content when clearing search
    const dashboardContent = document.querySelector('.dashboard-container');
    if (dashboardContent) {
      dashboardContent.style.display = 'block';
    }
  }

  showLoadingState() {
    const resultsContainer = document.querySelector('.search-results');
    if (resultsContainer) {
      resultsContainer.innerHTML = `
                <div class="search-loading">
                    <i class="fas fa-spinner fa-spin"></i>
                    <p>Searching...</p>
                </div>
            `;
      resultsContainer.style.display = 'block';
    }
  }

  showSearchError(message) {
    const resultsContainer = document.querySelector('.search-results');
    if (resultsContainer) {
      resultsContainer.innerHTML = `
                <div class="search-error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <h3>Search Error</h3>
                    <p>${message}</p>
                </div>
            `;
      resultsContainer.style.display = 'block';
    }
  }

  // Utility methods
  getUserId() {
    // Get user ID from session or global variable
    return window.userId || 1;
  }

  escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  escapeRegex(text) {
    return text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  }

  formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
  }

  formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString();
  }

  // Placeholder methods for file operations
  shareFile(fileId) {
    console.log('Share file:', fileId);
    // Implement share functionality
  }

  renameFile(fileId) {
    console.log('Rename file:', fileId);
    // Implement rename functionality
  }

  moveFile(fileId) {
    console.log('Move file:', fileId);
    // Implement move functionality
  }

  deleteFile(fileId) {
    if (confirm('Are you sure you want to delete this file?')) {
      console.log('Delete file:', fileId);
      // Implement delete functionality
    }
  }
}

// Initialize search functionality
let fileSearch;
document.addEventListener('DOMContentLoaded', () => {
  fileSearch = new FileSearch();
});
