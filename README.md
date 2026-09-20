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
5. Select **Import**, choose `database/spotly.sql`, and click **Import**.
6. Confirm that the `spotly` database contains `users`, `laboratories`, `reservations`, and `notifications`.
7. Open `http://localhost/spotly` in a browser.

The default local database settings are host `localhost`, user `root`, and an empty password. Update `config/db.php` if the local MySQL installation uses different credentials.

## Demo Accounts

| Role | Email | Password |
| --- | --- | --- |
| DOIT Staff/Admin | dana.santos@soit.edu | `Admin@123` |
| Faculty | felix.reyes@soit.edu | `Faculty@123` |
| Student | ari.cruz@student.soit.edu | `Student@123` |
| Student | bea.lim@student.soit.edu | `Student2@123` |

These are development/demo accounts only. Passwords are stored as `password_hash()` hashes in the SQL file.

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

## Test Plan Checklist

| ID | Test case | Steps | Expected result |
| --- | --- | --- | --- |
| AUTH-01 | Register account | Submit valid registration details | Account is created and password is stored as a hash |
| AUTH-02 | Duplicate email | Register with an existing email | Friendly duplicate-email error; no second account |
| AUTH-03 | Login routing | Log in as Student, Faculty, and Admin | Student/Faculty reach dashboard; Admin reaches admin page |
| AUTH-04 | Logout | Click Logout | Session is destroyed and login page appears |
| AUTH-05 | CSRF rejection | Remove or alter a form/API CSRF token | Request is rejected |
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