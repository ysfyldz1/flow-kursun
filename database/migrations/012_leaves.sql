-- İzin yönetimi. Gün sayımı iş günüyle (hafta sonu + resmî tatil hariç).
-- Yıllık izin hakkı işe giriş tarihinden kıdeme göre birikir (İş K. md. 53-54); leave_entitlement doluysa elle sabitlenmiş toplam haktır.
ALTER TABLE users
    ADD COLUMN hire_date DATE NULL AFTER rate_currency,
    ADD COLUMN leave_entitlement DECIMAL(5,1) NULL AFTER hire_date;

CREATE TABLE leave_requests (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id        INT UNSIGNED NOT NULL,
    type           VARCHAR(20)  NOT NULL,             -- annual, excuse, sick, unpaid
    start_date     DATE NOT NULL,
    end_date       DATE NOT NULL,
    half_start     TINYINT(1) NOT NULL DEFAULT 0,     -- ilk gün öğleden sonra başlar
    half_end       TINYINT(1) NOT NULL DEFAULT 0,     -- son gün öğlene kadar
    days           DECIMAL(5,1) NOT NULL,             -- iş günü olarak, talep anında hesaplanır
    reason         TEXT NULL,
    report_file    VARCHAR(255) NULL,                 -- hastalık raporu
    signed_file    VARCHAR(255) NULL,                 -- ıslak imzalı form taraması
    signed_at      DATETIME NULL,
    status         VARCHAR(20) NOT NULL DEFAULT 'pending',   -- pending, approved, rejected, cancelled
    reviewed_by    INT UNSIGNED NULL,
    reviewed_at    DATETIME NULL,
    review_note    TEXT NULL,                         -- red/iptal gerekçesi
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_user (user_id, start_date),
    KEY idx_range (status, start_date, end_date),
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Kişiye özel onaycılar; biri onaylaması yeterli. Kişiye atanmamışsa leaves.approve yetkisi olanlar onaylar.
CREATE TABLE leave_approvers (
    user_id      INT UNSIGNED NOT NULL,
    approver_id  INT UNSIGNED NOT NULL,
    PRIMARY KEY (user_id, approver_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (approver_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Resmî tatiller; arife gibi yarım günler half_day = 1 (öğleden sonra tatil)
CREATE TABLE holidays (
    date      DATE PRIMARY KEY,
    name      VARCHAR(150) NOT NULL,
    half_day  TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO role_permissions (role_id, permission)
SELECT id, 'leaves.own' FROM roles WHERE slug IN ('lawyer', 'accounting');
