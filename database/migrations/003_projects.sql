-- Ücretleri görme yetkisi: muhasebe görür, avukat görmez (yönetici zaten '*')
INSERT INTO role_permissions (role_id, permission)
SELECT id, 'rates.view' FROM roles WHERE slug = 'accounting';

CREATE TABLE projects (
    id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id            INT UNSIGNED NOT NULL,
    number               VARCHAR(30)  NOT NULL UNIQUE,     -- M-0002/001
    name                 VARCHAR(255) NOT NULL,
    type                 VARCHAR(30)  NOT NULL DEFAULT 'advisory',   -- litigation, advisory, transaction, other
    status               VARCHAR(20)  NOT NULL DEFAULT 'open',       -- open, on_hold, closed
    responsible_user_id  INT UNSIGNED NULL,
    billing_type         VARCHAR(20)  NOT NULL DEFAULT 'hourly',     -- hourly, fixed, retainer, non_billable
    currency             CHAR(3)      NOT NULL,
    hourly_rate          DECIMAL(12,2) NULL,     -- projeye özel saatlik ücret
    fixed_fee            DECIMAL(12,2) NULL,     -- sabit ücret tutarı
    retainer_amount      DECIMAL(12,2) NULL,     -- aylık sabit ücret
    budget_hours         DECIMAL(8,1)  NULL,
    court                VARCHAR(255) NULL,      -- dava ise mahkeme
    case_number          VARCHAR(100) NULL,      -- esas no
    description          TEXT NULL,
    opened_at            DATE NOT NULL,
    closed_at            DATE NULL,
    created_by           INT UNSIGNED NULL,
    created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_client (client_id),
    KEY idx_status (status),
    FOREIGN KEY (client_id) REFERENCES clients(id),
    FOREIGN KEY (responsible_user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Proje ekibi; hourly_rate doluysa bu projede o avukata özel ücret
CREATE TABLE project_members (
    project_id   INT UNSIGNED NOT NULL,
    user_id      INT UNSIGNED NOT NULL,
    hourly_rate  DECIMAL(12,2) NULL,
    PRIMARY KEY (project_id, user_id),
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
