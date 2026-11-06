-- Proje müvekkil portalında görünsün mü (hassas işler gizlenebilir)
ALTER TABLE projects ADD COLUMN portal_visible TINYINT(1) NOT NULL DEFAULT 1 AFTER description;

-- Portalda harcanan süre ve bütçe doluluğu gösterilsin mi
INSERT INTO settings (`key`, `value`) VALUES ('portal.show_hours', '1');
