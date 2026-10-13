# Production asset guard

**LIVE** on production `19c946870a6a54981acc8b41f4ef0b56188226fe`. One new build after correction; candidate and LIVE assets/HTML pass. [Release evidence](../../artifacts/landing-json-v1/production-release.json).

The release must use a clean Git export and Docker PHP 8.4. `.dockerignore` excludes `app/public/hot`; [image hardening](../../app/scripts/check-production-artifact.sh) rejects a hot file or symlink. The [manifest checker](../../app/scripts/check-vite-manifest.mjs) verifies all entries, dependencies and compiled files, then runs the [semantic runtime guard](../../app/scripts/check-dev-runtime.mjs).

The semantic guard rejects `@vite/client` and explicit HTTP(S), WS(S) or protocol-relative loopback URLs (localhost/subdomains, 127.x.x.x, IPv6 loopback) in the manifest and referenced JS/CSS/HTML/JSON. It decodes ordinary slash/Unicode/hex escapes and literal string concatenations. Rendered candidate HTML and actual live HTML must also pass this guard before/after cutover. HTML script/link/module references to a local dev server therefore fail. This is release artifact validation, not a general-purpose JavaScript dataflow or security analyzer.

Run against candidate or live public files and rendered HTML:

```sh
node app/scripts/check-dev-runtime.mjs /path/to/public /path/to/page.html
```

Ordinary text or hostname comparisons containing the word `localhost` are allowed. There are **no filename or module allowlists**. The guard does not exempt a loopback URL just because it appears in a dependency. Production network fallbacks to loopback are forbidden.

## The three blocked 448d7d2 bundles

| Bundle | Identifiable source | Exact use | Classification | Network risk / disposition |
| --- | --- | --- | --- | --- |
| `react-DrH1j9xG.js` | `@simplewebauthn/browser/esm/helpers/isValidDomain.js` and `@laravel/passkeys/dist/passkeys-yRK3MD0m.js` | Compare `hostname === 'localhost'` for valid WebAuthn development origin; show InvalidDomainError help text. The hostname comes from the caller/browser. No URL, redirect, fetch or API target is built by these strings. | C — NON-NETWORK VALIDATION / WEB PLATFORM STRING | NO. Preserve standards validation and help text; semantic guard allows it. |
| `jsx-runtime-BbC6BnSA.js` | `@inertiajs/core/dist/index.js`, `mergeDataIntoQueryString` | A dummy `new URL` base only when `window` is absent. The result reconstructs a relative link without the dummy origin; explicit input URLs keep their own origin via `hasHost`. No request is issued by this function. Browser path uses `window.location`. | C — NON-NETWORK VALIDATION / WEB PLATFORM STRING | NO. Remove the loopback literal from this exact parsing base using a checked production build transformation to `https://url-parser.invalid`. |
| `app-K53o_R4H.js` | [useCurrentUrl](../../app/resources/js/hooks/use-current-url.ts) | Parse `page.url` and read `.pathname` only for active-navigation comparisons; the server fallback origin is never returned or used for a request. Browser path uses `window.location.origin`. | C — NON-NETWORK VALIDATION / WEB PLATFORM STRING | NO. Remove the dummy origin entirely: extract the request path or parse the authoritative absolute request URL. |

The old ad hoc deploy scan rejected every lexical `localhost` match. This was broader than the actual dev-runtime protection requirement. The correction permits non-network strings while rejecting loopback URLs in runtime artifacts.

The [Inertia transformation](../../app/scripts/inert-inertia-url-base.mjs) changes **one exact URL constructor expression**, not arbitrary localhost strings. It fails if the upstream expression changes or occurs more than once, requiring a new semantic review. `.invalid` is a reserved parsing placeholder, never the application's configured canonical origin or a network fallback. It preserves original relative/absolute link and query behavior. No dependency files or lockfile are modified.

[Five focused guard tests](../../app/tests/release/dev-runtime.test.mjs) cover failing dev HTML/loopback assets/Vite references, allowed WebAuthn/plain text, hot-file/symlink checks, HTML/modules, and the actual transformed Inertia function's relative and absolute results. Run from `app`:

```sh
node --test tests/release/dev-runtime.test.mjs
```
