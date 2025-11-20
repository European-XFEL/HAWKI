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
        Schema::create('performance', function (Blueprint $table) {
            $table->bigIncrements('id')->primary();
            $table->integer('response_ms')->nullable();
            $table->string('model', 32)->nullable();
            $table->tinyInteger('streaming')->nullable();
            $table->string('measured_on', 32)->nullable();
            $table->string('context', 255)->nullable();
            $table->string('group_id', 32)->nullable();
            $table->string('attachments', 32)->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('performance');
    }
};
