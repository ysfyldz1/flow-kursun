CREATE TABLE statements (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    number              VARCHAR(20)  NULL UNIQUE,          -- HD-2026-0001; taslakta boş, kesinleşince verilir
    client_id           INT UNSIGNED NOT NULL,
    currency            CHAR(3)      NOT NULL,
    locale              VARCHAR(5)   NOT NULL DEFAULT 'tr',
    status              VARCHAR(20)  NOT NULL DEFAULT 'draft',   -- draft, issued, cancelled
    period_start        DATE NULL,
    period_end          DATE NOT NULL,
    issue_date          DATE NULL,
    due_date            DATE NULL,
    subtotal            DECIMAL(14,2) NOT NULL DEFAULT 0,
    vat_rate            DECIMAL(5,2)  NOT NULL DEFAULT 0,
    vat_amount          DECIMAL(14,2) NOT NULL DEFAULT 0,
    withholding_rate    DECIMAL(5,2)  NOT NULL DEFAULT 0,     -- stopaj
    withholding_amount  DECIMAL(14,2) NOT NULL DEFAULT 0,
    total               DECIMAL(14,2) NOT NULL DEFAULT 0,     -- ara toplam + KDV − stopaj
    paid_amount         DECIMAL(14,2) NOT NULL DEFAULT 0,
    show_bank           TINYINT(1)    NOT NULL DEFAULT 0,
    notes               TEXT NULL,                            -- müvekkile görünen not
    -- Resmi fatura (muhasebe başka yerde keser, buraya yükler)
    invoice_number      VARCHAR(50)  NULL,
    invoice_file        VARCHAR(255) NULL,
    invoice_uploaded_at DATETIME NULL,
    created_by          INT UNSIGNED NULL,
    issued_at           DATETIME NULL,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_client (client_id),
    KEY idx_status (status),
    FOREIGN KEY (client_id) REFERENCES clients(id),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Döküm satırları. type: time, fixed, retainer, expense, other.
-- is_info = 1: sabit ücretli projede bilgi amaçlı listelenen zaman kaydı (tutara katılmaz)
CREATE TABLE statement_lines (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    statement_id   INT UNSIGNED NOT NULL,
    type           VARCHAR(20)  NOT NULL,
    project_id     INT UNSIGNED NULL,
    time_entry_id  INT UNSIGNED NULL,
    work_date      DATE NULL,
    user_name      VARCHAR(150) NULL,
    description    TEXT NOT NULL,
    hours          DECIMAL(8,2) NULL,
    rate           DECIMAL(12,2) NULL,
    amount         DECIMAL(14,2) NULL,
    is_info        TINYINT(1) NOT NULL DEFAULT 0,
    sort           INT NOT NULL DEFAULT 0,
    KEY idx_statement (statement_id),
    FOREIGN KEY (statement_id) REFERENCES statements(id) ON DELETE CASCADE,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payments (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    statement_id  INT UNSIGNED NOT NULL,
    amount        DECIMAL(14,2) NOT NULL,
    paid_at       DATE NOT NULL,
    method        VARCHAR(30) NULL,          -- transfer, cash, card, other
    reference     VARCHAR(150) NULL,
    notes         TEXT NULL,
    source        VARCHAR(20) NOT NULL DEFAULT 'office',   -- office | portal (müvekkil bildirimi onaylandı)
    created_by    INT UNSIGNED NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_statement (statement_id),
    FOREIGN KEY (statement_id) REFERENCES statements(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO settings (`key`, `value`) VALUES
    ('billing.vat_rate', '20'),
    ('billing.withholding_rate', '20'),
    ('billing.due_days', '15'),
    ('billing.bank_accounts', NULL),
    ('org.address', NULL),
    ('org.tax_info', NULL);
