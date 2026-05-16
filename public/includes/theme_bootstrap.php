<script>
(function(){
  var KEY = 'tracs_theme_preference';
  var LEGACY_KEY = 'tracs-theme';
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
  document.documentElement.setAttribute('data-theme', theme || 'light');
  document.documentElement.setAttribute('data-theme-preference', pref || 'browser');
})();
</script>
