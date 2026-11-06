CREATE TABLE time_entries (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id       INT UNSIGNED NOT NULL,
    project_id    INT UNSIGNED NOT NULL,
    client_id     INT UNSIGNED NOT NULL,             -- rapor kolaylığı için projeden kopyalanır
    work_date     DATE NOT NULL,
    minutes       INT UNSIGNED NOT NULL,             -- 6 dakikaya yukarı yuvarlanmış süre
    raw_minutes   INT UNSIGNED NOT NULL,             -- girilen ham süre
    description   TEXT NOT NULL,
    is_billable   TINYINT(1) NOT NULL DEFAULT 1,
    -- Ücret giriş anında kopyalanır; sonradan ücret değişse de kayıt değişmez
    rate          DECIMAL(12,2) NULL,
    currency      CHAR(3) NOT NULL,
    amount        DECIMAL(12,2) NULL,
    rate_source   VARCHAR(30) NULL,
    statement_id  INT UNSIGNED NULL,                 -- dökümde kullanıldıysa kilitli
    created_by    INT UNSIGNED NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_user_date (user_id, work_date),
    KEY idx_project (project_id, work_date),
    KEY idx_client (client_id, work_date),
    KEY idx_statement (statement_id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (project_id) REFERENCES projects(id),
    FOREIGN KEY (client_id) REFERENCES clients(id),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Kullanıcı başına tek çalışan sayaç
CREATE TABLE timers (
    user_id      INT UNSIGNED PRIMARY KEY,
    project_id   INT UNSIGNED NOT NULL,
    description  TEXT NULL,
    started_at   DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
