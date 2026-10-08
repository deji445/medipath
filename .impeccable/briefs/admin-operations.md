# Admin Operations Direction Contract

## Scope

Build two authenticated operational segments inside MediPath's established app shell:

1. Hospital administration for a tenant's intake configuration and staff access.
2. A Super Admin platform view for creating and governing hospital tenants.

These are extensions of the existing admissions workspace, not a new visual identity. Preserve the Calm Triage Desk system, Care Teal palette, Instrument Sans typography, borders-first depth, and compact operational density.

## Mode

Operate. Administrators should be able to understand current state, make one deliberate change, and verify its effect without leaving the page.

## Information hierarchy

- Lead with the current hospital or platform context and the operational consequence of its state.
- Put intake availability, routing mode, and clinical capability before cosmetic identity fields.
- Keep staff identity, role, and access state scannable as a single roster rather than isolated cards.
- In platform context, show tenant health and configuration together so inactive or paused hospitals are immediately distinguishable.
- Explain safety-sensitive settings in plain language beside the control.

## Interaction and states

- Every mutation is server-authorized; client-supplied hospital IDs never establish tenancy.
- Hospital administrators may manage only their own hospital and may not deactivate themselves.
- Staff roles available to a hospital are Triage, Doctor, and Hospital Admin; Super Admin is never assignable from a tenant screen.
- Super Admin may create and configure hospital tenants but remains outside every hospital admissions queue.
- Show saving/loading, validation, success, empty, inactive, paused, and error states.
- Destructive-looking state changes use explicit labels; status color always carries meaning.

## Responsive behavior

- Desktop uses a dense settings workspace or directory-and-detail composition within the existing sidebar shell.
- Narrow screens stack controls in reading order, preserve full-width primary actions, and turn data rows into compact labeled blocks without horizontal scrolling.

## Visual invariants

- White working surfaces on the slate canvas, crisp one-pixel borders, restrained shadows only for primary panels or overlays.
- Care Teal identifies the recommended action and active selection.
- Amber communicates paused intake; red is reserved for inactive access or destructive consequences.
- No decorative metrics, gradients, glass, or nested card grids.
