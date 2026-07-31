-- Adds a cancellation note column to orders (required when cancelling).
USE maayi_cms;
ALTER TABLE orders ADD COLUMN cancel_note VARCHAR(255) NULL AFTER order_status;
