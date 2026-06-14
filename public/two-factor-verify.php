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
        tracs_auth_log_event($conn, 'two_factor_session_expired', 'expired', tracs_auth_pending_identifier(), $pendingUserId, 'verification_timeout');
    }
    tracs_auth_clear_pending_2fa();
    $_SESSION['login_error'] = TRACS_2FA_SESSION_EXPIRED;
    $_SESSION['login_show_help'] = true;
    header('Location: /login.php');
    exit;
}

$user = tracs_get_user_by_id($conn, $pendingUserId);
if (!$user || !tracs_user_can_login($user) || !tracs_two_factor_schema_ready($conn)) {
    tracs_auth_log_event($conn, 'two_factor_verification_blocked', 'blocked', tracs_auth_pending_identifier(), $pendingUserId, 'account_or_schema_unavailable');
    tracs_auth_clear_pending_2fa();
    $_SESSION['login_error'] = TRACS_AUTH_GENERIC_INVALID;
    $_SESSION['login_show_help'] = true;
    header('Location: /login.php');
    exit;
}

if (!tracs_two_factor_user_configured($user)) {
    $_SESSION['tracs_pre_2fa_mode'] = 'setup';
    header('Location: /two-factor-setup.php');
    exit;
}

if (tracs_two_factor_locked($user)) {
    tracs_auth_clear_pending_2fa();
    $_SESSION['login_error'] = TRACS_AUTH_GENERIC_LOCKED;
    $_SESSION['login_show_help'] = true;
    header('Location: /login.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $storedSecret = (string)($user['two_factor_secret'] ?? '');
    $secret = tracs_two_factor_decrypt_secret($storedSecret);
    if ($secret === null) {
        tracs_auth_log_event($conn, 'two_factor_verification_blocked', 'blocked', tracs_auth_pending_identifier(), $pendingUserId, 'secret_unavailable');
        tracs_auth_clear_pending_2fa();
        $_SESSION['login_error'] = 'Two-factor authentication could not be verified. Please contact your administrator.';
        $_SESSION['login_show_help'] = true;
        header('Location: /login.php');
        exit;
    }

    $code = (string)($_POST['verification_code'] ?? '');
    if (!tracs_two_factor_verify_code($secret, $code)) {
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
        tracs_auth_complete_full_login($conn, $user, tracs_auth_pending_identifier(), tracs_auth_pending_landing());
    }
}

$tracs_build_info = tracs_build_public_payload();
$_tracs_visual_theme_preference = tracs_normalize_visual_theme(tracs_get_user_preference($conn, $pendingUserId, 'visual_theme', 'default'));
$_css_v = @filemtime(__DIR__ . '/assets/tracs.css') ?: time();
$_spacing_css_v = @filemtime(__DIR__ . '/assets/tracs-spacing.css') ?: time();
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
<title>TRACS - Verify 2FA</title>
<?php include __DIR__ . '/includes/theme_bootstrap.php'; ?>
<link rel="manifest" href="manifest.json">
<link rel="stylesheet" href="/assets/tracs.css?v=<?=$_css_v?>">
<link rel="stylesheet" href="/assets/tracs-spacing.css?v=<?=$_spacing_css_v?>">
<script>window.TRACS_BUILD_INFO = <?=json_encode($tracs_build_info, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)?>;</script>
</head><body>
<div class="login-shell two-factor-shell">
  <div class="login-grid"></div>
  <div class="login-card two-factor-card two-factor-card-compact fadein">
    <div class="login-top">
      <div class="login-logo"><img src="/assets/img/logo.svg" alt="TRACS Logo"></div>
      <div class="login-p">Enter the current code from your authenticator app.</div>
    </div>
    <div class="login-body two-factor-body two-factor-verify-body">
      <?php if($error): ?><div class="err-box two-factor-alert" role="alert"><?=htmlspecialchars($error, ENT_QUOTES, 'UTF-8')?></div><?php endif; ?>
      <?php if($error): ?><div class="login-help"><?=htmlspecialchars($login_help, ENT_QUOTES, 'UTF-8')?></div><?php endif; ?>
      <div class="two-factor-copy">
        <div class="two-factor-title">Two-factor verification</div>
      </div>
      <form action="/two-factor-verify.php" method="post" class="two-factor-form" data-two-factor-form>
        <?= csrf_input() ?>
        <div class="form-group">
          <label class="form-label" for="verification_code">Verification code</label>
          <input id="verification_code" class="form-input two-factor-code" type="text" name="verification_code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" placeholder="000000" required autofocus>
        </div>
        <button type="submit" class="btn-login" data-loading-text="Verifying..."><span class="btn-login-label">Verify and continue</span></button>
      </form>
    </div>
    <div class="login-foot"><div class="status-online"><span class="status-dot"></span>TRACS Secure Login</div></div>
  </div>
</div>
<?php $_js_v = @filemtime(__DIR__ . '/assets/tracs.js') ?: time(); ?>
<script src="assets/tracs.js?v=<?=$_js_v?>"></script>
</body></html>
