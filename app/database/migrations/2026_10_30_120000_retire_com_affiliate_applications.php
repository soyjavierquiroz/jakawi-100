<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Retired acquisition data is not a financial ledger. Preserve any unexpected records.
        if (Schema::hasTable('program_applications') && ! DB::table('program_applications')->exists()) {
            Schema::drop('program_applications');
        }
    }

    public function down(): void
    {
        // Intentionally no historical schema/data recreation. Restore code forward if needed.
    }
};
