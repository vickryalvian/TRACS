<?php
declare(strict_types=1);

final class ClientReminderRecords
{
    // A linked reminder owns mutable scheduling fields; legacy unlinked follow-ups remain readable.
    public static function query(): string
    {
        return "SELECT f.id, f.client_id, f.service_id, f.billing_record_id, f.reminder_id, f.action_type,
            COALESCE(r.title, f.title) AS title,
            CASE WHEN f.reminder_id IS NULL THEN f.due_at ELSE r.due_date END AS due_at,
            COALESCE(r.priority, f.priority) AS priority,
            CASE WHEN f.reminder_id IS NULL THEN f.status
                 WHEN r.id IS NULL OR r.archived_at IS NOT NULL THEN 'cancelled'
                 WHEN r.is_completed=1 THEN 'completed' ELSE 'open' END AS status,
            COALESCE(r.user_id, f.assigned_to) AS assigned_to,
            r.description, CASE WHEN f.reminder_id IS NULL THEN f.completed_at ELSE r.completed_at END AS completed_at, f.created_by, f.created_at,
            COALESCE(r.updated_at, f.updated_at) AS updated_at
            FROM tracs_client_followups f LEFT JOIN tracs_reminders r ON r.id=f.reminder_id";
    }
}
