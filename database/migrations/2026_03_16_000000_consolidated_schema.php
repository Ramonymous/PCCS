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
        // Core Laravel tables
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('avatar')->nullable();
            $table->string('google2fa_secret')->nullable();
            $table->boolean('google2fa_enabled')->default(false);
            $table->timestamp('google2fa_enabled_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        Schema::create('cache', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->integer('expiration');
        });

        Schema::create('cache_locks', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('owner');
            $table->integer('expiration');
        });

        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });

        Schema::create('job_batches', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->integer('total_jobs');
            $table->integer('pending_jobs');
            $table->integer('failed_jobs');
            $table->longText('failed_job_ids');
            $table->mediumText('options')->nullable();
            $table->integer('cancelled_at')->nullable();
            $table->integer('created_at');
            $table->integer('finished_at')->nullable();
        });

        Schema::create('failed_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        // Permission tables (Spatie)
        $teams = config('permission.teams');
        $tableNames = config('permission.table_names');
        $columnNames = config('permission.column_names');
        $pivotRole = $columnNames['role_pivot_key'] ?? 'role_id';
        $pivotPermission = $columnNames['permission_pivot_key'] ?? 'permission_id';

        throw_if(empty($tableNames), Exception::class, 'Error: config/permission.php not loaded. Run [php artisan config:clear] and try again.');
        throw_if($teams && empty($columnNames['team_foreign_key'] ?? null), Exception::class, 'Error: team_foreign_key on config/permission.php not loaded. Run [php artisan config:clear] and try again.');

        Schema::create($tableNames['permissions'], static function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });

        Schema::create($tableNames['roles'], static function (Blueprint $table) use ($teams, $columnNames) {
            $table->bigIncrements('id');
            if ($teams || config('permission.testing')) {
                $table->unsignedBigInteger($columnNames['team_foreign_key'])->nullable();
                $table->index($columnNames['team_foreign_key'], 'roles_team_foreign_key_index');
            }
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            if ($teams || config('permission.testing')) {
                $table->unique([$columnNames['team_foreign_key'], 'name', 'guard_name']);
            } else {
                $table->unique(['name', 'guard_name']);
            }
        });

        Schema::create($tableNames['model_has_permissions'], static function (Blueprint $table) use ($tableNames, $columnNames, $pivotPermission, $teams) {
            $table->unsignedBigInteger($pivotPermission);
            $table->string('model_type');
            $table->unsignedBigInteger($columnNames['model_morph_key']);
            $table->index([$columnNames['model_morph_key'], 'model_type'], 'model_has_permissions_model_id_model_type_index');

            $table->foreign($pivotPermission)
                ->references('id')
                ->on($tableNames['permissions'])
                ->onDelete('cascade');

            if ($teams) {
                $table->unsignedBigInteger($columnNames['team_foreign_key']);
                $table->index($columnNames['team_foreign_key'], 'model_has_permissions_team_foreign_key_index');

                $table->primary([
                    $columnNames['team_foreign_key'],
                    $pivotPermission,
                    $columnNames['model_morph_key'],
                    'model_type',
                ], 'model_has_permissions_permission_model_type_primary');
            } else {
                $table->primary([
                    $pivotPermission,
                    $columnNames['model_morph_key'],
                    'model_type',
                ], 'model_has_permissions_permission_model_type_primary');
            }
        });

        Schema::create($tableNames['model_has_roles'], static function (Blueprint $table) use ($tableNames, $columnNames, $pivotRole, $teams) {
            $table->unsignedBigInteger($pivotRole);
            $table->string('model_type');
            $table->unsignedBigInteger($columnNames['model_morph_key']);
            $table->index([$columnNames['model_morph_key'], 'model_type'], 'model_has_roles_model_id_model_type_index');

            $table->foreign($pivotRole)
                ->references('id')
                ->on($tableNames['roles'])
                ->onDelete('cascade');

            if ($teams) {
                $table->unsignedBigInteger($columnNames['team_foreign_key']);
                $table->index($columnNames['team_foreign_key'], 'model_has_roles_team_foreign_key_index');

                $table->primary([
                    $columnNames['team_foreign_key'],
                    $pivotRole,
                    $columnNames['model_morph_key'],
                    'model_type',
                ], 'model_has_roles_role_model_type_primary');
            } else {
                $table->primary([
                    $pivotRole,
                    $columnNames['model_morph_key'],
                    'model_type',
                ], 'model_has_roles_role_model_type_primary');
            }
        });

        Schema::create($tableNames['role_has_permissions'], static function (Blueprint $table) use ($tableNames, $pivotRole, $pivotPermission) {
            $table->unsignedBigInteger($pivotPermission);
            $table->unsignedBigInteger($pivotRole);

            $table->foreign($pivotPermission)
                ->references('id')
                ->on($tableNames['permissions'])
                ->onDelete('cascade');

            $table->foreign($pivotRole)
                ->references('id')
                ->on($tableNames['roles'])
                ->onDelete('cascade');

            $table->primary([$pivotPermission, $pivotRole], 'role_has_permissions_permission_id_role_id_primary');
        });

        // Application tables
        Schema::create('customers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index('code');
            $table->softDeletes();
        });

        Schema::create('suppliers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index('code');
            $table->softDeletes();
        });

        Schema::create('finish_goods', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('customer_id');
            $table->string('part_number');
            $table->string('part_name');
            $table->string('alias')->nullable();
            $table->string('model')->nullable();
            $table->string('variant')->nullable();
            $table->integer('stock')->default(0);
            $table->string('wh_address')->nullable();
            $table->enum('type', ['ASSY', 'DIRECT'])->default('ASSY');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
            $table->index(['customer_id', 'part_number'], 'idx_customer_part');
            $table->index('part_number', 'idx_part_number');
            $table->index('type', 'idx_type');
            $table->index('is_active', 'idx_is_active');
        });

        Schema::create('pccs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('from')->nullable();
            $table->string('to')->nullable();
            $table->string('supply_address')->nullable();
            $table->string('next_supply_address')->nullable();
            $table->string('ms_id')->nullable();
            $table->string('inventory_category')->nullable();
            $table->string('part_no');
            $table->string('part_name')->nullable();
            $table->string('color_code')->nullable();
            $table->string('ps_code')->nullable();
            $table->string('order_class')->nullable();
            $table->string('prod_seq_no')->nullable();
            $table->string('kd_lot_no')->nullable();
            $table->integer('ship')->default(0);
            $table->string('slip_no', 12);
            $table->string('slip_barcode')->unique()->index();
            $table->boolean('printed')->default(false);
            $table->date('date');
            $table->time('time');
            $table->string('hns')->nullable();
            $table->timestamps();
            $table->index('slip_barcode');
            $table->index('part_no');
            $table->softDeletes();
        });

        Schema::create('pcc_cpps', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('finish_good_id');
            $table->enum('stage', ['PRODUCTION CHECK', 'PDI CHECK', 'DELIVERY', 'ALL'])
                ->default('ALL')
                ->comment('Stage where this CCP should be checked');
            $table->integer('revision')->default(1);
            $table->string('check_point_img')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('stage');
            $table->foreign('finish_good_id')->references('id')->on('finish_goods')->onDelete('cascade');
        });

        Schema::create('hpm_schedules', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('slip_number')->unique();
            $table->date('schedule_date')->nullable();
            $table->date('adjusted_date')->nullable();
            $table->time('schedule_time')->nullable();
            $table->time('adjusted_time')->nullable();
            $table->integer('delivery_quantity')->default(0);
            $table->integer('adjustment_quantity')->default(0);
            $table->timestamps();
        });

        Schema::create('hpm_pcc_traces', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('pcc_id');
            $table->enum('event_type', ['PRODUCTION CHECK', 'RECEIVED', 'PDI CHECK', 'DELIVERY']);
            $table->timestamp('event_timestamp');
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->foreign('pcc_id')->references('id')->on('pccs')->onDelete('cascade');
            $table->index('pcc_id');
            $table->index('event_type');
        });

        Schema::create('hpm_pcc_events', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('pcc_trace_id');
            $table->unsignedBigInteger('event_users');
            $table->enum('event_type', ['PRODUCTION CHECK', 'RECEIVED', 'PDI CHECK', 'DELIVERY']);
            $table->timestamp('event_timestamp');
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->foreign('pcc_trace_id')->references('id')->on('hpm_pcc_traces')->onDelete('cascade');
            $table->foreign('event_users')->references('id')->on('users')->onDelete('cascade');
            $table->index('pcc_trace_id');
            $table->index('event_type');
        });

        Schema::create('scanner_locks', function (Blueprint $table) {
            $table->id();
            $table->string('scanner_identifier');
            $table->timestamp('locked_until')->nullable();
            $table->string('reason')->nullable();
            $table->unsignedBigInteger('locked_by_user_id')->nullable();
            $table->text('metadata')->nullable();
            $table->timestamps();

            $table->index('locked_until');
            $table->unique(['scanner_identifier', 'locked_by_user_id'], 'scanner_user_unique');
            $table->foreign('locked_by_user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
        });

        // Web push subscription (webpush config)
        Schema::connection(config('webpush.database_connection'))->create(config('webpush.table_name'), function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->morphs('subscribable');
            $table->string('endpoint', 500)->unique();
            $table->string('public_key')->nullable();
            $table->string('auth_token')->nullable();
            $table->string('content_encoding')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection(config('webpush.database_connection'))->dropIfExists(config('webpush.table_name'));

        Schema::dropIfExists('scanner_locks');
        Schema::dropIfExists('hpm_pcc_events');
        Schema::dropIfExists('hpm_pcc_traces');
        Schema::dropIfExists('hpm_schedules');
        Schema::dropIfExists('pcc_cpps');
        Schema::dropIfExists('pccs');
        Schema::dropIfExists('finish_goods');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('customers');

        Schema::dropIfExists('role_has_permissions');
        Schema::dropIfExists('model_has_roles');
        Schema::dropIfExists('model_has_permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('permissions');

        Schema::dropIfExists('notifications');
        Schema::dropIfExists('failed_jobs');
        Schema::dropIfExists('job_batches');
        Schema::dropIfExists('jobs');
        Schema::dropIfExists('cache_locks');
        Schema::dropIfExists('cache');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
