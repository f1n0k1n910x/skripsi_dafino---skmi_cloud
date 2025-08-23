<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>File Preview : <?php echo htmlspecialchars($fileName); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <?php if ($fileCategory === 'code'): ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/vs2015.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/languages/php.min.js"></script>
    <script>hljs.highlightAll();</script>
    <?php endif; ?>
    <style>
        :root {
            --metro-blue: #0078D7;
            --metro-dark-blue: #0056b3;
            --metro-light-gray: #E1E1E1;
            --metro-medium-gray: #C8C8C8;
            --metro-dark-gray: #666666;
            --metro-text-color: #333333;
            --metro-bg-color: #F0F0F0;
            --metro-success: #4CAF50;
            --metro-error: #E81123;
            --metro-warning: #FF8C00;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            background-color: var(--metro-bg-color);
            color: var(--metro-text-color);
            padding: 0;
            height: 100vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .header-sticky {
            position: sticky;
            top: 0;
            z-index: 1000;
            background-color: #fff;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: transform 0.3s ease-in-out;
        }

        .header-sticky h1 {
            margin: 0;
            font-size: 1.8em;
            font-weight: 300;
            color: var(--metro-text-color);
            display: flex;
            align-items: center;
        }

        .header-sticky h1 i {
            margin-right: 15px;
            color: var(--metro-blue);
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
            border: 2px solid var(--metro-light-gray);
            transition: border-color 0.3s ease-in-out;
        }

        .profile-container .profile-image:hover {
            border-color: var(--metro-blue);
        }

        .profile-container .username {
            font-weight: 600;
            color: var(--metro-text-color);
            font-size: 1em;
            text-decoration: none;
            transition: color 0.3s ease-in-out;
        }

        .profile-container .username:hover {
            color: var(--metro-blue);
        }

        /* Base Main Container Styles (Desktop) */
        .main-container {
            flex-grow: 1;
            overflow: hidden;
            padding: 20px;
            display: flex;
            background-color: var(--metro-bg-color); /* Ensure background is consistent */
        }

        .preview-pane {
            flex: 3;
            background-color: #fff;
            margin-right: 20px;
            border-radius: 8px;
            padding: 20px;
            overflow-y: auto;
            position: relative;
            animation: fadeIn 0.5s ease-out;
        }

        .file-info-pane {
            flex: 1;
            background-color: #fff;
            border-radius: 8px;
            padding: 20px;
            animation: slideInRight 0.5s ease-out;
        }

        .file-info-pane h3 {
            margin-top: 0;
            font-weight: 600;
            font-size: 1.2em;
            color: var(--metro-text-color);
            border-bottom: 2px solid var(--metro-light-gray);
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
            color: var(--metro-dark-gray);
            margin-bottom: 5px;
        }

        .file-info-item span {
            display: block;
            font-size: 1em;
            color: var(--metro-text-color);
            word-wrap: break-word;
        }

        .back-button {
            display: inline-flex;
            align-items: center;
            background: transparent;
            border: none;
            cursor: pointer;
            color: var(--metro-text-color);
            text-decoration: none;
            font-size: 1.1em;
            font-weight: 400;
            margin-bottom: 20px;
            transition: color 0.2s ease-in-out, transform 0.2s ease-in-out;
        }

        .back-button:hover {
            color: var(--metro-blue);
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
            background-color: var(--metro-bg-color);
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
            background-color: var(--metro-light-gray);
            color: var(--metro-dark-gray);
            padding: 30px;
            border-radius: 5px;
            text-align: center;
            animation: fadeIn 0.5s ease-out;
        }

        .general-file-info .icon {
            font-size: 48px;
            color: var(--metro-blue);
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
            background-color: var(--metro-blue);
            color: #FFFFFF;
            text-decoration: none;
            border-radius: 5px;
            font-weight: 600;
            transition: background-color 0.2s ease-out, transform 0.2s ease-out;
            margin-top: 20px;
        }

        .download-button:hover {
            background-color: var(--metro-dark-blue);
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

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideInRight {
            from { transform: translateX(20px); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        .breadcrumbs {
            margin-bottom: 20px;
            font-size: 0.9em;
        }
        .breadcrumbs a {
            color: var(--metro-dark-gray);
            text-decoration: none;
            transition: color 0.2s ease-in-out;
        }
        .breadcrumbs a:hover {
            color: var(--metro-blue);
            text-decoration: underline;
        }
        .breadcrumbs span {
            color: var(--metro-dark-gray);
            margin: 0 5px;
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
            background-color: var(--metro-blue);
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
            background-color: var(--metro-dark-blue);
            transform: scale(1.05);
        }

        /* Styles from your provided code for text viewer */
        .viewer-container {
            padding: 1rem;
            background: #fff;
            border: 1px solid #ccc;
            overflow: auto;
            max-height: 70vh; /* Adjusted to fit preview-content height */
            width: 100%; /* Ensure it takes full width */
            box-sizing: border-box; /* Include padding and border in width */
        }
        .viewer-container pre {
            white-space: pre-wrap;
            word-wrap: break-word;
            background: #eef;
            padding: 1rem;
            text-align: left; /* Align text to left */
        }

        /* NEW: Styles for Archive Viewer */
        .archive-viewer {
            width: 100%;
            max-height: 70vh;
            overflow-y: auto;
            background-color: #f9f9f9;
            border: 1px solid var(--metro-light-gray);
            border-radius: 5px;
            padding: 15px;
            text-align: left;
        }

        .archive-viewer h3 {
            margin-top: 0;
            margin-bottom: 15px;
            color: var(--metro-text-color);
            font-size: 1.3em;
            border-bottom: 1px solid var(--metro-medium-gray);
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
            border: 1px solid var(--metro-light-gray);
            padding: 8px 12px;
            text-align: left;
        }

        .archive-table th {
            background-color: var(--metro-bg-color);
            color: var(--metro-dark-gray);
            font-weight: 600;
        }

        .archive-table tbody tr:nth-child(even) {
            background-color: #f2f2f2;
        }

        .archive-table tbody tr:hover {
            background-color: var(--metro-light-gray);
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
                border: 1px solid var(--metro-light-gray);
                border-radius: 5px;
                overflow: hidden; /* Ensure border-radius applies */
            }

            .archive-table td {
                text-align: right; /* Align content to the right */
                padding-left: 50%; /* Make space for the data-label */
                position: relative;
                border: none; /* Remove individual cell borders */
                border-bottom: 1px solid var(--metro-light-gray); /* Add bottom border for separation */
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
                color: var(--metro-dark-gray);
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
            body.tablet-landscape .header-sticky {
                padding: 10px 20px;
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
            body.tablet-portrait .header-sticky {
                padding: 10px 20px;
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
            body.mobile .header-sticky {
                padding: 10px 15px;
            }
            body.mobile .header-sticky h1 {
                margin-right: 5px; /* Reduce margin for icon */
                font-size: 1.3em; /* Smaller font size for header */
            }
            body.mobile .header-sticky h1 i {
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
