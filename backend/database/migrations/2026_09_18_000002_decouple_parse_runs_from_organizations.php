<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parse_runs', function (Blueprint $table): void {
            $table->text('source_url')->nullable()->after('organization_id');
            $table->text('normalized_url')->nullable()->after('source_url');
            $table->string('source_external_id')->nullable()->after('normalized_url');
            $table->index(['source_external_id', 'status']);
        });

        DB::table('parse_runs')
            ->orderBy('id')
            ->eachById(function (object $parseRun): void {
                $organization = DB::table('organizations')->find($parseRun->organization_id);

                if ($organization === null) {
                    return;
                }

                DB::table('parse_runs')
                    ->where('id', $parseRun->id)
                    ->update([
                        'source_url' => $organization->source_url,
                        'normalized_url' => $organization->normalized_url,
                        'source_external_id' => $organization->external_id,
                    ]);
            });

        Schema::table('parse_runs', function (Blueprint $table): void {
            $table->dropForeign(['organization_id']);
            $table->foreignId('organization_id')->nullable()->change();
            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->cascadeOnDelete();
        });

        $unconfirmedIds = DB::table('organizations')
            ->whereNull('last_synced_at')
            ->pluck('id');

        if ($unconfirmedIds->isNotEmpty()) {
            DB::table('parse_runs')
                ->whereIn('organization_id', $unconfirmedIds)
                ->update(['organization_id' => null]);

            DB::table('organizations')->whereIn('id', $unconfirmedIds)->delete();
        }
    }

    public function down(): void
    {
        DB::table('parse_runs')->whereNull('organization_id')->delete();

        Schema::table('parse_runs', function (Blueprint $table): void {
            $table->dropForeign(['organization_id']);
            $table->foreignId('organization_id')->nullable(false)->change();
            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->cascadeOnDelete();
            $table->dropIndex(['source_external_id', 'status']);
            $table->dropColumn(['source_url', 'normalized_url', 'source_external_id']);
        });
    }
};
