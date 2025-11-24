<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('call_sessions')) {
            Schema::create('call_sessions', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->string('call_sid', 64)->unique();
                $table->string('from_number', 32)->nullable();
                $table->string('to_number', 32)->nullable();
                $table->enum('status', ['initiated', 'active', 'completed', 'failed'])->default('initiated');
                $table->enum('direction', ['inbound', 'outbound'])->default('inbound');
                $table->string('assistant_thread_id', 128)->nullable();
                $table->dateTime('started_at')->nullable();
                $table->dateTime('ended_at')->nullable();
                $table->integer('twilio_billable_sec')->default(0);
                $table->string('hangup_cause', 64)->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->foreign('tenant_id')
                    ->references('id')
                    ->on('tenants')
                    ->onDelete('cascade');

                $table->index(['tenant_id', 'started_at']);
                $table->index('call_sid');
            });

            return;
        }

        Schema::table('call_sessions', function (Blueprint $table) {
            if (!Schema::hasColumn('call_sessions', 'tenant_id')) {
                $table->unsignedBigInteger('tenant_id')->nullable()->after('id');
            }

            if (!Schema::hasColumn('call_sessions', 'call_sid')) {
                $table->string('call_sid', 64)->after('tenant_id');
            }

            if (!Schema::hasColumn('call_sessions', 'from_number')) {
                $table->string('from_number', 32)->nullable();
            }

            if (!Schema::hasColumn('call_sessions', 'to_number')) {
                $table->string('to_number', 32)->nullable();
            }

            if (!Schema::hasColumn('call_sessions', 'status')) {
                $table->enum('status', ['initiated', 'active', 'completed', 'failed'])->default('initiated');
            }

            if (!Schema::hasColumn('call_sessions', 'direction')) {
                $table->enum('direction', ['inbound', 'outbound'])->default('inbound');
            }

            if (!Schema::hasColumn('call_sessions', 'assistant_thread_id')) {
                $table->string('assistant_thread_id', 128)->nullable();
            }

            if (!Schema::hasColumn('call_sessions', 'started_at')) {
                $table->dateTime('started_at')->nullable();
            }

            if (!Schema::hasColumn('call_sessions', 'ended_at')) {
                $table->dateTime('ended_at')->nullable();
            }

            if (!Schema::hasColumn('call_sessions', 'twilio_billable_sec')) {
                $table->integer('twilio_billable_sec')->default(0);
            }

            if (!Schema::hasColumn('call_sessions', 'hangup_cause')) {
                $table->string('hangup_cause', 64)->nullable();
            }

            if (!Schema::hasColumn('call_sessions', 'meta')) {
                $table->json('meta')->nullable();
            }

            if (!Schema::hasColumns('call_sessions', ['created_at', 'updated_at'])) {
                $table->timestamps();
            }
        });

        if (Schema::hasTable('tenants') && Schema::hasColumn('call_sessions', 'tenant_id')) {
            Schema::table('call_sessions', function (Blueprint $table) {
                if (!Schema::hasColumn('call_sessions', 'tenant_id')) {
                    return;
                }

                $table->foreign('tenant_id')
                    ->references('id')
                    ->on('tenants')
                    ->onDelete('cascade');
            });
        }

        if (Schema::hasColumn('call_sessions', 'call_sid')) {
            Schema::table('call_sessions', function (Blueprint $table) {
                $table->index('call_sid');
            });
        }

        if (Schema::hasColumns('call_sessions', ['tenant_id', 'started_at'])) {
            Schema::table('call_sessions', function (Blueprint $table) {
                $table->index(['tenant_id', 'started_at']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Intentionally left blank to avoid dropping existing data on rollback.
    }
};
