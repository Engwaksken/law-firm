<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('practice_areas', function (Blueprint $table) {
            $table->string('summary', 300)->nullable()->after('slug');
            $table->longText('body')->nullable()->after('description');
            $table->string('icon')->nullable();
            $table->string('image_path')->nullable();
            $table->json('faqs')->nullable();
            $table->boolean('is_featured')->default(false)->index();
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 300)->nullable();
        });

        Schema::table('advocates', function (Blueprint $table) {
            $table->foreignId('office_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->json('qualifications')->nullable();
            $table->text('experience')->nullable();
            $table->json('memberships')->nullable();
            // Publication requires both an explicit opt-in and verification of credentials (spec §5.4).
            $table->boolean('is_public')->default(false)->index();
            $table->timestamp('credentials_verified_at')->nullable();
            $table->foreignId('credentials_verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('accepts_consultations')->default(false);
            $table->boolean('show_email')->default(false);
            $table->boolean('show_phone')->default(false);
            $table->boolean('is_featured')->default(false)->index();
        });

        Schema::table('articles', function (Blueprint $table) {
            $table->string('type')->default('article')->index()->after('slug');
            $table->string('attachment_path')->nullable();
            $table->boolean('is_featured')->default(false)->index();
            $table->json('tags')->nullable();
        });

        Schema::table('consultations', function (Blueprint $table) {
            $table->string('reference', 20)->nullable()->unique()->after('id');
            $table->string('consultation_type')->default('in_person');
            $table->date('preferred_date')->nullable();
            $table->string('preferred_time')->nullable();
            $table->timestamp('privacy_accepted_at')->nullable();
        });

        Schema::table('contact_inquiries', function (Blueprint $table) {
            $table->timestamp('privacy_accepted_at')->nullable();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::create('careers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('office_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('type')->default('job')->index(); // job | internship | pupillage
            $table->string('employment_type')->nullable();   // full-time, part-time, contract
            $table->string('location')->nullable();
            $table->string('summary', 300)->nullable();
            $table->longText('description');
            $table->longText('requirements')->nullable();
            $table->string('status')->default('draft')->index(); // draft | published | closed
            $table->timestamp('published_at')->nullable()->index();
            $table->date('closes_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('career_applications', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 20)->unique();
            $table->foreignId('career_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('email')->index();
            $table->string('phone')->nullable();
            $table->text('cover_letter')->nullable();
            // Private disk only; downloaded through an authorised admin action.
            $table->string('cv_path');
            $table->string('cv_original_name');
            $table->string('status')->default('received')->index();
            $table->text('internal_notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('privacy_accepted_at');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('testimonials', function (Blueprint $table) {
            $table->id();
            $table->string('author_name');
            $table->string('author_title')->nullable();
            $table->text('quote');
            // Only shown when the client consented and staff approved (spec §5.1).
            $table->boolean('consent_obtained')->default(false);
            $table->boolean('is_approved')->default(false)->index();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('newsletter_subscribers', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('name')->nullable();
            $table->string('token', 64)->unique();
            $table->timestamp('confirmed_at')->nullable()->index();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_subscribers');
        Schema::dropIfExists('testimonials');
        Schema::dropIfExists('career_applications');
        Schema::dropIfExists('careers');

        Schema::table('contact_inquiries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('handled_by');
            $table->dropColumn('privacy_accepted_at');
        });
        Schema::table('consultations', function (Blueprint $table) {
            $table->dropUnique(['reference']);
            $table->dropColumn(['reference', 'consultation_type', 'preferred_date', 'preferred_time', 'privacy_accepted_at']);
        });
        Schema::table('articles', function (Blueprint $table) {
            $table->dropColumn(['type', 'attachment_path', 'is_featured', 'tags']);
        });
        Schema::table('advocates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('credentials_verified_by');
            $table->dropConstrainedForeignId('office_id');
            $table->dropColumn(['qualifications', 'experience', 'memberships', 'is_public', 'credentials_verified_at', 'accepts_consultations', 'show_email', 'show_phone', 'is_featured']);
        });
        Schema::table('practice_areas', function (Blueprint $table) {
            $table->dropColumn(['summary', 'body', 'icon', 'image_path', 'faqs', 'is_featured', 'meta_title', 'meta_description']);
        });
    }
};
