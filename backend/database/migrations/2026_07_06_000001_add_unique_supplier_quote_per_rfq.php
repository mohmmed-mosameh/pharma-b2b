<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * قبل فرض القيد، ننظّف 3 صفوف تكرار موجودة فعليًا بقاعدة البيانات
     * (اكتُشفت أثناء تدقيق أمني بتاريخ 2026-07-06):
     * - quote 5 (rfq 7) وquote 7 (rfq 9): مرفوضتان فعلًا تلقائيًا من
     *   منطق award() السابق (نفس المورد فاز بعرض والآخر رُفض) — تاريخية
     *   بحتة، نُرحّلها بالحذف الناعم فقط.
     * - quote 11 (rfq 14): تكرار حي (بيانات اختبار حسب تأكيد صاحب
     *   المشروع) على مناقصة لم تُفتح مظاريفها بعد — تقرر الإبقاء على
     *   العرض الأقدم (quote 10) ورفض هذا وحذفه ناعمًا.
     */
    private array $duplicateQuoteIdsToRetire = [5, 7, 11];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('quotes')
            ->whereIn('id', $this->duplicateQuoteIdsToRetire)
            ->update(['status' => 'rejected', 'deleted_at' => now()]);

        Schema::table('quotes', function (Blueprint $table) {
            // deleted_at مُضمَّن بالقيد كي لا تتعارض الصفوف المحذوفة ناعمًا
            // (deleted_at له قيمة زمنية مختلفة دومًا) مع الصف الحي الوحيد
            // المسموح به لكل (rfq_id, supplier_id) — بينما MySQL لا يدعم
            // فهارس فريدة جزئية (partial unique index) مباشرة.
            $table->unique(['rfq_id', 'supplier_id', 'deleted_at'], 'quotes_rfq_id_supplier_id_deleted_at_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropUnique('quotes_rfq_id_supplier_id_deleted_at_unique');
        });

        DB::table('quotes')->whereIn('id', [5, 7])->update(['deleted_at' => null]);
        DB::table('quotes')->where('id', 11)->update(['status' => 'submitted', 'deleted_at' => null]);
    }
};
