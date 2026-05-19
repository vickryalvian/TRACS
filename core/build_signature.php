<?php
/**
 * TRACS build signature metadata.
 *
 * TRACS System by Vickry.
 */

const TRACS_BUILD_OWNER = 'Vickry';
const TRACS_BUILD_VERSION = '1.0.0-first-deployment';
const TRACS_BUILD_DEPLOYED_AT = '2026-05-19T11:28:45+07:00';
const TRACS_BUILD_CODENAME = 'Dobby meowmeow build';

function tracs_build_environment(): string {
    $candidates = [
        $_ENV['TRACS_ENV'] ?? null,
        $_ENV['APP_ENV'] ?? null,
        getenv('TRACS_ENV'),
        getenv('APP_ENV'),
        $_SERVER['TRACS_ENV'] ?? null,
        $_SERVER['APP_ENV'] ?? null,
    ];

    foreach ($candidates as $candidate) {
        if (!is_string($candidate)) {
            continue;
        }
        $value = trim($candidate);
        if ($value === '') {
            continue;
        }
        return preg_replace('/[^A-Za-z0-9_.-]/', '', $value) ?: 'unspecified';
    }

    return 'unspecified';
}

function tracs_build_deployed_label(): string {
    try {
        return (new DateTimeImmutable(TRACS_BUILD_DEPLOYED_AT))->format('d M Y H:i T');
    } catch (Throwable $e) {
        return TRACS_BUILD_DEPLOYED_AT;
    }
}

function tracs_build_allows_internal_codename(): bool {
    $environment = strtolower(tracs_build_environment());
    return in_array($environment, ['local', 'dev', 'development', 'test', 'testing', 'staging'], true);
}

function tracs_build_public_payload(): array {
    return [
        'system' => 'TRACS',
        'name' => 'Tracking, Reminder & Automation Coordination System',
        'version' => TRACS_BUILD_VERSION,
        'deployedAt' => TRACS_BUILD_DEPLOYED_AT,
        'deployedLabel' => tracs_build_deployed_label(),
        'owner' => TRACS_BUILD_OWNER,
        'environment' => tracs_build_environment(),
        'easterEgg' => tracs_build_allows_internal_codename(),
    ];
}

function tracs_build_internal_payload(): array {
    return tracs_build_public_payload() + [
        'codename' => TRACS_BUILD_CODENAME,
    ];
}
