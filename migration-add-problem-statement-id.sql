-- Run the statement for the database your deployment uses.
-- MySQL
ALTER TABLE registrations
  ADD COLUMN problem_statement_id VARCHAR(100) NOT NULL DEFAULT '' AFTER leader_name;

-- PostgreSQL / Neon
-- ALTER TABLE registrations
--   ADD COLUMN problem_statement_id VARCHAR(100) NOT NULL DEFAULT '';
