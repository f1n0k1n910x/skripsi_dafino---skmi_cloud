<nav>
    <a href="<?php echo $baseV2Url; ?>">Root</a>
    <?php foreach ($breadcrumbs as $crumb): ?>
        &raquo; <a href="?folder=<?= $crumb['id']; ?>">
            <?= htmlspecialchars($crumb['folder_name']); ?>
        </a>
    <?php endforeach; ?>
</nav>
