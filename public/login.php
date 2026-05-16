<?php
require_once __DIR__ . '/../core/security/csrf.php';
tracs_start_session();
$error=$_SESSION['login_error']??''; unset($_SESSION['login_error']);
?><!DOCTYPE html>
<html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<?= csrf_meta_tag() ?>
<title>TRACS — Sign In</title>
<?php include __DIR__ . '/includes/theme_bootstrap.php'; ?>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="/assets/tracs.css">
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
      <?php if($error):?><div class="err-box"><?=htmlspecialchars($error)?></div><?php endif;?>
      <form action="/auth/login.php" method="POST" class="login-body" style="padding:0">
        <?= csrf_input() ?>
        <div class="form-group">
          <label class="form-label">Email Address</label>
          <input type="email" name="email" class="form-input" placeholder="operator@idcloudhost.com" required autofocus>
        </div>
        <div class="form-group">
          <label class="form-label">Password</label>
          <input type="password" name="password" class="form-input" placeholder="••••••••" required>
        </div>
        <button type="submit" class="btn-login">
          <span class="btn-login-label">Sign In →</span>
          <span class="radar-dot dot-1" aria-hidden="true"></span>
          <span class="radar-dot dot-2" aria-hidden="true"></span>
          <span class="radar-dot dot-3" aria-hidden="true"></span>
          <span class="radar-dot dot-4" aria-hidden="true"></span>
          <span class="radar-dot dot-5" aria-hidden="true"></span>
        </button>
      </form>
    </div>
    <div class="login-foot"><div class="status-online"><span class="status-dot"></span>TRACS System Online</div></div>
  </div>
</div>
<script src="assets/tracs.js"></script>
</body></html>
