-- Phase 1: Last Contacted Column
-- Migration to add last contacted tracking to inquiries

-- Add last_contacted column to inquiries table
ALTER TABLE inquiries 
ADD COLUMN last_contacted DATETIME NULL AFTER assigned_to,
ADD COLUMN contact_count INT DEFAULT 0 AFTER last_contacted;

-- Create index for efficient querying
CREATE INDEX idx_inquiries_last_contacted ON inquiries(last_contacted);

-- Update existing records with a reasonable default (created_at)
UPDATE inquiries SET last_contacted = created_at WHERE last_contacted IS NULL;
