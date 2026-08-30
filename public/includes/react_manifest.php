<?php
declare(strict_types=1);

require_once __DIR__ . '/../../core/security/direct_access.php';
tracs_deny_direct_script_access(__FILE__);

function tracs_react_manifest_assets(
    string $entryName,
    ?string $manifestPath = null,
    string $publicPrefix = 'assets/react-dist/'
): array {
    $entries = [
        'shiftAssignment' => 'src/modules/shift-assignment/main.jsx',
    ];
    $entryKey = $entries[$entryName] ?? '';
    $manifestPath ??= __DIR__ . '/../assets/react-dist/.vite/manifest.json';

    $empty = [
        'ready' => false,
        'script' => '',
        'styles' => [],
        'modulepreloads' => [],
    ];

    if ($entryKey === '' || !is_file($manifestPath) || !is_readable($manifestPath)) {
        return $empty;
    }

    $manifest = json_decode((string)file_get_contents($manifestPath), true);
    if (!is_array($manifest) || !isset($manifest[$entryKey]) || !is_array($manifest[$entryKey])) {
        return $empty;
    }

    $entry = $manifest[$entryKey];
    $script = tracs_react_asset_path($entry['file'] ?? null, $publicPrefix);
    if ($script === '') {
        return $empty;
    }

    $styles = [];
    $modulepreloads = [];
    $visited = [];
    tracs_react_collect_manifest_assets(
        $manifest,
        $entryKey,
        $publicPrefix,
        $styles,
        $modulepreloads,
        $visited
    );

    return [
        'ready' => true,
        'script' => $script,
        'styles' => array_values(array_unique($styles)),
        'modulepreloads' => array_values(array_unique(array_filter(
            $modulepreloads,
            static fn(string $path): bool => $path !== $script
        ))),
    ];
}

function tracs_react_collect_manifest_assets(
    array $manifest,
    string $key,
    string $publicPrefix,
    array &$styles,
    array &$modulepreloads,
    array &$visited
): void {
    if (isset($visited[$key]) || !isset($manifest[$key]) || !is_array($manifest[$key])) {
        return;
    }
    $visited[$key] = true;
    $entry = $manifest[$key];

    foreach (($entry['css'] ?? []) as $cssFile) {
        $path = tracs_react_asset_path($cssFile, $publicPrefix);
        if ($path !== '') {
            $styles[] = $path;
        }
    }

    foreach (($entry['imports'] ?? []) as $importKey) {
        if (!is_string($importKey) || !isset($manifest[$importKey])) {
            continue;
        }
        $importFile = tracs_react_asset_path($manifest[$importKey]['file'] ?? null, $publicPrefix);
        if ($importFile !== '') {
            $modulepreloads[] = $importFile;
        }
        tracs_react_collect_manifest_assets(
            $manifest,
            $importKey,
            $publicPrefix,
            $styles,
            $modulepreloads,
            $visited
        );
    }
}

function tracs_react_asset_path(mixed $file, string $publicPrefix): string {
    $file = ltrim(trim((string)$file), '/');
    if ($file === '' || str_contains($file, '..') || !preg_match('/^[a-zA-Z0-9._\/-]+$/', $file)) {
        return '';
    }
    return rtrim($publicPrefix, '/') . '/' . $file;
}
