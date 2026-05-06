<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use XetaSuite\Models\Maintenance;
use XetaSuite\Models\Material;
use XetaSuite\Models\Site;
use XetaSuite\Models\User;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('maintenances', function (Blueprint $table): void {
            $table->id();

            $table->foreignIdFor(Site::class)
                ->constrained()
                ->restrictOnDelete();

            $table->foreignIdFor(Material::class)
                ->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->string('material_name', 100)
                ->nullable()
                ->comment('The name of the material if the material is deleted.');

            $table->foreignIdFor(User::class, 'created_by_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->string('created_by_name', 100)
                ->nullable()
                ->comment('The name of the user who created the maintenance if the user is deleted.');

            $table->foreignIdFor(User::class, 'edited_by_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->mediumText('description');

            $table->string('type', 50)->default('corrective')->index();
            $table->string('realization', 50)->default('external')->index();
            $table->string('status', 50)->default('planned')->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('resolved_at')->nullable();

            $table->integer('incident_count')->default(0);

            $table->timestamps();
        });

        Schema::table('incidents', function (Blueprint $table): void {
            $table->foreignIdFor(Maintenance::class)
                ->after('material_name')
                ->nullable()
                ->constrained()
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('maintenances');
    }
};
