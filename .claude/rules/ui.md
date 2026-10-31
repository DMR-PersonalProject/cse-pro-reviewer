---
paths:
  - "app/Livewire/**"
  - "app/Filament/**"
  - "resources/**"
---
- Minimal UI: one accent colour (indigo), WCAG AA in both themes, keyboard first (1-4 / A-D to answer, arrows to navigate).
- Filament is the admin panel only; the user app is plain Livewire.
- Theme is shared through Filament's localStorage key `filament:theme:mode`; the toggle uses `window.cseTheme`.
