-- Görevler: basit yapılacaklar listesi. Projeye bağlanması isteğe bağlı (bağlı değilse ofis içi iş).
-- Durum yalnız açık / tamamlandı (done_at). Son tarih ve atananlar ileride genel hatırlatma yapısında da kullanılacak.
CREATE TABLE tasks (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title        VARCHAR(255) NOT NULL,
    description  TEXT NULL,
    project_id   INT UNSIGNED NULL,
    due_date     DATE NULL,
    priority     VARCHAR(10) NOT NULL DEFAULT 'normal',   -- normal, urgent
    done_at      DATETIME NULL,
    done_by      INT UNSIGNED NULL,
    created_by   INT UNSIGNED NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_open (done_at, due_date),
    KEY idx_project (project_id),
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (done_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE task_assignees (
    task_id  INT UNSIGNED NOT NULL,
    user_id  INT UNSIGNED NOT NULL,
    PRIMARY KEY (task_id, user_id),
    KEY idx_user (user_id),
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- tasks.own: görev verir, kendisine atananları ve verdiklerini görür; tasks.all: tüm görevleri yönetir
INSERT INTO role_permissions (role_id, permission)
SELECT id, 'tasks.own' FROM roles WHERE slug IN ('lawyer', 'accounting');
