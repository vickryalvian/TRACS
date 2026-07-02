<?php require '_bootstrap.php';
require_once __DIR__ . '/../../modules/alert-ticker/controller.php';

// Powers the live ticker bar's polling refresh (header.php). Returns the same
// merged feed used for the initial server-rendered ticker so all pages stay
// in sync without a full reload.
$TC = new AlertTickerController($conn, $uid);
ok($TC->formatAlertsForTicker());
