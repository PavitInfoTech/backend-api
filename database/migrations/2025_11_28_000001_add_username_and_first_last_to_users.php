<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('username')->nullable()->unique();
        });

        // Try to copy existing name values into username
        if (Schema::hasColumn('users', 'name')) {
            DB::table('users')->whereNotNull('name')->update([
                'username' => DB::raw('name')
            ]);

            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('name');
            });
        }
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'name')) {
                $table->string('name')->nullable();
            }
        });

        // Copy username back to name if present
        if (Schema::hasColumn('users', 'username')) {
            DB::table('users')->whereNotNull('username')->update([
                'name' => DB::raw('username')
            ]);

            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn(['username', 'first_name', 'last_name']);
            });
        }
    }
};
