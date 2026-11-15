
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Platform administration
            $table->boolean('is_platform_admin')
                ->default(false)
                ->after('password');

            // Additional profile information
            $table->string('phone', 30)
                ->nullable()
                ->after('is_platform_admin');

            $table->string('avatar_path')
                ->nullable()
                ->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'is_platform_admin',
                'phone',
                'avatar_path',
            ]);
        });
    }
};
