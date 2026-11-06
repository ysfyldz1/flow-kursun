CREATE TABLE expenses (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id       INT UNSIGNED NOT NULL,             -- masrafı yapan
    project_id    INT UNSIGNED NOT NULL,
    client_id     INT UNSIGNED NOT NULL,
    expense_date  DATE NOT NULL,
    category      VARCHAR(30) NOT NULL,              -- court_fee, notary, expert, travel, courier, translation, other
    description   TEXT NOT NULL,
    amount        DECIMAL(12,2) NOT NULL,
    currency      CHAR(3) NOT NULL,                  -- projenin para birimi
    is_billable   TINYINT(1) NOT NULL DEFAULT 1,     -- müvekkile yansıtılacak mı
    receipt_file  VARCHAR(255) NULL,
    statement_id  INT UNSIGNED NULL,                 -- dökümde kullanıldıysa kilitli
    created_by    INT UNSIGNED NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_project (project_id, expense_date),
    KEY idx_client (client_id),
    KEY idx_user (user_id, expense_date),
    KEY idx_statement (statement_id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (project_id) REFERENCES projects(id),
    FOREIGN KEY (client_id) REFERENCES clients(id),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE statement_lines ADD COLUMN expense_id INT UNSIGNED NULL AFTER time_entry_id;

INSERT INTO role_permissions (role_id, permission)
SELECT id, 'expenses.own' FROM roles WHERE slug = 'lawyer'
UNION ALL
SELECT id, 'expenses.all' FROM roles WHERE slug = 'accounting';
