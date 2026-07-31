-- ============================================================
-- Maayi Industries CMS — database
-- Import this file in phpMyAdmin (Import tab), or run:
--   mysql -u root -p < database.sql
-- ============================================================

DROP DATABASE IF EXISTS maayi_cms;
CREATE DATABASE maayi_cms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE maayi_cms;

-- ---------- users: admin logins ----------
CREATE TABLE users (
    user_id       INT AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(50)  NOT NULL UNIQUE,
    email         VARCHAR(120) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    -- 'admin' can manage users; 'member' is a normal registered account.
    role          VARCHAR(20)  NOT NULL DEFAULT 'member',
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ---------- categories (requirement 2.4) ----------
-- One category has many pages: a 1-to-many association.
CREATE TABLE categories (
    category_id INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(80) NOT NULL UNIQUE
);

-- ---------- pages: the products ----------
CREATE TABLE pages (
    page_id     INT AUTO_INCREMENT PRIMARY KEY,
    title       VARCHAR(150)  NOT NULL,
    category_id INT           NULL,
    body        TEXT          NOT NULL,
    price       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
                              ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_pages_category
        FOREIGN KEY (category_id) REFERENCES categories(category_id)
        ON DELETE SET NULL
);

-- ---------- comments (requirement 2.9) ----------
CREATE TABLE comments (
    comment_id  INT AUTO_INCREMENT PRIMARY KEY,
    page_id     INT          NOT NULL,
    author_name VARCHAR(80)  NOT NULL,
    body        TEXT         NOT NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_comments_page
        FOREIGN KEY (page_id) REFERENCES pages(page_id)
        ON DELETE CASCADE
);

-- ============================================================
-- Seed data
-- The admin login is created by visiting setup.php once.
-- ============================================================

INSERT INTO categories (name) VALUES
('Purified Water'),
('Kombucha'),
('Wines'),
('Whisky'),
('Vodka'),
('Cane Spirit'),
('Flavoured Spirit');

-- 10 real products (requirement 2.1: at least 10 pages, real data)
INSERT INTO pages (title, category_id, body, price) VALUES
('Maayi Purified Water 500ml', 1,
 'Table water purified by reverse osmosis and UV treatment at the Kasama plant. Clean, neutral taste with balanced minerals. Supplied in sealed cases for households, restaurants and event suppliers.',
 5.00),

('Maayi Purified Water 5L', 1,
 'The five-litre bulk format of our purified table water, produced on the same reverse-osmosis line. Aimed at offices, catering and household refill customers, and sold by the case to distributors.',
 18.00),

('Farmhouse Kombucha', 2,
 'A lightly fermented tea brewed in small batches with a farmhouse culture. Crisp and mildly tart with natural effervescence and a hint of orchard fruit. Contains live cultures and is kept refrigerated.',
 12.50),

('Playboy Kombucha', 2,
 'A bolder, sweeter kombucha with a punchy tropical profile and gentle fizz. Fermented to a lower acidity than the Farmhouse line, which makes it an easier introduction for new drinkers.',
 13.00),

('Kissmiss Ginger Wine', 3,
 'A warming fortified ginger wine with a spicy, peppery finish, made from a fermented grape base infused with fresh root ginger. Popular over the festive season, served neat, over ice, or in cocktails.',
 45.00),

('Black Diamond Whisky', 4,
 'A smooth blended whisky matured in oak, showing notes of caramel, vanilla and light smoke. Our flagship spirit, positioned as an affordable everyday blend for the regional market.',
 180.00),

('Trigger Whisky', 4,
 'A robust, full-bodied blended whisky with a sharper, grainier character than Black Diamond. Aimed at drinkers who prefer a stronger, more assertive finish at a competitive price point.',
 150.00),

('Challenger Vodka', 5,
 'A triple-distilled grain vodka, filtered for a clean, crisp profile and a neutral nose. Mixes well in cocktails and long drinks, and is bottled for both bars and retail distributors.',
 120.00),

('Playboy Cane Spirit', 6,
 'A clear cane spirit distilled from sugarcane molasses, light and slightly sweet with a smooth finish. A versatile base for mixed drinks and a strong seller in the value segment.',
 95.00),

('Dot Banana Spirit', 7,
 'A flavoured spirit carrying a soft, ripe banana aroma and a sweet, mellow taste. Enjoyed chilled as a shot or mixed into fruity cocktails, and one of our most distinctive lines.',
 90.00);

-- A few real comments so the public page is not empty (requirement 2.9)
INSERT INTO comments (page_id, author_name, body) VALUES
(1, 'Chanda Mwansa',  'We order this by the case for our lodge in Kasama. Seal quality has been consistent all year.'),
(3, 'Natasha Zulu',   'The Farmhouse has a much cleaner finish than most kombucha I have tried locally. Please keep it in stock.'),
(6, 'Joseph Banda',   'Black Diamond is our best-selling blend. I would like to see a one-litre format for the trade.'),
(8, 'Lillian Mwape',  'Mixes cleanly and does not overpower the tonic. Good value for a house vodka.');

-- ============================================================
-- Distributor / ordering tables
-- (Added for the distributor application + trade-pricing feature.)
-- ============================================================

CREATE TABLE distributors (
    distributor_id  INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT          NOT NULL UNIQUE,
    business_name   VARCHAR(120) NOT NULL,
    contact_person  VARCHAR(80)  NULL,
    phone           VARCHAR(30)  NULL,
    address         TEXT         NULL,
    approval_status VARCHAR(20)  NOT NULL DEFAULT 'pending',
    approved_by     INT          NULL,
    approved_at     DATETIME     NULL,
    CONSTRAINT fk_distributors_user
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    CONSTRAINT fk_distributors_approver
        FOREIGN KEY (approved_by) REFERENCES users(user_id) ON DELETE SET NULL
);

CREATE TABLE distributor_pricing (
    pricing_id         INT AUTO_INCREMENT PRIMARY KEY,
    distributor_id     INT           NOT NULL,
    page_id            INT           NOT NULL,
    adjustment_percent DECIMAL(5,2)  NULL,
    fixed_price        DECIMAL(10,2) NULL,
    note               VARCHAR(255)  NOT NULL,
    created_by         INT           NULL,
    UNIQUE KEY uq_distributor_page (distributor_id, page_id),
    CONSTRAINT fk_pricing_distributor
        FOREIGN KEY (distributor_id) REFERENCES distributors(distributor_id) ON DELETE CASCADE,
    CONSTRAINT fk_pricing_page
        FOREIGN KEY (page_id) REFERENCES pages(page_id) ON DELETE CASCADE,
    CONSTRAINT fk_pricing_creator
        FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL
);

CREATE TABLE orders (
    order_id       INT AUTO_INCREMENT PRIMARY KEY,
    distributor_id INT           NOT NULL,
    order_source   VARCHAR(20)   NOT NULL,
    payment_status VARCHAR(20)   NOT NULL,
    order_status   VARCHAR(20)   NOT NULL DEFAULT 'pending',
    cancel_note    VARCHAR(255)  NULL,
    total_amount   DECIMAL(12,2) NOT NULL,
    created_by     INT           NULL,
    created_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_orders_distributor
        FOREIGN KEY (distributor_id) REFERENCES distributors(distributor_id) ON DELETE RESTRICT,
    CONSTRAINT fk_orders_creator
        FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL
);

CREATE TABLE order_items (
    order_item_id INT           AUTO_INCREMENT PRIMARY KEY,
    order_id      INT           NOT NULL,
    page_id       INT           NOT NULL,
    quantity      INT           NOT NULL,
    unit_price    DECIMAL(10,2) NOT NULL,
    line_note     VARCHAR(255)  NULL,
    CONSTRAINT fk_items_order
        FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE,
    CONSTRAINT fk_items_page
        FOREIGN KEY (page_id) REFERENCES pages(page_id) ON DELETE RESTRICT,
    CONSTRAINT chk_quantity CHECK (quantity >= 1)
);

CREATE TABLE payments (
    payment_id     INT           AUTO_INCREMENT PRIMARY KEY,
    distributor_id INT           NOT NULL,
    amount         DECIMAL(12,2) NOT NULL,
    payment_date   DATE          NOT NULL,
    recorded_by    INT           NULL,
    note           VARCHAR(255)  NULL,
    CONSTRAINT fk_payments_distributor
        FOREIGN KEY (distributor_id) REFERENCES distributors(distributor_id) ON DELETE RESTRICT,
    CONSTRAINT fk_payments_recorder
        FOREIGN KEY (recorded_by) REFERENCES users(user_id) ON DELETE SET NULL,
    CONSTRAINT chk_amount CHECK (amount > 0)
);

-- ============================================================
-- Roles (data-driven, so admins can add staff roles like accountant)
-- ============================================================
CREATE TABLE roles (
    role_id   INT AUTO_INCREMENT PRIMARY KEY,
    name      VARCHAR(40)  NOT NULL UNIQUE,
    label     VARCHAR(60)  NOT NULL,
    is_staff  TINYINT(1)   NOT NULL DEFAULT 1,
    is_system TINYINT(1)   NOT NULL DEFAULT 0
);

INSERT INTO roles (name, label, is_staff, is_system) VALUES
    ('admin',       'Administrator', 1, 1),
    ('accountant',  'Accountant',    1, 0),
    ('member',      'Member',        0, 1),
    ('distributor', 'Distributor',   0, 1);
