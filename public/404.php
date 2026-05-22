<?php
if (!headers_sent()) {
    http_response_code(404);
}
require_once __DIR__ . '/../core/security/csrf.php';
tracs_start_session();
$dashboardHref = '/index.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>TRACS — 404 Page Not Found</title>
<?php include __DIR__ . '/includes/theme_bootstrap.php'; ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/tracs.css">
<style>
  html, body { min-height: 100%; overflow: auto; }
  body {
    margin: 0;
    background: var(--bg);
    color: var(--tx1);
    font-family: var(--font);
  }
  .not-found-grid {
    position: fixed;
    inset: 0;
    opacity: .58;
    background-size: 34px 34px;
    pointer-events: none;
  }
  .not-found-page {
    position: relative;
    z-index: 1;
    min-height: 100vh;
    min-height: 100dvh;
    display: grid;
    place-items: center;
    padding: clamp(24px, 6vh, 56px) clamp(22px, 5vw, 80px);
    box-sizing: border-box;
  }
  .not-found-shell {
    width: fit-content;
    max-width: 100%;
    display: grid;
    grid-template-columns: 260px max-content;
    gap: clamp(22px, 2.8vw, 36px);
    align-items: center;
    justify-content: center;
    padding: 0;
  }
  .not-found-copy {
    width: max-content;
    max-width: 100%;
  }
  .not-found-kicker {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 18px;
    color: var(--blue);
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0;
    text-transform: uppercase;
  }
  .not-found-kicker::before {
    content: "";
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--blue);
    box-shadow: 0 0 0 5px var(--blue-lt);
  }
  .not-found-title {
    margin: 0;
    font-size: clamp(32px, 4.2vw, 48px);
    line-height: 1.02;
    letter-spacing: 0;
    white-space: nowrap;
  }
  .not-found-subtitle {
    margin: 18px 0 0;
    color: var(--tx2);
    font-size: 16px;
    font-weight: 600;
    line-height: 1.45;
  }
  .not-found-desc {
    max-width: 520px;
    margin: 12px 0 0;
    color: var(--tx3);
    font-size: 14px;
    line-height: 1.65;
  }
  .not-found-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 28px;
  }
  .not-found-mascot {
    width: 100%;
    aspect-ratio: 1;
    border-radius: 50%;
  }
  .not-found-mascot img {
    width: 100%;
    height: 100%;
    display: block;
    object-fit: cover;
    border-radius: 50%;
    box-shadow: var(--shadow-lg);
  }
  [data-theme="dark"] .not-found-grid { opacity: .38; }
  @media (max-width: 1120px) {
    .not-found-shell {
      grid-template-columns: 1fr;
      gap: 28px;
    }
    .not-found-mascot {
      width: min(200px, 54vw);
      justify-self: center;
    }
  }
  @media (max-width: 760px) {
    .not-found-page {
      place-items: center;
      overflow-x: auto;
    }
    .not-found-actions .btn {
      flex: 1 1 180px;
      justify-content: center;
    }
  }
</style>
</head>
<body>
<div class="login-grid not-found-grid" aria-hidden="true"></div>
<main class="not-found-page">
  <section class="not-found-shell" aria-labelledby="notFoundTitle">
    <div class="not-found-mascot" aria-hidden="true">
      <img src="/assets/images/dobby-404.png" alt="" loading="eager" decoding="async">
    </div>
    <div class="not-found-copy">
      <div class="not-found-kicker">TRACS Not Found</div>
      <h1 class="not-found-title" id="notFoundTitle">404 — Page Not Found</h1>
      <p class="not-found-subtitle">Dobby could not find what you are looking for.</p>
      <p class="not-found-desc">The page may not exist, may have been moved, or you may not have permission to access it.</p>
      <div class="not-found-actions">
        <a class="btn btn-primary" href="<?=htmlspecialchars($dashboardHref, ENT_QUOTES, 'UTF-8')?>">Back to Dashboard</a>
        <button class="btn btn-ghost" type="button" onclick="history.length > 1 ? history.back() : location.href='<?=htmlspecialchars($dashboardHref, ENT_QUOTES, 'UTF-8')?>'">Go Back</button>
      </div>
    </div>
  </section>
</main>
</body>
</html>
