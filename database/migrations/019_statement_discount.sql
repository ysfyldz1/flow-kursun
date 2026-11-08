-- Dökümde tek toplam iskonto. subtotal iskonto öncesidir; KDV ve stopaj iskonto sonrası tutardan hesaplanır.
ALTER TABLE statements
    ADD COLUMN discount_type   VARCHAR(10)   NULL,                   -- percent, amount
    ADD COLUMN discount_value  DECIMAL(14,2) NULL,                   -- yüzde ya da tutar, girildiği gibi
    ADD COLUMN discount_amount DECIMAL(14,2) NOT NULL DEFAULT 0;
