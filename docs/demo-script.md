# MediPath demonstration script

This is a repeatable 5–8 minute demonstration of MediPath's patient intake,
emergency interruption, hospital routing, consent, and admissions workflow.
Use dummy clinical details only. Never enter a real patient's information.

## Before the demonstration

1. Start PostgreSQL and the MediPath development server.
2. Confirm `http://127.0.0.1:8000/up` returns a successful response.
3. Run `php artisan db:seed` to ensure the three demo hospitals and the dummy
   patient exist. The seeder is repeatable and does not erase existing data.
4. Run `powershell -ExecutionPolicy Bypass -File scripts/verify.ps1` after the
   final code change.
5. Confirm the BMS sender ID `medipath` is approved, its GHS main wallet is
   funded, and a real OTP can reach the presenter-controlled test phone.
6. Keep the following available without placing them in this file:
   - a presenter-controlled Ghanaian test phone number;
   - an active hospital staff account for MediPath Demo General Hospital;
   - an active Super Admin account.
7. Use the deterministic assessment driver for the most repeatable marked
   demonstration. If Gemini is shown, describe it as an optional AI-assisted
   path and retain the deterministic emergency screen in front of it.

## Part 1 — Patient entry and phone verification (about 1 minute)

1. Open the MediPath home page.
2. Point out the two clear routes: emergency help and guided intake.
3. Start patient intake and enter the presenter-controlled test phone number.
4. Explain that patient lookup does not continue until the number is verified.
5. Enter the six-digit code received from the lowercase `medipath` sender ID.
6. If this is the first use of the number, create a fictional profile. If it is
   returning, show that the saved profile is reused without requesting the date
   of birth again.

Suggested fictional profile:

- First name: `Kofi`
- Last name: `Demo`
- Date of birth: `1998-06-12`

## Part 2 — Normal intake and assessment (about 2 minutes)

1. Select that the patient is completing the intake personally.
2. Use this low-risk fictional concern:

   `I have had a mild sore throat for two days. I can breathe and swallow normally.`

3. Add only fictional history and observations. Skip optional vitals unless
   they support the explanation.
4. Show that the main concern is the focal point and that known profile facts
   are not requested again.
5. Answer the assessment prompts consistently. Explain that assessment stops
   when it has enough information and is capped at ten questions.
6. Show the result as decision support, not a diagnosis.

## Part 3 — Hospital routing and consent (about 1 minute)

1. Continue to hospital selection.
2. Explain the three seeded facilities:
   - MediPath Demo General Hospital — emergency capable, automatic routing;
   - MediPath Demo Community Clinic — clinic only, manual routing;
   - MediPath Demo Emergency Centre — emergency capable, manual routing.
3. Select an eligible recommended hospital.
4. Emphasize that selecting a hospital does not yet transmit clinical data.
5. Review the destination and the exact information to be shared.
6. Give the explicit hospital-sharing consent and submit the intake.
7. Keep the submission reference visible for the staff handoff.

## Part 4 — Hospital admissions handoff (about 1–2 minutes)

1. In a separate browser profile or private window, sign in with the securely
   supplied hospital staff account.
2. Open the admissions dashboard.
3. Find the new case using the patient name or submission reference.
4. Show the read-only patient snapshot, concern, assessment priority, history,
   supplied vitals, and recorded consent.
5. Move the case from received to acknowledged or in triage.
6. Explain that staff can only access submissions for their own hospital.

## Part 5 — Emergency interruption (about 1 minute)

1. Start another intake with dummy details.
2. Enter this fictional emergency concern:

   `I am vomiting blood and I am struggling to breathe.`

3. Show that deterministic screening interrupts the questionnaire immediately.
4. Point out Ghana's emergency number, `112`, the emergency-care action, and
   any safe first-aid guidance presented by the application.
5. Do not place a real emergency call during the demonstration.
6. Explain that AI conversation stops once deterministic emergency escalation
   is active, so it cannot delay urgent in-person care.

## Optional — Super Admin governance (about 30 seconds)

1. Sign in using the securely supplied Super Admin account.
2. Show hospital activation, submission acceptance, and capability controls.
3. Show the verified consultant-callback queue and audit history.
4. Explain that these controls affect routing and access without changing the
   clinical facts submitted by a patient.

## Repeat/reset steps

1. Close or acknowledge the demonstration submission from the hospital queue.
2. Use **Start another intake** or return to the home page.
3. Clear only the browser session if a completely fresh patient journey is
   required; do not delete the database.
4. Run `php artisan db:seed` again if the demo hospitals or dummy patient were
   edited. The seeders restore those records without wiping other data.
5. Verify that the BMS GHS main wallet still has funds before demonstrating a
   real OTP again.

## Statements to keep explicit

- MediPath provides pre-arrival decision support, not a diagnosis.
- It does not dispatch an ambulance or guarantee consultant response times.
- The emergency and Twi wording still requires qualified Ghanaian clinical and
  language review before real-world use.
- This is an academic prototype with dummy clinical data, not a certified
  production medical service or a live hospital integration.
