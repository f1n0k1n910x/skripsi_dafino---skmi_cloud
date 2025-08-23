<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require '../config.php';
require_once __DIR__ . '/helpers/utils.php';
require __DIR__ . '/services/storageService.php';

require 'services/authService.php';
require 'services/folderService.php';
require 'services/fileService.php';

session_start();
checkAuth(); // Redirects if not logged in

$currentFolderId = isset($_GET['folder']) ? (int)$_GET['folder'] : null;
$baseUploadDir   = __DIR__ . '/uploads/';

// Get folder details & breadcrumbs
$folder      = getFolderById($conn, $currentFolderId);
$breadcrumbs = getBreadcrumbs($conn, $currentFolderId);

// Fetch files & subfolders
$files      = getFiles($conn, $currentFolderId);
$subfolders = getSubfolders($conn, $currentFolderId);

// Render page
include 'views/pages/folder-list.php';
