-- Müvekkil portalı kullanıcıları (ofis kullanıcılarından tamamen ayrı)
CREATE TABLE portal_users (
    id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id          INT UNSIGNED NOT NULL,
    contact_id         INT UNSIGNED NULL,
    name               VARCHAR(150) NOT NULL,
    email              VARCHAR(190) NOT NULL UNIQUE,
    password           VARCHAR(255) NULL,             -- davet kabul edilene kadar boş
    locale             VARCHAR(5)   NOT NULL DEFAULT 'tr',
    is_active          TINYINT(1)   NOT NULL DEFAULT 1,
    invite_token_hash  CHAR(64)     NULL,             -- bağlantıdaki belirtecin SHA-256 özeti
    invite_expires_at  DATETIME     NULL,
    last_login_at      DATETIME     NULL,
    invited_by         INT UNSIGNED NULL,
    created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_client (client_id),
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    FOREIGN KEY (invited_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Müvekkilin "ödedim" bildirimi; ofis onaylayınca ödeme kaydına dönüşür
CREATE TABLE payment_notices (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    statement_id    INT UNSIGNED NOT NULL,
    portal_user_id  INT UNSIGNED NULL,
    amount          DECIMAL(14,2) NOT NULL,
    paid_at         DATE NOT NULL,
    reference       VARCHAR(150) NULL,
    notes           TEXT NULL,
    receipt_file    VARCHAR(255) NULL,
    status          VARCHAR(20) NOT NULL DEFAULT 'pending',   -- pending, approved, rejected
    reject_reason   VARCHAR(255) NULL,
    payment_id      INT UNSIGNED NULL,
    reviewed_by     INT UNSIGNED NULL,
    reviewed_at     DATETIME NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_statement (statement_id),
    KEY idx_status (status),
    FOREIGN KEY (statement_id) REFERENCES statements(id) ON DELETE CASCADE,
    FOREIGN KEY (portal_user_id) REFERENCES portal_users(id) ON DELETE SET NULL,
    FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE SET NULL,
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO role_permissions (role_id, permission)
SELECT id, 'portal.manage' FROM roles WHERE slug = 'accounting';
