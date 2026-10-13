# Challenge direct join

**LIVE — producción `7cdc8c12e71b23e6c6b99db522e2e6274be6b87f`; base/rollback protegido `f2b488b`.** Un build productivo desde export Git limpio, sin migraciones, cleanup ni recreación de DB/v2proof.

[Release](production-release.json): runtime read-only verifica el POST directo, ausencia de interstitial y Activación Sunset/Café. Register/login → Delivery se comprueba con la misma imagen inmutable y participaciones QA aisladas existentes. [Smoke público](production-browser.json) confirma el CTA final «QUIERO PARTICIPAR» sin enviar signup/join. Cero nuevas conversiones/participaciones en producción.

Activación requiere elección operativa real. Challenge actual: CTA explícito → join → Delivery; adquisición conserva `intended_action=PARTICIPATE` y lo valida tras autenticar. Registro genérico, GET y contexto sin consentimiento no participan. Evidencia/recompensas conservan email verificado; Sunset fecha y Café sucursal mantienen Activación. Delivery V1.1 y Unlock sin cambio; Welcome Courtesy FUTURE / OFF / NOT IMPLEMENTED.

[Backend enfocado](backend-validation.txt), [contratos afectados](related-contracts.txt), [acción validada](action-validation.txt), [browser 390 aislado](browser-validation.json), [validación](validation.json). Un build frontend final. Sin matriz ni suite amplia.

[Autenticado directo](authenticated-direct-390.png) y [guest directo](guest-direct-390.png): ambos llegan a «YA ESTÁS DENTRO DEL RETO» sin solicitud intermedia a `/activar`.
