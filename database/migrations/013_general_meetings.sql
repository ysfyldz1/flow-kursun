-- Genel kurul bilgi formu: pay sahipleri tek ortak linkten (oturumsuz) katılım şeklini bildirir;
-- vekaleten katılımda forma özel Word şablonundan doldurulmuş vekaletname üretilir.
CREATE TABLE general_meetings (
    id                     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id              INT UNSIGNED NOT NULL,           -- genel kurulu yapılan şirket (müvekkil)
    project_id             INT UNSIGNED NULL,
    token                  CHAR(32) NOT NULL UNIQUE,        -- herkese açık form linki
    meeting_at             DATETIME NOT NULL,               -- form bu saatte kendiliğinden kapanır
    location               VARCHAR(255) NULL,
    agenda                 TEXT NOT NULL,
    proxy_doc_file         VARCHAR(255) NULL,               -- .docx şablon (doldurulur) ya da .pdf örnek
    proxy_doc_name         VARCHAR(255) NULL,
    default_proxy_name     VARCHAR(255) NULL,               -- önerilen vekil
    default_proxy_tc       CHAR(11) NULL,
    default_proxy_address  VARCHAR(500) NULL,
    is_open                TINYINT(1) NOT NULL DEFAULT 1,
    event_id               INT UNSIGNED NULL,               -- takvimdeki toplantı kaydı
    created_by             INT UNSIGNED NULL,
    created_at             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_client (client_id, meeting_at),
    FOREIGN KEY (client_id) REFERENCES clients(id),
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- id_no 11 haneyse gerçek kişi (TC), 10 haneyse tüzel kişi (VKN). Grup grup pay: "95874, 1110" + "A, B"
CREATE TABLE meeting_shareholders (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    meeting_id   INT UNSIGNED NOT NULL,
    full_name    VARCHAR(255) NOT NULL,
    id_no        VARCHAR(11) NULL,
    share_count  VARCHAR(255) NULL,
    share_group  VARCHAR(255) NULL,
    address      VARCHAR(500) NULL,
    sort_order   INT NOT NULL DEFAULT 0,
    KEY idx_meeting (meeting_id),
    FOREIGN KEY (meeting_id) REFERENCES general_meetings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pay sahibi başına tek yanıt; ofis sıfırlarsa yeniden doldurulabilir
CREATE TABLE meeting_responses (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    meeting_id        INT UNSIGNED NOT NULL,
    shareholder_id    INT UNSIGNED NOT NULL UNIQUE,
    attendance        VARCHAR(20) NOT NULL,              -- in_person (asaleten), proxy (vekaleten), representative (temsilen)
    proxy_name        VARCHAR(255) NULL,
    proxy_tc          CHAR(11) NULL,
    proxy_address     VARCHAR(500) NULL,
    proxy_is_default  TINYINT(1) NOT NULL DEFAULT 0,
    doc_token         CHAR(32) NULL UNIQUE,              -- doldurulmuş vekaletnameyi oturumsuz indirme
    ip_address        VARCHAR(45) NULL,
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_meeting (meeting_id),
    FOREIGN KEY (meeting_id) REFERENCES general_meetings(id) ON DELETE CASCADE,
    FOREIGN KEY (shareholder_id) REFERENCES meeting_shareholders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO role_permissions (role_id, permission)
SELECT id, 'meetings.manage' FROM roles WHERE slug = 'lawyer';
