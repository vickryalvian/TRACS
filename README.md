# TRACS — Operational Dashboard

Dark, high-density control panel. PHP 8+ / MySQL. No framework.

## Quick Start

### LAMP/LEMP
```bash
# Point DocumentRoot to /public/
mysql -u root -p DB_NAME < config/install.sql
# Edit config/database.php with your credentials
# Visit your domain — login: admin@tracs.local / password
```

### Docker
```bash
docker-compose up -d
# http://localhost:8080
```

## Directory
```
tracs/
├── api/           REST endpoints (POST JSON in/out)
├── auth/          login.php, logout.php, auth_check.php
├── config/        database.php (EDIT), install.sql
├── modules/       MVC: case, reminder, checklist, alert-ticker, activity-log
└── public/        WEB ROOT
    ├── assets/    tracs.css + tracs.js
    ├── includes/  header.php, footer.php, page_helpers.php
    ├── index.php  Dashboard
    ├── cases.php, reminders.php, checklist.php
    ├── activity.php, finance.php, domains.php
    └── login.php
```

## Config
Edit `config/database.php` or set env vars: DB_HOST, DB_PORT, DB_USER, DB_PASS, DB_NAME

## Apache VHost
```apache
DocumentRoot /var/www/tracs/public
<Directory /var/www/tracs/public>
    AllowOverride All
    Require all granted
</Directory>
```

## Requirements
- PHP 8.0+, mysqli extension, session
- MySQL 5.7+ / MariaDB 10.3+
- Apache (mod_rewrite) or Nginx

## Security Before Go-Live
- Change default password immediately
- Set display_errors=Off
- Enable HTTPS
- Restrict /api/, /auth/, /config/, /modules/ in Nginx
