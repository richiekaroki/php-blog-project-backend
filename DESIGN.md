# Design System

The project uses a unified design system built with **Tailwind CSS 4** and custom CSS variables. All style decisions—colors, typography, spacing, and component design—are defined in the central `style.css` file found in the Vue `frontend/src/` directory.

## 1. Color Palette

The palette is split into a **light** and **dark** theme.  Each color has a semantic name (e.g., `--color-primary`) and a foreground contrast color.

### 1.1 Light Theme

| Variable | Hex | Purpose |
|---------|------|---------|
| `--color-background` | `#FBF9F1` | Page background |
| `--color-foreground` | `#2E2910` | Primary text |
| `--color-card` | `#FFFFFF` | Card background |
| `--color-primary` | `#2C5745` | Green brand |
| `--color-secondary` | `#EBE3A7` | Warm cream |
| `--color-accent` | `#EB7D00` | Warm orange accent |
| `--color-destructive` | `#C53030` | Red error |
| `--color-muted` | `#F5F0DC` | Parchment-like muted bg |
| `--color-warm-cream` | `#EBE3A7` | Alias for secondary |
| `--color-forest-green` | `#2C5745` | Alias for primary |
| `--color-dark-olive` | `#2E2910` | Alias for foreground |

### 1.2 Dark Theme

| Variable | Hex | Purpose |
|---------|------|---------|
| `--color-background` | `#1A1708` | Dark background |
| `--color-foreground` | `#EBE3A7` | Light text |
| `--color-card` | `#252010` | Dark card |
| `--color-primary` | `#3D7A63` | Lighter green brand |
| `--color-secondary` | `#3D3520` | Dark secondary |
| `--color-accent` | `#FF9A2E` | Bright orange |
| `--color-destructive` | `#E53E3E` | Brighter red |
| `--color-muted` | `#2A2412` | Dark muted |
| `--color-warm-cream` | `#3D3520` | Alias for secondary |
| `--color-forest-green` | `#3D7A63` | Dark forest green |
| `--color-dark-olive` | `#EBE3A7` | Dark olive text |

All components reference these variables via `theme()` or Tailwind's custom utilities.

## 2. Typography

- **Heading font:** `Lora` (serif), loaded from Google Fonts.  Font weights 400–700.
- **Body font:** `Source Sans 3` (sans-serif), weights 300–700.
- Font sizes are defined as utility classes; base size is 16 px with clamp-based scaling for headlines.
- **Scale**: 10 px – 28 px for body and headings, `clamp(2rem, 4vw, 2.75rem)` for large titles.

## 3. Spacing & Layout

- Border radius tokens: `--radius-sm` (4 px), `--radius-md` (6 px), `--radius-lg` (8 px), `--radius-xl` (12 px), `2xl` (16 px) for large elements.
- Spacing utilities follow Tailwind's convention (0, 1, 2, 3, …).  Common custom values: `1.5rem` (24 px) for card body, `2rem` (32 px) for content padding.
- Layout constants: sidebar width 260 px, max-width 1100 px for content, header height 64 px.
- Shadows: subtle card (`0 1px 3px rgba(0,0,0,.08)`), hover card (`0 12px 24px rgba(0,0,0,.1)`), modal (`0 20px 60px rgba(0,0,0,.2)`).

## 4. Dark Mode

- Toggled via a `ThemeProvider` in Vue.  Preference persists in `localStorage` and respects `prefers-color-scheme`.
- Dark theme variables override the light defaults using the `.dark` class.
- **PHP login page** (`public/admin/login.php`) includes its own dark-mode toggle button (fixed top-right) with a `.dark` class on `<html>`. Dark overrides are defined inline in the `<style>` block.
- Dark mode preference is persisted in `localStorage` and restored on page load.

## 5. Component Design

- All UI primitives come from **shadcn‑vue** – the same design‑agnostic primitives used by shadcn‑ui (React).
- Common components: `Button`, `Card`, `Input`, `Select`, `Textarea`, `Toast`, `MarkdownEditor`.
- Layout primitives: `AppLayout`, `Sidebar`, `Header`.

## 6. Authentication UI

### Passwordless-First Login

The default login flow is **magic link / passwordless**. Both the Vue SPA and the PHP admin pages open with the passwordless tab selected.

- **Vue `LoginView.vue`**: Defaults to `mode = 'magic'`; shows email input and "Send me a secure link" button.
- **PHP `login.php`**: Defaults `$magicMode = true`; the **Passwordless** tab is pre-selected.
- After submission, the user sees a **"Check your inbox"** confirmation screen with a link to retry.

### "Enter the blog" CTA

The main call-to-action on the public landing page and navbar is labeled **Enter the blog** and routes to `/login` (Vue) or `/admin/login.php` (PHP).

### Password Fallback

A **Password** tab remains available as a fallback for existing admin users. The password hash is stored in the `admins` table but is **not required** for daily use.

### Sign-Up Request

New users request access via a `POST /api/signup-request` endpoint that stores a pending invitation in the `invitations` table and sends a confirmation email. An admin approves the request and the user is invited via magic link.

```html
<div class="theme-bg theme-fg p-6 rounded-2xl shadow-lg">
  <h1 class="text-3xl font-display font-semibold mb-4">Blog Demo</h1>
  <p class="text-base font-body mb-8">Feature‑rich PHP + Vue blog.</p>
  <Button variant="primary">Read More</Button>
</div>
```

The classes rely on the Tailwind utilities as well as the theming utilities (`theme-…`).

## 7. Documentation

- Tokens and theme configuration are compiled from `frontend/src/style.css` during the Vite build.
- References and usage guidelines live in the project source.  Document further as new components are added.

---

> All colors and sizes are intentional choices for a warm, earthy palette suitable for a long‑read blog.
