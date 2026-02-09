<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds 'ollama' to the generator_instances.type enum.
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement("ALTER TABLE generator_instances MODIFY COLUMN type ENUM('stable_diffusion_forge', 'comfyui', 'ollama') NOT NULL DEFAULT 'stable_diffusion_forge'");
        } elseif ($driver === 'pgsql') {
            // PostgreSQL: Laravel enum() creates a CHECK constraint, not a native ENUM type.
            // Drop the old constraint and re-add with the new value.
            DB::statement("ALTER TABLE generator_instances DROP CONSTRAINT IF EXISTS generator_instances_type_check");
            DB::statement("ALTER TABLE generator_instances ADD CONSTRAINT generator_instances_type_check CHECK (type IN ('stable_diffusion_forge', 'comfyui', 'ollama'))");
        }

        // For SQLite: the original migration already includes 'ollama' in the enum,
        // so fresh databases (including test databases with RefreshDatabase) will
        // have the correct CHECK constraint from the start. No action needed.
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement("ALTER TABLE generator_instances MODIFY COLUMN type ENUM('stable_diffusion_forge', 'comfyui') NOT NULL DEFAULT 'stable_diffusion_forge'");
        } elseif ($driver === 'pgsql') {
            DB::statement("ALTER TABLE generator_instances DROP CONSTRAINT IF EXISTS generator_instances_type_check");
            DB::statement("ALTER TABLE generator_instances ADD CONSTRAINT generator_instances_type_check CHECK (type IN ('stable_diffusion_forge', 'comfyui'))");
        }
    }
};

