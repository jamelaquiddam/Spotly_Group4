USE spotly;

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS is_verified BOOLEAN NOT NULL DEFAULT 0 AFTER department,
    ADD COLUMN IF NOT EXISTS verification_token VARCHAR(64) NULL AFTER is_verified,
    ADD COLUMN IF NOT EXISTS token_expires_at DATETIME NULL AFTER verification_token,
    ADD COLUMN IF NOT EXISTS is_active BOOLEAN NOT NULL DEFAULT 1 AFTER token_expires_at;

CREATE TABLE IF NOT EXISTS login_attempts (
    attempt_id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(100) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    failed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_login_attempts_email_time (email, failed_at),
    INDEX idx_login_attempts_ip_time (ip_address, failed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

UPDATE users SET email = 'doit.admin@mapua.edu.ph', is_verified = 1, is_active = 1 WHERE email = 'dana.santos@soit.edu';
UPDATE users SET email = 'faculty1@mapua.edu.ph', is_verified = 1, is_active = 1 WHERE email = 'felix.reyes@soit.edu';
UPDATE users SET email = 'student1@mymail.mapua.edu.ph', is_verified = 1, is_active = 1 WHERE email = 'ari.cruz@student.soit.edu';
UPDATE users SET email = 'student2@mymail.mapua.edu.ph', is_verified = 1, is_active = 1 WHERE email = 'bea.lim@student.soit.edu';

-- Review existing accounts before deactivating or correcting any legacy records.
SELECT user_id, email, role, is_verified, is_active
FROM users
WHERE (role = 'Student' AND LOWER(SUBSTRING_INDEX(email, '@', -1)) <> 'mymail.mapua.edu.ph')
   OR (role IN ('Faculty', 'DOIT Staff/Admin') AND LOWER(SUBSTRING_INDEX(email, '@', -1)) <> 'mapua.edu.ph');