-- Add pin column to artists table
ALTER TABLE artists ADD COLUMN pin TINYINT(1) DEFAULT 0 AFTER image_url;

-- Add index for faster sorting
ALTER TABLE artists ADD INDEX idx_pin (pin);
