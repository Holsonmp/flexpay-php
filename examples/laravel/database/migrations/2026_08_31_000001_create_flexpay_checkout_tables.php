<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('flexpay_checkout_sessions', function (Blueprint $table): void {
            $table->id();
            $table->string('checkout_id', 80)->unique();
            $table->string('merchant_reference', 128)->unique();
            $table->string('provider_transaction_id', 128)->nullable()->unique();
            $table->string('status', 32)->index();
            $table->unsignedInteger('version');
            $table->mediumText('payload_json');
            $table->timestamps();
        });
        Schema::create('flexpay_checkout_transitions', function (Blueprint $table): void {
            $table->id();
            $table->string('checkout_id', 80);
            $table->string('from', 32);
            $table->string('to', 32);
            $table->string('occurred_at', 40);
            $table->string('source', 32);
            $table->unsignedInteger('version');
            $table->unique(['checkout_id', 'version']);
        });
        Schema::create('flexpay_checkout_callbacks', function (Blueprint $table): void {
            $table->string('event_id', 80)->primary();
            $table->string('checkout_id', 80);
            $table->timestamp('processed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flexpay_checkout_callbacks');
        Schema::dropIfExists('flexpay_checkout_transitions');
        Schema::dropIfExists('flexpay_checkout_sessions');
    }
};
