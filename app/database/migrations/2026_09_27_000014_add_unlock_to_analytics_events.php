<?php
use Illuminate\Database\Migrations\Migration; use Illuminate\Database\Schema\Blueprint; use Illuminate\Support\Facades\Schema;
return new class extends Migration { public function up(): void { Schema::table('analytics_events',function(Blueprint $t){$t->foreignId('unlock_id')->nullable()->after('experience_id')->constrained('unlocks')->nullOnDelete();$t->index('unlock_id');}); } public function down(): void { Schema::table('analytics_events',function(Blueprint $t){$t->dropForeign(['unlock_id']);$t->dropIndex(['unlock_id']);$t->dropColumn('unlock_id');}); } };
