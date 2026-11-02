<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->string('template')->default('default')->after('slug');      // default | full-width
            $table->text('excerpt')->nullable()->after('template');
            $table->foreignId('og_image_id')->nullable()->constrained('media')->nullOnDelete();
            $table->boolean('noindex')->default(false);
            $table->boolean('show_in_sitemap')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            // Workflow: draft → in_review → published (or scheduled via future published_at) → archived
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_notes')->nullable();
        });

        Schema::create('page_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reason', 40);   // published | restored | manual
            $table->json('snapshot');       // page attributes + ordered blocks
            $table->timestamp('created_at')->useCurrent();
            $table->index(['page_id', 'created_at']);
        });

        Schema::table('media', function (Blueprint $table) {
            $table->string('title')->nullable()->after('original_name');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('caption')->nullable();
        });

        Schema::table('menu_items', function (Blueprint $table) {
            $table->string('link_type')->default('url')->after('label'); // url | page | route
            $table->string('route_name')->nullable()->after('url');
            $table->boolean('open_in_new_tab')->default(false);
        });

        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('summary', 300)->nullable();
            $table->longText('description')->nullable();
            $table->foreignId('image_id')->nullable()->constrained('media')->nullOnDelete();
            $table->dateTime('starts_at')->index();
            $table->dateTime('ends_at')->nullable();
            $table->string('location')->nullable();
            $table->boolean('is_online')->default(false);
            $table->string('registration_url')->nullable();
            $table->string('status')->default('draft')->index(); // draft | published | cancelled
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
        Schema::table('menu_items', fn (Blueprint $t) => $t->dropColumn(['link_type', 'route_name', 'open_in_new_tab']));
        Schema::table('media', fn (Blueprint $t) => $t->dropColumn(['title', 'width', 'height', 'caption']));
        Schema::dropIfExists('page_revisions');
        Schema::table('pages', function (Blueprint $table) {
            foreach (['og_image_id', 'created_by', 'updated_by', 'submitted_by', 'published_by'] as $fk) {
                $table->dropConstrainedForeignId($fk);
            }
            $table->dropColumn(['template', 'excerpt', 'noindex', 'show_in_sitemap', 'submitted_at', 'review_notes']);
        });
    }
};
