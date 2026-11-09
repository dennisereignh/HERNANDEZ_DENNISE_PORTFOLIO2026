-- Portfolio database: Dennise Hernandez
-- Import via phpMyAdmin (Import tab) or: mysql -u root < portfolio_db.sql
CREATE DATABASE IF NOT EXISTS hernandez_portfolio_db
CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE hernandez_portfolio_db;

CREATE TABLE IF NOT EXISTS contact_messages (
  id          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  name        VARCHAR(100)  NOT NULL,
  email       VARCHAR(150)  NOT NULL,
  message     TEXT          NOT NULL,
  ip_address  VARCHAR(45)   DEFAULT NULL,   -- used only to limit spam
  is_read     TINYINT(1)    NOT NULL DEFAULT 0,
  created_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_created (created_at),
  KEY idx_ip_created (ip_address, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Handy queries
-- All messages, newest first:   SELECT * FROM contact_messages ORDER BY created_at DESC;
-- Unread only:                  SELECT * FROM contact_messages WHERE is_read = 0;
-- Mark one as read:             UPDATE contact_messages SET is_read = 1 WHERE id = 1;
