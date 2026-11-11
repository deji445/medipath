# MediPath verification evidence

Date: 2026-11-11  
Scope: final-year demonstration prototype using dummy clinical data only

## Release candidate

- Repository: `deji445/medipath`
- Verified application commit: `ae35825eaf03135c8d8f039bd976cbaf8fa6e919`
- Demonstration host: Laravel Cloud
- Production database: Neon PostgreSQL `production` branch
- Automated workflow: [MediPath verification run #27](https://github.com/deji445/medipath/actions/runs/43000883701)

## Automated verification

The release candidate passed all three independent GitHub Actions jobs from a
clean checkout:

1. **Application quality and tests**: frontend formatting and linting,
   TypeScript validation, PHP formatting, PHPStan, the Laravel test suite, and
   the production asset build passed.
2. **Clinical safety benchmark**: deterministic emergency screening, supported
   first-aid boundaries, and AI-consultant safety tests passed.
3. **Fresh PostgreSQL schema contracts**: PostgreSQL 17 started with an empty
   database; migrations, reference seeders, and rollback-safe SQL contract
   tests passed.

Local verification produced the following measured results:

- Laravel suite: 452 tests discovered, 430 passed, 22 expected skips, and
  1,849 assertions.
- Focused clinical-safety suite: 274 tests passed with 776 assertions.
- Focused authorization, consent, routing, and intake suite: 238 tests passed
  with 912 assertions.
- PostgreSQL schema contract: passed; inserted contract records were rolled
  back.
- Production frontend build: passed with 3,359 modules transformed.

## Production journey evidence

The deployed demonstration was exercised with fictional concerns and a
presenter-controlled phone:

- An AI consultation transferred its concern into patient intake without
  asking the patient to repeat the concern.
- A fresh BMS verification code was accepted, displayed for prototype testing,
  and used to complete phone verification.
- The patient journey reached assessment, hospital routing, explicit sharing
  consent, and the completion page.
- A fictional emergency statement triggered deterministic interruption,
  displayed Ghana's emergency number `112`, exposed the emergency-care action,
  and stopped ordinary AI questioning.
- Reloading the public home page cleared the temporary AI conversation.
- The staff login route loaded successfully.
- The BMS delivery audit resolved four verification events as `DELIVERED`, with
  no pending, failed, or expired delivery checks at the time of verification.

## Responsive and accessibility evidence

The production home, patient-start, emergency, and staff-login pages were
checked at a 390 by 844 CSS-pixel viewport:

- no document-level horizontal overflow was present;
- visible inputs and actions had accessible names;
- visible images did not omit alternative text;
- heading order and page landmarks were valid; and
- no browser console warnings or errors were observed on the checked routes.

The patient intake page retained its single page heading and persistent journey
orientation. The emergency page led with `Call 112 now`.

## Security and privacy boundaries checked

Automated coverage confirms that:

- staff cannot fetch or update another hospital's submissions by changing an
  identifier;
- hospital staff roles and Super Admin operations are separated;
- a hospital submission cannot be created without explicit hospital-sharing
  consent;
- high-acuity submissions require an emergency-capable facility;
- choosing a less suitable facility requires additional confirmation; and
- deterministic emergency rules run before AI guidance.

No environment file, API key, database password, OTP value, or real patient
record is included in this evidence file.

## Known prototype limitations

- MediPath is decision support, not a diagnosis, prescription service, or
  substitute for professional assessment.
- The on-screen OTP is intentionally enabled for the marked demonstration and
  must be disabled before any real-patient use.
- Twi clinical terminology and emergency wording require independent review by
  qualified Ghanaian clinical and language reviewers.
- The ambulance screen is a demonstration and does not dispatch or track a
  real ambulance.
- Facility availability and routing information are demonstration data rather
  than live hospital integrations.
- The responsive review used an emulated Chromium viewport, not a physical
  phone or a full screen-reader session.
- An authenticated staff-portal walkthrough still requires the securely held
  demonstration credentials at presentation time.

## Presentation readiness

Before the final presentation:

1. Confirm the Laravel Cloud deployment for the intended commit is healthy.
2. Confirm the BMS main wallet has enough credit and send one OTP to the
   presenter-controlled phone.
3. Keep hospital-staff and Super Admin credentials outside the repository.
4. Use only the fictional patient details in `docs/demo-script.md`.
5. Follow `docs/demo-script.md` from start to finish once on the presentation
   device and network.

