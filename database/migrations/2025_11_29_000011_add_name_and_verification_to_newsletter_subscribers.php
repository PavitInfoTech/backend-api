<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('newsletter_subscribers', function (Blueprint $table) {
            $table->string('name')->nullable()->after('id');
            $table->string('verification_token')->nullable()->after('email');
            $table->timestamp('verified_at')->nullable()->after('verification_token');
            $table->string('unsubscribe_token')->unique()->nullable()->after('verified_at');
        });
    }

    public function down()
    {
        Schema::table('newsletter_subscribers', function (Blueprint $table) {
            $table->dropColumn(['name', 'verification_token', 'verified_at', 'unsubscribe_token']);
        });
    }
};
