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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Trade name
            $table->string('generic_name')->nullable(); // Scientific name
            $table->string('company')->nullable(); // Manufacturer
            $table->string('category');
            $table->string('form')->nullable(); // e.g. tablet, syrup
            $table->string('strength')->nullable(); // e.g. 500mg
            $table->string('image')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
