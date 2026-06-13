<?php
require_once __DIR__ . '/../../core/security/direct_access.php';
tracs_deny_direct_script_access(__FILE__);
$_tracs_bootstrap_visual_theme = '';
if (isset($_tracs_visual_theme_preference)) {
  $_tracs_bootstrap_visual_theme = (string)$_tracs_visual_theme_preference;
} elseif (isset($visual_theme)) {
  $_tracs_bootstrap_visual_theme = (string)$visual_theme;
}
$_tracs_bootstrap_visual_theme = strtolower(trim($_tracs_bootstrap_visual_theme));
$_tracs_bootstrap_visual_theme = str_replace('-', '_', $_tracs_bootstrap_visual_theme);
$_tracs_bootstrap_visual_theme = str_replace(' ', '_', $_tracs_bootstrap_visual_theme);
$_tracs_bootstrap_visual_theme = 'default';
?>
<script>
(function(){
  var KEY = 'tracs_theme_preference';
  var LEGACY_KEY = 'tracs-theme';
  var VISUAL_KEY = 'tracs_visual_theme_preference';
  var VISUAL_LEGACY_KEY = 'tracs-visual-theme';
  var SERVER_VISUAL = <?=json_encode($_tracs_bootstrap_visual_theme)?>;
  var pref = localStorage.getItem(KEY);
  var legacy = localStorage.getItem(LEGACY_KEY);
  var valid = { light: true, dark: true, auto: true, system: true };
  if (!valid[pref] && valid[legacy]) {
    pref = legacy === 'system' ? 'auto' : legacy;
    localStorage.setItem(KEY, pref);
  }
  function timeTheme(date) {
    var hour = (date || new Date()).getHours();
    return hour >= 6 && hour < 18 ? 'light' : 'dark';
  }
  function browserTheme() {
    return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
  }
  var theme = pref === 'light' || pref === 'dark'
    ? pref
    : (pref === 'auto' || pref === 'system' ? timeTheme() : browserTheme());
  function normalizeVisualTheme(value) {
    value = String(value || '').trim().toLowerCase().replace(/[-\s]+/g, '_');
    if (value === 'default') return 'default';
    return value === 'tracs_v2' || value === 'tracsv2' || value === 'intercom_inspired' ? 'default' : '';
  }
  var serverVisual = normalizeVisualTheme(SERVER_VISUAL);
  var visual = serverVisual
    || normalizeVisualTheme(localStorage.getItem(VISUAL_KEY))
    || normalizeVisualTheme(localStorage.getItem(VISUAL_LEGACY_KEY))
    || 'default';
  if (serverVisual) {
    localStorage.setItem(VISUAL_KEY, visual);
    localStorage.setItem(VISUAL_LEGACY_KEY, visual);
  }
  document.documentElement.setAttribute('data-theme', theme || 'light');
  document.documentElement.setAttribute('data-theme-preference', pref || 'browser');
  document.documentElement.setAttribute('data-visual-theme', 'default');
  document.documentElement.setAttribute('data-visual-theme-preference', visual);
})();
</script>
