# Mijn kookboek — Design Aesthetic

This file is the source of truth for visual design across the app. **Always read
this file before changing any UI** (Welcome page, app shell, recipe pages,
cook view, dialogs, etc.). The screenshots in `design/` are the visual brief;
this document is the codified extraction.

## North Star

> Braun by Dieter Rams meets a chunky modern app. Restrained palette,
> tactile shapes, color-coded blocks with intent, terracotta accents on cream
> backgrounds. Industrial-warm, not sterile-minimal.

The reference screenshots in `design/`:

- `155d1bf*.jpg` — **Dieter Rams Palette**. The canonical color story: cream
  putty + saturated orange + warm reds + olive. Calm, vintage-Braun.
- `b9c864*.jpg` — **Simple Beat (drum machine)**. The interaction language:
  cream background, black filled tiles, burnt-orange highlights, monospaced
  timers, pill-shaped chrome, gentle drop shadows.
- `4eb588a*.jpg` — **Watch widgets grid**. The composition language: chunky
  rounded blocks, color-coded panels, flat fills, no gradients, generous
  type, dense data inside tiles.
- `10d213e*.jpg` — **Running app**. The shape language: pastel + saturated
  panels stacked with **puzzle-piece notches between them**. Bold display
  numbers, black icons, playful cohesion.

## Color tokens

The full palette below should map onto Tailwind theme tokens (CSS variables
in `app.css`). When building UI use the semantic name, never hardcode the hex.

| Token | Hex | Role |
|---|---|---|
| `--bg-cream` | `#E8E2D5` | Page background. Warm putty. |
| `--bg-cream-soft` | `#F2EDE2` | Card/panel background, slightly lighter than page |
| `--ink` | `#1A1614` | Body text, button surfaces, filled tiles |
| `--ink-soft` | `#5A4A40` | Secondary text |
| `--ink-faint` | `#9B8E80` | Tertiary / metadata |
| `--accent` | `#E0673E` | **Primary accent**. Buttons, active state, brand. Burnt orange. |
| `--accent-soft` | `#F4D9C9` | Soft tint for active backgrounds, badges |
| `--warn` | `#BF1B1B` | Destructive / strong-warn |
| `--olive` | `#736B1E` | Tertiary block / informational |
| `--rule` | `#1A161420` | Hairlines, dotted dividers (ink at 12% alpha) |

**Color-coded category blocks** (use sparingly, e.g. recipe cards by cuisine
or section markers):

| Token | Hex | Mood |
|---|---|---|
| `--block-lime` | `#C5E04A` | Energetic, attention |
| `--block-pink` | `#F7C2D8` | Playful, soft warning |
| `--block-sky` | `#A6D8F1` | Cool, info |
| `--block-cream` | `#F2EDE2` | Neutral grouping |

**Always pair a block color with `--ink` text** (black on color), never the
reverse. Blocks lose their punch on dark backgrounds.

### Forbidden colors

- ❌ Pure white (`#FFFFFF`) — too sterile. Use `--bg-cream-soft` instead.
- ❌ Saturated brand-blues (Tailwind blue-500/600). Doesn't fit the warm
  story. If you need cool, use `--block-sky`.
- ❌ Gradients. Anywhere. Flat fills only.

## Typography

Two faces, used with discipline:

| Use | Family | Weight | Notes |
|---|---|---|---|
| Display headlines | **Instrument Serif** (gratis Google) | 400 + italic for emphasis | Already loaded on `Welcome.vue`. Keep usage to: hero titles, big section openers, recipe titles in print and gallery cards. |
| Body & UI | **Instrument Sans** (theme default) | 400 / 500 / 600 | Everything else: labels, paragraphs, buttons, list items. |
| Numbers (timers, quantities) | Sans with `tabular-nums` class | 600 | `font-mono` is acceptable for stopwatch/cook timer when you want a more "instrument" feel. |

Type rhythm:
- Hero: `text-5xl` to `text-7xl`, `tracking-tight`, `leading-[1.05]`
- Section opener: `text-3xl` to `text-4xl`, `tracking-tight`, serif
- Card title: `text-lg` to `text-xl`, sans, weight 600
- Body: `text-base` (`text-[15px]` is also fine), `leading-relaxed`
- Small label: `text-xs uppercase tracking-[0.2em]`, color `--ink-faint`

Italics carry semantic weight — reserve for **single emphasized word in a
headline** ("een kookboek dat *begrijpt* wat je kookt"). Don't italicize
paragraphs.

## Shape language

- **Radius scale**: `rounded-xl` (12px) for inputs/cards, `rounded-2xl` (16px)
  for panels, `rounded-3xl` (24px) for hero/CTA banners, `rounded-full` for
  buttons-as-pills, badges, FABs.
- **Chunky over delicate**: prefer 14–16px radii over 4–8px. The aesthetic is
  tactile, not glassy.
- **Flat fills, no inner gradients, no inset glow**. A single soft drop shadow
  is allowed on raised cards: `shadow-[0_8px_24px_rgba(26,22,20,0.08)]`.

### Puzzle-piece notch (when to use)

The running-app screenshot uses negative-space cutouts between stacked
panels. Use this for:
- Recipe show page: the section that joins ingredients to steps
- Cook view: between the timer pill and the body content
- NOT for everything — overuse turns it into a gimmick.

Skip on first pass; introduce only when a specific composition begs for it.

## Component patterns

### Buttons

| Variant | Surface | Text | Border | Use |
|---|---|---|---|---|
| Primary | `--ink` | `--bg-cream` | none | Default action |
| Accent | `--accent` | `--ink` | none | Hero CTA, "Klaar met koken" |
| Ghost | transparent | `--ink` | `--rule` | Cancel, secondary |
| Destructive | transparent | `--warn` | `--warn`/30 | Delete, stop session |

Sizes: pill-shaped (`rounded-full`), `px-5 py-2.5` for default, `px-6 py-3`
for hero. Hover: lighten by 10% (`hover:bg-[#3A2C24]` for the ink button).
Active: `active:scale-[0.98]`. Never use a glow ring outside focus.

### Cards / tiles

A card is `--bg-cream-soft` with `border border-[--rule]` and the soft
shadow. On hover for clickable cards: `hover:-translate-y-1
hover:shadow-[0_12px_32px_rgba(26,22,20,0.12)]` and 200ms transition.

For **filled tiles** (the watch-widget style), the entire card is a single
saturated color — `--ink` filled + cream text, or `--accent`/`--block-lime`
filled + `--ink` text. Use sparingly: hero highlights, score chips,
"Welcome, Mike!" style greetings.

### Lists (ingredient / step rows)

- Row container: cream-soft background, full-width, no inner padding gutter
- Left edge: a tappable affordance (checkbox, number badge) that's visually
  distinct from the row body — this preserves the "you can scroll" feeling
- Quantity tabular-nums on the right edge
- Active/completed state: opacity reduce + line-through, never grey-out the
  whole row beyond recognition

### Timers

Stopwatch should feel like a **physical instrument**. Display with
`font-mono tabular-nums`, surrounding pill in `--accent` (running) or
`--ink-faint` (idle / completed). The pulse animation on the clock icon is
mandatory while running — it's the only way the user knows it's tickend.

### Forms & inputs

Inputs sit on `--bg-cream-soft` with `border border-[--rule]`, focus ring
in `--accent` at 30% alpha. No floating labels — labels above the input,
classic, in `--ink-soft`.

## Spacing & rhythm

- Page max-width: `max-w-6xl` (1152px) for marketing/landing, `max-w-4xl`
  for app pages, `max-w-2xl` for cook view.
- Vertical rhythm between sections: `py-16` mobile, `py-20` desktop.
- Inside cards: `p-5` to `p-6`. Generous, not luxurious.
- Mobile gutter: `px-4` to `px-5`. Never less than 16px.

## What to avoid

- ❌ Glassy/glassmorphism (heavy backdrop-blur on chrome backgrounds)
- ❌ Generic shadcn neutral-grey UI without warming the palette
- ❌ Dark mode that just inverts to slate-grey — if we ever ship dark mode,
  it's `--ink` background with `--bg-cream` text, not bluish slate
- ❌ Decorative icons everywhere. Icons mark **interactive affordances**, not
  decoration of headlines.
- ❌ Lucide icons at very small sizes (size-3 or smaller) — they lose weight.
  Stick to size-4 minimum on body, size-5 on headlines.

## When unsure

Look at the four screenshots in `design/`. If a proposed UI element wouldn't
look at home next to the Simple Beat drum machine or the Dieter Rams palette
card, redesign it.

---

**Implementation note**: the cream/ink/accent tokens above don't yet exist in
`app.css`. They should be added as CSS custom properties under `@theme inline`
so they're consumable as `bg-cream`, `text-ink`, `bg-accent` etc. Until then,
hardcoded hex values are acceptable but should match this table exactly.
