# Incidentes y lecciones

## `migrate:fresh` accidental en QA

**Síntoma:** un reset destructivo se dirigió a un entorno que no debía recibirlo.

**Causa:** separación insuficiente entre destinos de test y producción.

**Fix:** wrapper aislado `./bin/jakawi-test` con proyecto, host y base de test explícitos.
**Prevención:** jamás usar `migrate:fresh` en producción; toda prueba/destrucción de test pasa por el wrapper.

## Pantalla negra por contexto Inertia

**Síntoma:** pantalla React negra.

**Causa:** `useAppearance`/`usePage` se usó fuera de un contexto Inertia.

**Fix:** se desacopló el bootstrap/uso global de esa suposición.
**Prevención:** componentes globales deben funcionar sin asumir una página Inertia disponible.

## Pantalla negra por assets Vite desincronizados

**Síntoma:** manifest/chunks 404 y pantalla React negra.

**Causa:** imágenes `app` y `web` se desplegaron con builds frontend distintos.

**Fix:** reconstrucción y recreación coordinada.
**Prevención:** después de cambios frontend, desplegar `app` y `web` juntos, siempre.

## `InvalidAccessKeyId` de MinIO

**Síntoma:** Laravel no autenticaba contra MinIO.

**Causa:** el contenedor `app` conservaba configuración sin `MEDIA_AWS_ENDPOINT`.

**Fix:** recrear el contenedor con el entorno correcto.
**Prevención:** después de cambiar `.env`, recrear el servicio afectado y verificar su configuración efectiva.

## `SignatureDoesNotMatch` de imgproxy

**Síntoma:** imgproxy no podía leer S3/MinIO.

**Causa:** el hostname API de MinIO estaba detrás del proxy de Cloudflare.

**Fix:** se retiró ese proxy.
**Prevención:** mantener `myminioback.kuruk.in` DNS-direct.
