<?php
/**
 * Create the four Nusa Putra University intern accounts (Teknik Informatika,
 * internship period 2026-07-20 to 2027-01-21) with the passwords supplied by
 * the requester. Idempotent: any user whose email or username already exists
 * (ignoring 'removed' rows, which release their identity for reuse) is
 * skipped entirely, never updated.
 *
 * Dry run:
 *   php bin/create-intern-users.php
 *
 * Apply:
 *   php bin/create-intern-users.php --apply
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script may only be run from the command line.\n");
    exit(1);
}

const INTERN_UNIVERSITY = 'Nusa Putra University';
const INTERN_STUDY_PROGRAM = 'Teknik Informatika';
const INTERN_POSITION = 'Intern';
const INTERN_START_DATE = '2026-07-20';
const INTERN_END_DATE = '2027-01-21';

function seedFail(string $message): never
{
    fwrite(STDERR, "ERROR: {$message}\n");
    exit(1);
}

function loadSeedEnvironment(string $path): void
{
    foreach (['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASS'] as $key) {
        $value = getenv($key);
        if ($value !== false && $value !== '') {
            $_ENV[$key] = $value;
        }
    }
    if (!is_file($path)) return;
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        if ($key === '' || isset($_ENV[$key])) continue;
        $_ENV[$key] = trim($value, "\"'");
    }
}

function seedFetchOne(mysqli $db, string $sql, string $types = '', array $params = []): ?array
{
    $stmt = $db->prepare($sql);
    if (!$stmt) throw new RuntimeException($db->error);
    if ($types !== '') $stmt->bind_param($types, ...$params);
    if (!$stmt->execute()) throw new RuntimeException($stmt->error);
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function seedTableExists(mysqli $db, string $table): bool
{
    $row = seedFetchOne(
        $db,
        'SELECT 1 AS found FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=? LIMIT 1',
        's',
        [$table]
    );
    return $row !== null;
}

function resolveSeedActor(mysqli $db, int $requestedActorId): int
{
    if ($requestedActorId > 0) {
        $actor = seedFetchOne($db, 'SELECT id FROM tracs_users WHERE id=? LIMIT 1', 'i', [$requestedActorId]);
        if (!$actor) seedFail('The requested --actor-id does not exist.');
        return (int)$actor['id'];
    }
    $actor = seedFetchOne($db, "
        SELECT u.id
        FROM tracs_users u
        JOIN tracs_roles r ON r.id=u.role_id
        WHERE r.slug='super_admin' AND u.status='active' AND u.is_active=1
        ORDER BY u.id
        LIMIT 1
    ");
    if (!$actor) seedFail('No active Super Admin exists to attribute this seed to; pass --actor-id.');
    return (int)$actor['id'];
}

function identityExists(mysqli $db, string $email, string $username): bool
{
    $row = seedFetchOne(
        $db,
        "SELECT id FROM tracs_users
         WHERE (email=? OR username=?) AND COALESCE(status,'active')<>'removed'
         LIMIT 1",
        'ss',
        [$email, $username]
    );
    return $row !== null;
}

loadSeedEnvironment(__DIR__ . '/../.env');
require_once __DIR__ . '/../config/database.php';

$options = getopt('', ['apply', 'actor-id:']);
$apply = array_key_exists('apply', $options);
$requestedActorId = max(0, (int)($options['actor-id'] ?? 0));

foreach (['tracs_users', 'tracs_roles', 'user_intern_profiles'] as $table) {
    if (!seedTableExists($conn, $table)) {
        seedFail("Required table {$table} is missing. Apply config/migrations/2026_05_17_user_management.sql and 2026_05_17_intern_user_management.sql first.");
    }
}

$internRole = seedFetchOne($conn, "SELECT id FROM tracs_roles WHERE slug='intern' LIMIT 1");
if (!$internRole) seedFail("No 'intern' role exists. Apply config/migrations/2026_05_17_intern_user_management.sql first.");
$internRoleId = (int)$internRole['id'];

$specs = [
    [
        'name' => 'Muhammad Fauzi Surya',
        'username' => 'fauzi',
        'email' => 'fauzi@idcloudhost.co.id',
        'password' => '573bt%Rued7M',
    ],
    [
        'name' => 'Muhamad Ghibran Muslih',
        'username' => 'ghibran',
        'email' => 'ghibran@idcloudhost.co.id',
        'password' => 'ap%pq8lJt8vpO',
    ],
    [
        'name' => 'Moch. Hamdi Addzikri',
        'username' => 'hamdi',
        'email' => 'hamdi@idcloudhost.co.id',
        'password' => 'ls1uDIN%WtAB',
    ],
    [
        'name' => 'Rangga Hishbu Shafar',
        'username' => 'rangga',
        'email' => 'rangga@idcloudhost.co.id',
        'password' => '*E99bA4r9DpnL5m',
    ],
];

$toCreate = [];
$toSkip = [];
foreach ($specs as $spec) {
    if (strlen($spec['password']) < 8) {
        seedFail("Password for {$spec['email']} is shorter than the 8-character minimum.");
    }
    if (identityExists($conn, $spec['email'], $spec['username'])) {
        $toSkip[] = $spec;
    } else {
        $toCreate[] = $spec;
    }
}

fwrite(STDOUT, "TRACS intern account seed\n");
fwrite(STDOUT, "Role: Intern | Position: " . INTERN_POSITION . " | University: " . INTERN_UNIVERSITY . " | Study Program: " . INTERN_STUDY_PROGRAM . "\n");
fwrite(STDOUT, "Internship period: " . INTERN_START_DATE . " to " . INTERN_END_DATE . "\n");
foreach ($toSkip as $spec) {
    fwrite(STDOUT, "SKIP (already exists): {$spec['name']} <{$spec['email']}>\n");
}
foreach ($toCreate as $spec) {
    fwrite(STDOUT, "CREATE: {$spec['name']} <{$spec['email']}> (username: {$spec['username']})\n");
}

if (!$toCreate) {
    fwrite(STDOUT, "Nothing to do; all four accounts already exist.\n");
    exit(0);
}

if (!$apply) {
    fwrite(STDOUT, "Dry run only. Re-run with --apply to commit these changes.\n");
    exit(0);
}

$conn->begin_transaction();
try {
    $actorId = resolveSeedActor($conn, $requestedActorId);
    $createdIds = [];

    $userStmt = $conn->prepare("
        INSERT INTO tracs_users
          (name, username, email, phone, position, password, role, is_active, status, role_id, division_id,
           shift_preference, avatar_initials_color, created_by, updated_by, last_password_change_at, created_at, updated_at)
        VALUES (?, ?, ?, NULL, ?, ?, 'operator', 1, 'active', ?, NULL, NULL, NULL, ?, ?, NOW(), NOW(), NOW())
    ");
    if (!$userStmt) throw new RuntimeException($conn->error);

    $profileStmt = $conn->prepare("
        INSERT INTO user_intern_profiles
          (user_id, university_name, study_program, internship_start_date, internship_end_date,
           mentor_user_id, internship_status, evaluation_status, skill_level, allowed_task_scope,
           special_notes, created_by, updated_by, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, NULL, 'active', 'not_started', 'beginner', NULL, NULL, ?, ?, NOW(), NOW())
    ");
    if (!$profileStmt) throw new RuntimeException($conn->error);

    $university = INTERN_UNIVERSITY;
    $studyProgram = INTERN_STUDY_PROGRAM;
    $position = INTERN_POSITION;
    $startDate = INTERN_START_DATE;
    $endDate = INTERN_END_DATE;

    foreach ($toCreate as $spec) {
        $passwordHash = password_hash($spec['password'], PASSWORD_DEFAULT);
        if ($passwordHash === false) throw new RuntimeException("Unable to hash password for {$spec['email']}.");

        $userStmt->bind_param(
            'sssssiii',
            $spec['name'],
            $spec['username'],
            $spec['email'],
            $position,
            $passwordHash,
            $internRoleId,
            $actorId,
            $actorId
        );
        if (!$userStmt->execute()) throw new RuntimeException($userStmt->error);
        $userId = (int)$userStmt->insert_id;

        $profileStmt->bind_param(
            'issssii',
            $userId,
            $university,
            $studyProgram,
            $startDate,
            $endDate,
            $actorId,
            $actorId
        );
        if (!$profileStmt->execute()) throw new RuntimeException($profileStmt->error);

        $createdIds[$spec['email']] = $userId;
    }

    $userStmt->close();
    $profileStmt->close();
    $conn->commit();
} catch (Throwable $e) {
    $conn->rollback();
    seedFail($e->getMessage());
}

fwrite(STDOUT, "Applied successfully:\n");
foreach ($createdIds as $email => $id) {
    fwrite(STDOUT, "  #{$id} {$email}\n");
}
fwrite(STDOUT, "Passwords were the ones supplied for this seed; not printed here. Have interns rotate them after first login.\n");
