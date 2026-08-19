<?php
declare(strict_types=1);

$style = file_get_contents(__DIR__ . '/../public/assets/tracs.css');
if ($style === false
    || !str_contains($style, '--dashboard-widget-gap: var(--card-gap);')
    || !str_contains($style, '--dashboard-column-gap: var(--card-gap);')
    || !str_contains($style, '--dashboard-row-gap: var(--dashboard-widget-gap);')
    || !str_contains($style, '.col-left,')
    || !str_contains($style, 'gap: var(--dashboard-row-gap);')) {
    fwrite(STDERR, "Dashboard widget spacing must use one shared gap token.\n");
    exit(1);
}

echo "TRACS dashboard widget spacing checks passed.\n";
