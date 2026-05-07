# Design — UX Principles

## Core Principles

### 1. Non-blocking feedback
Actions never block the user waiting for server confirmation. Optimistic updates with graceful error recovery.
- Toggle purchased: item moves immediately, syncs in background
- Delete item: removed immediately, 5s undo snackbar
- Suggestions: appear on first keypress (local layers), AI layer loads separately

### 2. Progressive disclosure
Complex features reveal detail on demand; simple use cases remain simple.
- Price estimation: summary bar visible, per-item breakdown expandable
- Weekly summary: in-app banner first, email opt-in explicit
- Duplicate detection: warning inline, not blocking modal

### 3. Consent-first AI
All AI-powered features are either transparent or opt-in.
- Autocomplete AI layer: only with `?include_ai=1` (frontend controls this)
- Weekly summary email: defaults to OFF (GDPR marketing)
- Replenishment: user can silence permanently; never forced

### 4. Privacy by default
No analytics on landing page. Minimal PII in system. Historical data survives list deletion (owned by user, not list).
- `HistoryAnonymizer` prevents PII from reaching Claude
- Account deletion audit log uses bcrypt-hashed user_id
- Anonymous collaborators tracked by session UUID only

### 5. Keyboard and screen reader accessibility
Every interactive element accessible without mouse. All reviewed in S5-UX gate.
- Combobox (autocomplete): full ARIA combobox pattern
- Duplicate warning: `role="alert"` for screen readers
- Charts: `aria-label` on recharts elements

### 6. Mobile-first
All UI designed and tested for mobile viewport first.
- HeroSection redesigned for mobile (Epic 0)
- SharedListPage: tested responsive on mobile
- Add item modal: accessibility-improved for touch interaction

## Feature-Specific UX Decisions

| Feature | Decision | Rationale |
|---------|----------|-----------|
| Waitlist position | Show approximate (±5) | Prevents scraping; preserves UX value |
| Freemium limit | 3 active lists | Enough for real use; motivates upgrade |
| Undo on delete | 5s snackbar (frontend only) | Simple; undo is rare; no backend complexity |
| Complement chip | Auto-hides after 30s | Non-intrusive; not blocking workflow |
| Biometric prompt | 30-day cooldown on decline | Respectful; re-prompts on new device |
| Share token | 410 Gone for all failures | No enumeration; consistent UX |
| Statistics gate | ≥3 lists before showing charts | Prevents misleading sparse data visuals |
