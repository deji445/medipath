---
target: the patient intake takes too many steps
total_score: 24
max_score: 40
na_heuristics:
p0_count: 0
p1_count: 4
target_identity: "file:C:\\Users\\pc\\OneDrive\\Documents\\final year\\medipath\\resources\\js\\pages\\intake"
timestamp: 2026-10-05T21-34-05Z
slug: resources-js-pages-intake
---

# Patient Intake Critique

## Design Health Score

| #         | Heuristic                       |     Score | Key Issue                                                                                                   |
| --------- | ------------------------------- | --------: | ----------------------------------------------------------------------------------------------------------- |
| 1         | Visibility of system status     |       3/4 | Good progress UI, but “Step 1 of 4,” six journey stages, and the real 19-screen path contradict each other. |
| 2         | Match with the real world       |       3/4 | Mostly calm language; some internal terms and medication-entry syntax leak into the patient experience.     |
| 3         | User control and freedom        |       2/4 | Back navigation is incomplete during assessment, and review cannot jump directly to each editable section.  |
| 4         | Consistency and standards       |       2/4 | Visual patterns are consistent; the meanings of “saved,” “complete,” and progress are not.                  |
| 5         | Error prevention                |       3/4 | Safety, validation, and consent are strong; draft recovery and some clinical defaults need work.            |
| 6         | Recognition rather than recall  |       3/4 | Labels and guidance are visible, but symptom details are recalled and entered twice.                        |
| 7         | Flexibility and efficiency      |       1/4 | One rigid path, six one-question round trips, and no genuine returning-patient shortcut.                    |
| 8         | Aesthetic and minimalist design |       3/4 | Visually clean, but confirmation and reassurance screens create interaction clutter.                        |
| 9         | Error recovery                  |       2/4 | Inline errors help; interruption recovery and assessment backtracking are weak.                             |
| 10        | Help and documentation          |       2/4 | Some inline guidance, but insufficient help for uncertain medical-history answers.                          |
| **Total** |                                 | **24/40** | **Acceptable, but the workflow needs significant simplification.**                                          |

## Design Specificity Verdict

The clinical rules are specific to MediPath, but the interaction model is still a generic long wizard. The experience’s real strength—moving one patient story from symptoms to suitable care and a consented hospital handoff—is fragmented into isolated forms and “complete” screens.

The deterministic scan found **0 code-pattern violations**. That does not disprove the user’s concern: step count, duplicated decisions, and false finish lines are workflow problems outside the detector’s coverage. Browser evidence confirmed the conflicting progress signals and dense Current Visit page. No reliable browser overlay was created because the available browser evaluation surface is read-only.

## Overall Impression

The flow is safe, legible, and technically guarded—but much longer than it feels at the start. A normal journey is approximately **19 screens, 18 forward actions, and 24–26 substantive responses**. Returning patients save typing but still travel through almost the same number of screens.

The best target is **7–8 screens**, while keeping emergency screening, unsafe-hospital override, and destination-specific consent intact.

## What’s Working

- Emergency red flags are checked before AI guidance and can interrupt the ordinary flow immediately.
- Hospital suitability rules and explicit, hospital-specific consent are strong safety boundaries.
- Returning-patient history is already persisted, providing the technical foundation for a much faster repeat visit.

## Priority Issues

1. **[P1] The workflow is over-segmented.** Remove the separate Intake Saved, Safety Complete, and Hospital Selected screens. Route directly from intake review to safety, from a clear safety result to assessment, and from hospital selection to sharing consent. Combine guidance and recommended-hospital selection. Suggested command: `$impeccable distill`.
2. **[P1] The patient describes symptoms twice.** Before safety, ask only for the main concern and essential demographics. Collect structured symptom detail once during assessment, ending with one optional “Anything else?” field. Suggested command: `$impeccable clarify`.
3. **[P1] Returning patients lack a real fast path.** After secure identity verification, show one compact profile/history summary with “No changes—continue” and “Update details.” The unchanged route should jump straight to the current concern. Suggested command: `$impeccable distill`.
4. **[P1] Interrupted mobile users lack visible recovery.** Autosave server-side drafts, show “Saved just now,” restore the exact step, and expose “Continue saved intake.” Avoid storing health data casually in local storage. Suggested command: `$impeccable harden`.
5. **[P2] History input is patient-unfriendly.** Use “None / Add item / Not sure,” structured medication rows, and one-tap “No changes.” Require an active pregnancy-status choice when relevant. Suggested command: `$impeccable clarify`.

## Proposed Shorter Journey

1. Patient — phone lookup, secure identity confirmation, and unchanged-profile shortcut.
2. Current concern — main complaint plus only missing or clinically necessary demographics.
3. Emergency safety — deterministic red-flag screen; this remains separate.
4. Assessment, part 1 — grouped symptom duration, change, and severity.
5. Assessment, part 2 — impact, associated symptoms, and optional additional note.
6. Guidance and hospital choice — urgency explanation plus recommended suitable hospitals on the same screen.
7. Review and consent — concise care summary, chosen hospital, disclosure scope, explicit consent.
8. Receipt — submission reference and what happens next.

A returning patient with unchanged details could complete this in **6–7 screens**.

## Persona Red Flags

- **Jordan, first-time patient:** The interface promises four steps, displays six stages, and delivers roughly 19 screens. “Intake saved” sounds finished even though assessment has not started.
- **Sam, keyboard/screen-reader user:** Native semantics are generally sound, but eight safety checkboxes and repeated one-question pages create a long navigation sequence. Saved/processing status lacks a clear live announcement.
- **Casey, distracted mobile user:** Eighteen transitions, a long unsaved Current Visit form, typing-heavy history, and no visible resume state make interruption costly.

## Minor Observations

- The mobile journey rail hides later stages off-screen even though it correctly scrolls the active stage into view.
- The opening’s “Step 1 of 4” should be removed or reconciled with the true journey.
- Review should have section-level edit links if it remains.
- Repeated “nothing shared yet” messages should appear at the first relevant point and final consent, not every transition.
- The safety screen should stay separate, but its eight warnings can be grouped into scannable categories.

## Questions to Consider

- Is “creating an intake record” meaningful to the patient, or should that internal save event be invisible?
- If a returning patient confirms nothing has changed, what clinical reason prevents an immediate jump to the current concern?
- Can guidance and hospital recommendation become one confident decision surface rather than multiple stages?
