# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

The primary users are patients in Ghana and companions or caregivers completing intake on their behalf. They may be at home, travelling to care, or already at a hospital as a walk-in. Their job is to describe the patient's situation accurately, understand the urgency, choose an appropriate facility, and share a structured summary before clinical assessment.

Secondary users are hospital admissions staff, triage nurses, doctors, hospital administrators, and the internal MediPath Super Admin. Hospital staff need to see incoming cases quickly, understand the patient's history and urgency, and manage each hospital's routing and operating preferences.

## Product Purpose

MediPath moves basic history-taking and symptom intake ahead of hospital arrival. It collects patient identity, medical history, medications, symptoms, optional vitals, location, and consent; produces safe triage guidance rather than a diagnosis; helps the patient select an appropriate facility; and sends a structured summary to that hospital.

Success means a patient or companion can complete the journey with minimal confusion, receive an understandable urgency result, avoid an obviously unsuitable facility, and provide hospital staff with useful information before arrival.

## Positioning

MediPath combines a patient-led pre-arrival intake with persistent returning-patient history, explicit sharing consent, capability-aware hospital routing, and independently configured hospital portals. It is not a general symptom-search site or a hospital management system: its distinctive mechanism is carrying one structured intake from the patient through triage guidance and facility selection into the chosen hospital's admissions queue.

## Operating Context

The product is a final-year web prototype intended for demonstration with dummy patient data and three deliberately different pre-seeded hospitals. Patients may use it on a phone with limited bandwidth, while hospital staff use an authenticated admissions dashboard. The prototype supports both pre-arrival use and a hospital-specific walk-in QR entry path.

The core journey is patient lookup or creation, identity and history intake, symptom assessment, severity result, hospital recommendation or override, explicit consent, and hospital submission. Returning patients use a phone-number lookup to recover their platform identity and pre-fill medical history. Hospital tenants have separate branding, capability, routing, staff-availability, and incoming-case views.

## Capabilities and Constraints

- The application uses Laravel, Inertia, Vue 3, TypeScript, and PostgreSQL and is deployed together on a PHP-capable host.
- The build is multi-tenant, with shared tables and explicit hospital tenant IDs for the prototype.
- AI output is medical intake and triage guidance only. It must never claim to diagnose, prescribe, or replace professional medical advice.
- Deterministic red-flag rules must be able to interrupt the AI conversation immediately rather than relying only on model output.
- High-risk routing must prioritize a capable hospital. Choosing an unsuitable preferred hospital requires an explicit additional confirmation.
- Patient health information is shared with a hospital only after a deliberate consent action.
- Current medications are structured informational inputs; the prototype does not perform medication-interaction checking.
- Manual address and manual vitals entry remain available when geolocation or wearable data is unavailable.
- Only dummy data is permitted for the prototype. Production-grade security, regulatory compliance, automated hospital verification, live hospital-system integrations, and real ambulance dispatch are outside the current build.
- Public hospital registration and volunteer-doctor experiences are design or demonstration scope unless time remains after the must-have journey.

## Brand Commitments

The product name is MediPath. Product language must be calm, direct, safety-conscious, and easy for a non-clinician to understand. It must distinguish guidance from diagnosis, make emergency escalation unmistakable, and avoid overstating clinical certainty or operational partnerships.

## Evidence on Hand

- The product requirements document is `C:/Users/pc/OneDrive/Documents/final year/MediPath_AI_PRD (1).docx`.
- The repository contains the working Laravel application, PostgreSQL clinical schema, staff authentication, tenant foundations, and the four-step patient intake workflow.
- Three demo hospitals and one returning demo patient are available through idempotent database seeders.
- Automated tests cover authentication, tenant establishment, schema contracts, phone normalization, returning-patient lookup, consent requirements, and completed intake persistence.
- No real hospital partnership claims, clinical outcome data, testimonials, certifications, or production-compliance evidence are available and none may be fabricated.

## Product Principles

1. Safety outranks speed: emergency red flags and unsuitable routing choices must be handled explicitly.
2. Ask only what advances the care journey: each screen and question should reduce uncertainty for the patient or receiving staff.
3. Keep the patient in control: saved identity, location access, wearable access, and hospital sharing require clear choices and consent.
4. Preserve clinical clarity: structure important data, show uncertainty honestly, and separate reported facts from AI-generated guidance.
5. Respect hospital autonomy: each tenant controls its capability, routing mode, branding, and internal queue without weakening platform-level patient safety guards.

## Accessibility & Inclusion

The patient journey is mobile-first, usable on narrow screens, and considerate of limited bandwidth. Language should be clear English with short explanations, obvious progress, readable form controls, and alternatives to geolocation and wearable integrations. Ghanaian-language localisation is future work. Interfaces should support keyboard navigation, visible focus, semantic labels, understandable validation, and sufficient contrast.
