-- Kurşun: proje aşamaları. Aşaması olmayan proje eskisi gibi çalışır.
-- Bir aşama: ad, kapsam (satır başına bir madde), tavan bedel, süre (başlangıçtan itibaren).
-- is_out_of_scope = 1 olan "Kapsam dışı" grubu, projeye ilk aşama eklenince kendiliğinden açılır; bedeli/süresi yoktur.
-- Opsiyonel aşama onaylanana kadar (approved_at NULL) zaman girişine kapalıdır.
CREATE TABLE project_phases (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id      INT UNSIGNED NOT NULL,
    position        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    name            VARCHAR(255) NOT NULL,
    scope           TEXT NULL,
    fee             DECIMAL(14,2) NULL,
    duration_value  SMALLINT UNSIGNED NULL,
    duration_unit   VARCHAR(10) NULL,              -- day, week, month
    start_date      DATE NULL,                     -- boşsa projenin açılış tarihi
    is_optional     TINYINT(1) NOT NULL DEFAULT 0,
    approved_at     DATETIME NULL,
    approved_by     INT UNSIGNED NULL,
    completed_at    DATE NULL,
    is_out_of_scope TINYINT(1) NOT NULL DEFAULT 0,
    created_by      INT UNSIGNED NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_project (project_id, position),
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Aşama bütçesinin bu oranı harcanınca uyarı
ALTER TABLE projects ADD COLUMN phase_warn_pct TINYINT UNSIGNED NOT NULL DEFAULT 80;

-- Zaman kaydı isteğe bağlı olarak bir aşamaya bağlanır; boşsa aşamasız
ALTER TABLE time_entries
    ADD COLUMN phase_id INT UNSIGNED NULL AFTER project_id,
    ADD KEY idx_phase (phase_id),
    ADD FOREIGN KEY (phase_id) REFERENCES project_phases(id) ON DELETE SET NULL;
