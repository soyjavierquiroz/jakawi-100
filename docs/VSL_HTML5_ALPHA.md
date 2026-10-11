# JAKAWI VSL HTML5 Alpha — Phase 3A

Development checkout: `/home/jakawi-vsl-dev`, branch `feat/jakawi-vsl-v1`.
Infrastructure commit: `57aec795cae667fbd487359aeef4ef0bdea3b541`.
No push, deployment, migration, production route, Growth event, or image-pipeline change.

## Infrastructure review

The authoritative Phase 1D/1E summary was compared with all five diffs, not inferred from report filenames.

| File | Verified changes |
| --- | --- |
| `bin/check-vsl-isolation.py` | Accepts exactly one additional dedicated dev entry network; only web joins it. App, DB, frontend tools and every test service remain internal. Dedicated resources, env, images and loopback port assertions remain enforced. |
| `bin/jakawi-vsl-test` | Accepts a single quoted PHPUnit filter and adds warning detail. Fixed test configuration, project, env, locking and effective Laravel DB assertions remain intact. |
| `compose.vsl-dev.yaml` | DB/web: 0.5 CPU, 256 MiB, 256 PIDs; app/tools: 1 CPU, 1536 MiB, 256 PIDs. Web entry network is independent and non-external. Port remains `127.0.0.1:8093:80`; tools target is `tools-offline`. |
| `compose.vsl-test.yaml` | Test DB: 0.5 CPU/256 MiB; test app: 1 CPU/1536 MiB; both 256 PIDs. App targets the corrected `test` stage. No port or external network. |
| `docker/Dockerfile.vsl` | Test stage creates an empty `.env` and removes `public/build`; offline tools copy only the assets-stage font cache. Production Dockerfile is untouched. |

Both guards passed. Resolved Compose and running container inspections confirmed CPU/memory/PID limits, dedicated volumes and networks, and absence of production mounts/tags or shared writable volumes. Effective dev/test Laravel DB configurations were asserted without displaying credentials. The running test image has an empty `.env` and no `public/build`. Added diff contains no credentials or production DB references. Staged exactly those five files; reviewed staged changes and ran `git diff --cached --check` before committing.

Host pre-flight: 4 CPUs, 7.8 GiB RAM total, 4.7 GiB available, 17 GiB free disk. Production checkout remained clean at `16909608893ee1e955c03ed52516198a1b44ba5b`.

## Files and public contract

Player files are under `app/resources/js/components/media/jakawi-vsl-player/`:

- `types.ts`: typed MP4/controlled fixture source, state, errors, event union and component props.
- `html5-engine.ts`: native media lifecycle, commands, URL policy and autoplay preference policy.
- `use-vsl-playback.ts`: per-source controller lifetime, callback forwarding and preference listeners.
- `JakawiVslPlayer.tsx`: video, poster, manual fallback, audio CTA and fullscreen.
- `PlaybackControls.tsx`: labeled buttons, seek/volume sliders, time display and focus styles.
- `JakawiVslPlayer.module.css`: scoped fullscreen presentation.
- `index.ts`: component and public types.

Example integration for a later authorized landing change:

```tsx
<JakawiVslPlayer
    source={{ type: 'mp4', url: 'https://media.example.com/video.mp4' }}
    poster="/poster.jpg"
    fit="cover"
    focalPosition="50% 35%"
    onEvent={(event) => console.log(event.type)}
/>
```

The alpha is not yet imported into any marketing landing. HTTPS URLs must serve browser-decodable MP4 media. Embedded URL usernames/passwords are rejected; no credentials are supplied by this component. The fixture is accepted only in Vite development mode at the fixed same-origin path.

`onEvent` emits `ready`, `playing`, `paused`, `progress`, `ended`, `error`, and `audio-activated`; each carries a playback snapshot. Error kinds distinguish autoplay policy rejection, manual playback rejection, media failure and invalid source. This is a technical callback, with no analytics integration. A future adapter can implement the same command/event contract; no YouTube/Bunny implementation is included.

## Playback behavior

One mounted video element persists through pause, resume, audio activation, end and replay. A local controller owns its listeners for one source session. Source change disposes that controller and initializes another on the same element. Disposal removes listeners, invalidates pending promises, pauses and releases the media source. Stale session callbacks and events whose selected resource differs are ignored.

Autoplay defaults on, always muted. Reduced-motion, Save-Data and explicit autoplay opt-out disable the automatic request; suppressed autoplay uses `preload=none`. A readiness event requests playback once. Promise resolution alone does not set playing; native `playing` confirms it and enables the audio CTA. `NotAllowedError` leaves a poster/manual action. A preference change pauses without automatically resuming.

Click-to-sound registers audio intent inside the user gesture, pauses to ensure a fresh playing transition, seeks to zero, unmutes and requests playback synchronously. No React effect delays the gesture. A new native `playing` event plus native unmuted/nonzero-volume state confirms audio activation. Audio failure remutes and leaves a manual retry; user gesture permits another activation attempt. This confirms provider state, not physical speaker output.

Pause preserves position, mute and volume. End invalidates pending play, stops, seeks to zero and stays replay-ready. Readiness events cannot restart ended playback. Replay seeks to zero on the existing element and preserves explicit mute/volume preference. No loop.

The normal frame is responsive 3:4, with CSS cover/contain and configurable focal position for a 9:16 source. Fullscreen uses contain and a centered complete frame; WebKit native video fullscreen is a fallback. Buttons and sliders are keyboard-accessible, with labels, visible focus and a polite status message. No global video styles.

## Isolated demo

`app/dev/vsl-demo/` is a separate Vite serve-only entry. It is absent from production Vite inputs and Laravel routes. Config requires `VSL_DEMO=jakawi-vsl-dev` and `APP_ENV=local`, refuses builds, and limits filesystem serving to the demo, frontend resources and dependencies.

```bash
cd /home/jakawi-vsl-dev
bin/jakawi-vsl-demo
# http://127.0.0.1:8094
bin/jakawi-vsl-demo --stop
```

Launcher requires the VSL branch and dev guard, uses existing exclusive dev images, generates a six-second 180x320 H.264/AAC test pattern/tone using single-threaded ffmpeg, and copies it to disposable container storage. The fixture stays under ignored `.vsl-state` locally; no MP4 is committed or placed in `app/public`. There are no bind mounts, upload API or private media copies.

Vite (`jakawi-vsl-dev-demo`) stays on the internal dev network. A separate nginx ingress (`jakawi-vsl-dev-demo-web`) joins only the dedicated dev entry/internal networks and publishes `127.0.0.1:8094:80`. It has 0.5 CPU/256 MiB/256 PID limits; Vite inherits 1 CPU/1536 MiB/256 PIDs. Stop checks both project and purpose labels before removing only these containers. Production and the existing dev web containers are not altered. The demo is left running for review. An SSH loopback tunnel is needed for a remote browser.

## Validation and limitations

Tests use Node's built-in test runner with a simulated media element, not a browser playback engine. `npm run test:vsl` compiles the controller into a temporary directory, runs the focused suite and removes the compiled output. No test framework or dependency was added. Test files: `app/tests/media/jakawi-vsl-player.test.mjs` and `run-vsl-tests.sh`. The package change adds only that test script. No package-lock or dependency change.

All 17 targeted tests passed. Full TypeScript check, scoped formatting/lint (12 formatted files, zero lint warnings/errors) and the offline Vite build passed. Bash syntax checks passed for both launchers.

Tests cover initial/requested/confirmed state, autoplay blocking, click-to-sound, audio failure/retry, pause/node preservation, end/reset/replay, source change, stale events/promises, cleanup, reduced motion/Save-Data, pre-ready manual playback, seek/mute/volume/progress, source rejection, deferred autoplay cancellation, error recovery, synchronous play exceptions and AbortError recovery.

Isolated frontend checks use a disposable `frontend-check` container with current files copied in, no source bind mounts. Commands inside it:

```bash
npm run test:vsl
npm run types:check
./node_modules/.bin/vp check resources/js/components/media/jakawi-vsl-player tests/media/jakawi-vsl-player.test.mjs dev/vsl-demo package.json
npm run build
```

The app Vite build remains offline on the internal network using cached fonts. Existing optional fontaine/plugin timing notices are not player failures. Formatting was scoped to the new frontend/test/demo files and package JSON; the reported unrelated formatting debt was not touched. No unrelated PHPUnit baseline changes were made.

HTTP checks confirm the demo HTML, TSX and CSS transforms load, and the fixture returns `video/mp4`, byte-range support and HTTP 206. ffprobe confirms 9:16 H.264 video and AAC audio. These checks do not prove real playback, autoplay policy, audible output, keyboard focus behavior, mobile Safari or fullscreen. The demo build refusal, exclusion from the app build manifest, and a filesystem request outside the allowed frontend directories (HTTP 403) were also verified. No browser executable or browser connector is available; real browser acceptance remains pending. Browser media support, URL availability, autoplay restrictions and fullscreen support remain platform-dependent.

## Git state at completion of Phase 3A

Infrastructure is committed at the SHA above. Alpha work is left uncommitted for review: modified `app/package.json`; new player modules, demo directory, media tests, `bin/jakawi-vsl-demo` and this report. The index is empty. Nothing was pushed. Production checkout is still clean at the original SHA and the production app container remains healthy. The temporary frontend check container was removed; only the review demo remains running alongside the pre-existing isolated development/test services.

## Phase 3B review

The current review and manual browser acceptance matrix are in [VSL_HTML5_REVIEW.md](VSL_HTML5_REVIEW.md). Phase 3B adds native buffering state, idempotent play on confirmed playback, preserved media-error recovery during preference pauses, guarded fullscreen promises and correct zero-volume messaging. The demo adds source/focal controls and its own TypeScript project. The reviewed Alpha is committed locally during Phase 3B; the uncommitted state described above is historical. The suite now has 20 passing simulated tests. Browser acceptance remains pending.
