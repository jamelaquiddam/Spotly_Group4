USE spotly;

ALTER TABLE reservations
    MODIFY COLUMN status ENUM('Pending', 'Approved', 'Rejected', 'Cancelled', 'No-Show') NOT NULL DEFAULT 'Pending',
    ADD COLUMN IF NOT EXISTS cancelled_at DATETIME NULL AFTER approved_at,
    ADD COLUMN IF NOT EXISTS cancel_reason VARCHAR(255) NULL AFTER cancelled_at,
    ADD COLUMN IF NOT EXISTS checked_in_at DATETIME NULL AFTER cancel_reason,
    ADD COLUMN IF NOT EXISTS released_at DATETIME NULL AFTER checked_in_at;

-- Optional examples for manual testing. Uncomment and replace user/room IDs as needed.
-- INSERT INTO reservations (user_id, room_id, date, start_time, end_time, purpose, course_section, expected_attendees, status)
-- VALUES (3, 1, CURDATE(), TIME_FORMAT(DATE_SUB(NOW(), INTERVAL 25 MINUTE), '%H:%i:00'), TIME_FORMAT(DATE_ADD(NOW(), INTERVAL 90 MINUTE), '%H:%i:00'), 'No-show test', 'TEST-NS', 1, 'Approved');
-- INSERT INTO reservations (user_id, room_id, date, start_time, end_time, purpose, course_section, expected_attendees, status)
-- VALUES (3, 2, CURDATE(), TIME_FORMAT(DATE_ADD(NOW(), INTERVAL 2 HOUR), '%H:%i:00'), TIME_FORMAT(DATE_ADD(NOW(), INTERVAL 3 HOUR), '%H:%i:00'), 'Cancellation test', 'TEST-CAN', 1, 'Approved');
