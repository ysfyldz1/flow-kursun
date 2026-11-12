-- Kurşun: aşamalı döküm.
-- Döküm kapsamı: müvekkilin tamamı (ikisi de boş), tek proje (project_id) ya da tek aşama (phase_id).
ALTER TABLE statements
    ADD COLUMN project_id INT UNSIGNED NULL AFTER client_id,
    ADD COLUMN phase_id   INT UNSIGNED NULL AFTER project_id,
    ADD FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL,
    ADD FOREIGN KEY (phase_id) REFERENCES project_phases(id) ON DELETE SET NULL;

-- Satırın aşaması. Yeni satır türleri: phase_fee (aşama bedeli ya da taksiti), phase_extra (ek zaman bedeli).
-- Grupta phase_fee satırı varsa o aşamanın zaman satırları bilgi amaçlıdır (is_info = 1).
ALTER TABLE statement_lines
    ADD COLUMN phase_id INT UNSIGNED NULL AFTER project_id,
    ADD KEY idx_phase (phase_id),
    ADD FOREIGN KEY (phase_id) REFERENCES project_phases(id) ON DELETE SET NULL;
