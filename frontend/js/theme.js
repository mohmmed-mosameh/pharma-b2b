/* =========================================================
   PharmaLink | فارما لينك — تبديل الوضع الداكن/الفاتح
   يُحمَّل كسكربت حاجب (blocking) داخل <head> ليطبَّق الوضع
   قبل أول رسم للصفحة (تجنّب وميض الوضع الفاتح لمستخدمي الدارك).
   ========================================================= */

function getTheme() {
  var stored = localStorage.getItem('pharma_theme');
  if (stored === 'light' || stored === 'dark') return stored;
  return 'light';
}

function setTheme(theme) {
  localStorage.setItem('pharma_theme', theme);
  applyTheme();
  updateThemeToggles();
}

function toggleTheme() {
  setTheme(getTheme() === 'dark' ? 'light' : 'dark');
}

function applyTheme() {
  document.documentElement.setAttribute('data-theme', getTheme());
}

function updateThemeToggles() {
  var isDark = getTheme() === 'dark';
  document.querySelectorAll('.theme-toggle').forEach(function (btn) {
    var icon = btn.querySelector('i');
    if (icon) {
      icon.classList.toggle('bi-moon', !isDark);
      icon.classList.toggle('bi-sun', isDark);
    }
    var label = (typeof t === 'function')
      ? t(isDark ? 'common.themeToggleLight' : 'common.themeToggleDark')
      : (isDark ? 'Light mode' : 'Dark mode');
    btn.setAttribute('aria-label', label);
    btn.setAttribute('title', label);
  });
}

function wireThemeToggles() {
  document.querySelectorAll('.theme-toggle').forEach(function (btn) {
    if (btn.dataset.themeWired) return;
    btn.dataset.themeWired = '1';
    if (!btn.querySelector('i')) {
      btn.innerHTML = '<i class="bi bi-moon"></i>';
    }
    btn.addEventListener('click', toggleTheme);
  });
  updateThemeToggles();
}

/* تطبيق فوري ومتزامن أثناء تحليل <head> — قبل أي رسم للصفحة */
applyTheme();

document.addEventListener('DOMContentLoaded', wireThemeToggles);
