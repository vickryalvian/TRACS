<?php
declare(strict_types=1);

require_once __DIR__ . '/../public/includes/react_manifest.php';

function preview_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$tempDir = sys_get_temp_dir() . '/tracs-react-preview-' . bin2hex(random_bytes(6));
mkdir($tempDir, 0700, true);
$manifestPath = $tempDir . '/manifest.json';

file_put_contents($manifestPath, json_encode([
    'src/modules/shift-assignment/main.jsx' => [
        'file' => 'assets/shift.js',
        'css' => ['assets/shift.css'],
        'imports' => ['_shared.js'],
    ],
    '_shared.js' => [
        'file' => 'assets/shared.js',
        'css' => ['assets/shared.css'],
    ],
], JSON_THROW_ON_ERROR));

$assets = tracs_react_manifest_assets(
    'shiftAssignment',
    $manifestPath,
    'assets/react-dist/'
);

preview_assert($assets['ready'] === true, 'Preview manifest entry was not resolved.');
preview_assert(
    $assets['script'] === 'assets/react-dist/assets/shift.js',
    'Preview script path changed.'
);
preview_assert(
    $assets['styles'] === [
        'assets/react-dist/assets/shift.css',
        'assets/react-dist/assets/shared.css',
    ],
    'Preview CSS dependency collection changed.'
);
preview_assert(
    $assets['modulepreloads'] === ['assets/react-dist/assets/shared.js'],
    'Preview module preload collection changed.'
);

$missing = tracs_react_manifest_assets(
    'shiftAssignment',
    $tempDir . '/missing.json',
    'assets/react-dist/'
);
preview_assert($missing['ready'] === false, 'Missing preview assets must fail safely.');
preview_assert($missing['script'] === '', 'Missing preview assets exposed a script path.');

$unknown = tracs_react_manifest_assets(
    'unknownEntry',
    $manifestPath,
    'assets/react-dist/'
);
preview_assert($unknown['ready'] === false, 'Unknown manifest entries must be rejected.');

$page = file_get_contents(__DIR__ . '/../public/shift-assignment-react-preview.php');
preview_assert($page !== false, 'Preview page is unreadable.');
preview_assert(
    str_contains($page, "require_once __DIR__ . '/auth/auth_check.php'"),
    'Preview page no longer requires the normal authenticated shell.'
);
preview_assert(
    str_contains($page, "tracs_require_page_permission(\$conn, 'shifts.view')"),
    'Preview page no longer requires shifts.view.'
);
preview_assert(
    str_contains($page, 'id="tracs-shift-assignment-root"'),
    'Preview React root changed.'
);
preview_assert(
    str_contains($page, "tracs_react_manifest_assets('shiftAssignment')"),
    'Preview page no longer loads the allowlisted Shift Assignment entry.'
);
preview_assert(
    str_contains($page, "header_remove('X-Powered-By')"),
    'Preview page no longer removes X-Powered-By.'
);

foreach (['POST', 'PATCH', 'DELETE'] as $method) {
    preview_assert(
        !str_contains($page, $method),
        "Preview page unexpectedly contains {$method} behavior."
    );
}

unlink($manifestPath);
rmdir($tempDir);

echo "TRACS Shift Assignment React preview checks passed.\n";
