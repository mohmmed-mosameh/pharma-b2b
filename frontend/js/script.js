/* =========================================================
   PharmaLink | فارما لينك — سكربت عام
   ========================================================= */
document.addEventListener('DOMContentLoaded', function () {

  /* ---------- تبديل تبويب تسجيل الدخول / إنشاء حساب ---------- */
  var tabLogin = document.getElementById('tab-login');
  var tabRegister = document.getElementById('tab-register');
  var paneLogin = document.getElementById('pane-login');
  var paneRegister = document.getElementById('pane-register');

  function showPane(which) {
    if (!tabLogin || !tabRegister) return;
    var isLogin = which === 'login';
    tabLogin.classList.toggle('active', isLogin);
    tabRegister.classList.toggle('active', !isLogin);
    paneLogin.classList.toggle('d-none', !isLogin);
    paneRegister.classList.toggle('d-none', isLogin);
  }
  if (tabLogin) tabLogin.addEventListener('click', function () { showPane('login'); });
  if (tabRegister) tabRegister.addEventListener('click', function () { showPane('register'); });

  /* ---------- اختيار نوع الحساب ---------- */
  var roleButtons = document.querySelectorAll('[data-role]');
  roleButtons.forEach(function (btn) {
    btn.addEventListener('click', function () {
      var role = btn.getAttribute('data-role');
      var roleStep = document.getElementById('step-role');
      var formStep = document.getElementById('step-form');
      var roleLabel = document.getElementById('selected-role-label');
      if (roleStep && formStep) {
        roleStep.classList.add('d-none');
        formStep.classList.remove('d-none');
      }
      if (roleLabel) roleLabel.textContent = role;
    });
  });
  var backToRole = document.getElementById('back-to-role');
  if (backToRole) {
    backToRole.addEventListener('click', function () {
      document.getElementById('step-form').classList.add('d-none');
      document.getElementById('step-role').classList.remove('d-none');
    });
  }

  /* ---------- إظهار / إخفاء كلمة المرور ---------- */
  document.querySelectorAll('.toggle-password').forEach(function (icon) {
    icon.addEventListener('click', function () {
      var input = document.querySelector(icon.getAttribute('data-target'));
      if (!input) return;
      var isPass = input.getAttribute('type') === 'password';
      input.setAttribute('type', isPass ? 'text' : 'password');
      icon.classList.toggle('bi-eye');
      icon.classList.toggle('bi-eye-slash');
    });
  });

  /* ---------- تصفية جدول المناقصات ---------- */
  var categoryFilter = document.getElementById('category-filter');
  var searchInput = document.getElementById('tender-search');
  var rows = document.querySelectorAll('.tender-row');

  function filterTenders() {
    var cat = categoryFilter ? categoryFilter.value : 'all';
    var q = searchInput ? searchInput.value.trim().toLowerCase() : '';
    rows.forEach(function (row) {
      var rowCat = row.getAttribute('data-category');
      var rowName = row.getAttribute('data-name').toLowerCase();
      var matchesCat = cat === 'all' || cat === rowCat;
      var matchesSearch = q === '' || rowName.indexOf(q) !== -1;
      row.style.display = (matchesCat && matchesSearch) ? '' : 'none';
    });
  }
  if (categoryFilter) categoryFilter.addEventListener('change', filterTenders);
  if (searchInput) searchInput.addEventListener('input', filterTenders);

  /* ---------- تفعيل رابط الصفحة الحالية في الشريط ---------- */
  var here = window.location.pathname.split('/').pop() || 'index.html';
  document.querySelectorAll('.js-nav-link').forEach(function (link) {
    var href = link.getAttribute('href');
    if (href === here) link.classList.add('active');
  });

  /* ---------- زر "ابدأ الآن" في الرئيسية ---------- */
  var ctaBtn = document.getElementById('cta-start');
  if (ctaBtn) {
    ctaBtn.addEventListener('click', function () {
      window.location.href = 'login.html';
    });
  }

  /* ---------- مودال تأكيد تسجيل الخروج (يُضاف تلقائيًا لكل الصفحات) ---------- */
  if (!document.getElementById('logoutModal')) {
    var modalHTML =
      '<div class="modal fade" id="logoutModal" tabindex="-1" aria-hidden="true">' +
        '<div class="modal-dialog modal-dialog-centered" style="max-width:380px;">' +
          '<div class="modal-content">' +
            '<div class="modal-body">' +
              '<i class="bi bi-box-arrow-right d-block"></i>' +
              '<h2 class="h5 fw-bold mb-2">هل انت متأكد من تسجيل الخروج من حسابك؟</h2>' +
            '</div>' +
            '<div class="modal-footer">' +
              '<button type="button" class="btn btn-pl-ghost" data-bs-dismiss="modal">الغاء</button>' +
              '<a href="#" id="confirmLogoutBtn" class="btn btn-pl">تسجيل الخروج</a>' +
            '</div>' +
          '</div>' +
        '</div>' +
      '</div>';
    document.body.insertAdjacentHTML('beforeend', modalHTML);
    document.getElementById('confirmLogoutBtn').addEventListener('click', function (e) {
      e.preventDefault();
      if (typeof logout === 'function') {
        logout();
      } else {
        window.location.href = 'login.html';
      }
    });
  }

  document.querySelectorAll('.logout-link').forEach(function (link) {
    link.addEventListener('click', function (e) {
      e.preventDefault();
      var modalEl = document.getElementById('logoutModal');
      if (window.bootstrap && modalEl) {
        new bootstrap.Modal(modalEl).show();
      } else {
        window.location.href = 'login.html';
      }
    });
  });

  /* ---------- شرائح معايير الترسية (تحديث القيمة المئوية) ---------- */
  document.querySelectorAll('.criteria-row input[type="range"]').forEach(function (range) {
    var out = document.querySelector('[data-out="' + range.id + '"]');
    function update() { if (out) out.textContent = range.value + '%'; }
    range.addEventListener('input', update);
    update();
  });

  /* ---------- فتح المظاريف (كشف السعر) ---------- */
  document.querySelectorAll('.btn-open-envelope').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var row = btn.closest('tr');
      if (!row) return;
      var priceCell = row.querySelector('.masked');
      var realVal = row.getAttribute('data-real-price');
      if (priceCell && realVal) {
        priceCell.textContent = realVal;
        priceCell.classList.remove('masked');
      }
      btn.textContent = 'تم الفتح';
      btn.classList.add('opened');
    });
  });

  /* ---------- خانات رمز التحقق (OTP) ---------- */
  var otpInputs = document.querySelectorAll('.otp-row input');
  otpInputs.forEach(function (input, idx) {
    input.addEventListener('input', function () {
      input.value = input.value.replace(/[^0-9]/g, '').slice(0, 1);
      if (input.value && otpInputs[idx + 1]) otpInputs[idx + 1].focus();
    });
    input.addEventListener('keydown', function (e) {
      if (e.key === 'Backspace' && !input.value && otpInputs[idx - 1]) {
        otpInputs[idx - 1].focus();
      }
    });
  });

  /* ---------- نماذج تجريبية: منع إعادة التحميل وإظهار تأكيد ---------- */
  document.querySelectorAll('form[data-demo-submit]').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var msg = form.getAttribute('data-demo-submit');
      var nextUrl = form.getAttribute('data-next');
      if (msg) alert(msg);
      if (nextUrl) window.location.href = nextUrl;
    });
  });

  /* ---------- عناصر بإجراء تجريبي (أزرار غير مرتبطة بنموذج) ---------- */
  document.querySelectorAll('[data-demo-action]').forEach(function (el) {
    el.addEventListener('click', function (e) {
      e.preventDefault();
      alert(el.getAttribute('data-demo-action'));
      var nextUrl = el.getAttribute('data-next');
      if (nextUrl) window.location.href = nextUrl;
    });
  });

  /* ---------- عرض تفاصيل عرض شركة معيّنة عبر باراميتر company ---------- */
  var offerData = {
    A: { name: 'شركة A', price: '480$', duration: '5', discount: '5%', status: 'معتمدة', statusClass: 'badge-approved', date: '15 / 5 / 2026', notes: '' },
    B: { name: 'شركة B', price: '500$', duration: '7', discount: '3%', status: 'جديدة', statusClass: 'badge-new', date: '16 / 5 / 2026', notes: 'نلتزم بتوريد الكمية كاملة خلال المدة المحددة مع ضمان جودة التخزين والنقل.' },
    C: { name: 'شركة C', price: '470$', duration: '7', discount: '2%', status: 'معتمدة', statusClass: 'badge-approved', date: '17 / 5 / 2026', notes: 'نلتزم بتوريد الكمية كاملة خلال المدة المحددة مع ضمان جودة التخزين والنقل.' }
  };
  var offerRoot = document.getElementById('offer-detail-root');
  if (offerRoot) {
    var params = new URLSearchParams(window.location.search);
    var company = params.get('company') || 'A';
    var d = offerData[company] || offerData.A;
    document.querySelectorAll('[data-field]').forEach(function (el) {
      var field = el.getAttribute('data-field');
      if (d[field] !== undefined) el.textContent = d[field];
    });
    var badge = document.getElementById('offer-status-badge');
    if (badge) {
      badge.textContent = d.status;
      badge.className = 'badge-status ' + d.statusClass;
    }
    var title = document.getElementById('offer-title');
    if (title) title.textContent = 'تفاصيل العرض – ' + d.name;
    var pill = document.getElementById('offer-pill');
    if (pill) pill.textContent = d.name;
    var notesRow = document.getElementById('offer-notes-row');
    if (notesRow) notesRow.style.display = d.notes ? 'flex' : 'none';
  }
});
