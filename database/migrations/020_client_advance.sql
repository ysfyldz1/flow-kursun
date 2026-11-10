-- Müvekkil masraf avansı. Avans müvekkil bazında, para birimi başına ayrı bakiye.
-- Bakiye = client_advances toplamı − avanstan düşülen masraflar (expenses.from_advance = 1).
-- Avanstan düşülen masraf döküme girmez.
ALTER TABLE clients ADD COLUMN uses_advance TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE expenses ADD COLUMN from_advance TINYINT(1) NOT NULL DEFAULT 0 AFTER is_billable;

-- type: deposit (+, avans alındı), refund (−, iade), adjust (±, düzeltme)
CREATE TABLE client_advances (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id   INT UNSIGNED NOT NULL,
    currency    CHAR(3)       NOT NULL,
    type        VARCHAR(10)   NOT NULL,
    amount      DECIMAL(14,2) NOT NULL,      -- işaretli
    entry_date  DATE          NOT NULL,
    method      VARCHAR(30)   NULL,          -- transfer, cash, card, other
    reference   VARCHAR(150)  NULL,
    notes       TEXT NULL,
    created_by  INT UNSIGNED NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_client (client_id, currency, entry_date),
    FOREIGN KEY (client_id) REFERENCES clients(id),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
