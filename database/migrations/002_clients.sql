-- Sıralı numaralar (müvekkil kodu, ileride proje ve döküm no) için sayaç
CREATE TABLE counters (
    name   VARCHAR(50) PRIMARY KEY,
    value  INT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE clients (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code          VARCHAR(20)  NOT NULL UNIQUE,
    type          ENUM('person', 'company') NOT NULL DEFAULT 'company',
    name          VARCHAR(255) NOT NULL,
    tax_id        VARCHAR(20)  NULL,              -- gerçek kişide TCKN, tüzel kişide VKN
    tax_office    VARCHAR(100) NULL,
    email         VARCHAR(190) NULL,
    phone         VARCHAR(50)  NULL,
    address       TEXT NULL,
    city          VARCHAR(100) NULL,
    country       VARCHAR(100) NULL,
    currency      CHAR(3)      NOT NULL DEFAULT 'TRY',
    locale        VARCHAR(5)   NOT NULL DEFAULT 'tr',   -- döküm ve portal dili
    hourly_rate   DECIMAL(12,2) NULL,             -- müvekkilin genel saatlik ücreti (currency cinsinden)
    notes         TEXT NULL,
    is_active     TINYINT(1)   NOT NULL DEFAULT 1,
    created_by    INT UNSIGNED NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_name (name),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE client_contacts (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id   INT UNSIGNED NOT NULL,
    name        VARCHAR(150) NOT NULL,
    title       VARCHAR(100) NULL,
    email       VARCHAR(190) NULL,
    phone       VARCHAR(50)  NULL,
    is_primary  TINYINT(1)   NOT NULL DEFAULT 0,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bu müvekkilde avukata özel saatlik ücret (müvekkilin para birimi cinsinden)
CREATE TABLE client_user_rates (
    client_id    INT UNSIGNED NOT NULL,
    user_id      INT UNSIGNED NOT NULL,
    hourly_rate  DECIMAL(12,2) NOT NULL,
    PRIMARY KEY (client_id, user_id),
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
