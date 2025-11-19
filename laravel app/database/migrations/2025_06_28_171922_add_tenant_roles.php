<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop unique index if exists (Laravel doesn't provide easy way to check indexes; you might want to handle exceptions)
        Schema::table('roles', function (Blueprint $table) {
            try {
                $table->dropUnique('roles_name_guard_name_unique');
            } catch (\Throwable $e) {}
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->boolean('is_tenant_role')->default(false);
            $table->foreignId('tenant_id')->nullable()->constrained('tenants');
        });

        if (DB::getDriverName() === 'mysql') {
            // Add unique indexes with prefix length for MySQL
            DB::statement('ALTER TABLE roles ADD UNIQUE roles_name_guard_name_unique (name(100), guard_name(100))');
            DB::statement('ALTER TABLE roles ADD UNIQUE roles_tenant_name_guard_name_unique (tenant_id, name(100), guard_name(100))');
        } else {
            // For other DBs, just use Laravel schema builder
            Schema::table('roles', function (Blueprint $table) {
                $table->unique(['name', 'guard_name'], 'roles_name_guard_name_unique');
                $table->unique(['tenant_id', 'name', 'guard_name'], 'roles_tenant_name_guard_name_unique');
            });
        }

        DB::table('roles')
            ->where('name', 'like', 'tenancy:%')
            ->update([
                'name' => DB::raw("REPLACE(name, 'tenancy:', '')"),
                'tenant_id' => null,
                'is_tenant_role' => true,
            ]);

        Schema::table('permissions', function (Blueprint $table) {
            $table->boolean('is_tenant_permission')->default(false);
        });
    }

   public function down(): void
{
    $connection = DB::getDriverName();

    // Step 1: Drop foreign key first
    Schema::table('roles', function (Blueprint $table) {
        if (Schema::hasColumn('roles', 'tenant_id')) {
            $table->dropForeign(['tenant_id']);
        }
    });

    // Step 2: Drop indexes after FK is gone
    if ($connection === 'mysql') {
        // Check if index exists (optional safety)
        try {
            DB::statement('ALTER TABLE roles DROP INDEX roles_tenant_name_guard_name_unique');
        } catch (\Exception $e) {}
        try {
            DB::statement('ALTER TABLE roles DROP INDEX roles_name_guard_name_unique');
        } catch (\Exception $e) {}
    } else {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropUnique('roles_tenant_name_guard_name_unique');
            $table->dropUnique('roles_name_guard_name_unique');
        });
    }

    // Step 3: Drop columns
    Schema::table('roles', function (Blueprint $table) {
        $table->dropColumn(['tenant_id', 'is_tenant_role']);
        $table->unique(['name', 'guard_name'], 'roles_name_guard_name_unique');
    });

    Schema::table('permissions', function (Blueprint $table) {
        $table->dropColumn('is_tenant_permission');
    });
}

};
