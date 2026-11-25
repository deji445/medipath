# Demo hospital walkthrough

The fictional **MediPath Demo Hospital** uses the existing tenant admissions workspace and two demonstration ambulances. It is not a real Ghana facility and is excluded from normal patient hospital recommendations. No request sends a real ambulance or SMS.

## Patient and staff views

1. Open `/walk-in/medipath-demo` in a patient browser. Continue through intake using invented patient details and the prototype verification code shown on screen. Save the intake.
2. In a separate browser, sign in at `/platform/login` with your existing Super Admin account, then open **Demo admissions** (`/demo/hospital`). Alternatively, a hospital administrator or triage user assigned to the demo hospital uses `/hospital/login` and `/dashboard`.
3. Select the saved case and acknowledge it. The patient receipt refreshes within 30 seconds; Medi checks while open every 10 seconds.
4. On the patient receipt or in Medi, choose **Request demo ambulance**. This creates one persistent request per submission and opens tracking.
5. Open **Demo dispatch** (`/hospital/dispatch`) in the staff browser. Assign an available vehicle and enter the crew name. The signed-in staff member is recorded as the assigning operator. An optional ETA is a manually entered demonstration estimate.
6. Advance through travelling to the patient, at patient, returning to hospital, and completed. Patient tracking refreshes every 5 seconds. Completing or cancelling releases the vehicle for another request.

The map shows real geography, but its vehicle position and path are illustrative stages, not GPS or road navigation. A request awaiting assignment has no arrival estimate. Separate browsers avoid staff and patient sessions affecting each other.

## Medi additions

- Resume the current intake or open its saved receipt.
- See intake acknowledgement, callback status, and demo transport status from the current session.
- Request and track a demo ambulance.
- Continue a chat into intake using the existing conversation handoff.
- Get a short explanation of the workflow, minimise the companion, and change language.
- Use supported browser English speech input and read-aloud. Twi voice remains unavailable pending provider testing; Twi text translations remain drafts requiring review.

## Boundaries

Staff requests and vehicles are scoped to their hospital. Super Admin demo operations are restricted to the fictional demo hospital. Double assignment and invalid journey transitions are rejected. Patient status comes only from the active browser session, not a supplied case identifier. Prototype phone verification must not be used as authentication for real patient records.

This version reuses the tenant settings and roles already in MediPath. Dispatch is performed by hospital administrators or triage staff, with a crew name recorded for the simulation; it does not introduce a separate crew account or a real emergency-services integration. Changing the demo hospital's intake requirements uses the existing hospital settings; a new arbitrary form-builder is outside this release.

## Deployment

Run the normal migrations before serving this release. Migration `2026_11_25_000001_create_demo_dispatch` adds the request and vehicle tables, demo hospital and fleet. Its rollback removes transport data but preserves the hospital because patient records can reference it. Real SMS remains disabled.
