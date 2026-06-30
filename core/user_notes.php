<?php
/**
 * TRACS internal user notes (supervisor-tier scope).
 *
 * Notes are attached to a target user account for access/training/monitoring
 * remarks. Visibility and mutation are restricted to supervisor-tier roles
 * and above via tracs_is_supervisor_or_above() — never exposed to the note
 * subject or any role below supervisor.
 */

require_once __DIR__ . '/access_control.php';
require_once __DIR__ . '/user_management.php';

const TRACS_USER_NOTE_CATEGORIES = [
    'access_provisioning',
    'training',
    'performance',
    'monitoring',
    'internship_evaluation',
    'administrative',
];

function tracs_user_notes_ensure_schema(mysqli $conn): bool {
    static $ready = false;
    if ($ready) return true;

    $sql = "CREATE TABLE IF NOT EXISTS `tracs_user_notes` (
      `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
      `target_user_id` INT UNSIGNED NOT NULL,
      `author_user_id` INT UNSIGNED NOT NULL,
      `category` VARCHAR(40) NOT NULL DEFAULT 'administrative',
      `content` TEXT NOT NULL,
      `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      INDEX `idx_tracs_user_notes_target` (`target_user_id`, `created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    try {
        if ($conn->query($sql) !== true) {
            error_log('TRACS user_notes schema failed: ' . $conn->error);
            return false;
        }
        $ready = tracs_table_exists($conn, 'tracs_user_notes');
        return $ready;
    } catch (Throwable $e) {
        error_log('TRACS user_notes schema exception: ' . $e->getMessage());
        return false;
    }
}

function tracs_user_note_category_label(string $category): string {
    return match ($category) {
        'access_provisioning' => 'Access Provisioning',
        'training' => 'Training',
        'performance' => 'Performance',
        'monitoring' => 'Monitoring',
        'internship_evaluation' => 'Internship Evaluation',
        default => 'Administrative',
    };
}

function tracs_user_notes_list(mysqli $conn, int $targetUserId): array {
    if ($targetUserId <= 0 || !tracs_user_notes_ensure_schema($conn)) return [];
    $stmt = $conn->prepare("
        SELECT n.id, n.target_user_id, n.author_user_id, n.category, n.content,
               n.created_at, n.updated_at,
               COALESCE(NULLIF(u.name, ''), u.email, 'Unknown') AS author_name
        FROM tracs_user_notes n
        LEFT JOIN tracs_users u ON u.id = n.author_user_id
        WHERE n.target_user_id = ?
        ORDER BY n.created_at DESC, n.id DESC
    ");
    if (!$stmt) return [];
    $stmt->bind_param('i', $targetUserId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    foreach ($rows as &$row) {
        $row['category_label'] = tracs_user_note_category_label((string)$row['category']);
    }
    return $rows;
}

function tracs_user_note_create(mysqli $conn, int $targetUserId, int $authorUserId, string $category, string $content): ?int {
    if ($targetUserId <= 0 || $authorUserId <= 0 || !tracs_user_notes_ensure_schema($conn)) return null;
    $content = trim($content);
    if ($content === '') return null;
    if (function_exists('mb_substr')) {
        $content = mb_substr($content, 0, 4000);
    } else {
        $content = substr($content, 0, 4000);
    }
    $category = in_array($category, TRACS_USER_NOTE_CATEGORIES, true) ? $category : 'administrative';

    $stmt = $conn->prepare("
        INSERT INTO tracs_user_notes (target_user_id, author_user_id, category, content, created_at, updated_at)
        VALUES (?, ?, ?, ?, NOW(), NOW())
    ");
    if (!$stmt) return null;
    $stmt->bind_param('iiss', $targetUserId, $authorUserId, $category, $content);
    if (!$stmt->execute()) {
        $stmt->close();
        return null;
    }
    $id = (int)$stmt->insert_id;
    $stmt->close();
    return $id;
}

function tracs_user_note_update(mysqli $conn, int $noteId, string $category, string $content): bool {
    if ($noteId <= 0 || !tracs_user_notes_ensure_schema($conn)) return false;
    $content = trim($content);
    if ($content === '') return false;
    if (function_exists('mb_substr')) {
        $content = mb_substr($content, 0, 4000);
    } else {
        $content = substr($content, 0, 4000);
    }
    $category = in_array($category, TRACS_USER_NOTE_CATEGORIES, true) ? $category : 'administrative';

    $stmt = $conn->prepare("UPDATE tracs_user_notes SET category=?, content=?, updated_at=NOW() WHERE id=?");
    if (!$stmt) return false;
    $stmt->bind_param('ssi', $category, $content, $noteId);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function tracs_user_note_delete(mysqli $conn, int $noteId): bool {
    if ($noteId <= 0 || !tracs_user_notes_ensure_schema($conn)) return false;
    $stmt = $conn->prepare("DELETE FROM tracs_user_notes WHERE id=?");
    if (!$stmt) return false;
    $stmt->bind_param('i', $noteId);
    $ok = $stmt->execute() && $stmt->affected_rows > 0;
    $stmt->close();
    return $ok;
}

function tracs_user_note_find(mysqli $conn, int $noteId): ?array {
    if ($noteId <= 0 || !tracs_user_notes_ensure_schema($conn)) return null;
    $stmt = $conn->prepare("SELECT id, target_user_id, author_user_id FROM tracs_user_notes WHERE id=? LIMIT 1");
    if (!$stmt) return null;
    $stmt->bind_param('i', $noteId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}
