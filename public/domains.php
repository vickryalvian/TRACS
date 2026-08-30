<?php
/**
 * Legacy route shim for Domain Transfer Log.
 *
 * The canonical page URL is now domain-transfer.php. A 308 keeps old
 * bookmarks and in-flight form posts pointed at this filename from breaking.
 */
$query = $_SERVER['QUERY_STRING'] ?? '';
$target = 'domain-transfer.php' . ($query !== '' ? '?' . $query : '');
header('Location: ' . $target, true, 308);
exit;
