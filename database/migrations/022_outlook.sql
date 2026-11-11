-- Outlook eklentisi: panel anahtarları, e-posta konuşma kimliği ve notu, gönderilince kaydedilecekler

-- Panel Outlook'un içinde (çerçevede) açıldığı için oturum çerezine güvenilmez; kişiye özel anahtar verilir.
-- Anahtarın kendisi saklanmaz, SHA-256 özeti tutulur.
CREATE TABLE addin_tokens (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id       INT UNSIGNED NOT NULL,
    token_hash    CHAR(64)     NOT NULL,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_used_at  DATETIME     NULL,
    UNIQUE KEY uq_token (token_hash),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Aynı yazışma zincirindeki e-postalar için proje önerisi; kaydederken eklenen kısa not
ALTER TABLE project_emails
    ADD COLUMN conversation_id VARCHAR(255) NULL AFTER internet_message_id,
    ADD COLUMN note            VARCHAR(500) NULL AFTER preview,
    ADD KEY idx_conversation (conversation_id(100));

-- Yazarken "gönderince kaydet": e-postaya x-flow-marker başlığı konur, saatlik iş Gönderilmiş Öğeler'de bulur.
-- Başlık bulunamazsa konu + alıcı + zamanla eşleştirilir.
CREATE TABLE outlook_pending (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id          INT UNSIGNED NOT NULL,
    marker           CHAR(32)     NOT NULL,
    project_id       INT UNSIGNED NOT NULL,
    subject          VARCHAR(500) NULL,
    recipients       VARCHAR(1000) NULL,
    note             VARCHAR(500) NULL,
    duration         VARCHAR(20)  NULL,        -- zaman kaydı istendiyse ("0,2")
    is_billable      TINYINT(1)   NOT NULL DEFAULT 1,
    created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    done_at          DATETIME     NULL,
    status           VARCHAR(20)  NOT NULL DEFAULT 'pending',   -- pending, saved, failed
    error            VARCHAR(255) NULL,
    UNIQUE KEY uq_marker (marker),
    KEY idx_open (status, created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
