<?php

namespace Database\Seeders;

use App\Models\PracticeArea;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Starter practice areas from the specification (§5.3). They are examples:
 * the firm edits, hides or deletes them under Website → Practice areas.
 * Runs only when no practice areas exist, so it never overwrites edits.
 */
class PracticeAreaSeeder extends Seeder
{
    public const AREAS = [
        'Corporate and Commercial Law' => 'Company formation, governance, contracts, mergers and commercial transactions.',
        'Civil Litigation' => 'Representation in civil disputes before courts and tribunals.',
        'Criminal Law' => 'Advice and representation for individuals and organisations in criminal matters.',
        'Family and Succession Law' => 'Marriage, divorce, custody, wills, probate and estate administration.',
        'Land and Property Law' => 'Conveyancing, land registration, leases, and property disputes.',
        'Employment and Labour Law' => 'Employment contracts, workplace policies, disputes and compliance.',
        'Tax Law' => 'Tax planning, compliance, objections and disputes with tax authorities.',
        'Intellectual Property Law' => 'Trademarks, copyright, patents and protection of creative and commercial assets.',
        'Banking and Finance Law' => 'Lending, security, financial regulation and debt recovery.',
        'Arbitration and Dispute Resolution' => 'Arbitration, mediation and negotiated settlement of disputes.',
        'Human Rights Law' => 'Protection and enforcement of fundamental rights and freedoms.',
        'Immigration Law' => 'Work permits, residence, citizenship and related applications.',
        'Technology and Data Protection Law' => 'Data protection compliance, technology contracts and digital regulation.',
    ];

    public function run(): void
    {
        if (PracticeArea::withTrashed()->exists()) {
            return;
        }

        $order = 0;
        foreach (self::AREAS as $name => $summary) {
            PracticeArea::create([
                'name' => $name,
                'slug' => Str::slug($name),
                'summary' => $summary,
                'description' => $summary,
                'is_active' => true,
                'is_featured' => $order < 6,
                'sort_order' => $order++,
            ]);
        }
    }
}
