# TRACS — Tasks & Roadmap

## Immediate (Post-Deploy)
- [ ] Change default admin password
- [ ] Enable HTTPS
- [ ] Set PHP session security flags
- [ ] Delete install.sql from server

## Short-Term Improvements
- [ ] Add pagination to cases list (>50 cases threshold)
- [ ] Add reminder email notifications (PHPMailer or sendmail)
- [ ] Add case notes/comment thread per case
- [ ] Add finance edit endpoint (currently create+delete only)
- [ ] Add bulk delete for tasks/reminders
- [ ] Add due date to tasks (currently no deadline)
- [ ] Add "mark all reminders as read" button

## UI Enhancements
- [ ] Add keyboard shortcut `N` = new case, `R` = new reminder, `T` = new task
- [ ] Add case detail view / modal (currently edit-only modal)
- [ ] Add dark/light mode toggle (CSS vars already structured for this)
- [ ] Add mobile responsive layout for sidebar (hamburger menu)
- [ ] Add print/export view for cases

## Technical Debt
- [ ] Add CSRF token validation on all API endpoints
- [ ] Add rate limiting on login endpoint
- [ ] Add input sanitization middleware in bootstrap
- [ ] Add proper HTTP caching headers for assets
- [ ] Move Google Fonts to self-hosted for offline/privacy

## Future Modules
- [ ] **Client Module** — client contact management linked to cases
- [ ] **Documents Module** — file upload/attachment per case
- [ ] **Reports Module** — PDF export of cases, monthly summaries
- [ ] **Calendar View** — timeline view of case deadlines
- [ ] **Multi-user Roles** — admin vs operator permissions
- [ ] **Notifications** — in-app notification bell
- [ ] **Search** — global search across all modules

## Performance
- [ ] Add query caching for dashboard stats
- [ ] Lazy load activity log (currently fetches last 50)
- [ ] Add index on tracs_cases.next_check_at for time_until calc
