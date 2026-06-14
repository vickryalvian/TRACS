-- Align the three main CS shift templates with the shared TRACS shift clock.
-- Shift 3 stores midnight as 00:00 and is marked cross-day; the UI displays 24:00.

UPDATE shift_templates
SET start_time = CASE shift_name
      WHEN 'Shift 1' THEN '00:00:00'
      WHEN 'Shift 2' THEN '08:00:00'
      WHEN 'Shift 3' THEN '16:00:00'
    END,
    end_time = CASE shift_name
      WHEN 'Shift 1' THEN '08:00:00'
      WHEN 'Shift 2' THEN '16:00:00'
      WHEN 'Shift 3' THEN '00:00:00'
    END,
    duration_minutes = 480,
    default_break_minutes = 0,
    is_cross_day = CASE WHEN shift_name = 'Shift 3' THEN 1 ELSE 0 END,
    default_assignment_type = 'regular_shift',
    count_as_work_hour = 1,
    is_active = 1,
    notes = CASE shift_name
      WHEN 'Shift 1' THEN 'Main CS shift: 00:00-08:00'
      WHEN 'Shift 2' THEN 'Main CS shift: 08:00-16:00'
      WHEN 'Shift 3' THEN 'Main CS shift: 16:00-24:00'
    END,
    updated_at = NOW()
WHERE shift_name IN ('Shift 1', 'Shift 2', 'Shift 3');

-- Draft and previewed monthly templates are definitions, not live schedules.
-- Refresh their unapplied generated rows to use the updated source template.
UPDATE shift_monthly_template_items item
JOIN shift_monthly_templates monthly ON monthly.id = item.template_id
JOIN shift_templates shift_template ON shift_template.id = item.shift_template_id
SET item.start_time = shift_template.start_time,
    item.end_time = shift_template.end_time,
    item.break_minutes = shift_template.default_break_minutes,
    item.assignment_type = shift_template.default_assignment_type
WHERE monthly.status IN ('draft', 'previewed')
  AND item.generated_assignment_id IS NULL
  AND shift_template.shift_name IN ('Shift 1', 'Shift 2', 'Shift 3');
