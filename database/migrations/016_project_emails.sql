-- Projeye kaydedilen e-postalar. E-postanın kendisi (MIME, ekleriyle) projenin SharePoint klasöründeki
-- "Yazışmalar" alt klasörüne .eml olarak yüklenir; burada liste ve tekrar kaydı önlemek için özet tutulur.
CREATE TABLE project_emails (
    id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id           INT UNSIGNED NOT NULL,
    internet_message_id  VARCHAR(400) NOT NULL,          -- posta kutusundan bağımsız kimlik (aynı e-posta iki kez kaydedilmesin)
    subject              VARCHAR(500) NULL,
    from_name            VARCHAR(255) NULL,
    from_email           VARCHAR(255) NULL,
    to_text              VARCHAR(1000) NULL,
    received_at          DATETIME NULL,
    has_attachments      TINYINT(1) NOT NULL DEFAULT 0,
    preview              VARCHAR(500) NULL,
    drive_item_id        VARCHAR(100) NULL,               -- SharePoint'teki .eml dosyası
    file_name            VARCHAR(255) NULL,
    size                 INT UNSIGNED NULL,
    saved_by             INT UNSIGNED NULL,
    created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_project_message (project_id, internet_message_id(190)),
    KEY idx_project (project_id, received_at),
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (saved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
