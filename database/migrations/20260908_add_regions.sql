-- Run once against the existing nokware_market database.
-- New registrations and listings must provide one of Ghana's 16 regions.
ALTER TABLE users ADD COLUMN region VARCHAR(40) NOT NULL DEFAULT 'Unspecified' AFTER town;
ALTER TABLE listings ADD COLUMN region VARCHAR(40) NOT NULL DEFAULT 'Unspecified' AFTER town;

CREATE INDEX idx_listings_region_status ON listings (region, status);
