-- Tüm projeleri görme. Bu yetki olmayan kullanıcı yalnız sorumlusu/ekip üyesi olduğu projeleri ve bunların
-- müvekkillerini görür (avukat). Muhasebe faturalama yaptığı için hepsini görür; yönetici zaten '*'.
INSERT INTO role_permissions (role_id, permission)
SELECT id, 'projects.all' FROM roles WHERE slug = 'accounting';
