-- Kurşun: varsayılan iskonto oranı. Dökümde öncelik projede, yoksa müvekkilde; dökümde elle değiştirilebilir.
ALTER TABLE clients  ADD COLUMN discount_pct DECIMAL(5,2) NULL AFTER hourly_rate;
ALTER TABLE projects ADD COLUMN discount_pct DECIMAL(5,2) NULL AFTER retainer_amount;
