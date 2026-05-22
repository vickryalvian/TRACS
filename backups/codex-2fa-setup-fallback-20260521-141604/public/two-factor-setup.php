<?php
require_once __DIR__ . '/../core/security/csrf.php';
require_once __DIR__ . '/../core/build_signature.php';
tracs_start_session();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/user_management.php';

if (tracs_is_fully_authenticated()) {
    header('Location: /index.php');
    exit;
}

$pendingUserId = tracs_auth_pending_user_id();
if ($pendingUserId <= 0 || tracs_auth_pending_expired()) {
    if ($pendingUserId > 0) {
        tracs_auth_log_event($conn, 'two_factor_session_expired', 'expired', tracs_auth_pending_identifier(), $pendingUserId, 'setup_timeout');
    }
    tracs_auth_clear_pending_2fa();
    $_SESSION['login_error'] = TRACS_2FA_SESSION_EXPIRED;
    $_SESSION['login_show_help'] = true;
    header('Location: /login.php');
    exit;
}

$user = tracs_get_user_by_id($conn, $pendingUserId);
if (!$user || !tracs_user_can_login($user) || !tracs_two_factor_schema_ready($conn)) {
    tracs_auth_log_event($conn, 'two_factor_setup_blocked', 'blocked', tracs_auth_pending_identifier(), $pendingUserId, 'account_or_schema_unavailable');
    tracs_auth_clear_pending_2fa();
    $_SESSION['login_error'] = 'Two-factor authentication is not ready. Please contact your administrator.';
    $_SESSION['login_show_help'] = true;
    header('Location: /login.php');
    exit;
}

if (tracs_two_factor_locked($user)) {
    tracs_auth_clear_pending_2fa();
    $_SESSION['login_error'] = TRACS_AUTH_GENERIC_LOCKED;
    $_SESSION['login_show_help'] = true;
    header('Location: /login.php');
    exit;
}

if (tracs_two_factor_user_configured($user) && tracs_auth_pending_mode() !== 'setup') {
    header('Location: /two-factor-verify.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $secret = (string)($_SESSION['tracs_pending_2fa_secret'] ?? '');
    $code = (string)($_POST['verification_code'] ?? '');
    if ($secret === '' || !tracs_two_factor_verify_code($secret, $code)) {
        $state = tracs_two_factor_record_failure($conn, $pendingUserId, tracs_auth_pending_identifier());
        if (!empty($state['locked'])) {
            tracs_auth_clear_pending_2fa();
            $_SESSION['login_error'] = TRACS_AUTH_GENERIC_LOCKED;
            $_SESSION['login_show_help'] = true;
            header('Location: /login.php');
            exit;
        }
        $error = TRACS_2FA_GENERIC_INVALID;
    } else {
        tracs_two_factor_confirm_setup($conn, $pendingUserId, $secret);
        tracs_auth_log_event($conn, 'two_factor_setup_completed', 'success', tracs_auth_pending_identifier(), $pendingUserId);
        $user = tracs_get_user_by_id($conn, $pendingUserId) ?: $user;
        tracs_auth_complete_full_login($conn, $user, tracs_auth_pending_identifier(), tracs_auth_pending_landing());
    }
}

if (empty($_SESSION['tracs_pending_2fa_secret'])) {
    $_SESSION['tracs_pending_2fa_secret'] = tracs_two_factor_generate_secret();
}
if (empty($_SESSION['tracs_pending_2fa_setup_logged'])) {
    tracs_auth_log_event($conn, 'two_factor_setup_started', 'started', tracs_auth_pending_identifier(), $pendingUserId);
    $_SESSION['tracs_pending_2fa_setup_logged'] = true;
}

$secret = (string)$_SESSION['tracs_pending_2fa_secret'];
$manualKey = tracs_two_factor_format_secret($secret);
$otpauth = tracs_two_factor_otpauth_uri($user, $secret);
$qrSvg = tracs_two_factor_qr_svg($otpauth);
$expiresAt = (int)($_SESSION['tracs_pre_2fa_expires_at'] ?? time());
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
<meta name="author" content="<?=htmlspecialchars(TRACS_BUILD_OWNER, ENT_QUOTES, 'UTF-8')?>">
<meta name="application-name" content="TRACS">
<meta name="tracs-build-owner" content="<?=htmlspecialchars(TRACS_BUILD_OWNER, ENT_QUOTES, 'UTF-8')?>">
<meta name="tracs-build-version" content="<?=htmlspecialchars(TRACS_BUILD_VERSION, ENT_QUOTES, 'UTF-8')?>">
<title>TRACS - Set Up 2FA</title>
<?php include __DIR__ . '/includes/theme_bootstrap.php'; ?>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="manifest" href="manifest.json">
<link rel="stylesheet" href="/assets/tracs.css">
<script>window.TRACS_BUILD_INFO = <?=json_encode($tracs_build_info, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)?>;</script>
</head><body>
<div class="login-shell two-factor-shell">
  <div class="login-grid"></div>
  <div class="login-card two-factor-card fadein">
    <div class="login-top">
      <div class="login-logo"><img src="/assets/img/logo.svg" alt="TRACS Logo"></div>
      <div class="login-p">Two-factor authentication is required for every TRACS account.</div>
    </div>
    <div class="login-body two-factor-body">
      <?php if($error): ?><div class="err-box two-factor-alert" role="alert"><?=htmlspecialchars($error, ENT_QUOTES, 'UTF-8')?></div><?php endif; ?>
      <?php if($error): ?><div class="login-help"><?=htmlspecialchars($login_help, ENT_QUOTES, 'UTF-8')?></div><?php endif; ?>
      <?php if($success): ?><div class="success-box two-factor-alert" role="status"><?=htmlspecialchars($success, ENT_QUOTES, 'UTF-8')?></div><?php endif; ?>
      <div class="two-factor-copy">
        <div class="two-factor-title">Set up your authenticator app</div>
        <p>Scan the QR code with Google Authenticator, Microsoft Authenticator, Authy, 1Password, or another TOTP app. Then enter the 6-digit code to finish sign-in.</p>
      </div>
      <div class="two-factor-qr-wrap">
        <div class="two-factor-qr"><?=$qrSvg?></div>
        <div class="two-factor-key">
          <span>Manual setup key</span>
          <code><?=htmlspecialchars($manualKey, ENT_QUOTES, 'UTF-8')?></code>
        </div>
      </div>
      <form action="/two-factor-setup.php" method="post" class="two-factor-form" data-two-factor-form>
        <?= csrf_input() ?>
        <div class="form-group">
          <label class="form-label" for="verification_code">Verification Code</label>
          <input id="verification_code" class="form-input two-factor-code" type="text" name="verification_code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" placeholder="000000" required autofocus>
        </div>
        <button type="submit" class="btn-login"><span class="btn-login-label">Verify and continue</span></button>
      </form>
      <div class="login-help two-factor-expiry">This setup session expires at <?=htmlspecialchars(date('H:i', $expiresAt), ENT_QUOTES, 'UTF-8')?>.</div>
    </div>
    <div class="login-foot"><div class="status-online"><span class="status-dot"></span>TRACS Secure Login</div></div>
  </div>
</div>
<script src="assets/tracs.js"></script>
<script>
document.querySelector('[data-two-factor-form]')?.addEventListener('submit', event => {
  const button = event.currentTarget.querySelector('button[type="submit"]');
  if (button) {
    button.disabled = true;
    button.querySelector('.btn-login-label').textContent = 'Verifying...';
  }
});
</script>
</body></html>
