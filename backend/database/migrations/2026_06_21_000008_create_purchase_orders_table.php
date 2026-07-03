<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rfq_id')
                ->constrained('rfqs')
                ->cascadeOnDelete();
            $table->foreignId('quote_id')
                ->constrained('quotes')
                ->cascadeOnDelete();
            $table->foreignId('supplier_id')
                ->constrained('organizations')
                ->cascadeOnDelete();
            $table->decimal('total_amount', 10, 2);
            $table->enum('status', ['pending', 'in_progress', 'completed', 'cancelled'])
                ->default('pending');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
