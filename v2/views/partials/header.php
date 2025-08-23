<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SKMI Cloud Storage</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../../css/internal.css">
    <style>
        /* Metro Design (Modern UI) & Windows 7 Animations */
        :root {
            --metro-blue: #0078D7; /* Windows 10/Metro accent blue */
            --metro-dark-blue: #0056b3;
            --metro-light-gray: #E1E1E1;
            --metro-medium-gray: #C8C8C8;
            --metro-dark-gray: #666666;
            --metro-text-color: #333333;
            --metro-bg-color: #F0F0F0;
            --metro-sidebar-bg: #2D2D30; /* Darker sidebar for contrast */
            --metro-sidebar-text: #F0F0F0;
            --metro-success: #4CAF50;
            --metro-error: #E81123; /* Windows 10 error red */
            --metro-warning: #FF8C00; /* Windows 10 warning orange */

            /* --- LOKASI EDIT UKURAN FONT SIDEBAR --- */
            --sidebar-font-size-desktop: 0.9em; /* Ukuran font default untuk desktop */
            --sidebar-font-size-tablet-landscape: 1.0em; /* Ukuran font untuk tablet landscape */
            --sidebar-font-size-tablet-portrait: 0.95em; /* Ukuran font untuk tablet portrait */
            --sidebar-font-size-mobile: 0.9em; /* Ukuran font untuk mobile */
            /* --- AKHIR LOKASI EDIT UKURAN FONT SIDEBAR --- */
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            display: flex;
            height: 100vh;
            background-color: var(--metro-bg-color);
            color: var(--metro-text-color);
            overflow: hidden; /* Prevent body scroll, main-content handles it */
        }

        /* Base Sidebar (for Desktop/Tablet Landscape) */
        .sidebar {
            width: 250px; /* Wider sidebar for Metro feel */
            background-color: var(--metro-sidebar-bg);
            color: var(--metro-sidebar-text);
            display: flex;
            flex-direction: column;
            padding: 20px 0;
            transition: width 0.3s ease-in-out, transform 0.3s ease-in-out;
            flex-shrink: 0; /* Prevent shrinking */
        }

        .sidebar-header {
            padding: 0 20px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            justify-content: center; /* Center logo */
        }

        .sidebar-header img {
            width: 150px; /* Larger logo */
            height: auto;
            display: block;
        }

        .sidebar-header h2 {
            margin: 0;
            font-size: 1.8em;
            color: var(--metro-sidebar-text);
            font-weight: 300; /* Lighter font weight */
        }

        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 0;
            flex-grow: 1;
        }

        .sidebar-menu li {
            margin-bottom: 5px; /* Closer spacing */
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 15px 20px; /* More padding */
            color: var(--metro-sidebar-text);
            text-decoration: none;
            font-size: var(--sidebar-font-size-desktop); /* Menggunakan variabel untuk desktop */
            transition: background-color 0.2s ease-out, color 0.2s ease-out;
            border-left: 5px solid transparent; /* For active state */
        }

        .sidebar-menu a i {
            margin-right: 15px;
            font-size: 1.4em;
            width: 25px; /* Fixed width for icons */
            text-align: center;
        }

        .sidebar-menu a:hover {
            background-color: rgba(255,255,255,0.1); /* Subtle hover */
            color: #FFFFFF;
        }

        .sidebar-menu a.active {
            background-color: var(--metro-blue); /* Metro accent color */
            border-left: 5px solid var(--metro-blue);
            color: #FFFFFF;
            font-weight: 600;
        }

        /* Storage Info */
        .storage-info {
            padding: 20px;
            border-top: 1px solid rgba(255,255,255,0.1);
            text-align: center;
            font-size: 0.9em;
        }

        .storage-info h4 {
            margin-top: 0;
            margin-bottom: 15px;
            color: var(--metro-sidebar-text);
            font-weight: 400;
        }

        .progress-bar-container {
            width: 100%;
            background-color: rgba(255,255,255,0.2);
            border-radius: 5px;
            height: 8px;
            margin-bottom: 10px;
            overflow: hidden;
            position: relative; /* Added for text overlay */
        }

        .progress-bar {
            height: 100%;
            background-color: var(--metro-success); /* Green for progress */
            border-radius: 5px;
            transition: width 0.5s ease-in-out;
            position: relative;
            overflow: hidden;
        }

        /* Progress bar text overlay */
        .progress-bar-text {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: #fff; /* White text for contrast */
            font-size: 0.7em; /* Smaller font size */
            font-weight: bold;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.5); /* Add shadow for readability */
            white-space: nowrap; /* Prevent text from wrapping */
        }

        .storage-text {
            font-size: 0.9em;
            color: var(--metro-light-gray);
        }

        /* Main Content */
        .main-content {
            flex-grow: 1;
            padding: 30px;
            display: flex;
            flex-direction: column;
            overflow-y: auto; /* Enable scrolling for content */
            background-color: #FFFFFF; /* White background for content area */
            border-radius: 8px;
            margin: 0; /* MODIFIED: Full width */
            /* box-shadow: 0 5px 15px rgba(0,0,0,0.1); */ /* Removed shadow */
        }

        /* Header Main - Now always white */
        .header-main {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--metro-light-gray);
            background-color: #FFFFFF; /* White header */
            padding: 15px 30px; /* Add padding for header */
            margin: -30px -30px 25px -30px; /* Adjust margin to cover full width */
            border-radius: 0; /* MODIFIED: No rounded top corners for full width */
            /*box-shadow: 0 2px 5px rgba(0,0,0,0.05); /* Subtle shadow for header */
        }

        .header-main h1 {
            margin: 0;
            color: var(--metro-text-color);
            font-size: 2.5em;
            font-weight: 300;
        }

        .search-bar {
            display: flex;
            align-items: center;
            background-color: var(--metro-light-gray);
            border-radius: 5px;
            padding: 8px 15px;
            box-shadow: inset 0 1px 3px rgba(0,0,0,0.1); /* Subtle inner shadow */
            transition: background-color 0.2s ease-out, box-shadow 0.2s ease-out;
        }

        .search-bar:focus-within {
            background-color: #FFFFFF;
            box-shadow: 0 0 0 2px var(--metro-blue); /* Focus highlight */
        }

        .search-bar input {
            border: none;
            outline: none;
            padding: 5px;
            font-size: 1em;
            width: 280px;
            background: transparent;
            color: var(--metro-text-color);
        }

        .search-bar i {
            color: var(--metro-dark-gray);
            margin-right: 10px;
        }

        /* Toolbar */
        .toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--metro-light-gray);
        }

        .toolbar-left {
            display: flex;
            gap: 10px; /* Space between buttons */
        }

        .toolbar-right {
            display: flex;
            gap: 10px; /* Space between buttons */
        }

        .toolbar-left button,
        .toolbar-right button {
            background-color: var(--metro-blue);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 3px; /* Sharper corners */
            cursor: pointer;
            font-size: 1em;
            transition: background-color 0.2s ease-out, transform 0.1s ease-in-out;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            white-space: nowrap; /* Prevent text wrapping */
        }

        .toolbar-left button:hover,
        .toolbar-right button:hover {
            background-color: var(--metro-dark-blue);
            transform: translateY(-1px); /* Subtle lift */
        }

        .toolbar-left button:active,
        .toolbar-right button:active {
            transform: translateY(0); /* Press effect */
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .toolbar-left button i {
            margin-right: 8px;
        }

        /* Archive button specific style */
        #archiveSelectedBtn {
            background-color: var(--metro-warning); /* Orange for archive */
            color: #FFFFFF;
            font-weight: normal;
        }
        #archiveSelectedBtn:hover {
            background-color: #E67A00; /* Darker orange on hover */
        }

        .view-toggle button {
            background-color: var(--metro-light-gray);
            border: 1px solid var(--metro-medium-gray);
            padding: 8px 12px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 1.1em;
            color: var(--metro-text-color);
            transition: background-color 0.2s ease-out, color 0.2s ease-out;
        }

        .view-toggle button.active {
            background-color: var(--metro-blue);
            color: white;
            border-color: var(--metro-blue);
        }

        /* Breadcrumbs */
        .breadcrumbs {
            margin-bottom: 20px;
            font-size: 0.95em;
            color: var(--metro-dark-gray);
            display: flex;
            align-items: center;
            flex-wrap: wrap;
        }

        .breadcrumbs a {
            color: var(--metro-blue);
            text-decoration: none;
            margin-right: 5px;
            transition: color 0.2s ease-out;
        }

        .breadcrumbs a:hover {
            text-decoration: underline;
            color: var(--metro-dark-blue);
        }

        .breadcrumbs span {
            margin-right: 5px;
            color: var(--metro-medium-gray);
        }

        /* File and Folder Display */
        .file-list-container {
            flex-grow: 1;
            background-color: #FFFFFF;
            border-radius: 8px;
            /* box-shadow: 0 2px 10px rgba(0,0,0,0.05); */ /* Removed as main-content has shadow */
            padding: 0; /* Removed padding as table handles it */
            overflow: auto; /* Allow horizontal scrolling for wide tables */
            -webkit-overflow-scrolling: touch; /* momentum scrolling on iOS */
            touch-action: pan-y; /* allow vertical scrolling by default */
        }

        /* List View */
        .file-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 0;
        }

        .file-table th, .file-table td {
            text-align: left;
            padding: 15px 20px; /* More padding */
            border-bottom: 1px solid var(--metro-light-gray);
            font-size: 0.95em;
        }

        .file-table th {
            background-color: var(--metro-bg-color); /* Light gray header */
            color: var(--metro-dark-gray);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85em;
            position: sticky;
            top: 0;
            z-index: 1;
        }

        .file-table tbody tr:hover {
            background-color: var(--metro-light-gray); /* Subtle hover */
        }

        .file-icon {
            margin-right: 12px;
            font-size: 1.3em; /* Slightly larger icons */
            width: 28px; /* Fixed width for icons */
            text-align: center;
            flex-shrink: 0; /* Prevent icon from shrinking */
        }

        /* Icon colors for list view (Metro-inspired) */
        /* These are now handled by internal.css via file-color-xxx classes */
        /* .file-icon.pdf { color: #E81123; } */
        /* .file-icon.doc { color: #2B579A; } */
        /* .file-icon.xls { color: #107C10; } */
        /* .file-icon.ppt { color: #D24726; } */
        /* .file-icon.jpg, .file-icon.png, .file-icon.gif { color: #8E24AA; } */
        /* .file-icon.zip { color: #F7B500; } */
        /* .file-icon.txt { color: #666666; } */
        /* .file-icon.exe, .file-icon.apk { color: #0078D7; } */
        /* .file-icon.mp3, .file-icon.wav { color: #00B294; } */
        /* .file-icon.mp4, .file-icon.avi { color: #FFB900; } */
        /* .file-icon.html, .file-icon.css, .file-icon.js, .file-icon.php, .file-icon.py, .file-icon.json, .file-icon.sql, .file-icon.java, .file-icon.c { color: #505050; } */
        .file-icon.folder { color: #FFD700; } /* Gold for folders */
        /* .file-icon.default { color: #999999; } */

        .file-name-cell {
            display: flex;
            align-items: center;
            max-width: 350px; /* Increased max-width */
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .file-name-cell a {
            color: var(--metro-text-color);
            text-decoration: none;
            font-weight: 400;
            display: block;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            transition: color 0.2s ease-out;
        }

        .file-name-cell a:hover {
            color: var(--metro-blue);
        }

        .file-checkbox {
            margin-right: 10px;
            transform: scale(1.2); /* Slightly larger checkbox */
            accent-color: var(--metro-blue); /* Metro accent color for checkbox */
        }

        /* Context Menu Styles */
        .context-menu {
            position: fixed;
            z-index: 12000; /* Higher z-index for context menu */
            background: #FFFFFF;
            border: 1px solid var(--metro-medium-gray);
            box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
            border-radius: 5px;
            overflow: hidden;
            min-width: 180px;
            display: none; /* Hidden by default */
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
            color: var(--metro-text-color);
            padding: 10px 15px;
            text-decoration: none;
            display: flex;
            align-items: center;
            font-size: 0.95em;
            transition: background-color 0.2s ease-out, color 0.2s ease-out;
            cursor: pointer;
        }

        .context-menu li i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }

        .context-menu li:hover {
            background-color: var(--metro-blue);
            color: #FFFFFF;
        }

        .context-menu .separator {
            height: 1px;
            background-color: var(--metro-light-gray);
            margin: 5px 0;
        }


        /* Grid View */
        .file-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); /* Adjusted minmax for better preview */
            gap: 25px; /* Increased gap */
            padding: 20px;
            overflow: auto; /* Allow horizontal scrolling for wide tables */
            -webkit-overflow-scrolling: touch; /* momentum scrolling on iOS */
            touch-action: pan-y; /* allow vertical scrolling by default */
        }

        .grid-item {
            background-color: #FFFFFF;
            border: 1px solid var(--metro-light-gray);
            border-radius: 5px;
            padding: 15px;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: transform 0.2s ease-out, box-shadow 0.2s ease-out;
            position: relative;
            overflow: hidden; /* Ensure content stays within bounds */
            user-select: none; /* Prevent text selection on long press */
            cursor: pointer; /* Indicate clickable */
            /* tabindex="0"; For keyboard navigation */
        }

        .grid-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.15);
        }

        .grid-item .file-checkbox {
            position: absolute;
            top: 10px;
            left: 10px;
            z-index: 1;
        }

        .grid-item .file-icon {
            font-size: 3.5em; /* Larger icon for grid */
            margin-bottom: 10px;
            width: auto;
            /* color: var(--metro-dark-gray); */ /* Default icon color for grid - now handled by internal.css */
        }

        /* Thumbnail Grid Specific Styles */
        .grid-thumbnail {
            width: 100%;
            height: 140px; /* Fixed height for consistent grid */
            object-fit: contain;
            margin-bottom: 10px;
            border-radius: 3px;
            border: 1px solid var(--metro-medium-gray);
            background-color: var(--metro-bg-color);
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: hidden;
            position: relative;
            font-size: 12px;
            color: var(--metro-text-color);
            text-align: left;
            padding: 5px;
            box-sizing: border-box;
        }

        .grid-thumbnail i {
            font-size: 4.5em; /* Larger icon for thumbnail placeholder */
            color: var(--metro-medium-gray);
        }

        .grid-thumbnail img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        .grid-thumbnail video, .grid-thumbnail audio {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .grid-thumbnail pre {
            font-size: 10px;
            white-space: pre-wrap;
            word-break: break-all;
            margin: 0;
            padding: 0;
            max-height: 100%;
            overflow: hidden;
        }

        .grid-thumbnail .file-type-label {
            font-size: 0.8em;
            color: #FFFFFF;
            position: absolute;
            bottom: 5px;
            right: 5px;
            background-color: rgba(0,0,0,0.5);
            padding: 3px 6px;
            border-radius: 3px;
        }

        .file-name {
            font-weight: 500;
            color: var(--metro-text-color);
            text-decoration: none;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 100%;
            display: block;
            margin-top: 5px;
            font-size: 1.05em;
            transition: color 0.2s ease-out;
        }
        
        .file-name:hover {
            color: var(--metro-blue);
        }

        .file-size {
            font-size: 0.85em;
            color: var(--metro-dark-gray);
            margin-top: 5px;
        }

        .action-buttons {
            display: flex;
            justify-content: center;
            margin-top: 15px;
            gap: 8px;
            width: 100%;
        }

        /* MODIFIED: Smaller action buttons for grid view */
        .action-buttons button {
            background-color: var(--metro-blue);
            color: white;
            border: none;
            padding: 6px 10px; /* Reduced padding */
            border-radius: 3px;
            cursor: pointer;
            font-size: 0.8em; /* Reduced font size */
            transition: background-color 0.2s ease-out, transform 0.1s ease-in-out;
        }

        .action-buttons button:hover {
            background-color: var(--metro-dark-blue);
            transform: translateY(-1px);
        }
        .action-buttons button:active {
            transform: translateY(0);
        }
        .action-buttons button.delete-button {
            background-color: var(--metro-error);
        }
        .action-buttons button.delete-button:hover {
            background-color: #C4001A;
        }
        /* Custom style for extract button hover in grid view */
        .action-buttons button.extract-button:hover {
            background-color: #ff3399; /* Custom hover color */
            color: #FFFFFF; /* Text color on hover */
        }

        /* Item More Button (three dots) */
        .item-more {
            position: absolute;
            top: 5px;
            right: 5px;
            background: none;
            border: none;
            font-size: 1.2em;
            color: var(--metro-dark-gray);
            cursor: pointer;
            padding: 5px;
            border-radius: 50%;
            transition: background-color 0.2s ease-out, color 0.2s ease-out;
            z-index: 10; /* Ensure it's above other elements */
        }
        .item-more:hover {
            background-color: rgba(0,0,0,0.1);
            color: var(--metro-text-color);
        }
        .file-table .item-more {
            position: static; /* Reset position for table view */
            margin-left: auto; /* Push to right in table cell */
            display: flex;
            align-items: center;
            justify-content: center;
            width: 30px; /* Fixed width for alignment */
            height: 30px; /* Fixed height for alignment */
        }


        /* Modal Styles (Pop-up CRUD) */
        .modal {
            display: flex; /* Changed to flex for centering */
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.6); /* Darker overlay */
            justify-content: center;
            align-items: center;
            opacity: 0;
            visibility: hidden; /* Hidden by default */
            transition: opacity 0.3s ease-out, visibility 0.3s ease-out;
        }

        .modal.show {
            opacity: 1;
            visibility: visible;
        }

        .modal-content {
            background-color: #FFFFFF;
            padding: 30px;
            border-radius: 5px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.3);
            width: 90%;
            max-width: 550px; /* Slightly larger modals */
            position: relative;
            transform: translateY(-20px); /* Initial position for animation */
            opacity: 0;
            transition: transform 0.3s ease-out, opacity 0.3s ease-out;
        }

        .modal.show .modal-content {
            transform: translateY(0);
            opacity: 1;
        }

        .close-button {
            color: var(--metro-dark-gray);
            position: absolute;
            top: 15px;
            right: 20px;
            font-size: 30px;
            font-weight: normal;
            cursor: pointer;
            transition: color 0.2s ease-out;
        }

        .close-button:hover,
        .close-button:focus {
            color: var(--metro-error);
        }

        .modal h2 {
            margin-top: 0;
            margin-bottom: 25px;
            color: var(--metro-text-color);
            font-size: 2em;
            font-weight: 300;
            border-bottom: 1px solid var(--metro-light-gray);
            padding-bottom: 15px;
        }

        .modal label {
            display: block;
            margin-bottom: 10px;
            font-weight: 600;
            color: var(--metro-text-color);
            font-size: 1.05em;
        }

        .modal input[type="text"],
        .modal input[type="file"] {
            width: calc(100% - 20px);
            padding: 12px;
            margin-bottom: 20px;
            border: 1px solid var(--metro-medium-gray);
            border-radius: 3px;
            font-size: 1em;
            color: var(--metro-text-color);
            background-color: #F9F9F9;
            transition: border-color 0.2s ease-out, box-shadow 0.2s ease-out;
        }
        
        .modal input[type="text"]:focus,
        .modal input[type="file"]:focus {
            border-color: var(--metro-blue);
            box-shadow: 0 0 0 2px rgba(0,120,215,0.3);
            outline: none;
            background-color: #FFFFFF;
        }

        .modal input[type="file"] {
            border: 1px solid var(--metro-medium-gray); /* Keep border for file input */
            padding: 10px;
        }

        .modal button[type="submit"] {
            background-color: var(--metro-blue);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 1.1em;
            transition: background-color 0.2s ease-out, transform 0.1s ease-in-out;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .modal button[type="submit"]:hover {
            background-color: var(--metro-dark-blue);
            transform: translateY(-1px);
        }
        .modal button[type="submit"]:active {
            transform: translateY(0);
        }

        .hidden {
            display: none !important;
        }

        /* Custom Notification Styles */
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 25px;
            border-radius: 5px;
            color: white;
            font-weight: bold;
            z-index: 1001;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            opacity: 0;
            transform: translateY(-20px);
            transition: opacity 0.3s ease-out, transform 0.3s ease-out;
        }

        .notification.show {
            opacity: 1;
            transform: translateY(0);
        }

        .notification.success {
            background-color: var(--metro-success);
        }

        .notification.error {
            background-color: var(--metro-error);
        }

        .notification.info {
            background-color: var(--metro-blue);
        }

        /* Upload Preview Modal Specific Styles */
        #uploadPreviewModal .modal-content {
            max-width: 650px;
            padding: 25px;
        }

        #uploadPreviewModal .modal-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            border-bottom: 1px solid var(--metro-light-gray);
            padding-bottom: 15px;
        }

        #uploadPreviewModal .modal-header h2 {
            flex-grow: 1;
            margin: 0;
            font-size: 2.2em;
            font-weight: 300;
            border-bottom: none; /* Remove double border */
            padding-bottom: 0;
        }

        #uploadPreviewModal .modal-header .back-button {
            background: none;
            border: none;
            font-size: 1.8em;
            cursor: pointer;
            margin-right: 15px;
            color: var(--metro-dark-gray);
            transition: color 0.2s ease-out;
        }
        #uploadPreviewModal .modal-header .back-button:hover {
            color: var(--metro-blue);
        }

        #uploadPreviewList {
            max-height: 450px; /* Increased height */
            overflow-y: auto;
            margin-bottom: 20px;
            padding-right: 10px; /* For scrollbar */
        }

        .upload-item {
            display: flex;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid var(--metro-light-gray);
            transition: background-color 0.2s ease-out;
        }

        .upload-item:last-child {
            border-bottom: none;
        }
        .upload-item:hover {
            background-color: var(--metro-bg-color);
        }

        .upload-item .file-icon {
            font-size: 2.8em; /* Larger icons */
            margin-right: 20px;
            flex-shrink: 0;
            width: 45px;
            text-align: center;
            /* color: var(--metro-dark-gray); */ /* Default color - now handled by internal.css */
        }

        .upload-item-info {
            flex-grow: 1;
        }

        .upload-item-info strong {
            display: block;
            font-weight: 600;
            color: var(--metro-text-color);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 100%;
            font-size: 1.05em;
        }

        .upload-progress-container {
            width: 100%;
            background-color: var(--metro-light-gray);
            border-radius: 5px;
            height: 8px;
            margin-top: 8px;
            overflow: hidden;
            position: relative;
        }

        .upload-progress-bar {
            height: 100%;
            background-color: var(--metro-success);
            border-radius: 5px;
            width: 0%;
            transition: width 0.3s ease-out;
        }

        .upload-status-icon {
            font-size: 1.6em; /* Larger status icons */
            margin-left: 20px;
            flex-shrink: 0;
            width: 30px;
            text-align: center;
        }

        .upload-status-icon.processing { color: var(--metro-blue); }
        .upload-status-icon.success { color: var(--metro-success); }
        .upload-status-icon.error { color: var(--metro-error); }
        .upload-status-icon.cancelled { color: var(--metro-warning); }
        
        .upload-action-button {
            background: none;
            border: none;
            font-size: 1.4em; /* Larger action button */
            cursor: pointer;
            color: var(--metro-dark-gray);
            margin-left: 15px;
            transition: color 0.2s ease-out;
        }

        .upload-action-button:hover {
            color: var(--metro-error);
        }

        .upload-item.complete .upload-action-button {
            display: none;
        }

        /* Styles for the dropdown containers */
        .dropdown-container {
            position: relative;
            display: inline-block;
        }

        .dropdown-content {
            display: none;
            position: absolute;
            background-color: #FFFFFF;
            min-width: 180px; /* Wider dropdowns */
            box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
            z-index: 10;
            border-radius: 3px;
            /*top: 50%;*/
            /*left: 0;*/
            margin-top: 8px; /* Space between button and dropdown */
            animation: fadeInScale 0.2s ease-out forwards; /* Windows 7 like animation */
            transform-origin: top left;
        }

        .dropdown-content a {
            color: var(--metro-text-color);
            padding: 12px 18px;
            text-decoration: none;
            display: block;
            font-size: 0.95em;
            transition: background-color 0.2s ease-out, color 0.2s ease-out;
        }

        .dropdown-content a:hover {
            background-color: var(--metro-blue);
            color: #FFFFFF;
        }

        .dropdown-container.show .dropdown-content {
            display: block;
        }

        /* Style for filter buttons (icons only) */
        .filter-button {
            background-color: var(--metro-blue);
            color: white;
            border: none;
            padding: 10px 12px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 1.2em; /* Slightly larger icon */
            transition: background-color 0.2s ease-out, transform 0.1s ease-in-out;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .filter-button:hover {
            background-color: var(--metro-dark-blue);
            transform: translateY(-1px);
        }
        .filter-button:active {
            transform: translateY(0);
        }

        /* Share Link Modal */
        #shareLinkModal .modal-content {
            max-width: 500px;
        }
        #shareLinkModal input[type="text"] {
            width: calc(100% - 120px); /* Adjust width for copy button */
            margin-right: 10px;
            display: inline-block;
            vertical-align: middle;
            background-color: var(--metro-bg-color);
            border: 1px solid var(--metro-medium-gray);
            cursor: text;
        }
        #shareLinkModal button {
            display: inline-block;
            vertical-align: middle;
            padding: 10px 18px;
            font-size: 0.95em;
            background-color: var(--metro-blue);
            color: white;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            transition: background-color 0.2s ease-out, transform 0.1s ease-in-out;
        }
        #shareLinkModal button:hover {
            background-color: var(--metro-dark-blue);
            transform: translateY(-1px);
        }
        #shareLinkModal .share-link-container {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }
        #shareLinkModal .share-link-container button {
            margin-left: 0; /* No extra margin */
        }
        #shareLinkModal p.small-text {
            font-size: 0.85em;
            color: var(--metro-dark-gray);
            margin-top: 10px;
        }

        /* Windows 7-like Animations */
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

        /* General button hover/active effects */
        button {
            outline: none;
        }
        button:focus {
            box-shadow: 0 0 0 2px rgba(0,120,215,0.5); /* Focus ring */
        }

        /* ========================================================================== */
        /* Responsive Classes for iPad, Tablet, HP (Android & iOS) */
        /* ========================================================================== */

        /* Default for Desktop/Windows */
        .sidebar-toggle-btn {
            display: none; /* Hidden on desktop */
        }
        .sidebar.mobile-hidden {
            display: flex; /* Always visible on desktop */
            transform: translateX(0);
        }
        .header-main .my-drive-title {
            display: block; /* "My Drive" visible on desktop */
        }
        .header-main .search-bar-desktop {
            display: flex; /* Search bar in header on desktop */
        }
        .search-bar-mobile {
            display: none; /* Mobile search bar hidden on desktop */
        }
        /* MODIFIED: Hide toolbar-filter-buttons by default on desktop */
        .toolbar-filter-buttons { 
            display: none;
        }

        /* Custom Scrollbar for Webkit browsers (Chrome, Safari) */
        ::-webkit-scrollbar {
            width: 8px; /* Width of the scrollbar */
            height: 8px; /* Height of horizontal scrollbar */
        }

        ::-webkit-scrollbar-track {
            background: var(--metro-light-gray); /* Color of the track */
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb {
            background: var(--metro-medium-gray); /* Color of the scroll thumb */
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--metro-dark-gray); /* Color of the scroll thumb on hover */
        }

        /* Class for iPad & Tablet (Landscape: min-width 768px, max-width 1024px) */
        @media (min-width: 768px) and (max-width: 1024px) {
            body.tablet-landscape .sidebar {
                width: 220px; /* Slightly narrower sidebar */
            }
            body.tablet-landscape .main-content {
                margin: 0; /* MODIFIED: Full width */
                padding: 20px;
            }
            body.tablet-landscape .header-main {
                padding: 10px 20px;
                margin: 0; /* MODIFIED: Full width */
            }
            body.tablet-landscape .header-main h1 {
                font-size: 2em;
            }
            body.tablet-landscape .search-bar input {
                width: 200px;
            }
            body.tablet-landscape .file-table th,
            body.tablet-landscape .file-table td {
                padding: 12px 15px;
                font-size: 0.9em; /* Slightly smaller font for table cells */
            }
            body.tablet-landscape .grid-item {
                padding: 10px;
            }
            body.tablet-landscape .grid-thumbnail {
                height: 120px;
            }
            body.tablet-landscape .file-grid {
                grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
                gap: 20px;
            }
            body.tablet-landscape .toolbar-left button,
            body.tablet-landscape .toolbar-right button {
                padding: 8px 15px; /* Slightly smaller buttons */
                font-size: 0.9em;
            }
            body.tablet-landscape .filter-button {
                padding: 8px 10px; /* Smaller filter buttons */
                font-size: 1.1em;
            }
            /* MODIFIED: Hide toolbar-filter-buttons in header on desktop/tablet landscape */
            body.tablet-landscape .toolbar-filter-buttons { 
                display: none;
            }
            /* MODIFIED: Ukuran pop-up disamakan dengan pop-up Edit Profile */
            body.tablet-landscape .modal-content {
                max-width: 550px; /* Consistent with profile.php */
                padding: 30px; /* Consistent with profile.php */
            }
            body.tablet-landscape .modal h2 {
                font-size: 2em; /* Consistent with profile.php */
            }
            body.tablet-landscape .modal label {
                font-size: 1.05em; /* Consistent with profile.php */
            }
            body.tablet-landscape .modal input[type="text"],
            body.tablet-landscape .modal input[type="file"] {
                padding: 12px; /* Consistent with profile.php */
                font-size: 1em; /* Consistent with profile.php */
            }
            body.tablet-landscape .modal button[type="submit"] {
                padding: 12px 25px; /* Consistent with profile.php */
                font-size: 1.1em; /* Consistent with profile.php */
            }
            body.tablet-landscape .sidebar-menu a {
                font-size: var(--sidebar-font-size-tablet-landscape); /* Menggunakan variabel untuk tablet landscape */
            }
        }

        /* Class for iPad & Tablet (Portrait: min-width 768px, max-width 1024px) */
        @media (min-width: 768px) and (max-width: 1024px) and (orientation: portrait) {
            body.tablet-portrait .sidebar {
                position: fixed;
                top: 0;
                left: 0;
                height: 100%;
                z-index: 100;
                transform: translateX(-100%); /* Hidden by default */
                /*box-shadow: 2px 0 10px rgba(0,0,0,0.2);*/
            }
            body.tablet-portrait .sidebar.show-mobile-sidebar {
                transform: translateX(0); /* Show when active */
            }
            body.tablet-portrait .sidebar-toggle-btn {
                display: block; /* Show toggle button */
                background: none;
                border: none;
                font-size: 1.8em;
                color: var(--metro-text-color);
                cursor: pointer;
                margin-left: 10px; /* Space from logo */
                order: 0; /* Place on the left */
            }
            body.tablet-portrait .header-main {
                justify-content: space-between; /* Align items */
                padding: 10px 20px;
                margin: 0; /* MODIFIED: Full width */
            }
            body.tablet-portrait .header-main h1 {
                font-size: 2em;
                flex-grow: 1; /* Allow title to take space */
                text-align: center; /* Center title */
            }
            body.tablet-portrait .header-main .my-drive-title {
                display: none; /* Hide "My Drive" */
            }
            body.tablet-portrait .header-main .search-bar-desktop {
                display: none; /* Hide desktop search bar */
            }
            body.tablet-portrait .search-bar-mobile {
                display: flex; /* Show mobile search bar */
                margin: 0 auto 20px auto; /* Centered below header */
                width: calc(100% - 40px);
            }
            body.tablet-portrait .main-content {
                margin: 0; /* MODIFIED: Full width */
                padding: 20px;
            }
            body.tablet-portrait .toolbar {
                flex-wrap: wrap;
                gap: 10px;
                justify-content: center;
            }
            body.tablet-portrait .toolbar-left,
            body.tablet-portrait .toolbar-right {
                width: 100%;
                justify-content: center;
                margin-bottom: 10px;
                flex-wrap: wrap; /* Allow buttons to wrap */
            }
            body.tablet-portrait .toolbar-left button,
            body.tablet-portrait .toolbar-right button {
                padding: 8px 15px; /* Smaller buttons */
                font-size: 0.9em;
            }
            /* MODIFIED: Show toolbar-filter-buttons for tablet portrait */
            body.tablet-portrait .toolbar-filter-buttons { 
                display: grid; /* Changed to grid for better control */
                grid-template-columns: repeat(auto-fit, minmax(80px, 1fr)); /* Responsive grid columns */
                gap: 10px; /* Space between buttons */
                justify-content: center; /* Center buttons */
                margin-top: 15px; /* Space from toolbar */
                width: 100%;
            }
            /* MODIFIED: Adjust filter button size for tablet portrait */
            body.tablet-portrait .toolbar-filter-buttons .filter-button {
                padding: 8px 10px; /* Smaller filter buttons */
                font-size: 1.1em;
            }
            /* MODIFIED: Hide filter and view buttons in main toolbar for tablet portrait */
            body.tablet-portrait .toolbar .dropdown-container,
            body.tablet-portrait .toolbar .view-toggle { 
                display: none;
            }
            body.tablet-portrait .file-table th,
            body.tablet-portrait .file-table td {
                padding: 10px 12px;
                font-size: 0.85em; /* Smaller font for table cells */
            }
            body.tablet-portrait .grid-item {
                padding: 8px;
            }
            body.tablet-portrait .grid-thumbnail {
                height: 100px;
            }
            body.tablet-portrait .file-grid {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
                gap: 15px;
            }
            /* MODIFIED: Ukuran pop-up disamakan dengan pop-up Edit Profile */
            body.tablet-portrait .modal-content {
                max-width: 550px; /* Consistent with profile.php */
                padding: 30px; /* Consistent with profile.php */
            }
            body.tablet-portrait .modal h2 {
                font-size: 2em; /* Consistent with profile.php */
            }
            body.tablet-portrait .modal label {
                font-size: 1.05em; /* Consistent with profile.php */
            }
            body.tablet-portrait .modal input[type="text"],
            body.tablet-portrait .modal input[type="file"] {
                padding: 12px; /* Consistent with profile.php */
                font-size: 1em; /* Consistent with profile.php */
            }
            body.tablet-portrait .modal button[type="submit"] {
                padding: 12px 25px; /* Consistent with profile.php */
                font-size: 1.1em; /* Consistent with profile.php */
            }
            /* MODIFIED: Scrollbar for File Type Filter dropdown */
            body.tablet-portrait .dropdown-content.file-type-filter-dropdown-content {
                max-height: 200px; /* Max height for 6 items + padding */
                overflow-y: auto;
            }
            body.tablet-portrait .sidebar-menu a {
                font-size: var(--sidebar-font-size-tablet-portrait); /* Menggunakan variabel untuk tablet portrait */
            }
        }

        /* Class for Mobile (HP Android & iOS: max-width 767px) */
        @media (max-width: 767px) {
            body.mobile .sidebar {
                position: fixed;
                top: 0;
                left: 0;
                height: 100%;
                width: 200px; /* Narrower sidebar for mobile */
                z-index: 100;
                transform: translateX(-100%); /* Hidden by default */
                /*box-shadow: 2px 0 10px rgba(0,0,0,0.2);*/
            }
            body.mobile .sidebar.show-mobile-sidebar {
                transform: translateX(0); /* Show when active */
            }
            body.mobile .sidebar-toggle-btn {
                display: block; /* Show toggle button */
                background: none;
                border: none;
                font-size: 1.5em;
                color: var(--metro-text-color);
                cursor: pointer;
                margin-left: 10px; /* Space from logo */
                order: 0; /* Place on the left */
            }
            body.mobile .header-main {
                justify-content: space-between; /* Align items */
                padding: 10px 15px;
                margin: 0; /* MODIFIED: Full width */
            }
            body.mobile .header-main h1 {
                font-size: 1.8em;
                flex-grow: 1; /* Allow title to take space */
                text-align: center; /* Center title */
            }
            body.mobile .header-main .my-drive-title {
                display: none; /* Hide "My Drive" */
            }
            body.mobile .header-main .search-bar-desktop {
                display: none; /* Hide desktop search bar */
            }
            body.mobile .search-bar-mobile {
                display: flex; /* Show mobile search bar */
                margin: 0 auto 15px auto; /* Centered below header */
                width: calc(100% - 30px);
            }
            body.mobile .main-content {
                margin: 0; /* MODIFIED: Full width */
                padding: 15px;
                overflow-x: hidden; /* Remove horizontal scrollbar */
            }
            body.mobile .file-list-container {
                overflow-x: hidden; /* Remove horizontal scrollbar for table */
            }
            body.mobile .file-table {
                width: 100%; /* Ensure table fits */
                border-collapse: collapse; /* Ensure collapse for proper rendering */
            }
            body.mobile .file-table thead {
                display: none; /* Hide table header on mobile for better stacking */
            }
            body.mobile .file-table tbody tr {
                display: flex;
                flex-wrap: wrap;
                border: 1px solid var(--metro-light-gray);
                margin-bottom: 10px;
                border-radius: 5px;
                background-color: #FFFFFF;
                /*box-shadow: 0 2px 5px rgba(0,0,0,0.05);*/
                position: relative; /* For checkbox positioning */
            }
            body.mobile .file-table td {
                display: block;
                width: 100%;
                padding: 8px 15px; /* Reduced padding */
                font-size: 0.8em; /* Smaller font for table cells */
                border-bottom: none; /* Remove individual cell borders */
                white-space: normal; /* Allow text to wrap */
                text-align: left;
            }
            body.mobile .file-table td:first-child { /* Checkbox */
                position: absolute;
                top: 8px;
                left: 8px;
                width: auto;
                padding: 0;
            }
            body.mobile .file-table td:nth-child(2) { /* Name */
                padding-left: 40px; /* Make space for checkbox */
                font-weight: 600;
                font-size: 0.9em;
            }
            body.mobile .file-table td:nth-child(3), /* Type */
            body.mobile .file-table td:nth-child(4), /* Size */
            body.mobile .file-table td:nth-child(5) { /* Last Modified */
                display: inline-block;
                width: 50%; /* Two columns per row */
                box-sizing: border-box;
                padding-top: 0;
                padding-bottom: 0;
            }
            body.mobile .file-table td:nth-child(3)::before { content: "Type: "; font-weight: normal; color: var(--metro-dark-gray); }
            body.mobile .file-table td:nth-child(4)::before { content: "Size: "; font-weight: normal; color: var(--metro-dark-gray); }
            body.mobile .file-table td:nth-child(5)::before { content: "Modified: "; font-weight: normal; color: var(--metro-dark-gray); }


            body.mobile .toolbar {
                flex-direction: column; /* Stack buttons */
                align-items: stretch; /* Stretch to full width */
                gap: 8px;
                padding-bottom: 10px;
            }
            body.mobile .toolbar-left,
            body.mobile .toolbar-right {
                width: 100%;
                flex-direction: row; /* Keep buttons in a row */
                flex-wrap: wrap; /* Allow buttons to wrap */
                justify-content: center; /* Center buttons */
                gap: 8px; /* Space between buttons */
                margin-right: 0;
            }
            body.mobile .toolbar-left button,
            body.mobile .toolbar-right button {
                flex-grow: 1; /* Allow buttons to grow */
                min-width: unset; /* Remove min-width constraint */
                padding: 8px 10px; /* Smaller padding */
                font-size: 0.85em; /* Smaller font size */
            }
            body.mobile .view-toggle {
                display: flex;
                width: 100%;
            }
            body.mobile .view-toggle button {
                flex-grow: 1;
            }
            body.mobile .file-icon {
                font-size: 1.1em;
                margin-right: 8px;
                width: 20px;
            }
            body.mobile .file-name-cell {
                max-width: 100%; /* Adjust for smaller screens */
            }
            body.mobile .grid-item {
                padding: 5px;
            }
            body.mobile .grid-thumbnail {
                height: 80px;
            }
            body.mobile .grid-thumbnail i {
                font-size: 3em;
            }
            body.mobile .file-grid {
                grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
                gap: 10px;
                padding: 10px;
            }
            body.mobile .file-name {
                font-size: 0.9em;
            }
            body.mobile .file-size {
                font-size: 0.75em;
            }
            body.mobile .action-buttons button {
                padding: 5px 8px;
                font-size: 0.7em;
            }
            /* MODIFIED: Adjust modal content for mobile */
            body.mobile .modal-content {
                max-width: 550px; /* Consistent with profile.php */
                padding: 30px; /* Consistent with profile.php */
            }
            body.mobile .modal h2 {
                font-size: 2em; /* Consistent with profile.php */
            }
            body.mobile .modal label {
                font-size: 1.05em; /* Consistent with profile.php */
            }
            body.mobile .modal input[type="text"],
            body.mobile .modal input[type="file"] {
                padding: 12px; /* Consistent with profile.php */
                font-size: 1em; /* Consistent with profile.php */
            }
            body.mobile .modal button[type="submit"] {
                padding: 12px 25px; /* Consistent with profile.php */
                font-size: 1.1em; /* Consistent with profile.php */
            }
            body.mobile .upload-item .file-icon {
                font-size: 2em;
                margin-right: 10px;
                width: 35px;
            }
            body.mobile .upload-status-icon {
                font-size: 1.2em;
            }
            body.mobile .upload-action-button {
                font-size: 1.1em;
            }
            body.mobile .share-link-container {
                flex-direction: column;
                align-items: stretch;
            }
            body.mobile #shareLinkModal input[type="text"] {
                width: 100%;
                margin-right: 0;
                margin-bottom: 10px;
            }
            body.mobile #shareLinkModal button {
                width: 100%;
            }
            /* MODIFIED: Show toolbar-filter-buttons for mobile */
            body.mobile .toolbar-filter-buttons { 
                display: grid; /* Changed to grid for better control */
                grid-template-columns: repeat(auto-fit, minmax(80px, 1fr)); /* Responsive grid columns */
                gap: 8px; /* Space between buttons */
                justify-content: center; /* Center buttons */
                margin-top: 10px; /* Space from toolbar */
                width: 100%;
            }
            /* MODIFIED: Adjust filter button size for mobile */
            body.mobile .toolbar-filter-buttons .filter-button {
                padding: 6px 8px; /* Even smaller filter buttons for mobile */
                font-size: 1em;
            }
            /* MODIFIED: Hide filter and view buttons in main toolbar for mobile */
            body.mobile .toolbar .dropdown-container,
            body.mobile .toolbar .view-toggle { 
                display: none;
            }
            /* MODIFIED: Scrollbar for File Type Filter dropdown */
            body.mobile .dropdown-content.file-type-filter-dropdown-content {
                max-height: 200px; /* Max height for 6 items + padding */
                overflow-y: auto;
            }
            body.mobile .sidebar-menu a {
                font-size: var(--sidebar-font-size-mobile); /* Menggunakan variabel untuk mobile */
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

        /* MODIFIED: Remove border-bottom from dropdown content links */
        .dropdown-content a {
            border-bottom: none !important;
        }
        /* MODIFIED: Remove border-bottom from context menu list items */
        .context-menu li {
            border-bottom: none !important;
        }

        /* MODIFIED: Google Drive Style - List View */
        .file-table {
            border: 1px solid #dadce0; /* Google Drive border color */
            border-radius: 8px; /* Rounded corners for the table */
            overflow: hidden; /* Ensures rounded corners apply to content */
        }

        .file-table th {
            background-color: #f8f9fa; /* Lighter header background */
            color: #5f6368; /* Darker gray for header text */
            font-weight: 500; /* Slightly lighter font weight */
            padding: 12px 24px; /* More padding */
            border-bottom: 1px solid #dadce0; /* Consistent border */
            font-size: 0.875em; /* Slightly larger header font */
            text-transform: none; /* No uppercase */
        }

        .file-table td {
            padding: 12px 24px; /* Consistent padding */
            border-bottom: 1px solid #dadce0; /* Consistent border */
            font-size: 0.9375em; /* Slightly smaller body font */
            color: #3c4043; /* Darker text color */
        }

        .file-table tbody tr:last-child td {
            border-bottom: none; /* No border on last row */
        }

        .file-table tbody tr:hover {
            background-color: #f0f0f0; /* Lighter hover effect */
        }

        .file-name-cell {
            display: flex;
            align-items: center;
            /* max-width: 400px; /* Adjusted max-width for name */
        }

        .file-name-cell .file-icon {
            font-size: 1.2em; /* Slightly smaller icon for better alignment */
            margin-right: 16px; /* More space between icon and name */
            width: auto; /* Let icon size determine width */
        }

        .file-name-cell a {
            font-weight: 400; /* Regular font weight */
            color: #3c4043; /* Darker text color */
        }

        .file-checkbox {
            margin-right: 16px; /* More space for checkbox */
            transform: scale(1.0); /* Default scale */
            accent-color: #1a73e8; /* Google Drive blue for checkbox */
        }

        /* MODIFIED: Google Drive Style - Grid View */
        .file-grid {
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); /* Slightly smaller grid items */
            gap: 16px; /* Reduced gap */
            padding: 0; /* Remove padding, grid-item handles it */
        }

        .grid-item {
            border: 1px solid #dadce0; /* Google Drive border color */
            border-radius: 8px; /* Rounded corners */
            padding: 12px; /* Reduced padding */
            box-shadow: none; /* No box shadow by default */
            transition: all 0.2s ease-out; /* Smooth transitions */
            display: flex;
            flex-direction: column;
            align-items: flex-start; /* Align content to start */
            text-align: left; /* Align text to left */
            position: relative;
            overflow: hidden;
        }

        .grid-item:hover {
            box-shadow: 0 1px 3px rgba(60,64,67,.3), 0 4px 8px rgba(60,64,67,.15); /* Google Drive hover shadow */
            transform: translateY(0); /* No lift on hover */
            border-color: transparent; /* Border disappears on hover */
        }

        .grid-item .file-checkbox {
            position: absolute;
            top: 8px;
            left: 8px;
            z-index: 2; /* Above thumbnail */
            transform: scale(1.0);
            accent-color: #1a73e8;
        }

        .grid-item .item-more {
            position: absolute;
            top: 8px;
            right: 8px;
            z-index: 2; /* Above thumbnail */
            background-color: rgba(255,255,255,0.8); /* Slightly opaque background */
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
            height: 120px; /* Slightly smaller thumbnail */
            margin-bottom: 8px; /* Reduced margin */
            border: none; /* No border on thumbnail */
            background-color: #e8f0fe; /* Light blue background for folders/generic files */
            border-radius: 4px; /* Slightly rounded corners */
            display: flex;
            justify-content: center;
            align-items: center;
            position: relative;
            overflow: hidden;
        }

        .grid-thumbnail i {
            font-size: 3.5em; /* Adjusted icon size */
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
            object-fit: cover; /* Cover to fill space */
        }

        .grid-thumbnail pre {
            font-size: 9px; /* Smaller font for code/text preview */
            padding: 5px;
        }

        .grid-thumbnail .file-type-label {
            display: none; /* Hide file type label in grid thumbnail */
        }

        .file-name {
            font-weight: 400; /* Regular font weight */
            color: #3c4043; /* Darker text color */
            font-size: 0.9375em; /* Consistent font size */
            margin-top: 0; /* No top margin */
            padding-left: 4px; /* Small indent for name */
            padding-right: 4px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            width: calc(100% - 8px); /* Adjust width for padding */
        }

        .file-name:hover {
            color: #1a73e8; /* Google Drive blue on hover */
        }

        .file-size {
            font-size: 0.8125em; /* Smaller font size for size */
            color: #5f6368; /* Medium gray for size text */
            margin-top: 4px; /* Reduced margin */
            padding-left: 4px;
        }

        /* Ensure Metro UI elements are not overridden by Google Drive styles */
        .sidebar, .sidebar-header, .sidebar-menu, .storage-info {
            /* Keep original Metro UI styles */
        }

        .modal-content, .notification {
            /* Keep original Metro UI styles */
        }

        /* Adjustments for mobile table view to maintain Google Drive aesthetic */
        @media (max-width: 767px) {
            body.mobile .file-table tbody tr {
                border: 1px solid #dadce0; /* Consistent border */
                border-radius: 8px; /* Consistent rounded corners */
                margin-bottom: 8px; /* Reduced margin */
                box-shadow: none; /* No shadow */
            }
            body.mobile .file-table td {
                padding: 8px 16px; /* Adjusted padding */
                font-size: 0.875em; /* Slightly larger font for mobile */
            }
            body.mobile .file-table td:first-child {
                top: 12px; /* Adjust checkbox position */
                left: 12px;
            }
            body.mobile .file-table td:nth-child(2) {
                padding-left: 48px; /* Adjust space for checkbox */
                font-size: 0.9em;
            }
            body.mobile .file-table td:nth-child(3),
            body.mobile .file-table td:nth-child(4),
            body.mobile .file-table td:nth-child(5) {
                padding-top: 4px;
                padding-bottom: 4px;
            }
            body.mobile .file-table td:nth-child(3)::before,
            body.mobile .file-table td:nth-child(4)::before,
            body.mobile .file-table td:nth-child(5)::before {
                color: #5f6368; /* Consistent text color */
            }
        }

    </style>
</head>
<body>
