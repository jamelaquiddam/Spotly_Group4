# Spotly

Spotly is a PHP/MySQL laboratory reservation system for SOIT Cisco Laboratories and Regular Computer Laboratories.

## Requirements

- XAMPP with Apache, MySQL, and PHP 8
- A browser
- Project folder copied to `C:\xampp\htdocs\spotly`

## XAMPP Setup

1. Copy this project folder to `C:\xampp\htdocs\spotly`.
2. Open the XAMPP Control Panel.
3. Start **Apache** and **MySQL**.
4. Open `http://localhost/phpmyadmin`.
5. For a new database, select **Import**, choose `database/spotly.sql`, and click **Import**. For an existing Phase 1-7 database, import `database/phase8_migration.sql` instead.
6. Confirm that the `spotly` database contains `users`, `laboratories`, `reservations`, and `notifications`.
7. Open `http://localhost/spotly` in a browser.

The default local database settings are host `localhost`, user `root`, and an empty password. Update `config/db.php` if the local MySQL installation uses different credentials.

## Demo Accounts

| Role | Email | Password |
| --- | --- | --- |
| DOIT Staff/Admin | doit.admin@mapua.edu.ph | `Admin@123` |
| Faculty | faculty1@mapua.edu.ph | `Faculty@123` |
| Student | student1@mymail.mapua.edu.ph | `Student@123` |
| Student | student2@mymail.mapua.edu.ph | `Student2@123` |

These are development/demo accounts only. Passwords are stored as `password_hash()` hashes in the SQL file, and these seed accounts are already verified.

## Mapua Email and Verification

- Students must use exactly `@mymail.mapua.edu.ph`.
- Faculty and DOIT Staff/Admin must use exactly `@mapua.edu.ph`.
- Public registration allows only Student and Faculty. DOIT accounts are created by an existing DOIT administrator or by the SQL seed.
- New accounts are unverified until the 24-hour verification link is opened.
- `database/phase8_migration.sql` ends with a query listing existing users whose email domain does not match their role. Correct those emails to an authorized Mapua address, deactivate them with the admin Manage Users page, or remove them according to your school’s data policy. Do not silently promote an invalid account.

## SMTP / DEV_MODE

`config/mail.php` contains the SMTP settings and the `DEV_MODE` switch. It is `true` for localhost demos, so registration displays a verification link on screen instead of sending email. Set `DEV_MODE` to `false` in production, fill in the SMTP host, port, username, app password, encryption, and sender address, and install PHPMailer with:

```text
composer install
```

For Gmail, use an app password rather than your normal account password. Never commit SMTP credentials. With `DEV_MODE` false, the app loads PHPMailer from `vendor/autoload.php` and sends verification mail through SMTP.

## Folder Structure

```text
spotly/
	config/db.php                 PDO database connection
	database/spotly.sql           Database, tables, and demo data
	auth/                         Login, registration, and logout
	pages/                        Student, booking, notification, and admin pages
	api/                          JSON availability, booking, and admin actions
	assets/css/style.css          Shared responsive styling
	assets/js/                    Calendar, booking, reservations, and admin scripts
	includes/                     Session guards and shared layout
	composer.json                 PHPMailer dependency definition
	index.php                     Session-aware entry redirect
```

## How Conflict Checking Works

When a reservation is submitted, `api/reserve.php` starts a database transaction and locks the selected laboratory row with `SELECT ... FOR UPDATE`. It then checks the same room and date for Pending or Approved reservations using:

```text
existing_start < new_end AND existing_end > new_start
```

This allows back-to-back reservations such as 10:00-11:00 and 11:00-12:00 because the ranges do not overlap. If an overlap exists, the transaction returns a conflict and inserts nothing. Otherwise, it inserts a Pending reservation and its notification together.

When an administrator approves a request, the room row is locked again and the overlap check is repeated against Approved reservations. This protects against two administrators approving overlapping requests at the same time. Rejected and cancelled reservations are excluded from availability, so their slots become free again.

## Security Review Notes

- PDO prepared statements are used for application database operations.
- Login, registration, reservation, cancellation, notification, and admin POST actions require CSRF tokens.
- Student and faculty pages require login; the admin page and admin JSON endpoint require `DOIT Staff/Admin`.
- Reservation APIs return only the logged-in user’s reservation details or non-private room schedule data.
- User-facing PHP output is escaped with `htmlspecialchars()`; client-rendered dynamic text is escaped before HTML insertion.
- Passwords use `password_hash()` and `password_verify()`.
- Login attempts are limited to five failures per email/IP combination within 15 minutes.
- Sessions use strict mode, HttpOnly cookies, SameSite=Lax, and session ID regeneration after login.

## Test Plan Checklist

| ID | Test case | Steps | Expected result |
| --- | --- | --- | --- |
| AUTH-01 | Register account | Submit valid registration details | Account is created and password is stored as a hash |
| AUTH-02 | Duplicate email | Register with an existing email | Friendly duplicate-email error; no second account |
| AUTH-03 | Login routing | Log in as Student, Faculty, and Admin | Student/Faculty reach dashboard; Admin reaches admin page |
| AUTH-04 | Logout | Click Logout | Session is destroyed and login page appears |
| AUTH-05 | CSRF rejection | Remove or alter a form/API CSRF token | Request is rejected |
| AUTH-06 | Student wrong domain | Register with `user@gmail.com` | Rejected with the student Mapua-domain message |
| AUTH-07 | Student faculty-domain mismatch | Register Student with `user@mapua.edu.ph` | Rejected |
| AUTH-08 | Faculty student-domain mismatch | Register Faculty with `user@mymail.mapua.edu.ph` | Rejected |
| AUTH-09 | Malicious domain suffix | Register with `x@mymail.mapua.edu.ph.evil.com` | Rejected by exact domain comparison |
| AUTH-10 | Uppercase normalization | Register `JUAN@MYMAIL.MAPUA.EDU.PH` as Student | Accepted and stored lowercase |
| AUTH-11 | Unverified login | Try logging in before opening verification link | Login blocked |
| AUTH-12 | Public admin escalation | Add `DOIT Staff/Admin` to the form in DevTools and submit | Server rejects the role |
| AUTH-13 | Login lockout | Submit five wrong passwords for one email/IP | Temporary 15-minute lockout message appears |
| CAL-01 | Load calendar | Open dashboard while logged in | Room list and Mon-Sat calendar load without page reload |
| CAL-02 | Filter rooms | Change lab type | Room dropdown and catalog show only that type |
| CAL-03 | Approved display | View a seeded approved reservation | Slot is red and shows time/course section only |
| CAL-04 | Pending display | View a seeded pending reservation | Slot is yellow and own reservation is marked Mine |
| CAL-05 | Maintenance block | Select CISCO-303 | Slots are gray and cannot be selected |
| BOOK-01 | New booking | Select future free range and submit valid details | Reservation is Pending, slot turns yellow, notification is created |
| BOOK-02 | Back-to-back bookings | Book 10:00-11:00, then 11:00-12:00 in the same room | Both bookings are accepted |
| BOOK-03 | Overlap conflict | Try 10:30-11:30 after 10:00-11:00 exists | Live check and server reject with conflict message |
| BOOK-04 | Closing time | Try a booking ending after 9:00 PM | Booking is rejected |
| BOOK-05 | Past date | Try booking yesterday | Booking is rejected |
| BOOK-06 | Capacity | Enter more attendees than room capacity | Booking is rejected |
| RES-01 | Reservation history | Open My Reservations after booking | Reservation details and Pending status appear |
| RES-02 | Cancel pending | Cancel a Pending reservation and confirm | Reservation is removed and its slot becomes free |
| RES-03 | Cancel approved | Try to cancel an Approved reservation | Cancel action is unavailable or server rejects it |
| NOT-01 | Notification badge | Create or receive a notification | Navbar unread count increases on every authenticated page |
| NOT-02 | Read notification | Mark one notification as read | Styling changes and badge decreases |
| NOT-03 | Mark all read | Click Mark all as read | All notifications become read and badge clears |
| ADM-01 | Admin protection | Open admin page/API as Student | Page/API returns forbidden access |
| ADM-02 | Approve request | Approve a Pending reservation as Admin | Status becomes Approved, slot turns red, requester is notified |
| ADM-03 | Reject request | Reject with and without a reason | Status becomes Rejected and requester receives the message |
| ADM-04 | Approval conflict | Approve overlapping pending requests sequentially | First succeeds; second is rejected as a conflict |
| ADM-05 | Lab status | Change a room to Under Maintenance | Students cannot book it and calendar slots turn gray |
| UI-01 | Mobile layout | Use a narrow browser viewport | Calendar becomes single-day and tables remain usable with scrolling |
| UI-02 | Empty states | Filter to no reservations/notifications | Friendly empty-state message appears |