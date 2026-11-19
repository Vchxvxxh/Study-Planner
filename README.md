# Semester Final Planner

<p align="center">
  <a href="https://your-website-link.com">
    <strong>🚀 VIEW LIVE WEBSITE ↗</strong>
  </a>
</p>

Exam study planner: add subjects, chapters and deadlines, and it spreads the remaining work over the days left (earliest deadline first, re-planned daily). Optional account sync across devices.

**Languages:** Bangla (default, mixed with common English terms) and English. Switch with the button in the header; the choice is remembered. To add or edit text, change both `bn` and `en` in `js/i18n.js`.

**Stack:** vanilla HTML/CSS/JS frontend, PHP 8 + MySQL (or SQLite) backend.

## Structure

```
index.html        app shell
css/style.css     styles
js/app.js         planner logic and UI
js/i18n.js        UI text, Bangla (bn) and English (en)
api.php           JSON API (login, sync, profile, avatar)
admin.php         user list / CSV export (key protected)
lib.php           DB connection and schema
config.example.php  copy to config.php
uploads/          avatars (script execution disabled)
```

## Setup

1. Copy `config.example.php` to `config.php` and fill in the DB details and a long random `admin_key`.
2. Upload everything to a PHP host with Apache (`.htaccess` is used). Tables are created on first request.
3. Make `uploads/` writable.

Never commit `config.php`; it is git-ignored.

## Known limitation

Login is email-only with no verification, so anyone who knows an email can open that planner. Fine for a personal project, add email OTP or passwords before storing anything sensitive.

## Development

Built by N.M. Niloy with AI assistance (Claude by Anthropic) for code
cleanup, bilingual (Bangla/English) support, and documentation.
All code was reviewed and tested by the author.
