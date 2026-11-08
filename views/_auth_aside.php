<?php
// Giriş ekranlarının sol bölmesi: kurum rengi ve desen; Ayarlar→Kurum'dan görsel yüklendiyse o görünür.
use App\Services\Settings;

$image = Settings::get('org.login_image');
$tagline = (string) Settings::get('org.login_tagline');
$hour = (int) date('G');
$greeting = match (true) {
    $hour >= 5 && $hour < 12  => 'auth.greet_morning',
    $hour >= 12 && $hour < 18 => 'auth.greet_day',
    $hour >= 18 && $hour < 22 => 'auth.greet_evening',
    default                   => 'auth.greet_night',
};
[$day, $date] = today_label();
?>
<aside class="auth-aside <?= $image ? 'has-image' : '' ?>" <?php if ($image): ?>style="background-image:url('/login-image')"<?php endif; ?>>
    <?php if (!$image): ?>
        <span class="auth-ring auth-ring-1"></span>
        <span class="auth-ring auth-ring-2"></span>
        <span class="auth-ring auth-ring-3"></span>
    <?php endif; ?>
    <div class="auth-aside-top">
        <strong><?= e(__($greeting)) ?></strong>
        <span><?= e("$day · $date") ?></span>
    </div>
    <div class="auth-aside-bottom">
        <h2><?= e((string) Settings::get('org.name', config('app.name'))) ?></h2>
        <?php if ($tagline !== ''): ?><p><?= e($tagline) ?></p><?php endif; ?>
    </div>
</aside>
<script>
// Şifre alanlarına göster/gizle düğmesi
addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.auth-split input[type=password]').forEach(input => {
        const wrap = document.createElement('span');
        wrap.className = 'auth-pw';
        input.parentNode.insertBefore(wrap, input);
        wrap.appendChild(input);
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.setAttribute('aria-label', <?= json_encode(__('auth.show_password'), JSON_UNESCAPED_UNICODE) ?>);
        btn.innerHTML = <?= json_encode(icon('eye'), JSON_UNESCAPED_UNICODE) ?>;
        btn.addEventListener('click', () => {
            input.type = input.type === 'password' ? 'text' : 'password';
            btn.classList.toggle('active', input.type === 'text');
        });
        wrap.appendChild(btn);
    });
});
</script>
