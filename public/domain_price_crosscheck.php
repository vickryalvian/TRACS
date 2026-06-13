<?php
/**
 * Legacy route shim for Domain Price Crosscheck.
 *
 * The canonical page URL is now domain-price-crosscheck.php. A 308 keeps old
 * bookmarks and in-flight form posts pointed at this filename from breaking.
 */
$query = $_SERVER['QUERY_STRING'] ?? '';
$target = 'domain-price-crosscheck.php' . ($query !== '' ? '?' . $query : '');
header('Location: ' . $target, true, 308);
exit;
