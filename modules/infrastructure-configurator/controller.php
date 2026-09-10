<?php
require_once __DIR__ . '/../../core/security/direct_access.php';
tracs_deny_direct_script_access(__FILE__);
require_once __DIR__ . '/master.php';

class InfrastructureConfiguratorController {
    public function getInitialViewModel(mysqli $conn, bool $includeInactive = false): array {
        return tracs_configurator_catalog($conn, $includeInactive);
    }
}
