-- Kullanıcının kendi şifresi: e-postayla sıfırlama ve şifre değişince diğer oturumların kapanması.
ALTER TABLE users
    ADD COLUMN session_epoch    INT UNSIGNED NOT NULL DEFAULT 0,    -- şifre değişince artar; eski oturumlar geçersiz kalır
    ADD COLUMN reset_token_hash CHAR(64) NULL,
    ADD COLUMN reset_expires_at DATETIME NULL,
    ADD UNIQUE KEY users_reset_token (reset_token_hash);
