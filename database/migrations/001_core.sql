CREATE TABLE roles (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug        VARCHAR(50)  NOT NULL UNIQUE,
    name_tr     VARCHAR(100) NOT NULL,
    name_en     VARCHAR(100) NOT NULL,
    is_system   TINYINT(1)   NOT NULL DEFAULT 0,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE role_permissions (
    role_id     INT UNSIGNED NOT NULL,
    permission  VARCHAR(100) NOT NULL,
    PRIMARY KEY (role_id, permission),
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_id         INT UNSIGNED NOT NULL,
    name            VARCHAR(150) NOT NULL,
    email           VARCHAR(190) NOT NULL UNIQUE,
    password        VARCHAR(255) NULL,              -- yalnızca Microsoft ile giren kullanıcıda boş olabilir
    title           VARCHAR(100) NULL,
    locale          VARCHAR(5)   NOT NULL DEFAULT 'tr',
    hourly_rate     DECIMAL(12,2) NULL,
    rate_currency   CHAR(3)      NULL,
    is_active       TINYINT(1)   NOT NULL DEFAULT 1,
    -- M365 entegrasyonu için ayrılan alanlar
    ms_object_id    VARCHAR(64)  NULL UNIQUE,
    ms_upn          VARCHAR(190) NULL,
    last_login_at   DATETIME     NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Kurum ayarları (logo, renk, açık modüller, varsayılan dil...)
CREATE TABLE settings (
    `key`       VARCHAR(100) PRIMARY KEY,
    `value`     TEXT NULL,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dış servis bağlantıları: M365 (kurum ve kullanıcı bazında), ileride diğerleri
CREATE TABLE integration_tokens (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    provider        VARCHAR(30)  NOT NULL,          -- 'microsoft'
    user_id         INT UNSIGNED NULL,              -- NULL ise kurum geneli
    access_token    TEXT NULL,
    refresh_token   TEXT NULL,
    expires_at      DATETIME NULL,
    scopes          TEXT NULL,
    meta            JSON NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_provider_user (provider, user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Denetim kaydı: kim, neyi, hangi kanaldan (web / portal / ai / api)
CREATE TABLE audit_log (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id       INT UNSIGNED NULL,
    channel       VARCHAR(20)  NOT NULL DEFAULT 'web',
    action        VARCHAR(100) NOT NULL,
    subject_type  VARCHAR(50)  NULL,
    subject_id    INT UNSIGNED NULL,
    changes       JSON NULL,
    ip            VARCHAR(45)  NULL,
    created_at    DATETIME     NOT NULL,
    KEY idx_subject (subject_type, subject_id),
    KEY idx_user_date (user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO roles (slug, name_tr, name_en, is_system) VALUES
    ('admin',      'Yönetici',         'Administrator', 1),
    ('lawyer',     'Avukat / Çalışan', 'Lawyer / Staff', 1),
    ('accounting', 'Muhasebe',         'Accounting',    1);

INSERT INTO role_permissions (role_id, permission) VALUES
    (1, '*'),
    (2, 'clients.view'), (2, 'clients.edit'),
    (2, 'projects.view'), (2, 'projects.edit'),
    (2, 'time.own'),
    (3, 'clients.view'), (3, 'projects.view'),
    (3, 'statements.manage'), (3, 'payments.manage'), (3, 'reports.view');

INSERT INTO settings (`key`, `value`) VALUES
    ('org.name', 'Flow'),
    ('org.locale', 'tr'),
    ('org.primary_color', '#1e3a8a');
