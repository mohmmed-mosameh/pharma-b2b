<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')
                ->unique()
                ->constrained('purchase_orders')
                ->cascadeOnDelete();
            $table->foreignId('supplier_id')
                ->constrained('organizations')
                ->cascadeOnDelete();
            $table->foreignId('pharmacy_id')
                ->constrained('organizations')
                ->cascadeOnDelete();
            $table->foreignId('rated_by')
                ->constrained('users')
                ->cascadeOnDelete();
            // تقييم من 1 إلى 5 بدقة نصف نقطة (مثل 3.5)
            $table->decimal('rating', 3, 1);
            $table->text('comment')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // معايير التقييم الوزني لكل مناقصة: يسمح للصيدلية بتخصيص
        // الأوزان لكل مناقصة على حدة، مع قيم افتراضية تطابق الـ UI.
        Schema::table('rfqs', function (Blueprint $table) {
            $table->unsignedTinyInteger('score_weight_price')
                ->default(50)
                ->after('opening_at')
                ->comment('وزن معيار أقل سعر من 100');
            $table->unsignedTinyInteger('score_weight_delivery')
                ->default(30)
                ->after('score_weight_price')
                ->comment('وزن معيار أسرع توريد من 100');
            $table->unsignedTinyInteger('score_weight_rating')
                ->default(15)
                ->after('score_weight_delivery')
                ->comment('وزن معيار أعلى تقييم سابق من 100');
            $table->unsignedTinyInteger('score_weight_verified')
                ->default(5)
                ->after('score_weight_rating')
                ->comment('وزن معيار شركة معتمدة (is_verified) من 100');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_ratings');

        Schema::table('rfqs', function (Blueprint $table) {
            $table->dropColumn([
                'score_weight_price',
                'score_weight_delivery',
                'score_weight_rating',
                'score_weight_verified',
            ]);
        });
    }
};
