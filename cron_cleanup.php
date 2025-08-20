    <?php
    include 'config.php'; // Pastikan path ke config.php benar
    include 'functions.php'; // Pastikan path ke functions.php benar

    // Panggil fungsi pembersihan
    cleanRecycleBinAutomatically($conn);

    $conn->close();
    echo "Recycle Bin cleanup script executed.";
    ?>
    