<?php

// Kullanım: php bin/create-admin.php "Ad Soyad" eposta@ornek.com sifre

require __DIR__ . '/../app/bootstrap.php';

use App\Core\DB;

[, $name, $email, $password] = $argv + [null, null, null, null];
if (!$name || !$email || !$password) {
    exit("Kullanım: php bin/create-admin.php \"Ad Soyad\" eposta sifre\n");
}

$id = DB::insert('users', [
    'role_id'  => (int) DB::value("SELECT id FROM roles WHERE slug = 'admin'"),
    'name'     => $name,
    'email'    => mb_strtolower($email),
    'password' => password_hash($password, PASSWORD_DEFAULT),
    'locale'   => config('app.locale', 'tr'),
]);

echo "Yönetici oluşturuldu (#$id).\n";
