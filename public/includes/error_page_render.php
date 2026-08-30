<?php
require_once __DIR__ . '/../../core/security/direct_access.php';
tracs_deny_direct_script_access(__FILE__);

/**
 * Renders the shared TRACS Dobby error-page shell used by every public/<code>.php
 * page (400/401/403/404/405/408/419/429/500). Keeping one copy of the markup/CSS
 * is what keeps all error pages pixel-identical instead of drifting independently.
 *
 * @param int    $code     HTTP status code (used in the <title> and kicker).
 * @param string $kicker   Small uppercase label above the title (e.g. "TRACS Not Found").
 * @param string $title    Big heading (e.g. "404 — Page Not Found").
 * @param string $subtitle Bold one-liner under the title.
 * @param string $desc     Supporting sentence under the subtitle.
 * @param array  $actions  List of ['href'=>string|null,'onclick'=>string|null,'label'=>string,'primary'=>bool]
 */
function tracs_render_error_page(int $code, string $kicker, string $title, string $subtitle, string $desc, array $actions): void {
  $dashboardHref = '/index.php';
  $_css_v = @filemtime(__DIR__ . '/../assets/tracs.css') ?: time();
  $_spacing_css_v = @filemtime(__DIR__ . '/../assets/tracs-spacing.css') ?: time();
  ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>TRACS — <?=htmlspecialchars($title, ENT_QUOTES, 'UTF-8')?></title>
<?php include __DIR__ . '/theme_bootstrap.php'; ?>
<link rel="stylesheet" href="/assets/tracs.css?v=<?=$_css_v?>">
<link rel="stylesheet" href="/assets/tracs-spacing.css?v=<?=$_spacing_css_v?>">
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
      overflow-x: hidden;
      padding: var(--space-6) var(--page-padding-inline);
    }
    .not-found-shell,
    .not-found-copy {
      min-width: 0;
      width: 100%;
    }
    .not-found-shell {
      max-width: 520px;
    }
    .not-found-title {
      overflow-wrap: anywhere;
      white-space: normal;
    }
    .not-found-actions {
      gap: var(--toolbar-gap);
      width: 100%;
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
      <div class="not-found-kicker">TRACS <?=htmlspecialchars($kicker, ENT_QUOTES, 'UTF-8')?></div>
      <h1 class="not-found-title" id="notFoundTitle"><?=htmlspecialchars((string)$code, ENT_QUOTES, 'UTF-8')?> — <?=htmlspecialchars($title, ENT_QUOTES, 'UTF-8')?></h1>
      <p class="not-found-subtitle"><?=htmlspecialchars($subtitle, ENT_QUOTES, 'UTF-8')?></p>
      <p class="not-found-desc"><?=htmlspecialchars($desc, ENT_QUOTES, 'UTF-8')?></p>
      <div class="not-found-actions">
        <?php foreach ($actions as $action): ?>
        <?php $cls = 'btn ' . (!empty($action['primary']) ? 'btn-primary' : 'btn-ghost'); ?>
        <?php if (!empty($action['href'])): ?>
        <a class="<?=$cls?>" href="<?=htmlspecialchars($action['href'], ENT_QUOTES, 'UTF-8')?>"><?=htmlspecialchars($action['label'], ENT_QUOTES, 'UTF-8')?></a>
        <?php else: ?>
        <button class="<?=$cls?>" type="button" onclick="<?=htmlspecialchars($action['onclick'] ?? '', ENT_QUOTES, 'UTF-8')?>"><?=htmlspecialchars($action['label'], ENT_QUOTES, 'UTF-8')?></button>
        <?php endif; ?>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
</main>
</body>
</html>
<?php
}
