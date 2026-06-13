<?php
require_once __DIR__ . '/../core/security/csrf.php';
require_once __DIR__ . '/../core/security/auth_hardening.php';
require_once __DIR__ . '/../core/build_signature.php';
tracs_start_session();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
if (tracs_is_fully_authenticated()) {
    header('Location: /index.php');
    exit;
}
if (tracs_auth_pending_user_id() > 0 && !tracs_auth_pending_expired()) {
    header('Location: ' . (tracs_auth_pending_mode() === 'setup' ? '/two-factor-setup.php' : '/two-factor-verify.php'));
    exit;
}
if (tracs_auth_pending_user_id() > 0 && tracs_auth_pending_expired()) {
    tracs_auth_clear_pending_2fa();
    $_SESSION['login_error'] = $_SESSION['login_error'] ?? TRACS_2FA_SESSION_EXPIRED;
    $_SESSION['login_show_help'] = true;
}
$error = $_SESSION['login_error'] ?? '';
$identifier = $_SESSION['login_identifier'] ?? '';
$show_captcha = !empty($_SESSION['login_captcha_required']);
$show_help = !empty($_SESSION['login_show_help'])
    || $show_captcha
    || in_array($error, [TRACS_AUTH_GENERIC_LOCKED, TRACS_2FA_SESSION_EXPIRED], true);
unset($_SESSION['login_error'], $_SESSION['login_show_help']);
$tracs_build_info = tracs_build_public_payload();
$login_help = TRACS_AUTH_HELP_MESSAGE;
$login_contact = trim((string)tracs_auth_env('TRACS_LOGIN_HELP_CONTACT', ''));
if ($login_contact !== '') {
    $login_help .= ' Contact: ' . $login_contact;
}
?><!DOCTYPE html>
<html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<?= csrf_meta_tag() ?>
<!-- TRACS System by Vickry -->
<meta name="author" content="<?=htmlspecialchars(TRACS_BUILD_OWNER, ENT_QUOTES, 'UTF-8')?>">
<meta name="application-name" content="TRACS">
<meta name="tracs-build-owner" content="<?=htmlspecialchars(TRACS_BUILD_OWNER, ENT_QUOTES, 'UTF-8')?>">
<meta name="tracs-build-version" content="<?=htmlspecialchars(TRACS_BUILD_VERSION, ENT_QUOTES, 'UTF-8')?>">
<title>TRACS — Sign In</title>
<?php include __DIR__ . '/includes/theme_bootstrap.php'; ?>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="manifest" href="manifest.json">
<link rel="stylesheet" href="/assets/tracs.css">
<?php if($show_captcha && tracs_auth_turnstile_enabled()): ?><script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script><?php endif; ?>
<script>
window.TRACS_BUILD_INFO = <?=json_encode($tracs_build_info, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)?>;
</script>
</head><body>
<div class="login-shell">
  <div class="login-grid"></div>
  <div class="login-card fadein">
    
    <div class="login-top">
      <div class="login-logo">
        <img src="/assets/img/logo.svg" alt="TRACS Logo">
      </div>
     
      <div class="login-p">Authorized personnel only. Credentials required.</div>
    </div>
    <div class="login-body">
      <?php if($error):?><div class="err-box" role="alert"><?=htmlspecialchars($error, ENT_QUOTES, 'UTF-8')?></div><?php endif;?>
      <form action="/auth/login.php" method="POST" class="login-body" style="padding:0">
        <?= csrf_input() ?>
        <div class="form-group">
          <label class="form-label">Email or Username</label>
          <input type="text" name="email" class="form-input" placeholder="operator@idcloudhost.com" value="<?=htmlspecialchars((string)$identifier, ENT_QUOTES, 'UTF-8')?>" autocomplete="username" required autofocus>
        </div>
        <div class="form-group">
          <label class="form-label">Password</label>
          <input type="password" name="password" class="form-input" placeholder="••••••••" autocomplete="current-password" required>
        </div>
        <?php if($show_captcha): ?>
          <div class="login-captcha" aria-label="Login verification">
            <div class="login-captcha-title">Verification required</div>
            <?php if(tracs_auth_turnstile_enabled()): ?>
              <div class="cf-turnstile" data-sitekey="<?=htmlspecialchars((string)tracs_auth_env('TRACS_TURNSTILE_SITE_KEY', ''), ENT_QUOTES, 'UTF-8')?>" data-theme="auto"></div>
            <?php else: ?>
              <label class="form-label" for="captcha_answer"><?=htmlspecialchars(tracs_auth_internal_captcha_question(), ENT_QUOTES, 'UTF-8')?></label>
              <input id="captcha_answer" type="text" inputmode="numeric" name="captcha_answer" class="form-input" autocomplete="off" required>
            <?php endif; ?>
          </div>
        <?php endif; ?>
        <button type="submit" class="btn-login">
          <span class="btn-login-label">Sign In →</span>
          <span class="radar-dot dot-1" aria-hidden="true"></span>
          <span class="radar-dot dot-2" aria-hidden="true"></span>
          <span class="radar-dot dot-3" aria-hidden="true"></span>
          <span class="radar-dot dot-4" aria-hidden="true"></span>
          <span class="radar-dot dot-5" aria-hidden="true"></span>
        </button>
        <?php if($show_help): ?><div class="login-help"><?=htmlspecialchars($login_help, ENT_QUOTES, 'UTF-8')?></div><?php endif; ?>
      </form>
    </div>
    <div class="login-foot"><div class="status-online"><span class="status-dot"></span>TRACS System Online</div></div>
  </div>
</div>
<script src="assets/tracs.js"></script>
</body></html>
