<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>File Preview : <?php echo htmlspecialchars($fileName); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <?php if ($fileCategory === 'code'): ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/vs2015.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/languages/php.min.js"></script>
    <script>hljs.highlightAll();</script>
    <?php endif; ?>
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
            flex-direction: column; /* Ensure body is column for header + main-container */
        }

        /* Header Main (Full-width, white, no background residue) */
        .header-main {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px; /* Padding for header */
            border-bottom: 1px solid var(--divider-color);
            background-color: var(--adminlte-header-bg); /* White header */
            box-shadow: none; /* No box-shadow */
            flex-shrink: 0; /* Prevent header from shrinking */
        }

        .header-main h1 {
            margin: 0;
            color: var(--adminlte-header-text);
            font-size: 2em; /* Slightly smaller title */
            font-weight: 400; /* Lighter font weight */
            display: flex;
            align-items: center;
        }

        .header-main h1 i {
            margin-right: 15px;
            color: var(--primary-color); /* Use primary color for icon */
        }

        .profile-container {
            display: flex;
            align-items: center;
        }

        .profile-container .profile-image {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            margin-left: 15px;
            border: 2px solid var(--divider-color);
            transition: border-color 0.3s ease-in-out;
        }

        .profile-container .profile-image:hover {
            border-color: var(--primary-color);
        }

        .profile-container .username {
            font-weight: 500;
            color: var(--text-color);
            font-size: 1em;
            text-decoration: none;
            transition: color 0.3s ease-in-out;
        }

        .profile-container .username:hover {
            color: var(--primary-color);
        }

        /* Main Content Container */
        .main-container {
            flex-grow: 1;
            overflow: hidden;
            padding: 20px;
            display: flex;
            background-color: var(--background-color);
        }

        .preview-pane {
            flex: 3;
            background-color: var(--surface-color);
            margin-right: 20px;
            border-radius: 0; /* Siku-siku */
            padding: 20px;
            overflow-y: auto;
            position: relative;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1); /* Subtle shadow */
        }

        .file-info-pane {
            flex: 1;
            background-color: var(--surface-color);
            border-radius: 0; /* Siku-siku */
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1); /* Subtle shadow */
        }

        .file-info-pane h3 {
            margin-top: 0;
            font-weight: 500;
            font-size: 1.2em;
            color: var(--text-color);
            border-bottom: 1px solid var(--divider-color);
            padding-bottom: 10px;
            margin-bottom: 15px;
        }

        .file-info-item {
            margin-bottom: 15px;
            line-height: 1.5;
        }

        .file-info-item strong {
            display: block;
            font-size: 0.9em;
            color: var(--secondary-text-color);
            margin-bottom: 5px;
        }

        .file-info-item span {
            display: block;
            font-size: 1em;
            color: var(--text-color);
            word-wrap: break-word;
        }

        .back-button {
            display: inline-flex;
            align-items: center;
            background: transparent;
            border: none;
            cursor: pointer;
            color: var(--secondary-text-color);
            text-decoration: none;
            font-size: 1.1em;
            font-weight: 400;
            margin-bottom: 20px;
            transition: color 0.2s ease-in-out, transform 0.2s ease-in-out;
        }

        .back-button:hover {
            color: var(--primary-color);
            transform: translateX(-5px);
        }

        .back-button i {
            margin-right: 8px;
            font-size: 1.2em;
        }

        .preview-content {
            text-align: center;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 20px;
            min-height: 500px;
            position: relative; /* Added for zoom controls positioning */
        }

        .preview-content img {
            max-width: 100%;
            max-height: 70vh;
            object-fit: contain;
            border-radius: 5px;
            transform-origin: center center; /* Added for zoom */
            transition: transform 0.1s ease-out; /* Added for smooth zoom */
        }

        .preview-content video, .preview-content audio {
            width: 100%;
            max-width: 800px;
            border-radius: 5px;
            transform-origin: center center; /* Added for zoom */
            transition: transform 0.1s ease-out; /* Added for smooth zoom */
        }

        .preview-content pre {
            background-color: var(--background-color);
            padding: 0px;
            border-radius: 5px;
            text-align: left;
            white-space: pre-wrap;
            word-break: break-all;
            width: 100%;
            box-sizing: border-box;
            max-height: 70vh;
            overflow-y: auto;
            transform-origin: top left; /* Added for zoom */
            transition: transform 0.1s ease-out; /* Added for smooth zoom */
        }

        .preview-content pre code {
            font-family: 'Consolas', 'Courier New', Courier, monospace;
            font-size: 0.9em;
        }

        .general-file-info {
            background-color: var(--background-color);
            color: var(--secondary-text-color);
            padding: 30px;
            border-radius: 5px;
            text-align: center;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .general-file-info .icon {
            font-size: 48px;
            color: var(--primary-color);
            margin-bottom: 20px;
        }

        .general-file-info p {
            font-size: 1.1em;
            font-weight: 400;
        }

        .download-button {
            display: inline-flex;
            align-items: center;
            padding: 12px 24px;
            background-color: var(--primary-color);
            color: #FFFFFF;
            text-decoration: none;
            border-radius: 0; /* Siku-siku */
            font-weight: 500;
            transition: background-color 0.2s ease-out, transform 0.2s ease-out;
            margin-top: 20px;
        }

        .download-button:hover {
            background-color: var(--primary-dark-color);
            transform: translateY(-2px);
        }

        .download-button i {
            margin-right: 10px;
        }

        .pdf-viewer {
            width: 100%;
            height: 70vh;
            border: none;
            transform-origin: center center; /* Added for zoom */
            transition: transform 0.1s ease-out; /* Added for smooth zoom */
        }

        /* Breadcrumbs (Material Design style) */
        .breadcrumbs {
            margin-bottom: 20px;
            font-size: 0.9em;
            color: var(--secondary-text-color);
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            padding: 0;
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

        /* Zoom Controls */
        .zoom-controls {
            position: absolute;
            top: 20px;
            right: 20px;
            display: flex;
            gap: 10px;
            z-index: 10;
        }

        .zoom-button {
            background-color: var(--primary-color);
            color: #fff;
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            font-size: 1.2em;
            cursor: pointer;
            display: flex;
            justify-content: center;
            align-items: center;
            transition: background-color 0.2s ease-out, transform 0.2s ease-out;
        }

        .zoom-button:hover {
            background-color: var(--primary-dark-color);
            transform: scale(1.05);
        }

        /* Styles from your provided code for text viewer */
        .viewer-container {
            padding: 1rem;
            background: var(--background-color);
            border: 1px solid var(--divider-color);
            overflow: auto;
            max-height: 70vh; /* Adjusted to fit preview-content height */
            width: 100%; /* Ensure it takes full width */
            box-sizing: border-box; /* Include padding and border in width */
        }
        .viewer-container pre {
            white-space: pre-wrap;
            word-wrap: break-word;
            background: var(--surface-color);
            padding: 1rem;
            text-align: left; /* Align text to left */
        }

        /* NEW: Styles for Archive Viewer */
        .archive-viewer {
            width: 100%;
            max-height: 70vh;
            overflow-y: auto;
            background-color: var(--background-color);
            border: 1px solid var(--divider-color);
            border-radius: 0; /* Siku-siku */
            padding: 15px;
            text-align: left;
        }

        .archive-viewer h3 {
            margin-top: 0;
            margin-bottom: 15px;
            color: var(--text-color);
            font-size: 1.3em;
            border-bottom: 1px solid var(--divider-color);
            padding-bottom: 10px;
        }

        .archive-table-container { /* Added for responsive table */
            overflow-x: auto; /* Enable horizontal scrolling for the table */
            -webkit-overflow-scrolling: touch; /* Smooth scrolling on iOS */
        }

        .archive-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9em;
            min-width: 400px; /* Ensure table doesn't get too narrow on small screens */
        }

        .archive-table th, .archive-table td {
            border: 1px solid var(--divider-color);
            padding: 8px 12px;
            text-align: left;
        }

        .archive-table th {
            background-color: var(--surface-color);
            color: var(--secondary-text-color);
            font-weight: 600;
        }

        .archive-table tbody tr:nth-child(even) {
            background-color: var(--background-color);
        }

        .archive-table tbody tr:hover {
            background-color: var(--divider-color);
        }

        /* Responsive table for small screens */
        @media (max-width: 767px) {
            .archive-table thead {
                display: none; /* Hide table headers on small screens */
            }

            .archive-table, .archive-table tbody, .archive-table tr, .archive-table td {
                display: block; /* Make table elements behave like block elements */
                width: 100%; /* Full width */
            }

            .archive-table tr {
                margin-bottom: 15px; /* Space between rows */
                border: 1px solid var(--divider-color);
                border-radius: 0; /* Siku-siku */
                overflow: hidden; /* Ensure border-radius applies */
            }

            .archive-table td {
                text-align: right; /* Align content to the right */
                padding-left: 50%; /* Make space for the data-label */
                position: relative;
                border: none; /* Remove individual cell borders */
                border-bottom: 1px solid var(--divider-color); /* Add bottom border for separation */
            }

            .archive-table td:last-child {
                border-bottom: none; /* No bottom border for the last cell in a row */
            }

            .archive-table td::before {
                content: attr(data-label); /* Display the data-label as a pseudo-element */
                position: absolute;
                left: 10px;
                width: calc(50% - 20px); /* Adjust width for label */
                padding-right: 10px;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
                font-weight: 600;
                color: var(--secondary-text-color);
                text-align: left; /* Align label to the left */
            }
        }


        /* ========================================================================== */
        /* Responsive Classes for iPad, Tablet, HP (Android & iOS) */
        /* ========================================================================== */

        /* Default for Desktop */
        body.desktop .main-container {
            padding: 20px;
            flex-direction: row;
        }
        body.desktop .preview-pane {
            margin-right: 20px;
        }
        body.desktop .file-info-pane {
            display: block; /* Show info pane */
        }

        /* Tablet Landscape (min-width 768px, max-width 1024px, landscape) */
        @media (min-width: 768px) and (max-width: 1024px) and (orientation: landscape) {
            body.tablet-landscape .main-container {
                padding: 15px;
                flex-direction: row;
            }
            body.tablet-landscape .preview-pane {
                margin-right: 15px;
                padding: 15px;
            }
            body.tablet-landscape .file-info-pane {
                padding: 15px;
                display: block; /* Show info pane */
            }
            body.tablet-landscape .header-main {
                padding: 10px 20px;
            }
            body.tablet-landscape .header-main h1 {
                font-size: 1.8em;
            }
            body.tablet-landscape .profile-container .username {
                font-size: 0.9em;
            }
            body.tablet-landscape .profile-container .profile-image {
                width: 35px;
                height: 35px;
            }
        }

        /* Tablet Portrait (min-width 768px, max-width 1024px, portrait) */
        @media (min-width: 768px) and (max-width: 1024px) and (orientation: portrait) {
            body.tablet-portrait .main-container {
                padding: 15px;
                flex-direction: column; /* Stack panes vertically */
            }
            body.tablet-portrait .preview-pane {
                margin-right: 0;
                margin-bottom: 15px; /* Space between stacked panes */
                padding: 15px;
                flex: none; /* Remove flex grow */
                width: auto; /* Auto width */
            }
            body.tablet-portrait .file-info-pane {
                padding: 15px;
                flex: none; /* Remove flex grow */
                width: auto; /* Auto width */
                display: block; /* Show info pane */
            }
            body.tablet-portrait .header-main {
                padding: 10px 20px;
            }
            body.tablet-portrait .header-main h1 {
                font-size: 1.8em;
            }
            body.tablet-portrait .profile-container .username {
                font-size: 0.9em;
            }
            body.tablet-portrait .profile-container .profile-image {
                width: 35px;
                height: 35px;
            }
        }

        /* Mobile (max-width 767px) */
        @media (max-width: 767px) {
            body.mobile .main-container {
                padding: 10px;
                flex-direction: column; /* Stack panes vertically */
            }
            body.mobile .preview-pane {
                margin-right: 0;
                margin-bottom: 10px; /* Space between stacked panes */
                padding: 10px;
                flex: none; /* Remove flex grow */
                width: auto; /* Auto width */
            }
            body.mobile .file-info-pane {
                padding: 10px;
                flex: none; /* Remove flex grow */
                width: auto; /* Auto width */
                display: block; /* Show info pane */
            }
            body.mobile .header-main {
                padding: 10px 15px;
            }
            body.mobile .header-main h1 {
                margin-right: 5px; /* Reduce margin for icon */
                font-size: 1.3em; /* Smaller font size for header */
            }
            body.mobile .header-main h1 i {
                margin-right: 8px;
            }
            body.mobile .profile-container .username {
                font-size: 0.8em; /* Smaller username font */
            }
            body.mobile .profile-container .profile-image {
                width: 30px; /* Smaller profile image */
                height: 30px;
            }
            body.mobile .breadcrumbs {
                font-size: 0.75em; /* Smaller breadcrumbs font */
            }
            body.mobile .back-button {
                font-size: 0.9em; /* Smaller back button font */
                margin-bottom: 15px;
            }
            body.mobile .preview-content {
                padding: 10px;
                min-height: 250px; /* Smaller min-height for mobile */
            }
            body.mobile .zoom-controls {
                top: 10px;
                right: 10px;
                gap: 5px;
            }
            body.mobile .zoom-button {
                width: 30px;
                height: 30px;
                font-size: 0.9em;
            }
            body.mobile .file-info-pane h3 {
                font-size: 1em; /* Smaller info pane header */
            }
            body.mobile .file-info-item strong {
                font-size: 0.8em;
            }
            body.mobile .file-info-item span {
                font-size: 0.9em;
            }
            body.mobile .download-button {
                padding: 8px 16px;
                font-size: 0.85em;
            }
            body.mobile .general-file-info {
                padding: 15px;
            }
            body.mobile .general-file-info .icon {
                font-size: 36px;
            }
            body.mobile .general-file-info p {
                font-size: 0.9em;
            }
            body.mobile .pdf-viewer {
                height: 40vh; /* Adjust PDF viewer height */
            }
            body.mobile .viewer-container {
                max-height: 40vh;
            }
            body.mobile .archive-viewer {
                max-height: 40vh;
                padding: 10px;
            }
            body.mobile .archive-viewer h3 {
                font-size: 1em;
                margin-bottom: 10px;
            }
            body.mobile .archive-table th, body.mobile .archive-table td {
                padding: 6px 8px;
                font-size: 0.8em;
            }
        }

        /* Specific class for iPad (regardless of orientation, if needed for specific overrides) */
        /* This can be used if iPad needs different behavior than generic tablet or mobile */
        @media only screen and (min-device-width: 768px) and (max-device-width: 1024px) {
            body.device-ipad .main-container {
                /* iPad specific adjustments if needed */
            }
        }

    </style>
</head>
<body>
