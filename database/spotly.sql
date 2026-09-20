CREATE DATABASE IF NOT EXISTS spotly
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE spotly;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS reservations;
DROP TABLE IF EXISTS laboratories;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    student_employee_no VARCHAR(20) NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('Student', 'Faculty', 'DOIT Staff/Admin') NOT NULL,
    department VARCHAR(100) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE laboratories (
    room_id INT PRIMARY KEY AUTO_INCREMENT,
    room_name VARCHAR(50) NOT NULL,
    room_code VARCHAR(20) NOT NULL UNIQUE,
    lab_type ENUM('Cisco Laboratory', 'Regular Computer Laboratory') NOT NULL,
    capacity INT NOT NULL,
    floor VARCHAR(20) NOT NULL,
    status ENUM('Available', 'Under Maintenance', 'Inactive') NOT NULL DEFAULT 'Available'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE reservations (
    reservation_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    room_id INT NOT NULL,
    approved_by INT NULL,
    date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    purpose VARCHAR(150) NOT NULL,
    course_section VARCHAR(30) NOT NULL,
    expected_attendees INT NOT NULL,
    status ENUM('Pending', 'Approved', 'Rejected') NOT NULL DEFAULT 'Pending',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    approved_at DATETIME NULL,
    CONSTRAINT fk_reservations_user
        FOREIGN KEY (user_id) REFERENCES users(user_id),
    CONSTRAINT fk_reservations_room
        FOREIGN KEY (room_id) REFERENCES laboratories(room_id),
    CONSTRAINT fk_reservations_approved_by
        FOREIGN KEY (approved_by) REFERENCES users(user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notifications (
    notification_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    reservation_id INT NULL,
    message VARCHAR(255) NOT NULL,
    is_read BOOLEAN NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notifications_user
        FOREIGN KEY (user_id) REFERENCES users(user_id),
    CONSTRAINT fk_notifications_reservation
        FOREIGN KEY (reservation_id) REFERENCES reservations(reservation_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO users
    (student_employee_no, first_name, last_name, email, password_hash, role, department)
VALUES
    ('DOIT-001', 'Dana', 'Santos', 'dana.santos@soit.edu', '$2y$12$duGNOcfO7PqAjtT4A4yeVumlMbH5l8ibAZLBA51EX/sUP5styoAzC', 'DOIT Staff/Admin', 'DOIT'),
    ('FAC-001', 'Felix', 'Reyes', 'felix.reyes@soit.edu', '$2y$12$cmlxhnj7GBHA.yrvdps7Ous/XKTtGOSx4a48Ojky8wy6dvfcqUg56', 'Faculty', 'School of Information Technology'),
    ('2024-0001', 'Ari', 'Cruz', 'ari.cruz@student.soit.edu', '$2y$12$Eh.e8OMM5nhW9T0o5EuD/e7Usbev9Pm14qcTQ9eZgReYMIpJiXXRK', 'Student', 'Information Technology'),
    ('2024-0002', 'Bea', 'Lim', 'bea.lim@student.soit.edu', '$2y$12$cJrrLWjxT6.C9QZZBgl10ulaNrstaxZsrXlVs7hKwM.4rT8pOPHxG', 'Student', 'Information Technology');

INSERT INTO laboratories
    (room_name, room_code, lab_type, capacity, floor, status)
VALUES
    ('Cisco Laboratory 301', 'CISCO-301', 'Cisco Laboratory', 30, '3rd Floor', 'Available'),
    ('Cisco Laboratory 302', 'CISCO-302', 'Cisco Laboratory', 24, '3rd Floor', 'Available'),
    ('Cisco Laboratory 303', 'CISCO-303', 'Cisco Laboratory', 36, '3rd Floor', 'Under Maintenance'),
    ('Computer Laboratory 201', 'LAB-201', 'Regular Computer Laboratory', 40, '2nd Floor', 'Available'),
    ('Computer Laboratory 202', 'LAB-202', 'Regular Computer Laboratory', 35, '2nd Floor', 'Available'),
    ('Computer Laboratory 203', 'LAB-203', 'Regular Computer Laboratory', 45, '2nd Floor', 'Available'),
    ('Computer Laboratory 204', 'LAB-204', 'Regular Computer Laboratory', 28, '2nd Floor', 'Available'),
    ('Computer Laboratory 205', 'LAB-205', 'Regular Computer Laboratory', 50, '2nd Floor', 'Available'),
    ('Computer Laboratory 206', 'LAB-206', 'Regular Computer Laboratory', 32, '2nd Floor', 'Available'),
    ('Computer Laboratory 207', 'LAB-207', 'Regular Computer Laboratory', 42, '2nd Floor', 'Available'),
    ('Computer Laboratory 208', 'LAB-208', 'Regular Computer Laboratory', 25, '2nd Floor', 'Available');

INSERT INTO reservations
    (user_id, room_id, approved_by, date, start_time, end_time, purpose, course_section, expected_attendees, status, created_at, approved_at)
VALUES
    (3, 1, 1, '2026-09-14', '08:00:00', '10:00:00', 'Cisco routing practice', 'BSIT-3A', 24, 'Approved', '2026-09-10 09:15:00', '2026-09-10 10:00:00'),
    (4, 4, 2, '2026-09-15', '10:00:00', '12:00:00', 'Database laboratory exercise', 'BSIT-2B', 35, 'Approved', '2026-09-11 13:20:00', '2026-09-11 14:05:00'),
    (2, 2, 1, '2026-09-16', '13:00:00', '15:00:00', 'Network security lecture', 'BSIT-4A', 20, 'Approved', '2026-09-12 08:30:00', '2026-09-12 09:10:00'),
    (3, 5, NULL, '2026-09-17', '08:00:00', '11:00:00', 'Web programming workshop', 'BSIT-3A', 30, 'Pending', '2026-09-15 11:45:00', NULL),
    (4, 6, NULL, '2026-09-18', '13:00:00', '16:00:00', 'Operating systems activity', 'BSIT-2B', 40, 'Pending', '2026-09-15 15:10:00', NULL),
    (2, 7, 1, '2026-09-19', '09:00:00', '12:00:00', 'Faculty consultation session', 'FAC-IT', 18, 'Approved', '2026-09-13 10:00:00', '2026-09-13 10:30:00'),
    (3, 8, NULL, '2026-09-20', '14:00:00', '16:00:00', 'Programming project meeting', 'BSIT-3A', 28, 'Pending', '2026-09-16 09:25:00', NULL);

INSERT INTO notifications (user_id, reservation_id, message, is_read, created_at)
VALUES
    (3, 1, 'Your reservation for CISCO-301 was approved.', 1, '2026-09-10 10:00:00'),
    (4, 2, 'Your reservation for LAB-201 was approved.', 0, '2026-09-11 14:05:00'),
    (2, 3, 'Your reservation for CISCO-302 was approved.', 0, '2026-09-12 09:10:00'),
    (3, 4, 'Your reservation for LAB-202 is pending approval.', 0, '2026-09-15 11:45:00'),
    (4, 5, 'Your reservation for LAB-203 is pending approval.', 0, '2026-09-15 15:10:00'),
    (2, 6, 'Your reservation for LAB-204 was approved.', 1, '2026-09-13 10:30:00'),
    (3, 7, 'Your reservation for LAB-205 is pending approval.', 0, '2026-09-16 09:25:00');
