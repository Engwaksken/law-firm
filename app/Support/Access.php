<?php

namespace App\Support;

/**
 * Single source of truth for roles and permissions.
 *
 * The seeder syncs these into the database; policies and tests reference the
 * constants so a renamed permission fails loudly instead of silently denying.
 * Administrators can still adjust role permissions from the admin panel.
 */
final class Access
{
    // Roles
    public const SYSTEM_OWNER = 'system-owner';
    public const SUPER_ADMIN = 'super-admin';
    public const MANAGING_PARTNER = 'managing-partner';
    public const PARTNER = 'partner';
    public const ADVOCATE = 'advocate';
    public const LEGAL_ASSOCIATE = 'legal-associate';
    public const LEGAL_ASSISTANT = 'legal-assistant';
    public const PARALEGAL = 'paralegal';
    public const FINANCE_OFFICER = 'finance-officer';
    public const RECEPTIONIST = 'receptionist';
    public const CONTENT_EDITOR = 'content-editor';
    public const CLIENT = 'client';
    public const AUDITOR = 'auditor';

    /**
     * Roles that bypass permission checks (Gate::before), except audit-log writes.
     * Super Administrators instead hold every permission, so record-level
     * policy rules (e.g. "cannot edit the System Owner") still apply to them.
     */
    public const UNRESTRICTED_ROLES = [self::SYSTEM_OWNER];

    /** Roles that must enrol in multi-factor authentication before using the panel. */
    public const MFA_REQUIRED_ROLES = [
        self::SYSTEM_OWNER, self::SUPER_ADMIN, self::MANAGING_PARTNER,
        self::PARTNER, self::FINANCE_OFFICER, self::AUDITOR,
    ];

    /** @var array<string, string> role => display name */
    public const ROLES = [
        self::SYSTEM_OWNER => 'System Owner',
        self::SUPER_ADMIN => 'Super Administrator',
        self::MANAGING_PARTNER => 'Managing Partner',
        self::PARTNER => 'Partner / Senior Advocate',
        self::ADVOCATE => 'Advocate',
        self::LEGAL_ASSOCIATE => 'Legal Associate',
        self::LEGAL_ASSISTANT => 'Legal Assistant',
        self::PARALEGAL => 'Paralegal',
        self::FINANCE_OFFICER => 'Finance Officer',
        self::RECEPTIONIST => 'Receptionist',
        self::CONTENT_EDITOR => 'Content Editor',
        self::CLIENT => 'Client',
        self::AUDITOR => 'Auditor',
    ];

    /** @var array<string, array<string, string>> group => [permission => label] */
    public const PERMISSIONS = [
        'Administration' => [
            'users.view' => 'View user accounts',
            'users.manage' => 'Manage user accounts',
            'roles.manage' => 'Manage roles and permissions',
            'settings.manage' => 'Configure system and branding settings',
            'organisation.manage' => 'Manage offices and departments',
            'activity-logs.view' => 'View audit logs',
        ],
        'Website' => [
            'content.view' => 'View website content',
            'content.manage' => 'Create and edit website content',
            'content.publish' => 'Publish website content',
            'inquiries.manage' => 'Handle contact inquiries',
            'consultations.view' => 'View consultation requests',
            'consultations.manage' => 'Manage consultation requests',
            'careers.manage' => 'Manage vacancies and job applications',
        ],
        'Legal practice' => [
            'clients.view' => 'View clients',
            'clients.manage' => 'Create and edit clients',
            'matters.view-assigned' => 'View assigned matters',
            'matters.view-all' => 'View all permitted matters',
            'matters.create' => 'Create matters',
            'matters.assign' => 'Assign advocates to matters',
            'conflicts.run' => 'Run conflict-of-interest checks',
            'conflicts.review' => 'Review and clear conflict checks',
            'engagements.approve' => 'Approve engagements',
            'case-notes.edit' => 'Edit legal notes',
            'documents.upload' => 'Upload documents',
            'documents.approve' => 'Approve documents',
        ],
        'Finance' => [
            'invoices.issue' => 'Approve and issue invoices, credit notes',
            'expenses.approve' => 'Approve expenses',
            'payments.record' => 'Record payments',
            'reports.export' => 'Export reports',
        ],
        'Client portal' => [
            'portal.access' => 'Access the client portal',
        ],
    ];

    /** @return array<string, list<string>> role => permissions (unrestricted roles get all) */
    public static function rolePermissions(): array
    {
        $all = self::permissionNames();

        return [
            self::SYSTEM_OWNER => $all,
            self::SUPER_ADMIN => $all,
            self::MANAGING_PARTNER => array_values(array_diff($all, ['roles.manage', 'portal.access'])),
            self::PARTNER => [
                'users.view', 'content.view', 'consultations.view', 'consultations.manage', 'inquiries.manage',
                'clients.view', 'clients.manage', 'matters.view-assigned', 'matters.view-all', 'matters.create',
                'matters.assign', 'conflicts.run', 'conflicts.review', 'engagements.approve', 'case-notes.edit',
                'documents.upload', 'documents.approve', 'reports.export', 'careers.manage',
            ],
            self::ADVOCATE => [
                'consultations.view', 'clients.view', 'clients.manage', 'matters.view-assigned', 'matters.create',
                'conflicts.run', 'case-notes.edit', 'documents.upload',
            ],
            self::LEGAL_ASSOCIATE => ['clients.view', 'matters.view-assigned', 'conflicts.run', 'case-notes.edit', 'documents.upload'],
            self::LEGAL_ASSISTANT => ['clients.view', 'matters.view-assigned', 'documents.upload'],
            self::PARALEGAL => ['clients.view', 'matters.view-assigned', 'documents.upload'],
            self::FINANCE_OFFICER => ['clients.view', 'invoices.issue', 'expenses.approve', 'payments.record', 'reports.export'],
            self::RECEPTIONIST => ['consultations.view', 'consultations.manage', 'inquiries.manage', 'clients.view', 'conflicts.run'],
            self::CONTENT_EDITOR => ['content.view', 'content.manage', 'content.publish', 'inquiries.manage'],
            self::CLIENT => ['portal.access'],
            self::AUDITOR => ['users.view', 'activity-logs.view', 'reports.export'],
        ];
    }

    /**
     * Roles $actor may grant: only roles whose permissions $actor already holds
     * (no privilege escalation), and System Owner only by a System Owner.
     *
     * @return \Illuminate\Support\Collection<int, \App\Models\Role>
     */
    public static function assignableRoles(\App\Models\User $actor): \Illuminate\Support\Collection
    {
        $roles = \App\Models\Role::query()->with('permissions')->orderBy('name')->get();
        if ($actor->isUnrestricted()) {
            return $roles;
        }

        $held = $actor->getAllPermissions()->pluck('name');

        return $roles->filter(fn ($role) => ! in_array($role->name, self::UNRESTRICTED_ROLES, true)
            && $role->permissions->pluck('name')->diff($held)->isEmpty())->values();
    }

    /** @return list<string> */
    public static function permissionNames(): array
    {
        return array_merge(...array_map('array_keys', array_values(self::PERMISSIONS)));
    }

    /** @return array<string, string> */
    public static function permissionLabels(): array
    {
        return array_merge(...array_values(self::PERMISSIONS));
    }
}
