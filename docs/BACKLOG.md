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

## Phase 2: Public website ✅ (2026-11-03)

- [x] Pages: home, about, practice areas (index and detail with FAQs), advocate directory and profiles,
      insights (type/category filters, search, downloads), careers, contact, consultation booking, privacy, sitemap.xml
- [x] Blade + Livewire 4 + Tailwind 4; colours, fonts, logo and text come from Branding and Website content settings
- [x] Desktop mega menu, mobile navigation, breadcrumbs, skip link, visible focus, reduced-motion support
- [x] Forms: contact, consultation request, job application (CV on the private disk), newsletter (double opt-in, signed links)
- [x] Spam protection: honeypot, minimum fill time, per-IP rate limits
- [x] Staff alerts link to the admin panel and never include the visitor's message; visitor messages are not written to the audit log
- [x] Publication rules: advocates need opt-in AND verified credentials; testimonials need consent AND approval;
      articles and vacancies support scheduled publication; empty stats/milestones sections stay hidden
- [x] All CMS rich text is sanitised before rendering
- [x] Admin: Inbox (inquiries, consultations with confirm/decline emails), Website (practice areas, advocates with
      verify/revoke, articles, categories, testimonials, newsletter subscribers with CSV export), Careers
      (vacancies, applications with audited CV download and permanent erasure), Website content settings page
- [x] New permission `careers.manage` (Managing Partner, Partner, plus unrestricted roles)
- [x] 13 starter practice areas from the spec (seeded once, fully editable)
- [x] 107 tests passing (61 new)

### Open items carried forward
- **Malware scanning of uploaded CVs** is still missing. Needed before go-live (ClamAV or similar).
- The privacy notice shows a generic default until the firm publishes an approved one (System → Website content).
- Queued emails need a running queue worker (`php artisan queue:work`) or `QUEUE_CONNECTION=sync`.
- Dark theme for the public site isn't built yet (spec says "where useful").
- No browser-based visual or accessibility audit yet (Phase 7). Markup follows WCAG patterns, but it hasn't been checked with tools.

## Phase 3: CMS ✅ (2026-11-04)

- [x] Dynamic pages at `/{slug}` built from 15 block types (hero, text, text+image, image, cards, stats, FAQs,
      quote, call to action, live practice areas/advocates/insights/events/testimonials, embedded forms)
- [x] Reserved URLs: CMS pages can never shadow built-in pages; slugs validated
- [x] Workflow: draft → in review → published/scheduled → archived, with review notifications and audit entries.
      The public site serves the **last published revision**, so edits by non-publishers never go live unreviewed.
      Live page URLs can only be changed by publishers.
- [x] Revision history with restore (restores the working copy only)
- [x] Staff-only draft preview (signed in + content access, noindex, no-store)
- [x] A CMS page can replace the homepage (its own URL redirects 301 to `/`)
- [x] Media library (alt text required for images; permanent delete blocked while in use) + MediaPicker
- [x] Menu builder for header and footer (2 levels; CMS page / built-in page / URL links; unsafe or unavailable links skipped)
- [x] Events module (admin + public listing/detail, cancelled notices, schema.org Event)
- [x] SEO: defaults, verification codes, schema.org LegalService on the homepage, robots.txt route,
      "discourage indexing" switch for staging, pages and events in the sitemap
- [x] Fixed: boolean settings saved as `false` reverted to their default
- [x] 163 tests passing (56 new)

### Open items carried forward
- Malware scanning of uploads (CVs and media library) is still outstanding.
- No browser-based visual or accessibility audit yet (Phase 7).

## Phase 4: Legal practice (next)
Client management, conflict checking, engagements, matters and cases, hearings, tasks, legal documents.

## Phase 3: CMS (original scope)
## Phase 4: Legal practice
## Phase 5: Finance and client portal
## Phase 6: Integrations
## Phase 7: Quality assurance
## Phase 8: Deployment
