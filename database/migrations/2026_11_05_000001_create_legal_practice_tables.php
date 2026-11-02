<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 4: legal practice. Confidential free-text columns (notes, descriptions,
 * identification numbers) are encrypted at the model layer, hence `text`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('reference', 20)->nullable()->unique()->after('id');
            $table->string('type')->default('individual')->after('reference');   // individual | corporate
            $table->string('status')->default('prospect')->index();             // prospect | active | inactive | former
            $table->string('category')->nullable()->index();
            $table->string('registration_number')->nullable();                  // corporate clients
            $table->string('id_type')->nullable();                              // national_id | passport | ...
            $table->text('id_number')->nullable();                              // encrypted
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('country')->default('Uganda');
            $table->string('normalized_name')->nullable()->index();             // conflict search
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('office_id')->nullable()->constrained()->nullOnDelete();
            $table->string('intake_source')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
        });
        // Widen notes for encryption.
        Schema::table('clients', fn (Blueprint $table) => $table->longText('notes')->nullable()->change());

        Schema::create('client_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('normalized_name')->index();
            $table->string('position')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->boolean('is_authorized_representative')->default(false);
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
        });

        Schema::create('courts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type')->default('court');   // court | tribunal | arbitration | other
            $table->string('division')->nullable();
            $table->string('location')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['name', 'division']);
        });

        Schema::create('matters', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 20)->unique();
            $table->string('title');
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->foreignId('practice_area_id')->nullable()->constrained()->nullOnDelete();
            $table->string('case_type')->nullable();
            $table->foreignId('court_id')->nullable()->constrained()->nullOnDelete();
            $table->string('jurisdiction')->nullable();
            $table->string('case_number')->nullable()->index();
            $table->date('filing_date')->nullable();
            $table->foreignId('lead_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('office_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('intake')->index();
            $table->string('priority')->default('normal')->index();
            // Ethical wall: only explicitly assigned users may see a restricted matter.
            $table->boolean('is_restricted')->default(false)->index();
            $table->longText('description')->nullable();                         // encrypted
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->string('closure_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('matter_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('matter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('support'); // lead | associate | assistant | paralegal | support
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['matter_id', 'user_id']);
        });

        Schema::create('matter_parties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('matter_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('normalized_name')->index();
            $table->string('party_type'); // opposing | opposing_counsel | co_party | witness | related | other
            $table->boolean('is_organization')->default(false);
            $table->string('contact')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('matter_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('matter_id')->constrained()->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->text('reason')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['matter_id', 'created_at']);
        });

        Schema::create('conflict_checks', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 20)->unique();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('matter_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('pending_review')->index(); // pending_review | cleared | conflict_found | waived
            $table->json('results')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('conflict_check_parties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conflict_check_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('normalized_name')->index();
            $table->string('role'); // prospective_client | opposing | related | other
        });

        Schema::create('engagements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('matter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->foreignId('conflict_check_id')->nullable()->constrained()->nullOnDelete();
            $table->string('fee_arrangement'); // hourly | fixed | retainer | contingency | pro_bono
            $table->decimal('hourly_rate', 15, 2)->nullable();
            $table->decimal('fixed_fee', 15, 2)->nullable();
            $table->decimal('retainer_amount', 15, 2)->nullable();
            $table->string('currency', 3)->default('UGX');
            $table->text('scope');
            $table->text('terms')->nullable();
            $table->date('start_date')->nullable();
            $table->string('status')->default('draft')->index(); // draft | pending_approval | approved | declined
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('decision_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('case_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('matter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title')->nullable();
            $table->longText('body');                        // encrypted
            $table->boolean('is_privileged')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('hearings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('matter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('court_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type')->default('hearing'); // hearing | mention | ruling | judgment | trial | other
            $table->string('title')->nullable();
            $table->dateTime('starts_at')->index();
            $table->dateTime('ends_at')->nullable();
            $table->string('courtroom')->nullable();
            $table->string('judicial_officer')->nullable();
            $table->string('status')->default('scheduled')->index(); // scheduled | adjourned | completed | cancelled
            $table->text('outcome')->nullable();                     // encrypted
            $table->json('reminder_days')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('deadlines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('matter_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('type')->default('filing'); // filing | submission | statutory | internal | other
            $table->dateTime('due_at')->index();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->json('reminder_days')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('matter_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('type')->default('client_meeting'); // client_meeting | internal | consultation | other
            $table->dateTime('starts_at')->index();
            $table->dateTime('ends_at')->nullable();
            $table->string('location')->nullable();
            $table->foreignId('organizer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('attendee_ids')->nullable();
            $table->text('notes')->nullable();
            $table->json('reminder_days')->nullable();
            $table->string('status')->default('scheduled');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('calendar_reminders_sent', function (Blueprint $table) {
            $table->id();
            $table->morphs('remindable');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->smallInteger('days_before');
            $table->date('for_date');
            $table->timestamp('sent_at')->useCurrent();
            $table->unique(['remindable_type', 'remindable_id', 'user_id', 'days_before', 'for_date'], 'reminder_once');
        });

        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('matter_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('due_at')->nullable()->index();
            $table->string('priority')->default('normal');
            $table->string('status')->default('open')->index(); // open | in_progress | blocked | done
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('escalated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('task_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->timestamps();
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('matter_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('task_id')->nullable()->constrained()->nullOnDelete();
            $table->string('folder')->nullable()->index();
            $table->string('category')->default('other')->index();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('current_version_id')->nullable();
            $table->string('status')->default('draft')->index(); // draft | pending_approval | approved
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->boolean('is_evidence')->default(false);
            $table->boolean('is_template')->default(false);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('disk');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size_bytes');
            $table->char('sha256', 64);                         // integrity / chain of custody
            $table->string('scan_status')->default('pending');  // pending | clean | infected | not_scanned | error
            $table->timestamp('scanned_at')->nullable();
            $table->text('change_notes')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['document_id', 'version_number']);
        });

        Schema::create('document_access_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_version_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action'); // upload | view | download | link_issued | approve | delete | restore
            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['document_id', 'created_at']);
        });
    }

    public function down(): void
    {
        foreach (['document_access_logs', 'document_versions', 'documents', 'task_comments', 'tasks', 'calendar_reminders_sent',
            'appointments', 'deadlines', 'hearings', 'case_notes', 'engagements', 'conflict_check_parties', 'conflict_checks',
            'matter_status_history', 'matter_parties', 'matter_assignments', 'matters', 'courts', 'client_contacts'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('clients', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropConstrainedForeignId('office_id');
            $table->dropConstrainedForeignId('responsible_user_id');
            $table->dropUnique(['reference']);
            $table->dropColumn(['reference', 'type', 'status', 'category', 'registration_number', 'id_type', 'id_number',
                'address', 'city', 'country', 'normalized_name', 'intake_source']);
        });
    }
};
