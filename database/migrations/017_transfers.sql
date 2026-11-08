-- Dosya Transferi: hesabı olmayan kişilere büyük dosya gönderme. Dosyalar kurumun M365'inde, belgelerden ayrı depoda durur.
-- drive_id transferin kendisinde tutulur: ayarlardan depo değişse de eski bağlantılar çalışır.
CREATE TABLE transfers (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    token               CHAR(32) NOT NULL,
    title               VARCHAR(255) NOT NULL,
    message             TEXT NULL,
    recipient_email     VARCHAR(1000) NULL,
    locale              VARCHAR(5) NOT NULL DEFAULT 'tr',
    password_hash       VARCHAR(255) NULL,
    expires_at          DATETIME NULL,                              -- NULL: süresiz
    notify_on_download  TINYINT(1) NOT NULL DEFAULT 1,
    status              VARCHAR(10) NOT NULL DEFAULT 'uploading',   -- uploading, ready
    drive_id            VARCHAR(255) NOT NULL,
    folder_item_id      VARCHAR(255) NOT NULL,
    download_count      INT UNSIGNED NOT NULL DEFAULT 0,
    first_downloaded_at DATETIME NULL,
    files_deleted_at    DATETIME NULL,                              -- süre dolduktan sonra dosyalar silindi; kayıt geçmiş için kalır
    created_by          INT UNSIGNED NULL,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_token (token),
    KEY idx_owner (created_by),
    KEY idx_cleanup (status, files_deleted_at, expires_at),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE transfer_files (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    transfer_id    INT UNSIGNED NOT NULL,
    relative_path  VARCHAR(1000) NOT NULL,
    size_bytes     BIGINT UNSIGNED NOT NULL,
    item_id        VARCHAR(255) NULL,                               -- yükleme doğrulanınca dolar
    download_count INT UNSIGNED NOT NULL DEFAULT 0,
    KEY idx_transfer (transfer_id),
    FOREIGN KEY (transfer_id) REFERENCES transfers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- file_id NULL: "Tümünü indir" (ZIP)
CREATE TABLE transfer_downloads (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    transfer_id INT UNSIGNED NOT NULL,
    file_id     INT UNSIGNED NULL,
    ip_address  VARCHAR(45) NULL,
    user_agent  VARCHAR(255) NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_transfer (transfer_id),
    FOREIGN KEY (transfer_id) REFERENCES transfers(id) ON DELETE CASCADE,
    FOREIGN KEY (file_id) REFERENCES transfer_files(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- transfers.own: gönderir, kendi transferlerini görür; transfers.all: tümünü görür ve yönetir
INSERT INTO role_permissions (role_id, permission)
SELECT id, 'transfers.own' FROM roles WHERE slug IN ('lawyer', 'accounting');
