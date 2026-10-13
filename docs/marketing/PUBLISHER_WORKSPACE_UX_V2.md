# Publisher workspace UX V2

Status: READY FOR DEPLOY, no deployment. Production remains on Publisher Workflow UX V1 and the editorial validation correction.

On desktop (1024 px and wider), the existing Admin editor uses the available width: editor 45%, saved Preview 55%. The sticky toolbar shows the landing name, lifecycle, audience and saved/dirty state. It groups AI actions and the primary GUARDAR Y PREVISUALIZAR action; publication stays explicit, and archive/unpublish live under Más acciones. The list marks the record being edited. Creating and saving a new landing keeps its editor open.

CREAR CON IA: COPIAR BRIEF PARA IA → GPT JAKAWI → IMPORTAR JSON → review message/media → GUARDAR Y PREVISUALIZAR → inspect Preview → PUBLICAR. Export keeps using the current form. Import never saves or publishes.

Editor sections: MENSAJE, MEDIA, CAPTACIÓN, CIERRE, PUBLICACIÓN / ATRIBUCIÓN, AVANZADO. Product truth starts collapsed; Ver datos reveals every authoritative value. Product switching and template/theme controls sit in AVANZADO; switching preserves the editorial message. IMAGE shows IMAGEN ACTUAL with its existing reference and alt controls.

Preview opens with an existing selected landing at 390 px. It uses the existing saved-state route and renderer. Controls select 390, 430 or Desktop; the canvas fits the pane without page overflow. Preview stays visible beside the desktop editor. Dirty state is explicit in both toolbar and Preview: El Preview todavía muestra la versión guardada. GUARDAR Y ACTUALIZAR PREVIEW submits the same form. A successful server response reloads the iframe and confirms PREVIEW ACTUALIZADO. Publication remains blocked while dirty.

Below 1024 px, editor and Preview stack. No new Publisher, backend, model, JSON schema, database migration, media library, AI integration or template engine. OfferConversionTemplate V3 remains frozen.

[Validation](../../artifacts/publisher-workspace-ux-v2/validation.md). [Live V1 workflow](PUBLISHER_WORKFLOW_UX_V1.md).
