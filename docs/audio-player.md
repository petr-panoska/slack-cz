# slackvibes 📻 — audio player

Persistent music player rendered jako součást hlavičky. Přežívá Turbo navigaci; audio hraje plynule při proklicích bez reinicializace.

## Stavy

Tři ortogonální stavy:

| Stav | Trigger | Vizuál | localStorage |
|---|---|---|---|
| **Compact (docked)** | Default; `−` v expanded | Pill v hlavičce vlevo od loginu | `slack-cz:music:compact = true` |
| **Expanded (floating)** | `+` v compact | 320px panel, draggable, s title/artist/progress/queue | `slack-cz:music:compact = false` |
| **Hidden** | `×` v panelu nebo „Vstoupit" na splash | Zmizí (`display: none`) až do dalšího full reloadu | `slack-cz:music:hidden = true` (smaže se na cold load) |

Mute/Play tlačítko slouží duálně:
- **Compact**: zobrazí ▶/⏸ + provádí play/pause
- **Expanded**: zobrazí ♪/⊘ + provádí mute/unmute

Dispatcher v controlleru: `toggleMuteOrPlay()` rozhodne podle `is-compact` třídy na panelu.

## Architektura

### Umístění markup

Markup žije v `templates/user_toolbar.twig` jako **první child** `.site-user`, před login/register/user buttons. Wrapper má:
- `id="music-player"`
- `data-turbo-permanent` — Turbo zachovává element napříč navigacemi
- `data-controller="intro"` — Stimulus controller scope

Wrapper používá CSS `display: contents` aby jeho děti (intro-overlay + music-panel) participovaly přímo v `.site-user` flex layoutu.

### Lifetime audio objektu

HTML5 `Audio` žije v **module scope** (`shared.audio` v `intro_controller.js`) — NE na Stimulus controlleru. Kritické: Turbo permanence může spouštět Stimulus disconnect/reconnect při "transplantaci" elementu mezi page snapshoty. Module scope řeší:

- `disconnect()` je no-op (NESÁHNE na audio)
- `connect()` se re-binduje na existující audio
- Audio hraje plynule přes jakýkoli DOM lifecycle event kromě full page unload

Module-level `currentInstance` pointer drží referenci na poslední živou instanci controlleru — listenery na audio (např. `ended` → next track) přes něj vždy trefí aktuální controller.

### First-connect detekce

Module-scope flag `firstConnect = true` rozlišuje:
- **První connect v JS contextu** (= full page reload): smaže `STORAGE_HIDDEN`, takže player se po refresh vrátí
- **Další connecty** (= Stimulus reconnect při Turbo nav): zachovává stav

Důsledek: klik `×` skryje player do nejbližšího full reloadu. Turbo navigace + browser back/forward respektují hidden stav.

## State storage (localStorage)

| Klíč | Účel | Default |
|---|---|---|
| `slack-cz:music:track` | Index aktuální stopy | `0` |
| `slack-cz:music:muted` | Mute on/off | `false` |
| `slack-cz:music:compact` | Compact (docked) vs expanded | `true` (compact) — nový uživatel vidí docked pill |
| `slack-cz:music:hidden` | Skrytý po `×` | smazáno na cold load |
| `slack-cz:music:position` | `{x, y}` draggované expanded pozice | žádný (defaultní CSS bottom-left) |

## Stopy

Tracks definované inline v `templates/base.html.twig` jako Twig array, předávané přes Stimulus `tracksValue`. Soubory v `public/audio/` (Pixabay-style filenames `artist-title-tags-id.mp3`), všechny překódované na **128 kbps stereo 44.1 kHz** přes ffmpeg.

Originály (256–320 kbps) jsou v `var/audio-original/` (var/ je gitignored). Pokud potřebuješ originály obnovit / re-encode jiným bitrate:

```bash
cd public/audio && for f in *.mp3; do
    cp ../../var/audio-original/$f .
done
```

Tracks se přehrávají **sekvenčně s crossfade**, playlist se loopuje. Klik na položku ve frontě přepne stopu. Auto-advance na `ended` event.

`audio.preload = 'metadata'` — nestahuje se celý mp3, jen metadata pro `duration`.

## Drag (jen expanded)

Drag handle = `.music-panel-header`. Click-and-drag posunuje panel; pozice persistuje v `STORAGE_POSITION`. Compact (docked) je v flex layoutu — drag je vypnutý.

`pointerdown` → `startDrag` → tracky `pointermove` / `pointerup` / `pointercancel` na `document`. Kliky na buttons / `.music-panel-trail` jsou vyloučené (tlačítka fungují normálně). Pozice se clampuje do viewportu s 8px marginem.

`touch-action: none` na hlavičce vypíná browser scroll/pan při drag na touch zařízení.

## Equalizer

Hlavička má 4 magenta tyčinky (`.music-eq > span`) s CSS-keyframe animací (height 28% → 100% → 28%). Každá tyčinka má jiný delay + duration → asynchronní bouncing.

Controller togluje `.is-playing` třídu na `.music-panel` podle `audio.paused`. CSS na základě toho startuje/pauzuje animaci. Respektuje `prefers-reduced-motion: reduce` (animace off, výška fixed na 60%).

## CSS state classes (na `.music-panel`)

- `.is-compact` — docked v hlavičce (default); body se sklopí, position relative
- `.is-playing` — řídí equalizer animaci + ▶/⏸ ikonu v compact mute button
- `.is-dragging` — vypíná transitions během drag, prohloubí stín

## Splash overlay (Vstoupit / slackvibes 📻)

Full-screen overlay zobrazený na cold load. Nutný protože browser blokuje autoplay bez user gesture. Dvě CTA pod sebou:

| Tlačítko | Action | Po kliku |
|---|---|---|
| **Vstoupit** | `intro#enterSilent` | `shared.started = true` + `.intro-started` + `.music-hidden` na wrapperu + `STORAGE_HIDDEN = true`. **Audio se nespouští.** Player zůstane skrytý — toggle-back přes full reload (firstConnect clear). |
| **slackvibes 📻** | `intro#enter` | `shared.started = true` + `.intro-started` + clear `music-hidden`/`STORAGE_HIDDEN` (kdyby tam zbyl z předchozí Turbo nav) + `startAudio()`. Klik je gesture pro autoplay unlock. |

Default UX: většina návštěvníků klikne „Vstoupit" → tichý vstup. Kdo chce hudbu, klikne na druhý button. Nepersistuje — každý cold load = nový splash s oběma volbami. Turbo navigace respektuje `shared.started` (module scope), takže splash se znovu neobjeví během procházení.

## Soubory

- `templates/user_toolbar.twig` — markup (player + login/register)
- `assets/controllers/intro_controller.js` — Stimulus controller (~270 řádků)
- `assets/styles/app.css` — `.music-panel`, `.music-mute`, `.music-mini`, `.music-eq`, `.music-close`, `.music-controls` etc.
- `public/audio/*.mp3` — stopy (128 kbps stereo)
- `var/audio-original/*.mp3` — gitignored zálohy před re-encodingem

## Operations

```bash
# Po změně controlleru / CSS / template (prod mode):
make dcAssetMapCompile

# Ladění audio play state v dev (browser console):
localStorage.removeItem('slack-cz:music:hidden')   # vrátit player
localStorage.setItem('slack-cz:music:compact', 'false')   # rozbalit
localStorage.removeItem('slack-cz:music:position')   # reset draggované pozice
```
