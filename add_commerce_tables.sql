-- ============================================================
-- Adds the distributor / ordering tables to the existing maayi_cms.
-- Import via phpMyAdmin (select maayi_cms -> Import) or run once.
-- Uses IF NOT EXISTS so it is safe to re-run and never drops data.
-- ============================================================
USE maayi_cms;

CREATE TABLE IF NOT EXISTS distributors (
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

CREATE TABLE IF NOT EXISTS distributor_pricing (
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

CREATE TABLE IF NOT EXISTS orders (
    order_id       INT AUTO_INCREMENT PRIMARY KEY,
    distributor_id INT           NOT NULL,
    order_source   VARCHAR(20)   NOT NULL,
    payment_status VARCHAR(20)   NOT NULL,
    order_status   VARCHAR(20)   NOT NULL DEFAULT 'pending',
    total_amount   DECIMAL(12,2) NOT NULL,
    created_by     INT           NULL,
    created_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_orders_distributor
        FOREIGN KEY (distributor_id) REFERENCES distributors(distributor_id) ON DELETE RESTRICT,
    CONSTRAINT fk_orders_creator
        FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS order_items (
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

CREATE TABLE IF NOT EXISTS payments (
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
