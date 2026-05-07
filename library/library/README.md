Demo Credentials
for admin the email is nalutaaya@gmail.com
password is nalutaaya
for student create an account
create database called library
and then import the database called library.sql

Complete File Structure

library/
├── .htaccess               ← Apache security & custom 404
├── index.php               ← Public home page
├── login.php               ← Standalone login
├── register.php            ← Student self-registration
├── logout.php              ← POST-only logout with activity log
├── dashboard.php           ← Role-based dashboard
├── books.php               ← Paginated catalog + search + detail
├── add_book.php            ← Add / Edit / Delete books (admin)
├── borrow.php              ← Borrow a book
├── return.php              ← Return + fine display
├── reservations.php        ← Reservation system
├── profile.php             ← Edit profile + change password
├── reports.php             ← Analytics + CSV export buttons (admin)
├── export.php              ← CSV export handler (admin)
├── fine_settings.php       ← Fine config + pay/waive (admin)
├── activity_log.php        ← Full audit log (admin)
├── 404.php                 ← Custom 404 page
├── config/
│   ├── database.php        ← DB connection, schema init, helpers
│   └── auth.php            ← Session, roles, flash, fine helpers
└── includes/
    ├── header.php          ← Top nav + full CSS design system
    └── footer.php          ← JS helpers + footer


 Setup

1. Copy `library/` folder to your web root 
2. Edit `config/database.php` with your MySQL credentials
3. Visit `http://localhost/library/` — database auto-initialises



 Tables Created

- `users` — with phone, student_id, avatar_color
- `books` — with cover_color
- `borrow_records` — with fine_amount, fine_paid, notes
- `reservations` — with status lifecycle
- `fine_settings` — configurable rate/cap/grace
- `activity_log` — full audit trail

Security

- Prepared statements everywhere (no SQL injection)
- bcrypt password hashing
- Role-gated admin pages
- POST-only logout
- .htaccess config-dir protection + security headers
- HttpOnly session cookies
