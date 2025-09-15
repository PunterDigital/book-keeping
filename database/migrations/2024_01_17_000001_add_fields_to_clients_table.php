<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('email')->nullable()->after('contact_name');
            $table->string('phone')->nullable()->after('email');
            $table->string('city')->nullable()->after('address');
            $table->string('postal_code')->nullable()->after('city');
            $table->string('country')->default('Czech Republic')->after('postal_code');
            $table->renameColumn('tax_id', 'company_id');
            $table->text('notes')->nullable()->after('company_id');
            $table->boolean('is_active')->default(true)->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['email', 'phone', 'city', 'postal_code', 'country', 'notes', 'is_active']);
            $table->renameColumn('company_id', 'tax_id');
        });
    }
};