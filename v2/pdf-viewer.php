<?php
if (isset($_GET['url']) && !empty($_GET['url'])) {
    $pdfUrl = $_GET['url'];
    
    // Parse the URL to get the path
    $parsedUrl = parse_url($pdfUrl);
    $fullPath = $parsedUrl['path']; // This gives: /skripsi_dafino---skmi_cloud/uploads/aaa%201.pdf
    
    // Decode URL encoding
    $decodedPath = urldecode($fullPath);
    
    // Remove leading slash and convert to server path
    $relativePath = ltrim($decodedPath, '/');
    
    // Build absolute server path
    $absolutePath = $_SERVER['DOCUMENT_ROOT'] . '/' . $relativePath;
    
    // Security check - ensure the file is within the uploads directory
    $uploadsDir = realpath($_SERVER['DOCUMENT_ROOT'] . '/skripsi_dafino---skmi_cloud/uploads/');
    $fileRealPath = realpath($absolutePath);
    
    if ($fileRealPath === false || strpos($fileRealPath, $uploadsDir) !== 0) {
        die('Access denied: File not in uploads directory');
    }
    
    if (file_exists($fileRealPath) && is_file($fileRealPath)) {
        $mimeType = mime_content_type($fileRealPath);
        if ($mimeType === 'application/pdf') {
            header('Content-type: application/pdf');
            header('Content-Disposition: inline; filename="' . basename($fileRealPath) . '"');
            header('Content-Length: ' . filesize($fileRealPath));
            readfile($fileRealPath);
            exit;
        } else {
            die('File is not a PDF');
        }
    } else {
        die('PDF file not found: ' . $fileRealPath);
    }
} else {
    die('No PDF URL provided');
}
?>