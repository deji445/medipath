---
name: MediPath
description: A calm, clinically clear interface for guided patient intake and hospital operations.
colors:
    care-green: '#197f6d'
    care-green-deep: '#116655'
    care-green-soft: '#dff2ec'
    supporting-green: '#2d8f79'
    supporting-green-soft: '#e7f1ed'
    clinical-ink: '#173a33'
    supporting-slate: '#45635b'
    quiet-slate: '#60766f'
    quiet-paper: '#ffffff'
    canvas: '#f4f7fb'
    border: '#d2e2dc'
    sage-signal: '#78aa8f'
    emergency-signal: '#c53f52'
    emergency-signal-deep: '#ad3145'
    emergency-signal-soft: '#fff0f2'
    emergency-signal-ink: '#8a2637'
    border: 'hsl(0 0% 92.8%)'
    destructive: 'hsl(0 84.2% 60.2%)'
    warning-surface: '#fffbeb'
    warning-ink: '#92400e'
    operational-success: '#10b981'
    operational-success-ink: '#047857'
typography:
    display:
        fontFamily: 'Instrument Sans, ui-sans-serif, system-ui, sans-serif'
        fontSize: '3rem'
        fontWeight: 700
        lineHeight: 1
        letterSpacing: '-0.025em'
    headline:
        fontFamily: 'Instrument Sans, ui-sans-serif, system-ui, sans-serif'
        fontSize: '1.5rem'
        fontWeight: 600
        lineHeight: 1.33
    title:
        fontFamily: 'Instrument Sans, ui-sans-serif, system-ui, sans-serif'
        fontSize: '1rem'
        fontWeight: 600
        lineHeight: 1.5
    body:
        fontFamily: 'Instrument Sans, ui-sans-serif, system-ui, sans-serif'
        fontSize: '1rem'
        fontWeight: 400
        lineHeight: 1.5
    label:
        fontFamily: 'Instrument Sans, ui-sans-serif, system-ui, sans-serif'
        fontSize: '0.875rem'
        fontWeight: 500
        lineHeight: 1.25
rounded:
    sm: '4px'
    md: '6px'
    lg: '8px'
    card: '12px'
    panel: '16px'
    feature: '24px'
spacing:
    xs: '4px'
    sm: '8px'
    md: '16px'
    lg: '24px'
    xl: '32px'
    2xl: '48px'
components:
    button-primary:
        backgroundColor: '{colors.care-green}'
        textColor: '{colors.quiet-paper}'
        typography: '{typography.label}'
        rounded: '{rounded.md}'
        padding: '8px 16px'
        height: '36px'
    button-primary-hover:
        backgroundColor: '{colors.care-green-deep}'
    button-outline:
        backgroundColor: '{colors.quiet-paper}'
        textColor: '{colors.clinical-ink}'
        typography: '{typography.label}'
        rounded: '{rounded.md}'
        padding: '8px 16px'
        height: '36px'
    input:
        backgroundColor: '{colors.quiet-paper}'
        textColor: '{colors.clinical-ink}'
        typography: '{typography.body}'
        rounded: '{rounded.md}'
        padding: '4px 12px'
        height: '36px'
        width: '100%'
    intake-panel:
        backgroundColor: '{colors.quiet-paper}'
        textColor: '{colors.clinical-ink}'
        rounded: '{rounded.panel}'
        padding: '32px'
---

# Design System: MediPath

## Overview

**Creative North Star: "The Calm Triage Desk"**

MediPath should feel like arriving at a well-organized clinical desk where every next action is visible and understandable. The interface is calm, clinical, and approachable: generous space lowers pressure, direct language reduces uncertainty, Care Green marks the primary path forward, and Sage adds a natural human note without making the experience feel cold.

The system is restrained rather than decorative. Light neutral canvases, crisp borders, compact controls, and a limited accent palette keep attention on patient information and operational decisions. Boldness comes from structural hierarchy and workflow rhythm—split compositions, connected sequences, and divided information—not decorative effects. Avoid alarmist emergency-room styling and playful wellness-app aesthetics. The approved direction is light-first across patient and staff experiences; the starter dashboard's dark appearance is not a design-system target.

**Key Characteristics:**

- Calm clinical clarity
- Care Green for primary actions; Sage Green for supportive guidance
- Light-first, high-contrast surfaces
- Spacious patient flows and compact operational controls
- Borders before shadows

## Colors

The palette combines a trustworthy Care Green with fresh supporting green, botanical ink, green-tinted neutrals, and quiet white surfaces. A small Sage Signal may punctuate ambient route graphics only. Status colors communicate meaning only when a real warning, error, or destructive action exists.

### Primary

- **Care Green:** The primary action color for patient-flow buttons, links, active tabs, focused controls, and the MediPath mark.
- **Deep Care Green:** The hover and pressed treatment for Care Green controls.
- **Soft Care Green:** A low-emphasis tint for route surfaces, selected states, and focus-adjacent backgrounds.
- **Supporting Green:** A secondary brand color for guidance, progress markers, and reassuring details.
- **Soft Supporting Green:** A low-emphasis tint for supportive or conversational states.

### Secondary

- **Operational Emerald:** A compact status signal for active tenants, available services, and other healthy operational states.
- **Operational Emerald Ink:** Success confirmation text after an administrative change has completed.
- **Emergency Signal:** A clear coral-red action treatment used only for direct emergency navigation and urgent clinical states. Its lighter tone stays unmistakable without making the interface feel severe.

### Neutral

- **Clinical Ink:** Primary text and high-contrast iconography.
- **Supporting Slate:** Explanatory copy and secondary information.
- **Quiet Slate:** Placeholders, captions, and tertiary metadata.
- **Quiet Paper:** Main cards, controls, and header surfaces.
- **Canvas:** The subdued page background behind patient-facing panels.
- **Border:** Dividers, field outlines, cards, and structural separation.

### Named Rules

**The Two-Signal Rule.** Use Care Green for the recommended next action and Supporting Green for guidance or progress. Do not let either color flood large surfaces.

**The Status Has Meaning Rule.** Reserve amber and red for genuine warning, validation, risk, or destructive states, not decoration. Emergency navigation uses the solid Emergency Signal color, with a compact glowing alarm reserved for the home-page shortcut.

## Typography

**Display Font:** Instrument Sans (with UI sans-serif and system fallbacks)  
**Body Font:** Instrument Sans (with UI sans-serif and system fallbacks)

**Character:** Instrument Sans is neutral, contemporary, and highly legible. A single family keeps clinical instructions coherent while changes in size and weight create an unmistakable hierarchy.

### Hierarchy

- **Display** (bold, 3rem, 1 line height): Landing-page statements only; step down to 2.25rem on small screens.
- **Headline** (semibold, 1.5rem, 1.33 line height): Page and intake-step headings.
- **Title** (semibold, 1rem, 1.5 line height): Card titles and compact section headings.
- **Body** (regular, 1rem, 1.5 line height): General reading and form guidance; supporting copy may reduce to 0.875rem.
- **Label** (medium, 0.875rem, 1.25 line height): Field labels, navigation items, buttons, and progress eyebrows.

### Named Rules

**The One Typeface Rule.** Use weight, scale, and spacing—not additional font families—to create hierarchy.

## Layout

Patient workflows use a centered reading column up to 56rem wide, with 16px side padding on small screens, 24px from the small breakpoint, and 32px to 40px vertical page rhythm. Intake panels use 24px padding on small screens and 32px on larger screens. Keep the six-stage patient journey visible beneath the intake header from Patient through Share. On narrow screens, let the rail scroll horizontally and center the active stage rather than compressing or hiding labels. Landing content expands to a 72rem container and becomes a two-column composition at the large breakpoint, with a 48px to 64px gap.

Staff content may expand to a 80rem container within the light application shell. Authentication uses an equal-weight split at the large breakpoint: contextual hospital-workspace orientation on one side and a focused sign-in panel on the other. Use a 4px base spacing rhythm, favoring 8px for tight relationships, 16px for component spacing, 24px for groups, 32px for sections, and 48px for major separation. Collapse multi-column layouts before reducing readable padding.

**The Workflow Rhythm Rule.** Make the care journey legible as a connected sequence, keep the active stage in view, and use responsive reflow before removing orientation cues.

## Elevation & Depth

MediPath is flat by default. Borders and changes in surface tone establish structure; subtle shadows reinforce important cards, menus, and overlays without making routine content float.

### Shadow Vocabulary

- **Control:** A minimal one-pixel shadow gives outlined controls definition against Quiet Paper.
- **Panel:** A restrained small shadow separates a primary intake panel from the Canvas.
- **Overlay:** Dialogs and menus may use a stronger shadow because they temporarily sit above the workflow.

### Named Rules

**The Borders First Rule.** Establish grouping with spacing, surface color, and a fine border before adding elevation.

## Shapes

Controls use gently curved 6px corners. Small decorative elements may use 4px, general interface surfaces use 8px to 12px, patient intake panels use 16px, and large landing-page feature containers may reach 24px. Dense operational work surfaces may remain square-edged so adjacent rows, sections, and split panes read as one continuous workspace. Borders remain thin and crisp. Use fully rounded shapes only for avatars, status dots, or genuine pill controls.

## Components

Components should feel restrained, spacious, reassuring, and strongly focused on clarity.

### Buttons

- **Shape:** Compact and gently curved (6px radius), normally 36px high; prominent actions may increase to 40px.
- **Primary:** Care Green with Quiet Paper text, medium label weight, and 16px horizontal padding.
- **Hover / Focus:** Deepen to Deep Care Green on hover. Preserve a visible three-pixel focus ring and never remove keyboard focus indication.
- **Secondary / Ghost:** Use Quiet Paper with a fine border for secondary actions; ghost treatment is reserved for low-emphasis navigation or utilities.

### Cards / Containers

- **Corner Style:** 12px for ordinary cards and 16px for major patient-flow panels; square corners for dense operational work surfaces that group adjacent rows or panes.
- **Background:** Quiet Paper over the Canvas.
- **Shadow Strategy:** Apply the Borders First Rule; patient panels may use the Panel shadow.
- **Border:** One-pixel neutral border.
- **Internal Padding:** 24px normally and 32px for spacious intake panels.
- **Information Architecture:** Review and summary surfaces use one clear outer panel with border dividers and responsive columns; do not nest a separate card around every information group.

### Inputs / Fields

- **Style:** Quiet Paper, Clinical Ink, a one-pixel border, 6px radius, and 12px horizontal padding.
- **Focus:** Shift the border toward the active color and show a clearly visible three-pixel focus ring.
- **Error / Disabled:** Error states use destructive color with explanatory text. Disabled controls lower contrast but remain legible and must not resemble an editable field.

### Navigation

Headers are white, lightly bordered, and deliberately quiet. Active navigation uses a subtle neutral fill with medium-weight text. Keep the MediPath identity visible but compact; patient navigation should not compete with the current form step.

### Patient Journey Rail

Use six ordered stages—Patient, History, Current visit, Guidance, Hospital, and Share—as the persistent intake orientation model. The active stage uses Supporting Green and `aria-current="step"`; completed stages remain legible but quieter, and upcoming stages recede. The rail stays horizontally scrollable on narrow screens and brings the active stage to the center when the route changes.

### Progress Eyebrow

Use a small medium-weight Supporting Green label above an intake heading to state the current step, such as “Step 1 of 4.” It supports orientation but never replaces the page heading or progress semantics.

## Do's and Don'ts

### Do:

- **Do** use Care Green for the primary next action and Supporting Green for current-step guidance.
- **Do** preserve visible keyboard focus, strong contrast, and readable mobile spacing.
- **Do** use borders and spacing as the default tools for grouping content.
- **Do** keep patient instructions direct, short, and visually close to the field or decision they explain.
- **Do** keep the active patient-journey stage visible and semantically identified across viewport sizes.

### Don't:

- **Don't** use the current starter dashboard's dark styling as the target for new MediPath screens.
- **Don't** use red or amber as decorative brand colors or to create unnecessary urgency.
- **Don't** introduce playful wellness illustrations, loud gradients, or oversized decorative effects.
- **Don't** add shadows to every surface or use multiple competing primary actions in one section.
