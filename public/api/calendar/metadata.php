<?php

require_once __DIR__ . '/_common.php';
calendar_require_method('GET');

$users = [];
if (tracs_table_exists($conn, 'tracs_users')) {
    $hasRoles = tracs_table_exists($conn, 'tracs_roles');
    $roleSelect = $hasRoles
        ? "COALESCE(r.slug,u.role,'agent') AS role_slug,COALESCE(r.name,u.role,'Agent') AS role_name"
        : "COALESCE(u.role,'agent') AS role_slug,COALESCE(u.role,'Agent') AS role_name";
    $roleJoin = $hasRoles ? ' LEFT JOIN tracs_roles r ON r.id=u.role_id' : '';
    $sql = "SELECT u.id,COALESCE(NULLIF(u.name,''),u.email) AS name,u.division_id,d.name AS division_name,{$roleSelect}
            FROM tracs_users u
            LEFT JOIN tracs_divisions d ON d.id=u.division_id
            {$roleJoin}
            WHERE u.is_active=1 AND COALESCE(u.status,'active')='active'";
    $types = '';
    $params = [];
    if (!calendar_can_manage()) {
        $sql .= ' AND u.id=?';
        $types = 'i';
        $params[] = $uid;
    } elseif ((string)($authUser['role_slug'] ?? '') === 'supervisor' && (int)($authUser['division_id'] ?? 0) > 0) {
        $sql .= ' AND (u.division_id=? OR u.id=?)';
        $types = 'ii';
        $params[] = (int)$authUser['division_id'];
        $params[] = $uid;
    }
    $sql .= ' ORDER BY name';
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        if ($types !== '') $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}

$rolesBySlug = [];
foreach ($users as $user) {
    $slug = (string)($user['role_slug'] ?? '');
    if ($slug !== '') {
        $rolesBySlug[$slug] = ['slug' => $slug, 'name' => (string)($user['role_name'] ?? ucfirst(str_replace('_', ' ', $slug)))];
    }
}

$divisions = [];
if (tracs_table_exists($conn, 'tracs_divisions')) {
    $stmt = $conn->prepare("SELECT id,name,code FROM tracs_divisions WHERE status='active' ORDER BY name");
    if ($stmt) {
        $stmt->execute();
        $divisions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}

ok([
    'timezone' => 'Asia/Jakarta',
    'date_format' => 'dd-mm-yyyy',
    'event_types' => calendar_event_types(),
    'statuses' => calendar_statuses(),
    'users' => $users,
    'roles' => array_values($rolesBySlug),
    'divisions' => $divisions,
    'permissions' => [
        'can_create' => calendar_can_manage(),
        'can_edit' => calendar_can_manage(),
        'can_delete' => calendar_can_manage(),
    ],
    'current_user' => [
        'id' => $uid,
        'name' => (string)($authUser['display_name'] ?? $authUser['name'] ?? $authUser['email'] ?? 'User'),
        'role' => (string)($authUser['role_slug'] ?? ''),
        'division_id' => (int)($authUser['division_id'] ?? 0) ?: null,
    ],
], 'Calendar metadata loaded.');
