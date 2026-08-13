# Design previews

Self-contained HTML harnesses for reviewing the plugin's front end without a
WordPress install. Each one splices in the real stylesheet, so what you see is
what the site renders.

| File | Shows |
|---|---|
| `portal.html` | The IFDA course student portal — signed in, two courses, signed out, no course found |

Open the file directly in a browser. Pick a size preset or drag the right edge
for any width; the readout shows the live dimensions and ratio. **Replay
animations** re-runs the entrance sequence, and **Reduced motion** checks the
`prefers-reduced-motion` path.

## Rebuilding after a CSS change

The stylesheet is embedded at build time, so re-splice it when it changes:

```sh
python3 - <<'PY'
css  = open('mizuki-booking/assets/css/mzk-front.css').read()
safe = css.replace('`', '\`')
p = 'preview/portal.html'
s = open(p).read()
import re
s = re.sub(r'const PLUGIN_CSS = `.*?`;', 'const PLUGIN_CSS = `' + safe + '`;', s, count=1, flags=re.S)
open(p, 'w').write(s)
PY
```

Fonts fall back to system faces here — Poppins and Radio Canada load from the
live site, not from this file. Spacing, colour, shape and motion are exact.
