<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SKMI Cloud Storage</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="css/internal.css">
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

        .search-bar {
            display: flex;
            align-items: center;
            background-color: var(--background-color); /* Light grey for search bar */
            border-radius: 0; /* Siku-siku */
            padding: 8px 12px;
            box-shadow: none; /* No box-shadow */
            border: 1px solid var(--divider-color); /* Subtle border */
            transition: border-color 0.2s ease-out;
        }

        .search-bar:focus-within {
            background-color: var(--surface-color);
            border-color: var(--primary-color); /* Material primary color on focus */
            box-shadow: none; /* No box-shadow */
        }

        .search-bar input {
            border: none;
            outline: none;
            padding: 5px;
            font-size: 0.95em;
            width: 250px; /* Slightly narrower */
            background: transparent;
            color: var(--text-color);
        }

        .search-bar i {
            color: var(--secondary-text-color);
            margin-right: 10px;
        }

        /* Toolbar */
        .toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px; /* Reduced margin */
            padding-bottom: 10px;
            border-bottom: 1px solid var(--divider-color);
        }

        .toolbar-left, .toolbar-right {
            display: flex;
            gap: 8px; /* Reduced gap */
        }

        .toolbar-left button, .toolbar-right button {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 9px 18px; /* Reduced padding */
            border-radius: 0; /* Siku-siku */
            cursor: pointer;
            font-size: 0.9em;
            transition: background-color 0.2s ease-out, transform 0.1s ease-in-out;
            box-shadow: none; /* No box-shadow */
            white-space: nowrap;
        }

        .toolbar-left button:hover, .toolbar-right button:hover {
            background-color: var(--primary-dark-color);
            transform: translateY(0); /* No lift */
        }

        .toolbar-left button:active, .toolbar-right button:active {
            transform: translateY(0);
            box-shadow: none; /* No box-shadow */
        }

        .toolbar-left button i {
            margin-right: 6px; /* Reduced margin */
        }

        /* Archive button specific style */
        #archiveSelectedBtn {
            background-color: var(--warning-color); /* Amber for archive */
        }
        #archiveSelectedBtn:hover {
            background-color: #FFB300; /* Darker amber on hover */
        }

        .view-toggle button {
            background-color: var(--background-color);
            border: 1px solid var(--divider-color);
            padding: 7px 10px; /* Reduced padding */
            border-radius: 0; /* Siku-siku */
            cursor: pointer;
            font-size: 1em;
            color: var(--secondary-text-color);
            transition: background-color 0.2s ease-out, color 0.2s ease-out, border-color 0.2s ease-out;
        }

        .view-toggle button.active {
            background-color: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        /* Breadcrumbs (Material Design style) */
        .breadcrumbs {
            margin-bottom: 15px;
            font-size: 0.9em;
            color: var(--secondary-text-color);
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            padding: 8px 0;
        }

        .breadcrumbs a {
            color: var(--primary-color);
            text-decoration: none;
            margin-right: 5px;
            transition: color 0.2s ease-out;
        }

        .breadcrumbs a:hover {
            text-decoration: underline;
            color: var(--primary-dark-color);
        }

        .breadcrumbs span {
            margin-right: 5px;
            color: var(--divider-color);
        }

        /* File and Folder Display */
        .file-list-container {
            flex-grow: 1;
            background-color: var(--surface-color);
            border-radius: 0; /* Siku-siku */
            box-shadow: none; /* No box-shadow */
            padding: 0;
            overflow: auto;
            -webkit-overflow-scrolling: touch;
            touch-action: pan-y;
            border: 1px solid var(--divider-color); /* Subtle border for container */
        }

        /* List View (Google Drive Style) */
        .file-table {
            width: 100%;
            border-collapse: collapse; /* Kunci utama */
            table-layout: fixed; /* Kunci utama */
            margin-top: 0;
            border: none; /* Remove outer border, container has it */
        }

        .file-table th, .file-table td {
            border-top: none;
            border-left: none;
            border-right: none;
            padding: 12px 24px;
            vertical-align: middle; /* Pastikan konten vertikal rata tengah */
            font-size: 0.875em;
            color: #3c4043; /* Google Drive text color */
        }

        .file-table th {
            background-color: #f8f9fa; /* Google Drive header background */
            color: #5f6368; /* Google Drive header text */
            font-weight: 500;
            text-transform: none;
            position: sticky;
            top: 0;
            z-index: 1;
            text-align: left; /* Biar header selalu rata */
        }

        .file-table tbody tr:last-child td {
            border-bottom: none;
        }

        .file-table tbody tr:hover {
            background-color: #f0f0f0; /* Google Drive hover effect */
        }

        /* Hilangkan efek patah di icon atau checkbox */
        .file-table td:first-child,
        .file-table th:first-child {
            width: 40px; /* Lebar tetap untuk checkbox/icon */
            text-align: center;
        }

        .file-icon {
            margin-right: 16px; /* Google Drive spacing */
            font-size: 1.2em;
            width: auto;
            text-align: center;
            flex-shrink: 0;
        }

        .file-icon.folder { color: #fbc02d; } /* Google Drive folder color */
        /* Other file icon colors are handled by internal.css */

        .file-name-cell {
            display: flex;
            align-items: center;
            max-width: 400px; /* Adjusted max-width */
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .file-name-cell a {
            color: #3c4043; /* Google Drive text color */
            text-decoration: none;
            font-weight: 400;
            display: block;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            transition: color 0.2s ease-out;
        }

        .file-name-cell a:hover {
            color: #1a73e8; /* Google Drive blue on hover */
        }

        .file-checkbox {
            margin-right: 16px; /* Google Drive spacing */
            transform: scale(1.0);
            accent-color: #1a73e8; /* Google Drive blue for checkbox */
        }

        /* Context Menu Styles (Material Design) */
        .context-menu {
            position: fixed;
            z-index: 12000;
            background: var(--surface-color);
            border: 1px solid var(--divider-color);
            box-shadow: 0px 2px 4px rgba(0,0,0,0.2); /* Subtle shadow */
            border-radius: 0; /* Siku-siku */
            overflow: hidden;
            min-width: 180px;
            display: none;
            animation: fadeInScale 0.2s ease-out forwards;
            transform-origin: top left;
        }
        .context-menu.visible {
            display: block;
        }

        .context-menu ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .context-menu li {
            color: var(--text-color);
            padding: 10px 15px;
            text-decoration: none;
            display: flex;
            align-items: center;
            font-size: 0.9em;
            transition: background-color 0.2s ease-out, color 0.2s ease-out;
            cursor: pointer;
            border-bottom: none !important; /* No border-bottom */
        }

        .context-menu li i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }

        .context-menu li:hover {
            background-color: var(--primary-color);
            color: #FFFFFF;
        }

        .context-menu .separator {
            height: 1px;
            background-color: var(--divider-color);
            margin: 5px 0;
        }

        /* Grid View (Google Drive Style) */
        .file-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 16px; /* Reduced gap */
            padding: 16px; /* Padding for the grid container */
        }

        .grid-item {
            background-color: var(--surface-color);
            border: 1px solid #dadce0; /* Google Drive border color */
            border-radius: 8px; /* Rounded corners */
            padding: 12px;
            display: flex;
            flex-direction: column;
            align-items: flex-start; /* Align content to start */
            text-align: left; /* Align text to left */
            box-shadow: none; /* No box-shadow */
            transition: all 0.2s ease-out;
            position: relative;
            overflow: hidden;
            user-select: none;
            cursor: pointer;
        }

        .grid-item:hover {
            box-shadow: 0 1px 3px rgba(60,64,67,.3), 0 4px 8px rgba(60,64,67,.15); /* Google Drive hover shadow */
            transform: translateY(0); /* No lift */
            border-color: transparent; /* Border disappears on hover */
        }

        .grid-item .file-checkbox {
            position: absolute;
            top: 8px;
            left: 8px;
            z-index: 2;
            transform: scale(1.0);
            accent-color: #1a73e8;
        }

        .grid-item .item-more {
            position: absolute;
            top: 8px;
            right: 8px;
            z-index: 2;
            background-color: rgba(255,255,255,0.8);
            border-radius: 50%;
            padding: 4px;
            font-size: 1em;
            color: #5f6368;
            opacity: 0; /* Hidden by default */
            transition: opacity 0.2s ease-out;
        }

        .grid-item:hover .item-more {
            opacity: 1; /* Show on hover */
        }

        .grid-thumbnail {
            width: 100%;
            height: 120px;
            margin-bottom: 8px;
            border: none;
            background-color: #e8f0fe; /* Light blue background for folders/generic files */
            border-radius: 4px;
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: hidden;
            position: relative;
            font-size: 12px;
            color: var(--text-color);
            text-align: left;
            padding: 5px;
            box-sizing: border-box;
        }

        .grid-thumbnail i {
            font-size: 3.5em;
            color: #1a73e8; /* Google Drive blue for icons */
        }

        .grid-thumbnail img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        .grid-thumbnail video, .grid-thumbnail audio {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .grid-thumbnail pre {
            font-size: 9px;
            white-space: pre-wrap;
            word-break: break-all;
            margin: 0;
            padding: 0;
            max-height: 100%;
            overflow: hidden;
        }

        .grid-thumbnail .file-type-label {
            display: none; /* Hide file type label in grid thumbnail */
        }

        .file-name {
            font-weight: 400;
            color: #3c4043;
            text-decoration: none;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 100%;
            display: block;
            margin-top: 0;
            padding-left: 4px;
            padding-right: 4px;
            font-size: 0.9375em;
            transition: color 0.2s ease-out;
        }
        
        .file-name:hover {
            color: #1a73e8;
        }

        .file-size {
            font-size: 0.8125em;
            color: #5f6368;
            margin-top: 4px;
            padding-left: 4px;
        }

        .action-buttons {
            display: flex;
            justify-content: center;
            margin-top: 10px;
            gap: 6px;
            width: 100%;
        }

        .action-buttons button {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 5px 9px;
            border-radius: 0; /* Siku-siku */
            cursor: pointer;
            font-size: 0.75em;
            transition: background-color 0.2s ease-out;
            box-shadow: none; /* No box-shadow */
        }

        .action-buttons button:hover {
            background-color: var(--primary-dark-color);
        }
        .action-buttons button.delete-button {
            background-color: var(--error-color);
        }
        .action-buttons button.delete-button:hover {
            background-color: #D32F2F;
        }
        .action-buttons button.extract-button:hover {
            background-color: #FF8F00; /* Darker amber on hover */
            color: #FFFFFF;
        }

        /* Modal Styles (Material Design) */
        .modal {
            display: flex;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.6);
            justify-content: center;
            align-items: center;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease-out, visibility 0.3s ease-out;
        }

        .modal.show {
            opacity: 1;
            visibility: visible;
        }

        .modal-content {
            background-color: var(--surface-color);
            padding: 30px;
            border-radius: 0; /* Siku-siku */
            box-shadow: 0 8px 17px 2px rgba(0,0,0,0.14), 0 3px 14px 2px rgba(0,0,0,0.12), 0 5px 5px -3px rgba(0,0,0,0.2); /* Material Design shadow */
            width: 90%;
            max-width: 550px;
            position: relative;
            transform: translateY(-20px);
            opacity: 0;
            transition: transform 0.3s ease-out, opacity 0.3s ease-out;
        }

        .modal.show .modal-content {
            transform: translateY(0);
            opacity: 1;
        }

        .close-button {
            color: var(--secondary-text-color);
            position: absolute;
            top: 15px;
            right: 20px;
            font-size: 28px; /* Slightly smaller */
            font-weight: normal;
            cursor: pointer;
            transition: color 0.2s ease-out;
        }

        .close-button:hover, .close-button:focus {
            color: var(--error-color);
        }

        .modal h2 {
            margin-top: 0;
            margin-bottom: 20px; /* Reduced margin */
            color: var(--text-color);
            font-size: 1.8em; /* Slightly smaller */
            font-weight: 400;
            border-bottom: 1px solid var(--divider-color);
            padding-bottom: 15px;
        }

        .modal label {
            display: block;
            margin-bottom: 8px; /* Reduced margin */
            font-weight: 500;
            color: var(--text-color);
            font-size: 1em;
        }

        .modal input[type="text"], .modal input[type="file"] {
            width: calc(100% - 24px); /* Adjust for padding and border */
            padding: 10px; /* Reduced padding */
            margin-bottom: 15px; /* Reduced margin */
            border: 1px solid var(--divider-color);
            border-radius: 0; /* Siku-siku */
            font-size: 0.95em;
            color: var(--text-color);
            background-color: var(--background-color);
            transition: border-color 0.2s ease-out, box-shadow 0.2s ease-out;
        }
        
        .modal input[type="text"]:focus, .modal input[type="file"]:focus {
            border-color: var(--primary-color);
            box-shadow: none; /* No box-shadow */
            outline: none;
            background-color: var(--surface-color);
        }

        .modal button[type="submit"] {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 10px 20px; /* Reduced padding */
            border-radius: 0; /* Siku-siku */
            cursor: pointer;
            font-size: 1em;
            transition: background-color 0.2s ease-out;
            box-shadow: none; /* No box-shadow */
        }

        .modal button[type="submit"]:hover {
            background-color: var(--primary-dark-color);
        }

        .hidden {
            display: none !important;
        }

        /* Custom Notification Styles (Material Design) */
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 12px 20px; /* Reduced padding */
            border-radius: 0; /* Siku-siku */
            color: white;
            font-weight: 500;
            z-index: 1001;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2); /* Subtle shadow */
            opacity: 0;
            transform: translateY(-20px);
            transition: opacity 0.3s ease-out, transform 0.3s ease-out;
        }

        .notification.show {
            opacity: 1;
            transform: translateY(0);
        }

        .notification.success {
            background-color: var(--success-color);
        }

        .notification.error {
            background-color: var(--error-color);
        }

        .notification.info {
            background-color: var(--primary-color);
        }

        /* Upload Preview Modal Specific Styles */
        #uploadPreviewModal .modal-content {
            max-width: 600px; /* Slightly smaller */
            padding: 20px;
        }

        #uploadPreviewModal .modal-header {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            border-bottom: 1px solid var(--divider-color);
            padding-bottom: 10px;
        }

        #uploadPreviewModal .modal-header h2 {
            flex-grow: 1;
            margin: 0;
            font-size: 1.8em;
            font-weight: 400;
            border-bottom: none;
            padding-bottom: 0;
        }

        #uploadPreviewModal .modal-header .back-button {
            background: none;
            border: none;
            font-size: 1.5em;
            cursor: pointer;
            margin-right: 10px;
            color: var(--secondary-text-color);
            transition: color 0.2s ease-out;
        }
        #uploadPreviewModal .modal-header .back-button:hover {
            color: var(--primary-color);
        }

        #uploadPreviewList {
            max-height: 400px; /* Reduced height */
            overflow-y: auto;
            margin-bottom: 15px;
            padding-right: 8px;
        }

        .upload-item {
            display: flex;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid var(--divider-color);
            transition: background-color 0.2s ease-out;
        }

        .upload-item:last-child {
            border-bottom: none;
        }
        .upload-item:hover {
            background-color: var(--background-color);
        }

        .upload-item .file-icon {
            font-size: 2.2em; /* Reduced icon size */
            margin-right: 15px;
            flex-shrink: 0;
            width: 40px;
            text-align: center;
        }

        .upload-item-info {
            flex-grow: 1;
        }

        .upload-item-info strong {
            display: block;
            font-weight: 500;
            color: var(--text-color);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 100%;
            font-size: 1em;
        }

        .upload-progress-container {
            width: 100%;
            background-color: var(--divider-color);
            border-radius: 0; /* Siku-siku */
            height: 6px;
            margin-top: 6px;
            overflow: hidden;
            position: relative;
        }

        .upload-progress-bar {
            height: 100%;
            background-color: var(--success-color);
            border-radius: 0; /* Siku-siku */
            width: 0%;
            transition: width 0.3s ease-out;
        }

        .upload-status-icon {
            font-size: 1.4em; /* Reduced status icon size */
            margin-left: 15px;
            flex-shrink: 0;
            width: 25px;
            text-align: center;
        }

        .upload-status-icon.processing { color: var(--primary-color); }
        .upload-status-icon.success { color: var(--success-color); }
        .upload-status-icon.error { color: var(--error-color); }
        .upload-status-icon.cancelled { color: var(--warning-color); }
        
        .upload-action-button {
            background: none;
            border: none;
            font-size: 1.2em; /* Reduced action button size */
            cursor: pointer;
            color: var(--secondary-text-color);
            margin-left: 10px;
            transition: color 0.2s ease-out;
        }

        .upload-action-button:hover {
            color: var(--error-color);
        }

        .upload-item.complete .upload-action-button {
            display: none;
        }

        /* Styles for the dropdown containers (Material Design) */
        .dropdown-container {
            position: relative;
            display: inline-block;
        }

        .dropdown-content {
            display: none;
            position: absolute;
            background-color: var(--surface-color);
            min-width: 180px;
            box-shadow: 0px 2px 4px rgba(0,0,0,0.2); /* Subtle shadow */
            z-index: 10;
            border-radius: 0; /* Siku-siku */
            margin-top: 8px;
            animation: fadeInScale 0.2s ease-out forwards;
            transform-origin: top left;
        }

        .dropdown-content a {
            color: var(--text-color);
            padding: 10px 15px;
            text-decoration: none;
            display: block;
            font-size: 0.9em;
            transition: background-color 0.2s ease-out, color 0.2s ease-out;
            border-bottom: none !important; /* No border-bottom */
        }

        .dropdown-content a:hover {
            background-color: var(--primary-color);
            color: #FFFFFF;
        }

        .dropdown-container.show .dropdown-content {
            display: block;
        }

        /* Style for filter buttons (icons only) */
        .filter-button {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 9px 11px; /* Adjusted padding */
            border-radius: 0; /* Siku-siku */
            cursor: pointer;
            font-size: 1.1em;
            transition: background-color 0.2s ease-out;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: none; /* No box-shadow */
            white-space: nowrap; /* Prevent text wrapping */
        }

        .filter-button:hover {
            background-color: var(--primary-dark-color);
        }

        /* Share Link Modal */
        #shareLinkModal .modal-content {
            max-width: 450px;
        }
        #shareLinkModal input[type="text"] {
            width: calc(100% - 110px); /* Adjust width for copy button */
            margin-right: 8px;
            display: inline-block;
            vertical-align: middle;
            background-color: var(--background-color);
            border: 1px solid var(--divider-color);
            cursor: text;
        }
        #shareLinkModal button {
            display: inline-block;
            vertical-align: middle;
            padding: 9px 16px;
            font-size: 0.9em;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 0; /* Siku-siku */
            cursor: pointer;
            transition: background-color 0.2s ease-out;
        }
        #shareLinkModal button:hover {
            background-color: var(--primary-dark-color);
        }
        #shareLinkModal .share-link-container {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }
        #shareLinkModal .share-link-container button {
            margin-left: 0;
        }
        #shareLinkModal p.small-text {
            font-size: 0.8em;
            color: var(--secondary-text-color);
            margin-top: 8px;
        }

        /* Animations */
        @keyframes fadeInScale {
            from {
                opacity: 0;
                transform: scale(0.95);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        @keyframes slideInFromTop {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal.show .modal-content {
            animation: slideInFromTop 0.3s ease-out forwards;
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

        /* General button focus effects */
        button {
            outline: none;
        }
        button:focus {
            box-shadow: 0 0 0 2px rgba(63,81,181,0.5); /* Material Design focus ring */
        }

        /* ========================================================================== */
        /* Responsive Classes for iPad, Tablet, HP (Android & iOS) */
        /* ========================================================================== */

        /* Default for Desktop */
        .sidebar-toggle-btn {
            display: none;
        }
        .sidebar.mobile-hidden {
            display: flex;
            transform: translateX(0);
        }
        .header-main .my-drive-title {
            display: block;
        }
        .header-main .search-bar-desktop {
            display: flex;
        }
        .search-bar-mobile {
            display: none;
        }
        .toolbar-filter-buttons { 
            display: none;
        }

        /* Custom Scrollbar for Webkit browsers (Chrome, Safari) */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: var(--background-color);
            border-radius: 0; /* Siku-siku */
        }

        ::-webkit-scrollbar-thumb {
            background: var(--divider-color);
            border-radius: 0; /* Siku-siku */
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--secondary-text-color);
        }

        /* Tablet Landscape */
        @media (min-width: 768px) and (max-width: 1024px) {
            body.tablet-landscape .sidebar {
                width: 200px; /* Narrower sidebar */
            }
            body.tablet-landscape .sidebar-header img {
                width: 100px;
            }
            body.tablet-landscape .main-content {
                padding: 15px;
            }
            body.tablet-landscape .header-main {
                padding: 10px 15px;
                margin: -15px -15px 15px -15px;
            }
            body.tablet-landscape .header-main h1 {
                font-size: 1.8em;
            }
            body.tablet-landscape .search-bar input {
                width: 180px;
            }
            body.tablet-landscape .toolbar-left button,
            body.tablet-landscape .toolbar-right button {
                padding: 8px 15px;
                font-size: 0.85em;
            }
            body.tablet-landscape .filter-button {
                padding: 8px 10px;
                font-size: 1em;
            }
            body.tablet-landscape .file-table th,
            body.tablet-landscape .file-table td {
                padding: 10px 20px;
                font-size: 0.85em;
            }
            body.tablet-landscape .file-grid {
                grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
                gap: 12px;
                padding: 12px;
            }
            body.tablet-landscape .grid-thumbnail {
                height: 100px;
            }
            body.tablet-landscape .modal-content {
                max-width: 500px;
                padding: 25px;
            }
            body.tablet-landscape .sidebar-menu a {
                font-size: var(--sidebar-font-size-tablet-landscape);
            }
        }

        /* Tablet Portrait */
        @media (min-width: 768px) and (max-width: 1024px) and (orientation: portrait) {
            body.tablet-portrait .sidebar {
                position: fixed;
                top: 0;
                left: 0;
                height: 100%;
                z-index: 100;
                transform: translateX(-100%);
                box-shadow: 2px 0 5px rgba(0,0,0,0.2); /* Subtle shadow for mobile sidebar */
            }
            body.tablet-portrait .sidebar.show-mobile-sidebar {
                transform: translateX(0);
            }
            body.tablet-portrait .sidebar-toggle-btn {
                display: block;
                background: none;
                border: none;
                font-size: 1.6em;
                color: var(--adminlte-header-text);
                cursor: pointer;
                margin-left: 0;
                order: 0;
            }
            body.tablet-portrait .header-main {
                justify-content: flex-start; /* Align items to start */
                padding: 10px 15px;
                margin: -15px -15px 15px -15px;
            }
            body.tablet-portrait .header-main h1 {
                font-size: 1.6em;
                flex-grow: 1;
                text-align: center;
                margin-left: -30px; /* Counteract toggle button space */
            }
            body.tablet-portrait .header-main .my-drive-title {
                display: none;
            }
            body.tablet-portrait .header-main .search-bar-desktop {
                display: none;
            }
            body.tablet-portrait .search-bar-mobile {
                display: flex;
                margin: 0 auto 15px auto;
                width: calc(100% - 30px);
            }
            body.tablet-portrait .main-content {
                padding: 15px;
            }
            body.tablet-portrait .toolbar {
                flex-wrap: wrap;
                gap: 8px;
                justify-content: center;
            }
            body.tablet-portrait .toolbar-left,
            body.tablet-portrait .toolbar-right {
                width: 100%;
                justify-content: center;
                margin-bottom: 8px;
                flex-wrap: wrap;
            }
            body.tablet-portrait .toolbar-left button,
            body.tablet-portrait .toolbar-right button {
                padding: 7px 12px;
                font-size: 0.8em;
            }
            body.tablet-portrait .toolbar-filter-buttons { 
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(70px, 1fr));
                gap: 8px;
                justify-content: center;
                margin-top: 10px;
                width: 100%;
            }
            body.tablet-portrait .toolbar-filter-buttons .filter-button {
                padding: 7px 9px;
                font-size: 1em;
            }
            body.tablet-portrait .toolbar .dropdown-container,
            body.tablet-portrait .toolbar .view-toggle { 
                display: none;
            }
            body.tablet-portrait .file-table th,
            body.tablet-portrait .file-table td {
                padding: 8px 18px;
                font-size: 0.8em;
            }
            body.tablet-portrait .file-grid {
                grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
                gap: 10px;
                padding: 10px;
            }
            body.tablet-portrait .grid-thumbnail {
                height: 90px;
            }
            body.tablet-portrait .modal-content {
                max-width: 500px;
                padding: 25px;
            }
            body.tablet-portrait .dropdown-content.file-type-filter-dropdown-content {
                max-height: 200px;
                overflow-y: auto;
            }
            body.tablet-portrait .sidebar-menu a {
                font-size: var(--sidebar-font-size-tablet-portrait);
            }
        }

        /* Mobile */
        @media (max-width: 767px) {
            body.mobile .sidebar {
                position: fixed;
                top: 0;
                left: 0;
                height: 100%;
                width: 180px; /* Even narrower sidebar */
                z-index: 100;
                transform: translateX(-100%);
                box-shadow: 2px 0 5px rgba(0,0,0,0.2);
            }
            body.mobile .sidebar.show-mobile-sidebar {
                transform: translateX(0);
            }
            body.mobile .sidebar-toggle-btn {
                display: block;
                background: none;
                border: none;
                font-size: 1.4em;
                color: var(--adminlte-header-text);
                cursor: pointer;
                margin-left: 0;
                order: 0;
            }
            body.mobile .header-main {
                justify-content: flex-start;
                padding: 10px 10px;
                margin: -15px -15px 15px -15px;
            }
            body.mobile .header-main h1 {
                font-size: 1.5em;
                flex-grow: 1;
                text-align: center;
                margin-left: -25px; /* Counteract toggle button space */
            }
            body.mobile .header-main .my-drive-title {
                display: none;
            }
            body.mobile .header-main .search-bar-desktop {
                display: none;
            }
            body.mobile .search-bar-mobile {
                display: flex;
                margin: 0 auto 10px auto;
                width: calc(100% - 20px);
            }
            body.mobile .main-content {
                padding: 10px;
                overflow-x: hidden;
            }
            body.mobile .file-list-container {
                overflow-x: hidden;
            }
            body.mobile .file-table thead {
                display: none;
            }
            body.mobile .file-table tbody tr {
                display: flex;
                flex-wrap: wrap;
                border: 1px solid #dadce0;
                margin-bottom: 8px;
                border-radius: 8px;
                background-color: var(--surface-color);
                box-shadow: none;
                position: relative;
            }
            body.mobile .file-table td {
                display: block;
                width: 100%;
                padding: 8px 16px;
                font-size: 0.875em;
                border-bottom: none;
                white-space: normal;
                text-align: left;
            }
            body.mobile .file-table td:first-child {
                position: absolute;
                top: 12px;
                left: 12px;
                width: auto;
                padding: 0;
            }
            body.mobile .file-table td:nth-child(2) {
                padding-left: 48px;
                font-weight: 500;
                font-size: 0.9em;
            }
            body.mobile .file-table td:nth-child(3),
            body.mobile .file-table td:nth-child(4),
            body.mobile .file-table td:nth-child(5) {
                display: inline-block;
                width: 50%;
                box-sizing: border-box;
                padding-top: 4px;
                padding-bottom: 4px;
                color: #5f6368;
            }
            body.mobile .file-table td:nth-child(3)::before { content: "Type: "; font-weight: normal; color: #5f6368; }
            body.mobile .file-table td:nth-child(4)::before { content: "Size: "; font-weight: normal; color: #5f6368; }
            body.mobile .file-table td:nth-child(5)::before { content: "Modified: "; font-weight: normal; color: #5f6368; }

            body.mobile .toolbar {
                flex-direction: column;
                align-items: stretch;
                gap: 6px;
                padding-bottom: 8px;
            }
            body.mobile .toolbar-left,
            body.mobile .toolbar-right {
                width: 100%;
                flex-direction: row;
                flex-wrap: wrap;
                justify-content: center;
                gap: 6px;
                margin-right: 0;
            }
            body.mobile .toolbar-left button,
            body.mobile .toolbar-right button {
                flex-grow: 1;
                min-width: unset;
                padding: 7px 9px;
                font-size: 0.8em;
            }
            body.mobile .view-toggle {
                display: flex;
                width: 100%;
            }
            body.mobile .view-toggle button {
                flex-grow: 1;
            }
            body.mobile .file-icon {
                font-size: 1em;
                margin-right: 6px;
                width: 18px;
            }
            body.mobile .file-name-cell {
                max-width: 100%;
            }
            body.mobile .grid-item {
                padding: 8px;
            }
            body.mobile .grid-thumbnail {
                height: 70px;
            }
            body.mobile .grid-thumbnail i {
                font-size: 2.8em;
            }
            body.mobile .file-grid {
                grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
                gap: 8px;
                padding: 8px;
            }
            body.mobile .file-name {
                font-size: 0.85em;
            }
            body.mobile .file-size {
                font-size: 0.7em;
            }
            body.mobile .action-buttons button {
                padding: 4px 7px;
                font-size: 0.65em;
            }
            body.mobile .modal-content {
                max-width: 450px;
                padding: 20px;
            }
            body.mobile .upload-item .file-icon {
                font-size: 1.8em;
                margin-right: 8px;
                width: 30px;
            }
            body.mobile .upload-status-icon {
                font-size: 1em;
            }
            body.mobile .upload-action-button {
                font-size: 1em;
            }
            body.mobile .share-link-container {
                flex-direction: column;
                align-items: stretch;
            }
            body.mobile #shareLinkModal input[type="text"] {
                width: 100%;
                margin-right: 0;
                margin-bottom: 8px;
            }
            body.mobile #shareLinkModal button {
                width: 100%;
            }
            body.mobile .toolbar-filter-buttons { 
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(60px, 1fr));
                gap: 6px;
                justify-content: center;
                margin-top: 8px;
                width: 100%;
            }
            body.mobile .toolbar-filter-buttons .filter-button {
                padding: 6px 8px;
                font-size: 0.9em;
            }
            body.mobile .toolbar .dropdown-container,
            body.mobile .toolbar .view-toggle { 
                display: none;
            }
            body.mobile .dropdown-content.file-type-filter-dropdown-content {
                max-height: 180px;
                overflow-y: auto;
            }
            body.mobile .sidebar-menu a {
                font-size: var(--sidebar-font-size-mobile);
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
