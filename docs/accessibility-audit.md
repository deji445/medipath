# MediPath patient-journey accessibility audit

Date: 2026-10-13

Scope: the shared patient intake layout and the rendered `/intake` start page,
supported by a static review of the remaining intake Vue pages. This is a
technical prototype audit, not clinical validation.

## Audit health score

| Dimension | Score | Key finding |
| --- | ---: | --- |
| Accessibility | 4/4 | Strong labels and focus states; the measured contrast and heading-order findings are resolved. |
| Performance | 3/4 | Intake routes are code-split and avoid large media; no blocking render issue was observed. |
| Theming | 2/4 | The visual system is coherent, but intake files still mix Tailwind palette values and hard-coded hex colors instead of shared semantic tokens. |
| Responsive design | 4/4 | No page-level overflow at 320 × 568 or 390 × 844; all persistent header controls meet the project's 44px touch-target standard. |
| Implementation integrity | 4/4 | Impeccable's deterministic detector returned no findings and the interface is clearly specific to MediPath's workflow. |
| **Total** | **17/20** | **Good — address the remaining theming work before final release.** |

## Implementation integrity verdict

**Pass.** The intake expresses a coherent patient-specific system: a persistent
care-journey rail, explicit consent language, Ghanaian phone formatting,
emergency access, and a focused primary action. The detector reported no
generic UI anti-patterns for `resources/js/pages/intake` or
`resources/js/layouts/IntakeLayout.vue`.

## Findings

### Resolved — Upcoming journey numbers meet normal-text contrast

- **Location:** `resources/js/layouts/IntakeLayout.vue:109` and
  `resources/js/layouts/IntakeLayout.vue:119`
- **Category:** Accessibility
- **Evidence:** The original rendered contrast was approximately `3.63:1`.
  After remediation, each 12px upcoming-stage numeral measures `5.71:1`
  against white on desktop and mobile.
- **Impact:** Low-vision users may have difficulty reading the remaining stage
  numbers, weakening orientation in a multi-step medical workflow.
- **Standard:** WCAG 2.2, 1.4.3 Contrast (Minimum), requires 4.5:1 for normal
  text.
- **Resolution:** The upcoming label and numeral now use the darker quiet slate
  treatment while remaining visually subordinate to the active stage.

### Resolved — The intake start page has one correctly ordered H1

- **Location:** `resources/js/pages/intake/Start.vue:104` and
  `resources/js/pages/intake/Start.vue:128`
- **Category:** Accessibility
- **Evidence:** The original accessibility tree announced “Start with the
  patient” as H2 before the H1. The updated desktop and 390px mobile renders
  expose only “Find or create the patient profile” as H1.
- **Impact:** Screen-reader heading navigation presents the page structure in
  an unexpected order.
- **Standard:** WCAG 2.2, 1.3.1 Info and Relationships.
- **Resolution:** The visually unchanged left-panel title is now supporting
  text rather than a heading that precedes the page title.

### Resolved — Persistent mobile header controls meet the 44px project standard

- **Location:** `resources/js/components/LanguageSwitch.vue:56` and
  `resources/js/layouts/IntakeLayout.vue:66-80`
- **Category:** Responsive design / Accessibility
- **Evidence:** At both 320 × 568 and 390 × 844 emulated viewports, the logo
  link, English choice, Twi choice, and emergency shortcut each measure at
  least 44 × 44 CSS pixels, with no document-level horizontal overflow.
- **Impact:** These frequent controls are less forgiving for patients with
  reduced dexterity or users operating a phone under stress.
- **Standard:** The MediPath design system specifies 44px mobile touch targets;
  WCAG 2.2 target-size minimum remains satisfied because each target is at
  least 24 × 24 CSS pixels.
- **Resolution:** The interactive boxes now use a 44px minimum while the
  narrowest header uses tighter spacing and prevents the emergency shortcut
  from shrinking. The visual icon sizes remain unchanged.

### P2 — Semantic color tokens are not the implementation source of truth

- **Location:** `resources/js/pages/intake/**`,
  `resources/js/layouts/IntakeLayout.vue`, and `DESIGN.md`
- **Category:** Theming
- **Evidence:** Intake views mix `teal-*`, `slate-*`, and hard-coded values such
  as `#197f6d` and `#c53f52`. `DESIGN.md` also contains two different `border`
  definitions.
- **Impact:** Contrast and brand fixes must be repeated across files and may
  drift between patient and staff surfaces.
- **Recommendation:** Establish Care Green, Clinical Ink, Quiet Slate, Border,
  and Emergency Signal as shared CSS/Tailwind tokens, then replace literal
  values incrementally.
- **Suggested command:** `$impeccable extract design tokens`

## Positive findings

- The mobile page had no document-level horizontal overflow at 320px or
  390px, and its persistent header controls meet the 44px target standard.
- Form controls have visible labels, understandable hints, validation hooks,
  and strong focus-ring treatments.
- The emergency shortcut has a clear accessible name and stays available in
  the patient header.
- The journey rail uses an ordered list, an accessible label, and
  `aria-current="step"` for orientation.
- The phone field receives intentional initial focus, and the primary action is
  concise: “Continue.”
- Reduced-motion handling exists for the journey rail behavior.
- No large images or decorative media burden the patient entry page.

## Verification limits

The responsive evidence came from an emulated Chromium viewport, not a
physical phone. No synthesized touch gesture or screen-reader session was run.
The full authenticated journey also requires a session-linked OTP, so keyboard
and focus progression across every subsequent page remains a final manual
release check.

## Recommended actions

1. **P2 — `$impeccable extract design tokens`:** make the documented palette
   the implementation source of truth.
2. **Final — `$impeccable polish patient intake`:** perform the bounded final
   desktop/mobile visual pass after fixes.

Re-run `$impeccable audit patient intake` after the fixes to measure the score
again.
