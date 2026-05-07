# FEAT-BIOMETRIC-UX — Biometric Onboarding UX

**Complexity:** MEDIUM | **Status:** S4-PASS (Code review passed; full S5 pending)

## User Stories

| ID | Story | Notes |
|----|-------|-------|
| HU-UX1 | Post-login modal prompts biometric registration | Implemented |
| HU-UX2 | Post-register same prompt (after email verification) | Implemented |
| HU-UX3 | User can decline with 30-day cooldown | Implemented |
| HU-UX4 | Login page shows passkey as primary CTA | Implemented |
| HU-UX5 | Re-prompt after 30 days or on new device | Implemented |

## Key Dependencies

- `BiometricOptInModal` component
- `useBiometricPromptDecision` hook
- localStorage (per-device decision, no server sync)
- FEAT-BIOMETRIC-AUTH (passkey capabilities)

## Design Decisions

- localStorage-only persistence (intentionally per-device, no server state)
- Default device name computed client-side from User-Agent
- Probe-based feature gating (not a separate config endpoint)
- 30-day cooldown on decline

## Known Limitations

- `window.confirm` used in one flow (tech debt, replace with modal component)
- localStorage markers reset if storage cleared (accepted: uncommon edge case)

## Review Findings

- ~86 tests passing, 100% coverage on new code
- 3 pre-existing test failures (Superia→Superlistia rebrand, unrelated)
