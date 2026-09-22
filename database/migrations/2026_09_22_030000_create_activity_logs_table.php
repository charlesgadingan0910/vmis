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
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();

            // Who performed the action. Kept nullable + a plain (not cascading)
            // foreign key so a log entry survives even if the acting account is
            // ever removed — an audit trail that disappears along with the
            // account it's supposed to be watching would defeat the purpose.
            // user_name is a point-in-time snapshot for the same reason: it
            // keeps reading correctly even if that user's name changes later,
            // and stays populated for a failed-login attempt where no account
            // could be matched at all.
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_name')->nullable();

            // e.g. created | updated | deleted | login | login_failed | logout
            $table->string('action', 30);

            // Human-readable grouping shown in the module filter — e.g.
            // "Vehicle", "Driver", "Maintenance Record", "User", "Authentication".
            $table->string('module', 60);

            // Which record this entry is about, when it's about one.
            $table->string('subject_type', 150)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();

            $table->text('description');

            // Optional before/after payload for updates, a full snapshot for
            // deletes, or the submitted values for creates — whatever a
            // reviewer would need to see exactly what changed.
            $table->json('changes')->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();

            // No updated_at — a log entry is written once and never modified.
            $table->timestamp('created_at')->nullable();

            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();

            // The System Activity Logs page filters/sorts on all of these —
            // without indexes, every filtered view does a full table scan,
            // and this table is expected to grow continuously by design.
            $table->index('user_id');
            $table->index('module');
            $table->index('action');
            $table->index('subject_type');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
