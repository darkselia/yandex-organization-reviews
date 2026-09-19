<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_snapshots', function (Blueprint $table): void {
            $table->foreignId('parse_run_id')
                ->nullable()
                ->after('organization_id')
                ->constrained()
                ->nullOnDelete();
            $table->unique('parse_run_id');
        });
    }

    public function down(): void
    {
        Schema::table('organization_snapshots', function (Blueprint $table): void {
            $table->dropUnique(['parse_run_id']);
            $table->dropConstrainedForeignId('parse_run_id');
        });
    }
};
