# MediPath Completion Roadmap

This roadmap turns the current prototype into a reliable, demonstrable final-year project. It is intentionally scoped to the existing product: a patient-led pre-arrival intake, safety guidance, appropriate hospital selection, consented data handoff, and a hospital admissions portal. It does not expand into clinical diagnosis, production medical compliance, live ambulance dispatch, or real hospital integrations.

## Current baseline

- Patient intake, assessment, routing, consent, hospital submission, staff portal, and platform administration are implemented in one Laravel/Inertia application.
- TypeScript validation, Laravel Pint, PHPStan, and the PHPUnit suite currently pass (80 passed, 22 expected skips).
- Tests force the deterministic assessment driver, while Gemini provider tests retain mocked HTTP coverage.
- The production frontend build passes twice in a normal Windows process and produces a current Vite manifest. Restricted execution environments still cannot load Vite's Windows native dependencies.
- The PostgreSQL contract suite passes against the configured local schema. Fresh-database verification remains blocked until the local PostgreSQL role is granted permission to create a disposable database.
- Eligible but less suitable hospital selections require a deliberate additional confirmation before they are saved.
- The working folder is not currently a Git repository, so source history and a safe rollback path are absent.

## Definition of done

MediPath is ready to submit or demonstrate when all of the following are true:

1. `npm run types:check`, `composer run lint:check`, `composer run types:check`, and `php artisan test` pass from a clean checkout.
2. `npm run build` produces a current Vite manifest and production assets on the target Windows environment.
3. The PostgreSQL schema can be created on a fresh database, seeded with the demo hospitals and patient, and validated using the supplied database contract tests.
4. The entire patient-to-hospital journey can be demonstrated with dummy data, including an emergency interruption and a normal routed submission.
5. Staff can only see and act on cases belonging to their hospital, while a Super Admin can manage platform hospitals.
6. Setup, demo credentials, limitations, and deployment instructions are documented without including secrets or real patient information.

## Phase 1 — Stabilize automated verification

### 1. Repair the assessment-routing test

**Priority:** critical  
**Files:** `tests/Feature/AssessmentRoutingTest.php`, `app/Services/Assessment/AssessmentInputBuilder.php`

The feature test manually creates its SQLite schema but does not create `patient_conditions`, `patient_allergies`, or `patient_medications`. The Gemini provider consequently fails when it builds structured patient context.

Choose one of these approaches:

- Add all three history tables, including the columns and relationships used by the models, to the test setup; or
- Bind `DeterministicAssessmentProvider` in this test if its purpose is to verify the controller journey rather than Gemini-context construction.

Add a focused regression assertion that an assessment can complete for a patient with no recorded conditions, allergies, or medications.

**Acceptance criteria:** `php artisan test --filter=AssessmentRoutingTest` passes consistently; then the full suite passes.

### 2. Make assessment-driver selection deterministic in tests

**Priority:** high  
**Files:** `phpunit.xml`, `config/medipath.php`, relevant assessment tests

Ensure the test environment cannot accidentally select Gemini because of a developer's local environment configuration. Default feature tests to the deterministic driver and retain direct Gemini-provider tests with mocked HTTP responses.

**Acceptance criteria:** test outcomes do not change when `.env.local` contains Gemini settings.

### 3. Add a single verification command

**Priority:** medium  
**Files:** `composer.json`, `README.md`

Create or document one command that runs frontend checks, formatting, static analysis, PHP tests, database contract checks, and a production build in the correct environment.

**Acceptance criteria:** a reviewer can run the documented verification sequence without guessing command order.

## Phase 2 — Make the frontend build reproducible

### 4. Resolve the Windows Vite/Tailwind build issue

**Priority:** critical  
**Files:** `package-lock.json`, `package.json`, `vite.config.ts` as needed

Run `npm run build` in a normal local terminal. If it fails again, delete only the generated `node_modules` directory, reinstall from the committed lockfile with `npm ci`, and rerun the build. Do not change package versions solely to hide a corrupted platform-native dependency.

If the build succeeds but warns about `fontaine`, either install that optional package intentionally or set the Laravel font fallback option to `false` so the build is warning-free.

**Acceptance criteria:** `npm run build` exits successfully twice in succession and updates `public/build/manifest.json`.

### 5. Validate the built application

**Priority:** high  
**Files:** `resources/js/pages/**`, `resources/css/app.css`

Serve the built application and test mobile and desktop layouts. Check public pages, intake progress, validation states, emergency guidance, routing cards, submission confirmation, login, dashboard, settings, staff management, and platform management.

**Acceptance criteria:** no console errors, broken routes, unreadable controls, or mobile overflow in the core journey.

## Phase 3 — Verify data and authorization boundaries

### 6. Run PostgreSQL schema and seed verification

**Priority:** critical  
**Files:** `database/migrations/001_initial_schema.sql`, `database/seeders/**`, `scripts/db-test.ps1`

Use a fresh disposable PostgreSQL database. Apply the Laravel baseline migration, run the seeders, and execute the database contract test script. Confirm the migration refuses a partially initialized clinical schema as intended.

**Acceptance criteria:** fresh setup, repeatable seeding, and database contract tests all pass without altering production-like data.

### 7. Exercise tenant isolation and staff roles

**Priority:** critical  
**Files:** `app/Http/Middleware/EstablishStaffTenant.php`, `app/Policies/HospitalPolicy.php`, `tests/Feature/HospitalAdmissionsDashboardTest.php`

Confirm triage staff, doctors, hospital administrators, and Super Admins have the expected access. Specifically test that one hospital cannot fetch or update another hospital's submission by changing an ID in the URL or request payload.

**Acceptance criteria:** cross-tenant access is denied; only permitted roles can update hospital settings or staff.

### 8. Check safety and consent edge cases

**Priority:** critical  
**Files:** `app/Services/Assessment/RedFlagDetector.php`, `app/Http/Controllers/PatientRoutingController.php`, `app/Http/Controllers/PatientSubmissionController.php`

Manually and automatically verify:

- A deterministic red flag interrupts the assessment and displays emergency guidance.
- A high-acuity assessment only recommends emergency-capable facilities.
- An unsuitable hospital override requires the additional confirmation described in the product requirements.
- A hospital submission cannot be created without deliberate hospital-share consent.
- All clinical examples and seed data remain dummy data.

**Acceptance criteria:** each scenario has an automated test or a short, repeatable manual test script.

## Phase 4 — Final product polish

### 9. Complete the demo script

**Priority:** high  
**Files:** create `docs/demo-script.md`

Write a 5–8 minute presentation flow using seeded dummy accounts and patients:

1. Start a new or returning patient intake.
2. Complete identity, history, and symptom steps.
3. Show a normal severity result and facility recommendation.
4. Give hospital-share consent and submit the summary.
5. Log into the receiving hospital portal and update the case status.
6. Briefly show the emergency red-flag interruption.
7. Show the Super Admin hospital-management view.

Include reset steps so the demo can be repeated.

**Acceptance criteria:** another person can follow the script without developer assistance.

### 10. Perform accessibility and content review

**Priority:** high  
**Files:** `resources/js/pages/**`, `resources/js/components/**`, `PRODUCT.md`

Review the public journey with keyboard-only navigation and a narrow mobile viewport. Confirm labels, errors, focus states, contrast, concise language, emergency wording, consent wording, and the distinction between guidance and diagnosis.

**Acceptance criteria:** every actionable control has a visible label and focus state; error and emergency messaging is understandable without clinical knowledge.

### 11. Document setup, limitations, and deployment

**Priority:** high  
**Files:** `README.md`, create `docs/deployment-checklist.md`

Update the documentation to cover:

- local prerequisites and environment configuration;
- PostgreSQL migration and seeding;
- deterministic versus Gemini assessment configuration;
- safe handling of API keys and dummy data only;
- required PHP extensions, queue/session/cache configuration, and asset build steps;
- known prototype limitations and explicit non-production claims;
- a short rollback and backup plan for deployment.

**Acceptance criteria:** a new developer can deploy the demonstration environment from the documentation without accessing uncommitted local settings.

## Phase 5 — Submission hygiene

### 12. Put the project under version control

**Priority:** high  
**Files:** `.gitignore`, repository metadata

Initialize a Git repository or restore the intended repository connection. Before the first commit, confirm `.env`, `.env.local`, runtime logs, generated assets, and `node_modules` are ignored. Review the staged diff for secrets, private connection strings, real user data, and generated files.

**Acceptance criteria:** the project has a clean initial commit, a remote backup where appropriate, and no secrets in tracked files.

### 13. Capture final evidence

**Priority:** medium  
**Files:** `docs/verification.md` or final project report

Record command outputs, passing test counts, build date, database verification result, browser/device checks, and screenshots of the principal patient and staff flows. Note any remaining limitation honestly.

**Acceptance criteria:** the final report can demonstrate both implementation quality and the prototype's safety boundaries.

## Suggested execution order

1. Repair and lock down the test environment.
2. Fix and verify the production frontend build.
3. Verify PostgreSQL migration, seeding, tenancy, consent, and safety paths.
4. Run the complete user-flow and accessibility review.
5. Finish documentation, demo materials, version control, and final evidence.

## Out of scope unless requirements change

- Diagnoses, prescriptions, or medication-interaction decisions.
- Use of real patient data or claims of clinical validation.
- Production regulatory certification, real hospital partnerships, or live EHR integration.
- Emergency dispatch or ambulance integration.
- Public hospital onboarding and volunteer-doctor workflows.
