-- ============================================================
-- Adds a data-driven `roles` table so admins can create/name staff
-- roles (accountant, etc.) instead of them being hardcoded.
-- Safe to run once on an existing database.
-- ============================================================
USE maayi_cms;

CREATE TABLE IF NOT EXISTS roles (
    role_id   INT AUTO_INCREMENT PRIMARY KEY,
    name      VARCHAR(40)  NOT NULL UNIQUE,   -- stored in users.role
    label     VARCHAR(60)  NOT NULL,          -- shown in the UI
    is_staff  TINYINT(1)   NOT NULL DEFAULT 1,-- 1 = company staff (admin area)
    is_system TINYINT(1)   NOT NULL DEFAULT 0 -- 1 = built-in, cannot be deleted
);

-- Built-in roles. 'admin' and 'accountant' are staff; 'member' and
-- 'distributor' are account types managed elsewhere, not staff.
INSERT INTO roles (name, label, is_staff, is_system) VALUES
    ('admin',       'Administrator', 1, 1),
    ('accountant',  'Accountant',    1, 0),
    ('member',      'Member',        0, 1),
    ('distributor', 'Distributor',   0, 1)
ON DUPLICATE KEY UPDATE label = VALUES(label);
