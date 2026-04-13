# Design System: Digital Indigo

## 1. Overview & Creative North Star
**The Creative North Star: "The Intelligent Concierge"**

This design system moves beyond the utility of a standard shopping list. It is an editorial, tech-forward experience that feels more like a high-end digital assistant than a spreadsheet. We achieve this through a "Digital Indigo" aesthetic—a sophisticated blend of deep, authoritative indigos, glass-like surfaces, and hyper-efficient layouts.

To break the "template" look, we utilize **intentional asymmetry**. Primary actions are not always centered; they are strategically placed to drive the eye through a narrative flow. We embrace **extreme typographic contrast**, pairing massive, tight-tracked display headers with airy, functional labels to create an environment that feels both human and machine-intelligent.

---

## 2. Color & Surface Architecture
Our palette isn't just a set of colors; it’s a hierarchy of intelligence. We use tonal depth to signify where the "AI" is thinking versus where the "User" is acting.

### Core Palette
- **Primary (`#2a14b4`):** The core of the experience. Use for high-intent actions.
- **Secondary (`#4953bc`):** Use for supporting UI elements and interactive states.
- **Tertiary (`#00451b`):** Reserved exclusively for "AI-Optimized" highlights and smart suggestions.

### The "No-Line" Rule
**Explicit Instruction:** Do not use 1px solid borders to section content. Boundaries must be defined solely through background color shifts. 
- A card (`surface_container_lowest`) should sit on a section (`surface_container_low`), which sits on the global `background`. 
- Depth is the new divider. If the UI feels cluttered, increase the white space, do not add a line.

### Surface Hierarchy & Nesting
Treat the UI as stacked sheets of frosted glass.
1.  **Level 0 (Base):** `surface` (`#f8f9fb`) — The canvas.
2.  **Level 1 (Sections):** `surface_container_low` (`#f3f4f6`) — Grouping related shopping categories.
3.  **Level 2 (Cards):** `surface_container_lowest` (`#ffffff`) — Individual list items or product cards.

### Signature Textures
- **The Intelligence Glow:** For AI-powered features, use a subtle linear gradient from `tertiary_container` to `tertiary_fixed_dim` at a 45-degree angle. This provides a "shimmer" that feels alive.
- **Glassmorphism:** Floating action buttons (FABs) or navigation bars must use `surface_container_lowest` at 80% opacity with a `24px` backdrop blur to allow the indigo tones to bleed through.

---

## 3. Typography: Editorial Utility
We use **Inter** exclusively, but we manipulate its personality through weight and tracking.

- **Display (Large/Medium):** `-0.04em` tracking, **Bold (700)**. These should feel heavy and authoritative.
- **Headlines:** `-0.02em` tracking, **Semi-Bold (600)**. Used for category names (e.g., "Produce," "Dairy").
- **Body:** `0em` tracking, **Regular (400)**. Optimized for readability in high-glare environments (like a grocery store).
- **Labels:** `+0.05em` tracking, **Medium (500)**, All Caps. Used for metadata like "AI Suggested" or "In Stock."

---

## 4. Elevation & Depth
We eschew the "drop shadow" of 2014. Elevation in this system is achieved through light physics.

- **The Layering Principle:** Depth is achieved by stacking. A `surface_container_highest` element over a `surface` background creates an immediate "lift" without a single pixel of shadow.
- **Ambient Shadows:** For elements that truly float (Modals, FABs), use:
  - `y: 8px, blur: 24px, color: rgba(42, 20, 180, 0.06)` (A tinted shadow using the primary indigo).
- **The Ghost Border Fallback:** If a border is required for accessibility on `surface_variant`, use `outline_variant` at **15% opacity**. It should be felt, not seen.

---

## 5. Components

### Buttons
- **Primary:** `primary` background with `on_primary` text. **Radius: md (12px)**. No shadow.
- **Secondary:** `secondary_container` background. Use for "Add to Cart."
- **AI Action:** `tertiary_container` background with a sparkle icon. This button should feel "premium."

### Smart Cards
- **Forbid Dividers:** Do not use lines between list items. Use a `8px` vertical gap.
- **Nesting:** Place list items inside a `surface_container_low` wrapper.
- **States:** On press, the card should shift from `surface_container_lowest` to `surface_container_high`.

### Input Fields
- **Search:** Use a `surface_container_high` background with no border. The focus state is a subtle `outline` (`#777586`) at 30% opacity.
- **Micro-intersections:** When the AI "recognizes" an item being typed, the input field's background should pulse softly with a `tertiary_fixed` glow.

### AI "Spark" Chips
- Use `tertiary_fixed` background with `on_tertiary_fixed` text.
- These chips indicate smart substitutions (e.g., "Switch to Organic?").

---

## 6. Do’s and Don’ts

### Do
- **Do** use asymmetrical margins. A 24px left margin and 16px right margin can make a list feel modern and editorial.
- **Do** use the Spacing Scale (8px increments) religiously.
- **Do** use `tertiary` green accents to celebrate AI efficiency.
- **Do** ensure all touch targets are at least 48px, considering the user is likely walking while using the app.

### Don't
- **Don’t** use black (`#000000`) for shadows. Use tinted Indigos.
- **Don’t** use 1px dividers. If you need separation, use a `surface_dim` background block or whitespace.
- **Don’t** use standard "Blue" for links. Use the `primary` Indigo.
- **Don’t** clutter the screen. If an item isn't essential to the current shopping trip, hide it in a "Layered" drawer.