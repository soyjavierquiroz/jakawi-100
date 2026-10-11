# Isolated browser checks

This harness renders the native `JakawiVslPlayer`; it has no Laravel/database dependency. Run only in disposable frontend tooling, with no production environment or volumes:

```sh
npx vp dev --config tests/media/tap-harness/vite.config.mjs
```

Run `node --test tests/media/tap-to-sound-browser.test.mjs` with:

- `VSL_PUPPETEER_MODULE`: absolute path to an existing Puppeteer module.
- `VSL_CHROMIUM`: absolute path to Chromium.
- `VSL_TAP_FIXTURES`: directory containing a playable `alpha.mp4` and HLS `playlist.m3u8`, variant playlists and segments (relative filenames).
- Optional `VSL_TAP_URL`: harness URL; defaults to `http://127.0.0.1:8096/tests/media/tap-harness/`.
- Optional `VSL_TAP_SCREENSHOT`: output image; defaults to `/tmp/jakawi-video-touch.png`.

MP4/HLS use actual test media intercepted at `https://media.test/`. YouTube uses an IFrame API contract fixture, including iframe hit testing and crop. Touch/scroll use Chromium emulation; this does not certify a physical mobile browser or live CDN availability. No provider credentials or production media are required. Tests skip when the optional browser tools/fixtures are absent.
