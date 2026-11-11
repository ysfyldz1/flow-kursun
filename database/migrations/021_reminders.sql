-- Hatırlatmalar: son görülme (özet e-postası için) ve gönderilen hatırlatmaların kaydı (aynısı iki kez gitmesin)
ALTER TABLE users ADD COLUMN last_seen_at DATETIME NULL AFTER last_login_at;

UPDATE users SET last_seen_at = last_login_at;

-- kind: digest, deadline_tomorrow, deadline_today; ref_id: olay (özet için 0)
CREATE TABLE reminder_log (
    kind     VARCHAR(30)  NOT NULL,
    ref_id   INT UNSIGNED NOT NULL,
    user_id  INT UNSIGNED NOT NULL,
    sent_on  DATE         NOT NULL,
    PRIMARY KEY (kind, ref_id, user_id, sent_on),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
