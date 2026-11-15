
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();

            // General information
            $table->string('name', 150);
            $table->string('slug', 180)->unique();

            // Contact information
            $table->string('email', 150)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('website')->nullable();

            // Localization settings
            $table->char('country_code', 2)->nullable();
            $table->char('currency', 3)->default('XOF');
            $table->string('timezone', 60)->default('Africa/Abidjan');

            // Account status
            $table->string('status', 30)->default('active')->index();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
