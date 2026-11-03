# COM — Cambio directo a CLUB desde soporte

La barra COM y la pantalla de bloqueo incluyen «Ver en CLUB». POST `/soporte/club`
con CSRF obtiene el UUID del destino exclusivamente desde el soporte guardado en
servidor, registra `end_reason=club`, termina soporte, regenera sesión y CSRF,
y redirige a la URL fija `https://jakawi.club/admin/soporte?subject=UUID`.
No recibe redirecciones ni UUID de destino desde el formulario.

GET `/admin/soporte?subject=UUID` exige superadmin, valida UUID y filtra la
selección. No inicia soporte ni concede autenticación. Mantiene la contraseña
reciente, MFA habilitado y motivo obligatorio. Para revisar a Javier se permite
sólo observar la propia cuenta administrativa; otros administradores continúan
excluidos. La lista de lecturas y el bloqueo de operaciones siguen vigentes.

Si CLUB inicia un GET OIDC durante soporte, COM valida exclusivamente sus
parámetros mediante el mismo validador estricto existente, sin ejecutar Passport
ni emitir códigos. Conserva la petición original en sesión COM con identificador
aleatorio, actor, soporte y vencimiento. Ofrece «Salir del modo soporte y continuar
en CLUB»: un POST con CSRF consume esa continuación, registra
`end_reason=oidc_resume`, termina soporte y redirige al `/oauth/authorize` local.
Sólo después actúan los permisos, validaciones y autenticación OIDC originales
con la identidad administrativa. Se preservan state, nonce y PKCE. Se rechazan
URLs ajenas, continuaciones inválidas, vencidas o ajenas al soporte/actor.

No hay sesiones ni cookies compartidas con CLUB, cambios de UUID, compras,
cuentas ni migraciones. CLUB/CRM no se modifican. La inspección de lectura del
controlador CLUB existente confirmó que recibe `subject`, conserva selección
al autenticar y devuelve `subject` a COM; un usuario inexistente produce una
búsqueda vacía, sin iniciar soporte ni crear al usuario observado.

Validación focalizada en PostgreSQL aislado con cuentas sintéticas: salida de
Drex, selección de retorno y reautenticación, Javier sobre sí mismo, OAuth con
sesión CLUB ausente/vencida, destino sin cuenta CLUB, identidad del código emitido
exclusivamente del actor, bloqueo a admin normal, CSRF, expiración y rechazo de
redirecciones arbitrarias. La parte externa de CLUB se conserva y no se inician
sesiones LIVE para comprobar estos recorridos.

Evidencia operativa y SHA: `/opt/sites/jakawi.com/artifacts/support-club-handoff/LIVE.json`.
