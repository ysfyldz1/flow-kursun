<?php

// Sistemdeki tüm izinler. Yeni modül eklendikçe buraya eklenir;
// rol ekranı kutucukları bu listeden çizer. Etiketler lang dosyalarında "perm.<izin>".
return [
    'clients'    => ['clients.view', 'clients.edit'],
    'projects'   => ['projects.view', 'projects.edit', 'documents.manage'],
    'meetings'   => ['meetings.manage'],
    'calendar'   => ['calendar.view', 'calendar.edit'],
    'time'       => ['time.own', 'time.all', 'expenses.own', 'expenses.all'],
    'leaves'     => ['leaves.own', 'leaves.approve', 'leaves.manage'],
    'billing'    => ['rates.view', 'statements.manage', 'payments.manage', 'portal.manage'],
    'reports'    => ['reports.view'],
    'admin'      => ['users.manage', 'roles.manage', 'settings.manage'],
];
