# Marketing Publisher workflow UX V1

Status: **LIVE** on current production application `ba25043f2cb35b7e7feae12a4f234792cbf84c66`; its editorial correction was deployed as `642dbd7c5ea6ed1d605fdba546cf19476b865013` from actual runtime `0a0d4b6939ae914ffe9face7436d099e1cfac6a4` on 2026-10-13 (America/La_Paz). Landing JSON V1 and AI Product Brief V1 remain LIVE. No JSON schema, domain, DB, lifecycle, template, adapter or preview engine changes.

COPIAR BRIEF PARA IA → external GPT → IMPORTAR JSON → CAMBIOS SIN GUARDAR → review/media → GUARDAR Y PREVISUALIZAR → inspect current saved Preview → PUBLICAR.

Import != save. `✓ JSON APLICADO` confirms the editorial form patch and its changed-field count. `● CAMBIOS SIN GUARDAR` identifies values that differ from the saved baseline, including media/video, audience and product selection. Export still exports current form state.

`GUARDAR Y PREVISUALIZAR` sends the existing save request. Only the successful server response confirms `✓ LANDING GUARDADA` / `✓ CAMBIOS GUARDADOS`, establishes the persisted baseline and opens/refreshes the real saved Preview. The editor stays open, preserving name, slug and copy. New records become editable saved DRAFTs. There is no automatic publication.

Preview V1 renders **saved state**. While the associated editor is dirty its heading is `PREVIEW · VERSIÓN GUARDADA`, with an explanation that unsaved changes are absent. After Save the returned persisted record supplies the current slug, and the iframe reloads even when that slug is unchanged. This uses the existing authorized Preview route. It does not serialize unsaved values or render them through a second endpoint.

On rejection, `NO SE PUDO GUARDAR` lists the affected fields, marks actual errors in red and focuses the first available affected control. Every current form value remains intact, and the saved Preview is unchanged. Slug collisions say `El slug "…" ya está en uso.`; unchanged own slugs remain allowed. Informational commercial-truth guidance uses neutral help styling. The editorial validation correction is LIVE: normal editorial prose is editable within plain-text/length constraints, while domain/action overrides remain structurally forbidden. It removes the keyword heuristic that rejected `una`; the exact Arcilla paragraphs save and refresh Preview in production. [Validation](../../artifacts/editorial-validation-fix/validation.md).

PUBLICAR with unsaved editor changes is blocked with `GUARDA TUS CAMBIOS ANTES DE PUBLICAR.` There is no auto-save/publish combination and no new technical requirement to view Preview before publishing. Publication and existing lifecycle actions remain separate from JSON productivity controls.

When IMAGE is selected, the Media section shows the currently resolved saved/product image, using the same authoritative asset and MediaUrl service as Preview. The existing reference input remains available for replacement; a newly typed reference is verified by saving. No media library is added.

Publisher UX V2 is **LIVE**: [desktop workspace and operator workflow](PUBLISHER_WORKSPACE_UX_V2.md). V1 save, validation and saved-Preview contracts remain LIVE. [Validation and reproduction](../../artifacts/publisher-workflow-ux-v1/validation.md).

[Production release](../../artifacts/publisher-workflow-ux-v1/production-release.json): one build, no migrations or domain mutations; protected rollback; real deployed Admin DRAFT smoke PASS, temporary record removed. No rebuild/redeploy for the documentation followup. Landing JSON V1 and AI Product Brief V1: LIVE. Batch: FUTURE. Welcome Courtesy: FUTURE / OFF.

[Editorial correction production release](../../artifacts/editorial-validation-fix/production-release.json): one build, protected previous runtime, no migrations/domain mutations; one existing Arcilla DRAFT editorial save, no publication or audience/campaign changes.
