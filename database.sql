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
    password_hash VARCHAR(255) NOT NULL
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
