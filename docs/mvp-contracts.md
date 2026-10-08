# MediPath AI MVP Contracts

Status: Draft for implementation  
Version: 0.1  
Source: MediPath AI Product Requirements Document version 2.0  
Scope: Ten week prototype using test or dummy patient data only

## 1 Purpose

This document converts the PRD into implementation contracts shared by the patient application, AI service, routing service, hospital portal, and Super Admin. A contract is complete when its values, validation rules, failure behaviour, and acceptance criteria are explicit enough for separate components to implement consistently.

This is a prototype product specification, not a clinically validated triage protocol. The red flag matrix, escalation wording, and severity rules must be reviewed by a qualified Ghanaian clinician before use with real patients.

## 2 MVP Success Condition

The MVP is successful when a test patient can:

1. complete a structured intake;
2. complete an AI-guided symptom conversation;
3. receive a validated severity and recommended action;
4. select an eligible seeded hospital;
5. explicitly consent to sharing the intake;
6. submit the summary; and
7. appear only in the selected hospital's admissions queue.

All other features are secondary until this journey works reliably.

## 3 Shared Enums

These values are case-sensitive API values.

### 3.1 Severity

The MVP uses three levels aligned conceptually with the WHO Interagency Integrated Triage Tool's red, yellow, and green acuity model. MediPath performs pre-arrival guidance rather than facility-based clinical triage.

- `HIGH`: Possible time-critical or life-threatening condition. Interrupt the conversation and show immediate emergency-care guidance. Only an `ER_CAPABLE` hospital is eligible for in-app submission.
- `MEDIUM`: Prompt professional assessment is recommended. The patient should be directed to an eligible clinic or emergency facility according to capability and availability.
- `LOW`: No red flag identified and the answers suggest that routine assessment or self-care guidance may be appropriate. The product must still state that symptoms may change and the result is not a diagnosis.

The product must not display numeric probabilities or claim diagnostic certainty.

### 3.2 Recommended action

- `SEEK_EMERGENCY_CARE_NOW`
- `SEEK_CARE_SOON`
- `ROUTINE_CARE_OR_SELF_CARE`

Required mapping:

- `HIGH` maps to `SEEK_EMERGENCY_CARE_NOW`.
- `MEDIUM` maps to `SEEK_CARE_SOON`.
- `LOW` maps to `ROUTINE_CARE_OR_SELF_CARE`.

### 3.3 Hospital capability

- `ER_CAPABLE`: Can receive all three severity levels.
- `CLINIC_ONLY`: Can receive `LOW` and `MEDIUM`; cannot receive `HIGH` through MediPath.

Capabilities describe the prototype's seeded configuration and are not a claim of accreditation.

### 3.4 Routing mode

- `AUTO`: MediPath ranks eligible hospitals and presents the highest-ranked hospital as the recommendation.
- `MANUAL`: MediPath does not automatically assign internal hospital staff or resources. The patient may still select the hospital, and the hospital receives the submission in its general queue.

Routing mode does not bypass capability checks.

### 3.5 Visit and submission status

Visit status:

- `DRAFT`
- `ASSESSING`
- `ASSESSMENT_COMPLETE`
- `SUBMITTED`
- `CANCELLED`

Hospital submission status:

- `RECEIVED`
- `ACKNOWLEDGED`
- `IN_TRIAGE`
- `CLOSED`

## 4 Patient Intake Contract

### 4.1 Required fields before symptom assessment

- `patient_id`: Server-generated UUID. Persistent across visits.
- `visit_id`: Server-generated UUID. New for every intake.
- `phone_number`: Required for the prototype identity lookup and normalized before matching.
- `first_name`: 1 to 80 characters.
- `last_name`: 1 to 80 characters.
- `age_years`: Integer from 0 to 120.
- `filling_as`: `SELF`, `COMPANION`, or `CAREGIVER`.
- `conditions`: Array of trimmed strings; an empty array is valid.
- `allergies`: Array of trimmed strings; an empty array is valid.
- `medications`: Array of structured medication objects; an empty array is valid.
- `ai_disclaimer_acknowledged`: Must be `true` before starting the AI conversation.
- `privacy_notice_acknowledged`: Must be `true` before saving the intake.

When `filling_as` is not `SELF`, the intake must also capture `respondent_name` and `relationship_to_patient`.

### 4.2 Medication object

```json
{
  "name": "string",
  "dose": "string or null",
  "frequency": "string or null"
}
```

`name` is required when a medication row exists. MediPath does not perform interaction checking or dosage validation.

### 4.3 Optional fields

- `sex_at_birth`: `FEMALE`, `MALE`, `INTERSEX`, `UNKNOWN`, or `PREFER_NOT_TO_SAY`.
- `pregnancy_status`: `PREGNANT`, `NOT_PREGNANT`, `UNKNOWN`, `NOT_APPLICABLE`.
- `manual_vitals`: Structured values with unit and capture time.
- `wearable_vitals`: Fitbit values with source and capture time.
- `current_location`: Latitude and longitude collected only after permission.
- `manual_location`: Patient-entered address or landmark.

If a routing decision requires location and geolocation is unavailable, the product must request a manual address or landmark.

### 4.4 Returning-patient identity

1. Normalize the supplied phone number.
2. Search for an exact normalized match.
3. If one patient matches, show masked identifying details and ask whether to use the saved profile.
4. If the patient declines or no record matches, create a new patient record.
5. Never merge records automatically.

The no-password and no-OTP flow is permitted only for dummy-data demonstrations. It is not approved for real patient data.

## 5 Red Flag Escalation Contract

### 5.1 Safety architecture

Red flags must be evaluated by a deterministic rules layer after every patient answer. The language model may also identify a possible red flag, but it cannot suppress or downgrade a deterministic match.

When a red flag matches:

1. stop routine questioning;
2. set severity to `HIGH`;
3. set action to `SEEK_EMERGENCY_CARE_NOW`;
4. show a prominent message directing the patient to local emergency services or the nearest appropriate emergency facility;
5. offer only `ER_CAPABLE` hospitals for submission;
6. retain the answers already collected; and
7. record the matched rule codes for the hospital summary and audit log.

Emergency contact text and telephone numbers must be configurable by deployment region. They must not be generated by the language model.

### 5.2 Prototype red flag categories

The initial rules must cover patient-reported indicators in these categories:

- absent, obstructed, or severe difficulty breathing;
- unconsciousness, inability to wake, new severe confusion, or active convulsions;
- severe or uncontrolled bleeding;
- possible stroke signs, including sudden one-sided weakness or numbness, facial droop, or sudden speech difficulty;
- concerning chest pain, especially pressure or squeezing, pain spreading to the arm, neck, jaw, back, or stomach, or chest pain with breathlessness, sweating, nausea, or light-headedness;
- severe allergic reaction with breathing or swallowing difficulty;
- major trauma, serious burns, or suspected poisoning; and
- stated immediate intent to self-harm or harm another person.

These are categories, not production-ready phrase lists. Each rule must have a stable code, triggering question or answer pattern, patient-facing message, and clinician-approved wording before real-world use.

### 5.3 Failure-safe behaviour

- If the red flag service fails, the AI conversation must not continue as if the patient is safe. Show a service-unavailable message and direct the patient to professional care.
- If the patient's answers become contradictory or cannot be interpreted, do not assign `LOW`; request clarification or fall back to professional assessment.
- A later AI response cannot downgrade a visit already marked `HIGH`.

## 6 AI Symptom Assessment Contract

### 6.1 Role restrictions

The AI is a medical intake assistant. It may collect symptoms, summarize answers, assign one of the defined prototype severity levels, and recommend the corresponding action. It must not:

- claim to diagnose a condition;
- prescribe, stop, or change medication;
- provide a numeric likelihood of a diagnosis;
- claim that the patient is safe;
- invent vital signs, history, hospital capabilities, or availability; or
- override deterministic red flag or routing rules.

### 6.2 Conversation behaviour

- Ask one question at a time.
- Prefer short, plain-language questions.
- Collect the main complaint, onset, duration, location, severity from 1 to 10 where relevant, associated symptoms, and relevant context.
- Stop when enough information exists for the structured result or when a red flag interrupt occurs.
- Complete the standard prototype conversation as soon as the available information is sufficient, with a hard limit of 10 AI questions. If still inconclusive, return `MEDIUM` and recommend professional assessment rather than continuing indefinitely.

### 6.3 Input contract

The AI service receives:

```json
{
  "schema_version": "1.0",
  "visit_id": "uuid",
  "age_years": 0,
  "sex_at_birth": "UNKNOWN",
  "pregnancy_status": "NOT_APPLICABLE",
  "conditions": [],
  "allergies": [],
  "medications": [],
  "manual_vitals": null,
  "wearable_vitals": null,
  "conversation": []
}
```

Names, phone numbers, exact addresses, and hospital tenant data must not be sent to the AI provider because they are unnecessary for symptom assessment.

### 6.4 Final output contract

The AI must return JSON matching this shape:

```json
{
  "schema_version": "1.0",
  "severity": "LOW",
  "recommended_action": "ROUTINE_CARE_OR_SELF_CARE",
  "symptom_summary": "Plain-language factual summary of information supplied by the patient.",
  "red_flags": [],
  "reason_codes": ["NO_RED_FLAG_IDENTIFIED"],
  "assessment_complete": true
}
```

Validation rules:

- No unknown fields are accepted in version 1.0.
- `severity` and `recommended_action` must use the shared enums and valid mapping.
- `symptom_summary` must be between 1 and 1,500 characters and must not contain a diagnosis presented as fact.
- `red_flags` and `reason_codes` must be arrays of known codes.
- Markdown, HTML, commentary outside the JSON object, and malformed JSON are rejected.

The server, not the client, validates the response. It may upgrade severity based on deterministic rules but must never silently downgrade it.

### 6.5 AI error behaviour

For timeout, provider error, rate limit, malformed JSON, or failed validation:

1. retry once using the same idempotency key;
2. if the retry fails, mark the assessment as unavailable;
3. do not create a fabricated result;
4. preserve the intake; and
5. direct the patient to professional assessment, with emergency guidance if any deterministic red flag was detected.

## 7 Hospital Routing Contract

### 7.1 Eligibility

- `HIGH` visits are eligible only for `ER_CAPABLE` hospitals.
- `MEDIUM` and `LOW` visits are eligible for either capability.
- An inactive hospital or a hospital not accepting prototype submissions is never eligible.

### 7.2 Ranking for auto routing

Eligible hospitals are ranked in this order:

1. exact capability suitability;
2. accepting submissions;
3. available clinical staff, when roster data exists;
4. shortest valid travel time, otherwise shortest distance;
5. stable hospital ID as a deterministic tie-breaker.

The response must include human-readable ranking reasons. The client must not independently recalculate the ranking.

### 7.3 Patient selection and override

- The patient may choose a different eligible hospital.
- For `MEDIUM`, choosing a `CLINIC_ONLY` hospital is permitted when it is eligible.
- For `HIGH`, submission to `CLINIC_ONLY` is blocked. The patient may leave the workflow, but MediPath must not transmit a high-acuity submission to a facility configured as unable to receive it.
- Every submission requires an explicit final confirmation naming the destination hospital.

The `HIGH` override block is a safety clarification beyond the PRD's general extra-confirmation language and requires project-owner approval.

### 7.4 Submission

A hospital submission contains:

- submission, visit, patient, and hospital IDs;
- patient demographics required for admission;
- conditions, allergies, and structured medications;
- symptom summary, severity, recommended action, and red flag codes;
- manual or wearable vitals with source and capture time;
- arrival type: `PRE_ARRIVAL` or `WALK_IN_QR`;
- patient sharing-consent timestamp; and
- creation timestamp.

The hospital portal must retrieve submissions through a server-side tenant filter. Client-supplied tenant IDs are never sufficient authorization.

## 8 Consent Contract

Consent events are separate and timestamped:

- acknowledgement that the AI is not a doctor;
- acknowledgement of the prototype privacy notice;
- permission to access Fitbit data;
- permission to access location; and
- confirmation to send the intake to the named hospital.

No hospital transmission occurs from scanning a QR code, choosing a hospital, or completing the AI chat alone. A deliberate confirmation action is always required.

## 9 Minimum Acceptance Criteria

### 9.1 Patient intake

- Required fields are validated on the client and server.
- A medication cannot be saved without a name.
- Every new intake gets a new visit ID.
- A returning dummy patient can reuse a persistent patient ID after confirming the masked match.
- Declining a match creates a separate patient rather than overwriting the existing record.

### 9.2 Symptom assessment

- The chat asks one question at a time.
- A deterministic red flag interrupts the chat before normal completion.
- The server rejects malformed or enum-invalid AI output.
- An AI failure never produces a default `LOW` result.
- The result screen always displays the medical disclaimer and recommended action.

### 9.3 Routing

- A `HIGH` visit never lists a `CLINIC_ONLY` hospital as eligible.
- Auto routing produces the same order for identical inputs.
- Manual-mode hospitals can receive submissions but perform their own internal triage.
- The patient sees and confirms the hospital name before transmission.
- If location is unavailable, manual location entry allows routing to continue.

### 9.4 Tenant isolation

- Hospital A staff cannot list, search, fetch, update, or infer Hospital B or C submissions.
- Changing a tenant ID in a client request does not change the authorized tenant.
- Super Admin access is distinct from hospital staff access.
- Any cross-tenant data exposure is a release-blocking defect.

### 9.5 Walk-in QR

- A tenant QR opens the intake with that hospital preselected.
- An existing patient must confirm before any data is sent.
- A new patient can complete intake without using the facility finder.
- The submission is tagged `WALK_IN_QR`.

## 10 Prototype Release Blockers

The demo must not be treated as ready when any of the following is true:

- a high-acuity red flag can complete as `LOW`;
- invalid AI output is accepted;
- a `HIGH` visit can be submitted to a `CLINIC_ONLY` hospital;
- hospital users can access another tenant's data;
- patient data is sent without explicit confirmation;
- real personal health data is required for the demonstration; or
- failure states are represented as successful assessments.

## 11 Deferred Contracts

The following remain outside the functional MVP unless the core journey is complete:

- real hospital system integration;
- production identity verification and OTP;
- automated hospital accreditation checks;
- production compliance certification;
- medication interaction checking;
- ambulance dispatch;
- NFC arrival;
- analytics;
- volunteer-doctor consultation; and
- public use with real patient data.

Fitbit remains a PRD feature but should be implemented after the core patient-to-hospital journey. Until then, the API should accept optional wearable values without requiring them.

## 12 Decisions Requiring Approval

1. Approve `LOW`, `MEDIUM`, and `HIGH` as the complete MVP severity enum.
2. Approve blocking, rather than merely warning about, `HIGH` submissions to `CLINIC_ONLY` hospitals.
3. Choose the Ghana-specific emergency wording and verified contact information for the demo environment.
4. Arrange clinical review of the exact red flag questions and matching rules.
5. Decide whether the demo stores full chat transcripts or only structured answers and the final summary.
6. Define the retention period for dummy patient and visit records.

## 13 Safety References

- [WHO Interagency Integrated Triage Tool](https://www.who.int/tools/triage) describes a three-level acuity model: red for immediate intervention, yellow for prompt assessment, and green for patients who can wait.
- [WHO and ICRC Basic Emergency Care](https://www.who.int/publications/i/item/basic-emergency-care-approach-to-the-acutely-ill-and-injured) provides the ABCDE approach for recognizing time-sensitive illness and injury.
- [WHO Community First Aid Responder guidance](https://cdn.who.int/media/docs/default-source/integrated-health-services-%28ihs%29/csy/cfar-pocketguide.pdf) highlights severe bleeding, airway obstruction, breathing difficulty, shock, and altered mental status as danger signs.
- [CDC stroke guidance](https://www.cdc.gov/stroke/signs-symptoms/index.html) lists sudden one-sided weakness, speech difficulty, confusion, vision problems, loss of balance, and severe headache as warning signs requiring urgent action.
- [NHS chest-pain guidance](https://www.nhs.uk/symptoms/chest-pain/) describes concerning chest pressure or squeezing, radiating pain, breathlessness, sweating, nausea, and light-headedness.

