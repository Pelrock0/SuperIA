# The Design System: Editorial Intelligence

This design system is a high-end, bespoke framework designed for a sophisticated Spanish shopping experience. It moves away from the "generic SaaS" look by prioritizing editorial-grade typography, rhythmic whitespace, and a "Tonal Layering" philosophy that replaces traditional borders with atmospheric depth.

---

## 1. Creative North Star: "The Digital Concierge"
The visual language is rooted in the concept of **The Digital Concierge**. Like a premium lifestyle magazine or a high-end boutique service, the interface should feel quiet yet authoritative. We achieve this through:
*   **Intentional Asymmetry:** Breaking the rigid grid by using varying card heights and generous, "breathing" margins.
*   **Atmospheric Depth:** Using glassmorphism and stacked surfaces to create a sense of physical reality.
*   **Editorial Contrast:** Pairing bold, tightly-tracked headlines with light, airy backgrounds to command attention without shouting.

---

## 2. Color & Surface Philosophy

### The "No-Line" Rule
**Borders are prohibited for sectioning.** To define a new area, use a background shift. For example, a `surface_container_lowest` (#ffffff) card should sit on a `surface_container_low` (#f3f4f6) background. Structure is created through color value, not structural lines.

### Surface Hierarchy & Nesting
Treat the UI as layers of fine paper or frosted glass.
*   **Base:** `surface` (#f8f9fb)
*   **Secondary Content Area:** `surface_container_low` (#f3f4f6)
*   **Primary Interactive Cards:** `surface_container_lowest` (#ffffff)
*   **Elevated Modals:** `surface_bright` (#f8f9fb)

### The Glass & Gradient Rule
To instill a "premium" feel, floating elements (like AI suggestions) should utilize **Glassmorphism**:
*   **Background:** `surface_container_lowest` at 80% opacity.
*   **Effect:** `backdrop-filter: blur(12px)`.
*   **Signature Texture:** Use a subtle linear gradient on primary CTAs from `primary` (#002645) to `primary_container` (#1a3c5e) at a 135-degree angle to provide a satin-like finish.

---

## 3. Typography: The Editorial Scale

We use **Inter** as our typographic backbone. The goal is a high-contrast, "Spanish Vogue" style hierarchy.

| Role | Token | Size | Weight | Tracking | Note |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **Display** | `display-lg` | 3.5rem | 800 | -0.04em | For hero moments and welcome screens. |
| **Headline** | `headline-md` | 1.75rem | 700 | -0.02em | Tight tracking is mandatory for headlines. |
| **Title** | `title-md` | 1.125rem | 600 | 0 | For card titles and list headers. |
| **Body** | `body-lg` | 1rem | 400 | +0.01em | Comfortable line-height (1.6) is required. |
| **Label** | `label-md` | 0.75rem | 500 | +0.03em | All-caps for utility markers only. |

---

## 4. Elevation & Depth

### The Layering Principle
Do not use shadows to separate adjacent cards. Instead, use "Tonal Nesting." Place a `surface_container_lowest` card inside a `surface_container` wrapper. The difference in hex value provides all the separation needed for a clean, modern aesthetic.

### Ambient Shadows
When an element must "float" (e.g., a sticky bottom navigation or an AI sparkle button):
*   **Color:** Use a 6% opacity version of `on_surface` (#191c1e).
*   **Blur:** High (24px to 40px).
*   **Spread:** -4px (to keep the shadow "tucked" under the element).

### The Ghost Border Fallback
If a visual separator is essential for accessibility, use a **Ghost Border**:
*   `outline_variant` (#c3c6cf) at **15% opacity**.
*   Standard 100% opaque borders are strictly forbidden.

---

## 5. Signature Components

### AI Sparkle Cards (✨)
AI-powered features (like smart categories or price predictions) must stand out without looking "techy."
*   **Background:** Use `tertiary_container` (#00441f) with a subtle grain texture.
*   **Iconography:** The sparkle icon (✨) should use `tertiary_fixed` (#a5f4b6).
*   **Typography:** White text (`on_tertiary`) to ensure maximum trust and contrast.

### Shopping List Items
*   **Style:** No dividers between items. Use `md` spacing (0.75rem) between rows.
*   **Checkbox:** Use `primary` (#002645) for the checked state. The unchecked state is a Ghost Border circle.
*   **Shared Indicators:** Shared lists use the `secondary` (#006687) people icon tucked into the top-right corner of the title with 50% opacity.

### Action Buttons
*   **Primary:** Linear gradient (Primary to Primary Container), `xl` roundedness (1.5rem), and `body-lg` bold text.
*   **Secondary (AI-Specific):** `tertiary_fixed` background with `on_tertiary_fixed` text.
*   **Input Fields:** `surface_container_high` (#e7e8ea) background, no border, `md` roundedness (0.75rem). On focus, shift background to `surface_container_lowest` with a 1px ghost border of `primary`.

---

## 6. Do’s and Don’ts

### Do
*   **Do** use Spanish-specific typography considerations (ensure "ñ" and accented characters have proper vertical clearance).
*   **Do** embrace whitespace. If you think there is enough space, add 8px more.
*   **Do** use `xl` (1.5rem) corner radii for large cards and `md` (0.75rem) for nested elements.
*   **Do** use `tertiary` (#002c12) for all AI success states to reinforce "Smart Savings."

### Don’t
*   **Don’t** use 1px solid dividers (use vertical space instead).
*   **Don’t** use standard "Blue" for success messages; always use the AI Green (`tertiary`).
*   **Don’t** use pure black for text; always use `on_background` (#191c1e) to maintain a soft, premium feel.
*   **Don’t** crowd the screen. This is a shopping tool, but it should feel like a wellness app.