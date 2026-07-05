/* =========================================================
   PharmaLink — نظام التبديل بين العربية والإنجليزية
   يجب أن يُحمَّل هذا الملف قبل js/api.js وjs/script.js وأي سكربت
   داخل الصفحة، كي تكون t()/getLang() جاهزة قبل استخدامها.

   مبدأ أساسي: اللغة الافتراضية عربي، والعربية لا تُعاد كتابتها
   بالـ DOM أبدًا (تبقى كما هي بالـ HTML الأصلي) — التبديل الفعلي
   بالـ DOM يحصل فقط لما تكون اللغة المختارة إنجليزي. هيك أي خطأ
   بقاموس الإنجليزي ما بينكسر الموقع العربي الحالي أبدًا.
   ========================================================= */
var I18N = {
  ar: {
    'common.logout': 'تسجيل الخروج',
    'common.confirmLogout': 'هل انت متأكد من تسجيل الخروج من حسابك؟',
    'common.cancel': 'الغاء',
    'common.account': 'حسابي',
    'common.menu': 'القائمة',
    'common.openNav': 'فتح قائمة التنقل',
    'common.langToggle': 'English',

    'nav.home': 'الرئيسية',
    'nav.dashboard': 'لوحة التحكم',
    'nav.createTender': 'إنشاء مناقصة',
    'nav.tenderCriteria': 'تحديد شروط ترسية المناقصة',
    'nav.reviewPublish': 'مراجعة ونشر المناقصة',
    'nav.offersReceived': 'استلام العروض',
    'nav.openEnvelopes': 'فتح المظاريف',
    'nav.awardTender': 'ترسية المناقصة',
    'nav.tenderReport': 'تقرير المناقصة',
    'nav.availableTenders': 'المناقصات المتاحة',
    'nav.submitOffer': 'تقديم العرض',
    'nav.trackStatus': 'متابعة حالة المناقصات',
    'nav.tenderResult': 'نتيجة المناقصة',
    'nav.login': 'تسجيل الدخول',

    'index.title': 'فارما لينك | PharmaLink — حلول ذكية لإدارة مناقصات وتوريد الأدوية',
    'index.logoAlt': 'شعار PharmaLink',
    'index.heroImgAlt': 'لوحة تحكم PharmaLink تظهر على شاشة حاسوب محاط بعبوات أدوية',
    'index.heroTitle': 'حلول ذكية لإدارة مناقصات وتوريد الأدوية ..!<br>ربط مباشر بين مخازن الأدوية وشركات التوريد',
    'index.heroText': 'توفّر منصة <span class="brand-name">PharmaLink</span> بيئة رقمية آمنة لتسهيل إدارة المناقصات الدوائية، ومقارنة عروض الموردين، وضمان أعلى درجات الشفافية والكفاءة في اتخاذ القرار.',
    'index.ctaStart': 'ابدأ الآن',
    'index.footer': '© 2026 PharmaLink — جميع الحقوق محفوظة',

    'login.title': 'تسجيل الدخول | PharmaLink',
    'login.chooseRole': 'اختر نوع الحساب',
    'login.role.pharmacy': 'مدير مخزن الأدوية',
    'login.role.supplier': 'مورد شركة الأدوية',
    'login.back': 'رجوع',
    'login.loggingInAs': 'الدخول بصفة:',
    'login.tabLogin': 'تسجيل الدخول',
    'login.tabRegister': 'إنشاء حساب',
    'login.email': 'البريد الالكتروني',
    'login.password': 'كلمة المرور',
    'login.rememberMe': 'تذكرني',
    'login.forgotPassword': 'هل نسيت كلمة السر؟',
    'login.submit': 'تسجيل الدخول',
    'login.submitBusy': 'جارٍ الدخول...',
    'login.or': 'أو',
    'login.googleLogin': 'تسجيل الدخول باستخدام جوجل',
    'login.facebookLogin': 'تسجيل الدخول باستخدام الفيسبوك',
    'login.noAccount': 'ليس لديك حساب؟',
    'login.createNewAccount': 'إنشاء حساب جديد',
    'login.fullName': 'الاسم الكامل / اسم الشركة',
    'login.phone': 'رقم الهاتف',
    'login.confirmPassword': 'تأكيد كلمة المرور',
    'login.registerSubmit': 'إنشاء الحساب',
    'login.registerSubmitBusy': 'جارٍ إنشاء الحساب...',
    'login.haveAccount': 'لديك حساب مسبقاً؟',

    'login.err.roleMismatch': 'هذا الحساب مسجَّل كـ "{role}". ارجع واختر نوع الحساب الصحيح.',
    'login.err.invalidCredentials': 'بيانات الدخول غير صحيحة',
    'login.err.passwordMismatch': 'كلمتا المرور غير متطابقتين',
    'login.err.registerFailed': 'فشل إنشاء الحساب، تحقق من البيانات',
    'login.err.socialFailed': 'تعذّر تسجيل الدخول عبر الحساب الاجتماعي، حاول مرة أخرى.',
  },

  en: {
    'common.logout': 'Logout',
    'common.confirmLogout': 'Are you sure you want to log out?',
    'common.cancel': 'Cancel',
    'common.account': 'My account',
    'common.menu': 'Menu',
    'common.openNav': 'Open navigation menu',
    'common.langToggle': 'العربية',

    'nav.home': 'Home',
    'nav.dashboard': 'Dashboard',
    'nav.createTender': 'Create Tender',
    'nav.tenderCriteria': 'Award Criteria',
    'nav.reviewPublish': 'Review & Publish',
    'nav.offersReceived': 'Offers Received',
    'nav.openEnvelopes': 'Open Envelopes',
    'nav.awardTender': 'Award Tender',
    'nav.tenderReport': 'Tender Report',
    'nav.availableTenders': 'Available Tenders',
    'nav.submitOffer': 'Submit Offer',
    'nav.trackStatus': 'Track Status',
    'nav.tenderResult': 'Tender Result',
    'nav.login': 'Login',

    'index.title': 'PharmaLink — Smart Solutions for Medicine Tenders & Supply',
    'index.logoAlt': 'PharmaLink logo',
    'index.heroImgAlt': 'A computer screen showing the PharmaLink dashboard, surrounded by medicine boxes',
    'index.heroTitle': 'Smart Solutions for Medicine Tenders & Supply..!<br>Connecting Pharmacies Directly with Suppliers',
    'index.heroText': '<span class="brand-name">PharmaLink</span> provides a secure digital environment that streamlines managing medicine tenders, comparing supplier offers, and ensures the highest levels of transparency and efficiency in decision-making.',
    'index.ctaStart': 'Get Started',
    'index.footer': '© 2026 PharmaLink — All rights reserved',

    'login.title': 'Login | PharmaLink',
    'login.chooseRole': 'Choose account type',
    'login.role.pharmacy': 'Pharmacy Manager',
    'login.role.supplier': 'Supplier Company',
    'login.back': 'Back',
    'login.loggingInAs': 'Logging in as:',
    'login.tabLogin': 'Login',
    'login.tabRegister': 'Create Account',
    'login.email': 'Email',
    'login.password': 'Password',
    'login.rememberMe': 'Remember me',
    'login.forgotPassword': 'Forgot password?',
    'login.submit': 'Login',
    'login.submitBusy': 'Logging in...',
    'login.or': 'Or',
    'login.googleLogin': 'Login with Google',
    'login.facebookLogin': 'Login with Facebook',
    'login.noAccount': "Don't have an account?",
    'login.createNewAccount': 'Create new account',
    'login.fullName': 'Full name / Company name',
    'login.phone': 'Phone number',
    'login.confirmPassword': 'Confirm password',
    'login.registerSubmit': 'Create Account',
    'login.registerSubmitBusy': 'Creating account...',
    'login.haveAccount': 'Already have an account?',

    'login.err.roleMismatch': 'This account is registered as "{role}". Go back and choose the correct account type.',
    'login.err.invalidCredentials': 'Invalid login credentials',
    'login.err.passwordMismatch': "Passwords don't match",
    'login.err.registerFailed': 'Failed to create account, please check your details',
    'login.err.socialFailed': 'Could not log in with the social account, please try again.',
  }
};

function getLang() {
  return localStorage.getItem('pharma_lang') || 'ar';
}

function setLang(lang) {
  localStorage.setItem('pharma_lang', lang);
  window.location.reload();
}

function t(key, vars) {
  var lang = getLang();
  var dict = I18N[lang] || I18N.ar;
  var str = (Object.prototype.hasOwnProperty.call(dict, key) ? dict[key] : (I18N.ar[key] || key));
  if (vars) {
    Object.keys(vars).forEach(function (k) {
      str = str.replace('{' + k + '}', vars[k]);
    });
  }
  return str;
}

/* تنسيق التاريخ حسب اللغة المختارة */
function fmtDate(value) {
  var lang = getLang();
  return new Date(value).toLocaleDateString(lang === 'ar' ? 'ar-EG' : 'en-GB');
}

/* يمسح كل عناصر data-i18n* بالصفحة ويستبدل نصها/خصائصها بالترجمة.
   لا شيء يحصل إذا كانت اللغة عربي (الافتراضي) — النص العربي الأصلي
   بالـ HTML يبقى كما هو دائمًا، فلا خطر على الموقع العربي الحالي. */
function applyI18n() {
  var lang = getLang();
  document.documentElement.setAttribute('lang', lang);
  document.documentElement.setAttribute('dir', lang === 'ar' ? 'rtl' : 'ltr');

  if (lang === 'ar') return;

  document.querySelectorAll('[data-i18n]').forEach(function (el) {
    el.innerHTML = t(el.getAttribute('data-i18n'));
  });
  document.querySelectorAll('[data-i18n-text]').forEach(function (el) {
    el.textContent = t(el.getAttribute('data-i18n-text'));
  });
  document.querySelectorAll('[data-i18n-placeholder]').forEach(function (el) {
    el.setAttribute('placeholder', t(el.getAttribute('data-i18n-placeholder')));
  });
  document.querySelectorAll('[data-i18n-title]').forEach(function (el) {
    el.setAttribute('title', t(el.getAttribute('data-i18n-title')));
  });
  document.querySelectorAll('[data-i18n-aria-label]').forEach(function (el) {
    el.setAttribute('aria-label', t(el.getAttribute('data-i18n-aria-label')));
  });
  document.querySelectorAll('[data-i18n-alt]').forEach(function (el) {
    el.setAttribute('alt', t(el.getAttribute('data-i18n-alt')));
  });
}

document.addEventListener('DOMContentLoaded', applyI18n);
