-- Proje belgeleri M365'te (SharePoint kitaplığı ya da merkezi OneDrive) durur; burada yalnızca bağlar tutulur.
ALTER TABLE projects ADD COLUMN drive_folder_id VARCHAR(100) NULL AFTER description;

-- Portalda müvekkille paylaşılan belgeler. source: office (ofis paylaştı) / client (müvekkil yükledi)
CREATE TABLE document_shares (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id    INT UNSIGNED NOT NULL,
    item_id       VARCHAR(100) NOT NULL,
    name          VARCHAR(255) NOT NULL,
    size          BIGINT UNSIGNED NULL,
    source        VARCHAR(10)  NOT NULL DEFAULT 'office',
    shared_by     INT UNSIGNED NULL,            -- ofis kullanıcısı
    portal_user_id INT UNSIGNED NULL,           -- yükleyen müvekkil kullanıcısı
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_project_item (project_id, item_id),
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (shared_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Belge yükleme ve portalda paylaşma: avukat (yönetici zaten '*')
INSERT INTO role_permissions (role_id, permission)
SELECT id, 'documents.manage' FROM roles WHERE slug = 'lawyer';
