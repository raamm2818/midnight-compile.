-- schema.sql
-- Run this once against your MySQL database before using register.php.
-- e.g.: mysql -u your_db_user -p < schema.sql

-- CREATE DATABASE IF NOT EXISTS fracture_point
--   CHARACTER SET utf8mb4
--   COLLATE utf8mb4_unicode_ci;

USE fracture_point;

CREATE TABLE IF NOT EXISTS registrations (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  team_name       VARCHAR(150)  NOT NULL,
  leader_name     VARCHAR(150)  NOT NULL,
  problem_statement_id VARCHAR(100) NOT NULL,
  created_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_team_name (team_name)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS team_members (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  registration_id   INT UNSIGNED NOT NULL,
  member_name       VARCHAR(150) NOT NULL,
  member_class      VARCHAR(100) NOT NULL,
  position          INT UNSIGNED NOT NULL DEFAULT 0,
  FOREIGN KEY (registration_id) REFERENCES registrations(id) ON DELETE CASCADE
) ENGINE=InnoDB;
