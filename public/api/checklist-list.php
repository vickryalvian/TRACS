<?php require '_bootstrap.php';
require_once __DIR__ . '/../../modules/checklist/controller.php';
require_once __DIR__ . '/checklist-attachment-lib.php';

/** Full checklist listing for the "View All Checklist" popup's Active tab
 * (the widget only fits a compact scroll of the same underlying list). */
$KC = new ChecklistController($conn, $uid);
$tasks = $KC->getTasks() ?: [];
usort($tasks, function ($a, $b) {
    if (($a['is_completed'] ?? 0) !== ($b['is_completed'] ?? 0)) return ($a['is_completed'] ?? 0) <=> ($b['is_completed'] ?? 0);
    return ($b['id'] ?? 0) <=> ($a['id'] ?? 0);
});

$items = array_map(function ($t) use ($conn, $uid) {
    $tid = (int)($t['id'] ?? 0);
    $name = tracs_creator_label($t);
    $createdAt = (string)($t['created_at'] ?? '');
    $metaText = 'by ' . $name . (($createdAt && strtotime($createdAt)) ? ' · ' . date('d M Y', strtotime($createdAt)) : '');
    $attachments = array_map(fn($a) => [
        'thumbnail_url' => $a['thumbnail_url'],
        'image_url' => $a['image_url'],
        'original_filename' => $a['original_filename'],
    ], checklist_attachment_list_for_task($conn, $tid));
    return [
        'id' => $tid,
        'title' => (string)($t['title'] ?? ''),
        'description' => (string)($t['description'] ?? ''),
        'is_completed' => !empty($t['is_completed']),
        'can_delete' => (int)($t['created_by'] ?? 0) === $uid,
        'meta_text' => $metaText,
        'attachments' => $attachments,
    ];
}, $tasks);

ok(['items' => $items]);
