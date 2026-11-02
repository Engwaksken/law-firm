# Development backlog

Spec: `OPENCODE PROJECT MANAGER Advocates.txt` (kept outside the repo).

## Phase 1: Foundation ✅ (2026-11-02)

- [x] Laravel 12 project around the pre-existing models/policies/migrations
- [x] Spatie permissions: 13 roles, 26 permissions, default role map (`app/Support/Access.php`)
- [x] Filament admin panel: login, password reset, email verification, profile, email-change verification
- [x] MFA (TOTP + recovery codes), enforced for privileged roles; secrets encrypted
- [x] Offices and departments; users linked to both
- [x] Admin screens: users (with MFA reset and bulk deactivate), roles & permissions, offices, departments, audit log
- [x] Branding & contact settings driving the panel's name, logo, colours and font
- [x] Audit trail for model changes, authentication and access changes
- [x] Dashboard widgets that respect permissions
- [x] 46 feature tests (access matrix, escalation, MFA, audit redaction, branding, rate limiting)

### Open items carried forward
- Production MySQL not yet exercised. Run the migrations against MySQL 8 before Phase 8.
- Existing models (Advocate, Client, Consultation, Page, Article, etc.) have tables and policies but
  no admin screens yet. Phases 2–4 add them.
- `/` still shows the Laravel welcome page until Phase 2.
- Not built yet: "sign out other sessions" and user access reviews (spec §19). Planned for Phase 7.
- Malware scanning of uploads (spec §19) is needed before Phase 4 document storage.

## Phase 2: Public website (next)
Homepage, About, Practice areas, Advocate profiles, News/insights, Careers, Contact & consultation
booking. Blade + Livewire + Tailwind, styled from the Branding settings.

## Phase 3: CMS
## Phase 4: Legal practice
## Phase 5: Finance and client portal
## Phase 6: Integrations
## Phase 7: Quality assurance
## Phase 8: Deployment
