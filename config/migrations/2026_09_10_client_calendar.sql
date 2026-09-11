-- Preserve existing activity types while allowing quotation, meeting, document and custom reminders.
ALTER TABLE tracs_client_followups MODIFY action_type VARCHAR(80) NOT NULL DEFAULT 'general_followup';
