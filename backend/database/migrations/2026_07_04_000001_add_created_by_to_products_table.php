<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * يميّز المنتجات التي أضافها مستخدم (صيدلية/مورد) عن الأصناف
     * الافتراضية المزروعة (seeded) التي تبقى created_by = NULL ولا يجوز
     * حذفها. فقط من أضاف الصنف يقدر يحذفه لاحقًا.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('created_by')
                ->nullable()
                ->after('supplier_id')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
        });
    }
};
