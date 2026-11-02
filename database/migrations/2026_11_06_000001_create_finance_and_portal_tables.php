<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5: billing and the client portal.
 * Money columns are decimal(15,2); all arithmetic uses bcmath (App\Support\Money).
 * Client trust/escrow accounts are intentionally NOT modelled (spec §12).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('default_hourly_rate', 15, 2)->nullable();
            $table->json('notification_preferences')->nullable();
        });

        Schema::create('time_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('matter_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->date('worked_on')->index();
            $table->unsignedInteger('minutes');
            $table->text('description');
            $table->boolean('billable')->default(true);
            $table->decimal('rate', 15, 2)->default(0);
            $table->decimal('amount', 15, 2)->default(0);
            $table->string('currency', 3)->default('UGX');
            $table->string('status')->default('unbilled')->index(); // unbilled | billed | written_off
            $table->unsignedBigInteger('invoice_item_id')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('matter_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->date('incurred_on')->index();
            $table->string('category'); // court_fees | filing | travel | courier | printing | expert | other
            $table->string('description');
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3)->default('UGX');
            $table->boolean('billable')->default(true);
            $table->foreignId('receipt_document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->string('status')->default('pending')->index(); // pending | approved | rejected | billed
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->unsignedBigInteger('invoice_item_id')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->nullable()->unique();     // assigned at issue: INV-2026-00001
            $table->foreignId('matter_id')->constrained()->restrictOnDelete();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->string('currency', 3)->default('UGX');
            $table->date('issue_date')->nullable();
            $table->date('due_date')->nullable()->index();
            // draft | pending_approval | issued | partially_paid | paid | void
            $table->string('status')->default('draft')->index();
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('tax_total', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->decimal('amount_paid', 15, 2)->default(0);
            $table->decimal('amount_credited', 15, 2)->default(0);
            $table->decimal('balance', 15, 2)->default(0);
            $table->text('notes')->nullable();          // printed on the invoice
            $table->text('internal_notes')->nullable(); // never printed
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->string('void_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('type')->default('fee'); // time | expense | fee | other
            $table->string('description', 500);
            $table->decimal('quantity', 12, 2)->default(1);
            $table->decimal('unit_price', 15, 2);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('line_subtotal', 15, 2);
            $table->decimal('line_tax', 15, 2);
            $table->decimal('line_total', 15, 2);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('invoice_sequences', function (Blueprint $table) {
            $table->string('series', 20);            // INV | RCT | CRN
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('last_number')->default(0);
            $table->primary(['series', 'year']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3);
            $table->string('method'); // cash | bank_transfer | mobile_money | cheque | card | other
            $table->string('reference')->nullable();
            $table->date('received_on')->index();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reversal_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('receipts', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();
            $table->foreignId('payment_id')->unique()->constrained()->restrictOnDelete();
            $table->timestamp('issued_at');
            $table->timestamps();
        });

        Schema::create('credit_notes', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->string('reason');
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('issued_at');
            $table->timestamps();
        });

        // ---------------------------------------------------------------- client portal

        Schema::create('client_portal_users', function (Blueprint $table) {
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->primary(['client_id', 'user_id']);
        });

        Schema::table('documents', function (Blueprint $table) {
            // Explicit, per-document decision to share with the client (never automatic).
            $table->timestamp('shared_with_client_at')->nullable()->index();
            $table->foreignId('shared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('uploaded_by_client')->default(false);
        });

        Schema::create('client_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('matter_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->longText('body');                                  // encrypted
            $table->string('status')->default('draft')->index();       // draft | pending_approval | published
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('document_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('matter_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('due_on')->nullable();
            $table->string('status')->default('open')->index();        // open | submitted | accepted | cancelled
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('document_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('client_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('matter_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('client_messages')->cascadeOnDelete();
            $table->string('subject')->nullable();
            $table->longText('body');                                  // encrypted
            $table->foreignId('sender_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('from_client');
            $table->string('category')->default('message');            // message | support
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['client_id', 'created_at']);
        });
    }

    public function down(): void
    {
        foreach (['client_messages', 'document_requests', 'client_updates', 'client_portal_users', 'credit_notes', 'receipts',
            'payments', 'invoice_sequences', 'invoice_items', 'invoices', 'expenses', 'time_entries'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shared_by');
            $table->dropColumn(['shared_with_client_at', 'uploaded_by_client']);
        });
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['default_hourly_rate', 'notification_preferences']));
    }
};
