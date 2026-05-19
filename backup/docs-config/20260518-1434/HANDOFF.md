# TRACS — Handoff Document

**Date:** 2025  
**Status:** ✅ Ready for first deployment

---

## Completed Features

| Feature | Status | Notes |
|---|---|---|
| Login / Logout | ✅ Complete | session_start guard, password_verify |
| Dashboard | ✅ Complete | Stats, cases, reminders, checklist, activity |
| Cases CRUD | ✅ Complete | Create, edit, delete, filter, search |
| Reminders CRUD | ✅ Complete | Create, edit, delete, toggle complete, filter |
| Checklist CRUD | ✅ Complete | Create, edit, delete, toggle, progress bar |
| Activity Log | ✅ Complete | Filter by module, search, paginate |
| Finance Log | ✅ Complete | Create, delete, filter in/out, stats |
| Domains Tracker | ✅ Complete | Create, edit, delete, expiry status |
| Ticker Bar | ✅ Complete | Auto-generated alerts + custom messages |
| Ticker Management | ✅ Complete | Add/delete via modal |
| Sidebar Navigation | ✅ Complete | Icon-only, tooltips, active state, badges |
| Dark Design System | ✅ Complete | tracs.css — full component library |
| Modal System | ✅ Complete | All shared modals in footer.php |
| Toast Notifications | ✅ Complete | success/error/info |
| Confirm Dialogs | ✅ Complete | For all destructive actions |
| Live Clock | ✅ Complete | Updates every second |
| Progress Bar | ✅ Complete | Checklist completion, live update |
| API Endpoints | ✅ Complete | 19 endpoints, all return JSON |
| Activity Logging | ✅ Complete | All CRUD actions logged |
| Docker Deploy | ✅ Complete | Dockerfile + docker-compose.yml |
| DB Schema | ✅ Complete | config/install.sql — all 9 tables |

---

## File Count
- **Total files:** 54
- **PHP pages:** 7 public pages + login
- **API endpoints:** 19
- **CSS lines:** ~339 (complete design system)
- **JS lines:** ~218 (all functionality)

---

## Known Bugs / Limitations

| Issue | Severity | Notes |
|---|---|---|
| No pagination on cases list | Low | Functional for <200 cases |
| Finance transfers not editable | By design | Audit trail integrity |
| No email reminders | Low | UI-only reminders |
| No multi-user real-time | Low | Single operator typical use |
| Google Fonts CDN dependency | Low | Fallback to system-ui if offline |

---

## Deployment Blockers
**None.** Project is ready to deploy.

---

## Pre-Deployment Checklist

```
[ ] Edit config/database.php with real DB credentials
[ ] Run: mysql -u user -p dbname < config/install.sql
[ ] Set Apache DocumentRoot to /public/
[ ] Enable mod_rewrite: a2enmod rewrite
[ ] Visit /login.php — should show login form
[ ] Login with admin@tracs.local / password
[ ] IMMEDIATELY change password
[ ] Test: create a case → verify appears on dashboard
[ ] Test: create a reminder → verify appears in reminders
[ ] Test: add a task → verify checklist updates
[ ] Verify ticker bar animates
[ ] Enable HTTPS (certbot)
```

---

## Deployment Commands

### Traditional
```bash
# Upload files to server
rsync -av --exclude='.git' . user@server:/var/www/tracs/

# On server
mysql -u root -p your_db_name < /var/www/tracs/config/install.sql
sudo a2enmod rewrite
sudo service apache2 restart
```

### Docker
```bash
cd /path/to/tracs
docker-compose up -d
docker-compose logs -f app
```

---

## Recommended Next Steps (Post-Deploy)

1. **Change admin password** — first priority
2. **Enable HTTPS** — `certbot --apache -d yourdomain.com`
3. **Set PHP session security** in php.ini:
   ```
   session.cookie_secure = 1
   session.cookie_httponly = 1
   session.cookie_samesite = Strict
   ```
4. **Set up DB backups** — `mysqldump` cron or managed backups
5. **Delete `config/install.sql`** after database is created
6. **Add more users** — `INSERT INTO tracs_users (email, password, name) VALUES ('user@domain.com', PASSWORD_HASH, 'Name');`
   Password hash: `php -r "echo password_hash('yourpassword', PASSWORD_DEFAULT);"`

---

## Continuation Instructions (For Another Developer / AI)

If picking this project up:

1. **Read `AI_MEMORY.md`** — critical rules and patterns
2. **Read `ARCHITECTURE.md`** — understand data flow and structure
3. **Source files are in `/home/claude/tracs_output/`** (dev) → deploy from there
4. **The design system is in `public/assets/tracs.css`** — all CSS vars are at `:root`
5. **All shared JS is in `public/assets/tracs.js`** — follow existing patterns
6. **Adding features:** follow the pattern in `cases.php` + `api/case-*.php` as the gold standard
7. **The `_bootstrap.php` in api/** handles all auth + response helpers — always require it first

---

## Project Context

This is a legal/operations dashboard for small team (1-5 operators).  
Primary use: tracking legal cases, reminders, checklists, domain management, finance logging.  
Target environment: Indonesian hosting (Niagahoster/Domainesia), VPS or shared hosting with PHP support.  
UI language: English (with Indonesian placeholder data in examples).
