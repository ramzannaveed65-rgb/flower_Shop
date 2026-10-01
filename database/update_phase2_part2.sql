-- =====================================================
-- Phase 2 Part 2 update: login tokens for customers
-- Run ONCE in phpMyAdmin:
--   click flower_shop on the left -> SQL tab -> paste this -> Go
-- =====================================================

ALTER TABLE customers
  ADD COLUMN api_token CHAR(64) NULL AFTER password_hash,
  ADD UNIQUE KEY unique_api_token (api_token);
