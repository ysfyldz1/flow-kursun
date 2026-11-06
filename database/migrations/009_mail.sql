-- Giden sistem e-postaları: ne gitti, hangi yolla, hata verdiyse neden
CREATE TABLE mail_log (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    driver      VARCHAR(10)  NOT NULL,              -- graph, smtp, log
    recipient   VARCHAR(190) NOT NULL,
    subject     VARCHAR(255) NOT NULL,
    status      VARCHAR(10)  NOT NULL,              -- sent, failed
    error       TEXT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
