/* ==========================================================================
   PharmaLink — API Integration Layer
   يربط الفرونت إند بالـ Laravel Backend على http://localhost:8000
   ========================================================================== */

const API_URL = 'http://localhost:8000/api';

/* ------------------------------------------------------------------ */
/* دالة apiCall المركزية — تُرسل جميع الطلبات للـ Backend              */
/* ------------------------------------------------------------------ */
async function apiCall(method, endpoint, data = null) {
    const token = localStorage.getItem('pharma_token');
    const options = {
        method,
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            ...(token ? { 'Authorization': `Bearer ${token}` } : {})
        },
        body: data ? JSON.stringify(data) : null
    };

    try {
        const res = await fetch(API_URL + endpoint, options);
        const json = await res.json().catch(() => ({}));

        if (res.status === 401) {
            localStorage.clear();
            window.location.href = 'login.html';
            return null;
        }

        if (!res.ok) {
            throw new Error(json.message || `خطأ ${res.status}`);
        }

        return json;
    } catch (err) {
        if (err.message.includes('Failed to fetch') || err.message.includes('NetworkError')) {
            showApiError('تعذّر الاتصال بالخادم. تأكد من تشغيل: php artisan serve');
        }
        throw err;
    }
}

/* ------------------------------------------------------------------ */
/* مساعدات Auth                                                         */
/* ------------------------------------------------------------------ */
function getUser()  { return JSON.parse(localStorage.getItem('pharma_user') || 'null'); }
function getToken() { return localStorage.getItem('pharma_token'); }

function requireAuth() {
    if (!getToken()) {
        window.location.href = 'login.html';
        return false;
    }
    return true;
}

/* يتحقق أن المستخدم الحالي له الدور المطلوب لهذه الصفحة، وإلا يعيده لصفحة مناسبة لدوره */
function requireRole(role) {
    if (!requireAuth()) return false;
    const user = getUser();
    if (!user || user.role !== role) {
        window.location.href = user && user.role === 'supplier' ? 'tenders.html' : 'dashboard.html';
        return false;
    }
    return true;
}

function logout() {
    apiCall('POST', '/auth/logout').catch(() => {}).finally(() => {
        localStorage.clear();
        window.location.href = 'login.html';
    });
}

/* ------------------------------------------------------------------ */
/* عرض رسالة خطأ للمستخدم                                              */
/* ------------------------------------------------------------------ */
function showApiError(msg) {
    let toast = document.getElementById('api-toast');
    if (!toast) {
        toast = document.createElement('div');
        toast.id = 'api-toast';
        toast.style.cssText = `
            position:fixed; top:20px; left:50%; transform:translateX(-50%);
            background:#dc3545; color:#fff; padding:14px 28px;
            border-radius:10px; font-weight:700; font-size:15px;
            z-index:99999; box-shadow:0 4px 20px rgba(0,0,0,.25);
            direction:rtl; max-width:90vw; text-align:center;
        `;
        document.body.appendChild(toast);
    }
    toast.textContent = msg;
    toast.style.display = 'block';
    setTimeout(() => { toast.style.display = 'none'; }, 5000);
}

function showApiSuccess(msg) {
    let toast = document.getElementById('api-toast-ok');
    if (!toast) {
        toast = document.createElement('div');
        toast.id = 'api-toast-ok';
        toast.style.cssText = `
            position:fixed; top:20px; left:50%; transform:translateX(-50%);
            background:#198754; color:#fff; padding:14px 28px;
            border-radius:10px; font-weight:700; font-size:15px;
            z-index:99999; box-shadow:0 4px 20px rgba(0,0,0,.25);
            direction:rtl; max-width:90vw; text-align:center;
        `;
        document.body.appendChild(toast);
    }
    toast.textContent = msg;
    toast.style.display = 'block';
    setTimeout(() => { toast.style.display = 'none'; }, 4000);
}

/* ------------------------------------------------------------------ */
/* مساعد: قراءة rfq_id من URL                                          */
/* ------------------------------------------------------------------ */
function getRfqIdFromUrl() {
    return new URLSearchParams(window.location.search).get('id')
        || localStorage.getItem('current_rfq_id');
}

function getQuoteIdFromUrl() {
    return new URLSearchParams(window.location.search).get('id');
}

/* ------------------------------------------------------------------ */
/* تحميل ملف (تقرير PDF) بتوثيق Bearer، بما إنه مش رابط عادي           */
/* ------------------------------------------------------------------ */
async function apiDownloadFile(endpoint, filename) {
    const token = localStorage.getItem('pharma_token');
    const res = await fetch(API_URL + endpoint, {
        headers: { 'Accept': 'application/pdf', ...(token ? { 'Authorization': `Bearer ${token}` } : {}) }
    });

    if (!res.ok) {
        const json = await res.json().catch(() => ({}));
        throw new Error(json.message || `تعذّر تحميل الملف (خطأ ${res.status})`);
    }

    const blob = await res.blob();
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    a.remove();
    window.URL.revokeObjectURL(url);
}

/* ------------------------------------------------------------------ */
/* مساعدات حالة المناقصة (Rfq.status) — مشتركة بين شاشات الصيدلية      */
/* ------------------------------------------------------------------ */
const RFQ_STATUS_LABELS = {
    open: { label: 'مفتوحة', cls: 'badge-open' },
    closed: { label: 'مغلقة', cls: 'badge-closed' },
    pending_opening: { label: 'بانتظار فتح المظاريف', cls: 'badge-new' },
    opened: { label: 'المظاريف مفتوحة', cls: 'badge-approved' },
    awarded: { label: 'مُرسّاة', cls: 'badge-approved' }
};

function rfqStatusBadge(status) {
    const s = RFQ_STATUS_LABELS[status] || { label: status, cls: 'badge-new' };
    return `<span class="badge-status ${s.cls}">${s.label}</span>`;
}

/* الصفحة المناسبة لمتابعة المناقصة من لوحة الصيدلية حسب حالتها الحالية */
function rfqPharmacyActionUrl(rfq) {
    switch (rfq.status) {
        case 'open': return 'offers-received.html?id=' + rfq.id;
        case 'closed':
        case 'pending_opening':
        case 'opened': return 'open-envelopes.html?id=' + rfq.id;
        case 'awarded': return 'tender-report.html?id=' + rfq.id;
        default: return 'manage-tenders.html';
    }
}

const QUOTE_STATUS_LABELS = {
    submitted: { label: 'قيد التقييم', cls: 'badge-new' },
    awarded: { label: 'فزت بالمناقصة 🏆', cls: 'badge-approved' },
    rejected: { label: 'لم يتم الاختيار', cls: 'badge-closed' },
    cancelled: { label: 'ملغى', cls: 'badge-closed' }
};

function quoteStatusBadge(status) {
    const s = QUOTE_STATUS_LABELS[status] || { label: status, cls: 'badge-new' };
    return `<span class="badge-status ${s.cls}">${s.label}</span>`;
}

/* ------------------------------------------------------------------ */
/* مساعد: تفادي حقن HTML عند عرض بيانات قادمة من المستخدم/الـ API      */
/* ------------------------------------------------------------------ */
function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}
