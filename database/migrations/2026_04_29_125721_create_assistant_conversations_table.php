<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('assistant_conversations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('site_id')->nullable()->constrained('sites')->nullOnDelete();
            $table->string('title')->nullable();
            $table->string('provider')->nullable();
            $table->string('model')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'site_id', 'last_message_at']);
        });

        Schema::create('assistant_conversation_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('conversation_id')
                ->constrained('assistant_conversations')
                ->cascadeOnDelete();
            $table->string('role'); // user | assistant | tool
            $table->text('content')->nullable();
            $table->jsonb('tool_calls')->nullable();
            $table->unsignedSmallInteger('iteration')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['conversation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_conversation_messages');
        Schema::dropIfExists('assistant_conversations');
    }
};
