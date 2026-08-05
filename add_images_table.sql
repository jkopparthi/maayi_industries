-- Run this ONCE on your existing database (phpMyAdmin > SQL tab).
-- Adds the images table without touching any existing data.
USE maayi_cms;

CREATE TABLE IF NOT EXISTS images (
    image_id      INT          AUTO_INCREMENT PRIMARY KEY,
    page_id       INT          NOT NULL,
    filename      VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NULL,
    uploaded_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_images_page
        FOREIGN KEY (page_id) REFERENCES pages(page_id) ON DELETE CASCADE
);
