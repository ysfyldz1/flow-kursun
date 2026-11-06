-- Takvim: duruşma, kesin süre, toplantı. Kaynak Flow'dur; atanan kişilerin Outlook takvimine tek yönlü yansıtılır.
CREATE TABLE events (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    type         VARCHAR(20)  NOT NULL,             -- hearing, deadline, meeting
    title        VARCHAR(255) NOT NULL,
    project_id   INT UNSIGNED NULL,
    starts_at    DATETIME     NOT NULL,             -- süre ise son günün tarihi (saat 00:00, all_day = 1)
    ends_at      DATETIME     NULL,
    all_day      TINYINT(1)   NOT NULL DEFAULT 0,
    location     VARCHAR(255) NULL,                 -- salon, adres ya da Teams bağlantısı
    notes        TEXT NULL,
    done_at      DATETIME     NULL,                 -- süre tamamlandı
    done_by      INT UNSIGNED NULL,
    created_by   INT UNSIGNED NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_starts (starts_at),
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (done_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Katılımcılar; ms_event_id: o kişinin Outlook takvimindeki kopyası
CREATE TABLE event_attendees (
    event_id     INT UNSIGNED NOT NULL,
    user_id      INT UNSIGNED NOT NULL,
    ms_event_id  VARCHAR(255) NULL,
    sync_error   VARCHAR(255) NULL,
    PRIMARY KEY (event_id, user_id),
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Takvimi görmek herkese, kayıt eklemek avukata (yönetici zaten '*')
INSERT INTO role_permissions (role_id, permission)
SELECT id, 'calendar.view' FROM roles WHERE slug IN ('lawyer', 'accounting')
UNION ALL
SELECT id, 'calendar.edit' FROM roles WHERE slug = 'lawyer';
