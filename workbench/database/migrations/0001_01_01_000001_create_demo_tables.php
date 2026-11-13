<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Replaces Testbench's default users table with a phone-based one.
     */
    public function up(): void
    {
        Schema::dropIfExists('users');

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('phone')->unique();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        (require __DIR__.'/../../../database/migrations/create_connex_tables.php.stub')->up();
    }
};
