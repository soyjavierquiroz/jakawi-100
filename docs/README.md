# Índice de documentación JAKAWI

Referencia: **2026-10-09**. Empieza por [CURRENT](CURRENT.md), el [Manual de Funciones](product/MANUAL_DE_FUNCIONES.md) y la [arquitectura](ARCHITECTURE.md). Para cargar oferta real, usa el [Manual de Inventario](operations/MANUAL_DE_INVENTARIO.md). Código de referencia y estado operativo se distinguen en CURRENT; los históricos no son runbooks.

## Product

- [Manual de Funciones](product/MANUAL_DE_FUNCIONES.md): fuente funcional para producto, soporte, operaciones, comercial y developers.
- [PRODUCT](PRODUCT.md): resumen de propuesta; [DOMAIN](DOMAIN.md): entidades; [UX-MVP](UX-MVP.md): convenciones de navegación.
- [UNLOCKS](UNLOCKS.md): mecanismo colectivo; [Cities V1](cities-v1.md): estados/localidad/aplicaciones.
- [ROADMAP](ROADMAP.md): futuro explícitamente no operativo.

## Architecture

- [CURRENT](CURRENT.md): **fuente del estado de producción y límites actuales**.
- [ARCHITECTURE](ARCHITECTURE.md): dominios, runtime y límites Product Detail/marketing/proyección.
- [MEDIA](MEDIA.md): almacenamiento privado y entrega de imágenes.
- [PAYMENTS](PAYMENTS.md): contrato QR preparado, deshabilitado.

## Development

- [README raíz](../README.md): estructura y comandos básicos Docker.
- [OPERATIONS](OPERATIONS.md): ejecución segura, aislamiento y baseline conocido.
- [README aplicación](../app/README.md): entrada a Laravel/React.
- [DESIGN-SYSTEM](DESIGN-SYSTEM.md), [ejemplos](examples/README.md): referencias de diseño.

## Deployment

- [DEPLOYMENT](DEPLOYMENT.md): política de producción directa y enlace al procedimiento.
- [OPERATIONS](OPERATIONS.md): runbook canónico de deploy/backups/rollback/disco.

## Operations

- [OPERATIONS](OPERATIONS.md): scheduler Docker PHP 8.4, seguridad DB y Docker.
- [CRM](CRM_FOUNDATION_V1.md): automations actuales y cron WordPress/FluentCRM.
- [MANUAL-SALES](MANUAL-SALES.md): ventas manuales; [CONVERSION](CONVERSION.md): pipeline económico.

## CRM

- [CRM V1](CRM_FOUNDATION_V1.md): fuente de arquitectura/contrato/schema y estado operativo CRM.
- [Bridge 1.0.1](https://github.com/soyjavierquiroz/jakawi-fluentcrm-bridge/blob/main/README.md): seguridad, configuración/provisioning y diagnostics de plugin.

## Growth / Attribution

- [Growth Measurement V1](growth-measurement-v1.md): nueve eventos, funnel y contexto de adquisición.
- [Meta Provider V1](../app/docs/meta-provider-v1.md): Pixel+CAPI implementados, producción OFF.
- [ATTRIBUTION](ATTRIBUTION.md): referido/economía; [CAMPAIGNS](CAMPAIGNS.md): reglas económicas.
- [MEMBER-REFERRALS](MEMBER-REFERRALS.md), [AFFILIATES](AFFILIATES.md), [CREATORS](CREATORS.md), [PARTNER-ACQUISITION](PARTNER-ACQUISITION.md): mecanismos específicos.

## Inventory

- [Manual de Inventario](operations/MANUAL_DE_INVENTARIO.md): **fuente operativa de carga real**, lifecycle, checklist, publicación y piloto.
- [Manual de Funciones](product/MANUAL_DE_FUNCIONES.md): comportamiento y recorridos que la oferta debe respetar.

## QA / Runbooks

- [OPERATIONS](OPERATIONS.md): wrapper aislado, frontend-check temporal, prohibiciones y baseline CampaignTest.
- [Manual de Inventario](operations/MANUAL_DE_INVENTARIO.md): checklist Guest/User/Member, disponibilidad y móvil 390px.
- [CRM](CRM_FOUNDATION_V1.md): delivery/requeue y cron sin mutaciones improvisadas.
- [Historial CRM](history/CRM_FOUNDATION_IMPLEMENTATION.md): validación anterior, HISTORICAL / REFERENCE.

## Inventario de autoridad y referencias retenidas

| Ruta | Propósito | Audiencia | Estado |
| --- | --- | --- | --- |
| ../README.md | Entrada y ejecución básica | Developers | CURRENT / entrada |
| README.md | Índice único y autoridad | Todos | CURRENT / índice |
| CURRENT.md | Estado productivo | Producto/dev/ops | AUTHORITATIVE |
| ARCHITECTURE.md | Dominios y runtime | Developers/producto | AUTHORITATIVE |
| product/MANUAL_DE_FUNCIONES.md | Uso y recorridos reales | Producto/ops/soporte/comercial/dev | AUTHORITATIVE |
| operations/MANUAL_DE_INVENTARIO.md | Carga comercial real | Comercial/ops/producto/Admin | AUTHORITATIVE |
| CRM_FOUNDATION_V1.md | Contrato y operación CRM | Developers/ops CRM | AUTHORITATIVE |
| growth-measurement-v1.md | Analytics canónicos y adquisición | Growth/dev/producto | AUTHORITATIVE |
| ../app/docs/meta-provider-v1.md | Implementación Meta | Developers/ops | AUTHORITATIVE / OFF |
| OPERATIONS.md | Procedimientos técnicos | Developers/ops | AUTHORITATIVE |
| DEPLOYMENT.md | Política/enlace a runbook | Developers/ops | CURRENT / navegación |
| DOMAIN.md, PRODUCT.md, UX-MVP.md | Entidades/resumen/navegación | Producto/dev | CURRENT / referencia delimitada |
| UNLOCKS.md, cities-v1.md | Mecánicas específicas | Producto/dev/ops | CURRENT / referencia específica |
| ATTRIBUTION.md, CONVERSION.md, CAMPAIGNS.md | Economía y referidos | Growth/comercial/dev | CURRENT / referencia específica |
| MANUAL-SALES.md, AFFILIATES.md, CREATORS.md, MEMBER-REFERRALS.md, PARTNER-ACQUISITION.md | Operación económica por actor | Comercial/ops/dev | CURRENT / referencia específica |
| MEDIA.md, PAYMENTS.md | Media / contrato QR deshabilitado | Developers/ops | CURRENT / referencia específica |
| ROADMAP.md | Estrategia futura | Producto/comercial | REFERENCE / no operativo |
| MVP.md, STATUS.md, INCIDENTS.md, LAUNCH-READINESS.md, history/CRM_FOUNDATION_IMPLEMENTATION.md | Contexto/snapshots anteriores | Producto/dev/ops | HISTORICAL / REFERENCE |
| DESIGN-SYSTEM.md, examples/README.md | Diseño/ejemplos | Diseño/dev | REFERENCE |
| ../CHANGELOG.md | Historia de cambios | Developers/ops | REFERENCE |

Los documentos breves remiten a su fuente canónica; no sustituyen los manuales. Histórico de tests/hotfix del bridge permanece en su repositorio y Git, separado del estado operativo actual.

## Reglas de autoridad y vigencia

- **CURRENT / AUTHORITATIVE** describe el sistema vigente dentro del alcance declarado; distinguir contrato estático de estado runtime y conservar fecha/fuente de evidencia. Una actualización documental no certifica una nueva observación de producción.
- **REFERENCE** aporta detalle de dominio/decisiones dentro de su alcance; no sustituye estado operativo ni el gate de lanzamiento.
- **HISTORICAL / REFERENCE** conserva snapshots anteriores y nunca debe tratarse como comportamiento/readiness actual.
- Cambios de producto/arquitectura deben actualizar en el mismo cambio las fuentes afectadas cuando corresponda; enlazar autoridad antes de duplicarla.

| Fuente añadida | Alcance | Estado |
| --- | --- | --- |
| [GLOSSARY](GLOSSARY.md) | Terminología canónica para todos los equipos | CURRENT / AUTHORITATIVE |
| [PILOT_READINESS](operations/PILOT_READINESS.md) | Único gate comercial GO / NO-GO Cochabamba | CURRENT / AUTHORITATIVE |
| [RUNBOOKS](operations/RUNBOOKS.md) | Recuperación por síntoma; procedimientos técnicos siguen en OPERATIONS | CURRENT / AUTHORITATIVE |
| [ADRs](decisions/README.md) | Decisiones aceptadas y sus límites | REFERENCE |
| [Plantillas](operations/templates/README.md) | Registros individuales de inventario/Partner | WORKING TEMPLATES |

[Meta Provider V1](../app/docs/meta-provider-v1.md) permanece en app/docs: es la ubicación existente de su contrato de integración, con referencias activas; mantenerla evita mover autoridad sin necesidad. CURRENT conserva los flags efectivos. El README del bridge es autoridad del contrato estático del plugin; estados de sync, cron y automations pertenecen a CURRENT/CRM/OPERATIONS.

## QA documental local

QA automatizado: [.github/workflows/docs.yml](../.github/workflows/docs.yml) ejecuta sólo enlaces y whitespace para cambios documentales en push/PR. Reutiliza el pin de checkout del workflow heredado app/.github; no activa sus checks de producto. Ejecutar `python3 bin/check-docs.py` desde cualquier directorio y `git diff --check` en cada repo. El checker usa sólo Python estándar y comprueba destinos relativos de Markdown en README raíz, docs/ y app/docs/; admite archivos explícitos de otro repo. No hace red, builds, PHP ni pruebas de producto. Comprueba archivos/directorios, no anchors, enlaces externos ni render Mermaid. Revisar manualmente el diff para secretos/PII; no se añade un escáner genérico con falsos positivos.
