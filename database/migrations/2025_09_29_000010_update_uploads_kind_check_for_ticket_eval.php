<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // For PostgreSQL: extend the CHECK constraint to allow 'ticket_eval'
        DB::statement("ALTER TABLE uploads DROP CONSTRAINT IF EXISTS uploads_kind_check;");
        DB::statement("ALTER TABLE uploads ADD CONSTRAINT uploads_kind_check CHECK (kind IN ('resolved','actual_end','ticket_eval'));");
    }

    public function down(): void
    {
        // Revert to original constraint without 'ticket_eval'
        DB::statement("ALTER TABLE uploads DROP CONSTRAINT IF EXISTS uploads_kind_check;");
        DB::statement("ALTER TABLE uploads ADD CONSTRAINT uploads_kind_check CHECK (kind IN ('resolved','actual_end'));");
    }
};

