-- Run this in the Supabase SQL Editor once, before using the deployed app.
CREATE TABLE IF NOT EXISTS registrations (
  id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  team_name VARCHAR(150) NOT NULL UNIQUE,
  leader_name VARCHAR(150) NOT NULL,
  problem_statement_id VARCHAR(100) NOT NULL,
  created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Safe to run on an older database created before problem_statement_id existed.
ALTER TABLE registrations
  ADD COLUMN IF NOT EXISTS problem_statement_id VARCHAR(100) NOT NULL DEFAULT '';

CREATE TABLE IF NOT EXISTS team_members (
  id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  registration_id BIGINT NOT NULL REFERENCES registrations(id) ON DELETE CASCADE,
  member_name VARCHAR(150) NOT NULL,
  member_class VARCHAR(100) NOT NULL,
  position INTEGER NOT NULL DEFAULT 0
);
