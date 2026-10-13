# PRE-LAUNCH DATA RESET V1

Completado el 2026-10-13 (America/La_Paz). DATA RESET ONLY: estructura y configuración preservadas; un administrador, catálogo e historial vacíos; inventario real pendiente. Runtime productivo sin rebuild/redeploy. Marketing engine y Publisher PRESERVED. Welcome Courtesy FUTURE / OFF. Coming Soon, banner y noindex no implementados en esta tarea.

## Respaldo

- Archivo: `/home/jakawi.com/backups/jakawi-pre-prelaunch-reset-20261013T220357Z.dump`.
- Tamaño: 466845 bytes.
- Custom `pg_dump` dentro de Docker PostgreSQL 16, base `jakawi`, rol `jakawi`, `--no-owner --no-acl`.
- `pg_restore --list`: PASS antes de borrar.
- SHA256: `c5691c6142cc8cb511d9e769c04a00d77d3e92a716570d88cf68db620312ab33`.

## Identidad y estructura

Único administrador identificado por DB y middleware `EnsureAdmin`: user id **1**, correo `javierquiroztv@gmail.com`, rol efectivo **ADMIN (`users.is_admin=true`)**. Total users: **1**. Este schema no contiene tablas separadas de roles, permisos ni sus pivotes; la autorización administrativa está en ese flag. Credenciales y datos 2FA comprobados sin diferencias dentro de PostgreSQL, sin exponer hashes. Passkeys del administrador preservadas si existían (conteo actual 0); credenciales de otros usuarios eliminadas. Referidos, opt-in de marketing y remember token del administrador limpiados; ningún estado de cliente/demo preservado.

`migrations` **62**, `app_settings` **2** y `cities` **5** preservados fila por fila. Los ajustes contienen los mínimos de payout affiliate/partner; no son programas DEMO. Schema-only comparado antes/después: idéntico (excluidos tokens aleatorios de protección de pg_dump). `migrate:status` del contenedor actual: todas las migraciones Ran, ninguna pendiente. Fuente de configuración, definiciones de plantilla, scheduler, volúmenes, backups previos y arquitectura intactos.

## Clasificación y ejecución

Inventario de las 56 tablas públicas contrastado con modelos/migraciones y 125 claves foráneas; ningún trigger de usuario. Se conservaron migrations, app_settings y cities; users/passkeys recibieron borrado selectivo. Las tablas siguientes se vaciaron explícitamente en una única transacción con locks, `TRUNCATE ... RESTART IDENTITY RESTRICT`, sin CASCADE. Todas las relaciones entrantes a las tablas vaciadas pertenecían al mismo conjunto. Las comprobaciones de conteos, administrador/credenciales y tablas preservadas se ejecutaron antes de COMMIT. Users no reinició su secuencia.

### Business / catalog

| Tabla | Después |
| --- | ---: |
| `benefit_location` | 0 |
| `benefits` | 0 |
| `campaign_participants` | 0 |
| `campaigns` | 0 |
| `challenges` | 0 |
| `experience_partner` | 0 |
| `experience_sessions` | 0 |
| `experiences` | 0 |
| `landing_presentations` | 0 |
| `locations` | 0 |
| `partners` | 0 |
| `reward_rules` | 0 |
| `unlock_location` | 0 |
| `unlocks` | 0 |

### Transaction / history / user-dependent

| Tabla | Después |
| --- | ---: |
| `analytics_events` | 0 |
| `attribution_touches` | 0 |
| `audit_logs` | 0 |
| `challenge_participations` | 0 |
| `challenge_reward_grants` | 0 |
| `challenge_social_entries` | 0 |
| `city_interests` | 0 |
| `conversions` | 0 |
| `crm_contact_links` | 0 |
| `crm_deliveries` | 0 |
| `experience_reservations` | 0 |
| `growth_provider_deliveries` | 0 |
| `jp_holds` | 0 |
| `membership_purchase_requests` | 0 |
| `membership_purchases` | 0 |
| `memberships` | 0 |
| `operational_adjustments` | 0 |
| `ownership_assignments` | 0 |
| `partner_applications` | 0 |
| `partner_user` | 0 |
| `program_applications` | 0 |
| `program_enrollments` | 0 |
| `redemptions` | 0 |
| `referral_relationships` | 0 |
| `reward_payout_reward_transaction` | 0 |
| `reward_payouts` | 0 |
| `reward_transactions` | 0 |
| `unlock_participations` | 0 |
| `unlock_status_history` | 0 |
| `user_profiles` | 0 |

### Auth / runtime

| Tabla | Después |
| --- | ---: |
| `cache` | 0 |
| `cache_locks` | 0 |
| `failed_jobs` | 0 |
| `job_batches` | 0 |
| `jobs` | 0 |
| `password_reset_tokens` | 0 |
| `sessions` | 0 |

No tabla `personal_access_tokens` ni tablas separadas de pagos/promoter/affiliate/creator en este schema: sus equivalentes son ventas, conversiones, ledger, payouts y programas listados arriba. Continuidad/verificación temporal almacenada en sesiones eliminada. Cache y locks de runtime vacíos; sus tablas permanecen. Tras QA se revocaron todas las sesiones temporales; sessions 0, password_reset_tokens 0, passkeys 0, failed_jobs/jobs/job_batches 0. Conteos corresponden a la verificación; tráfico posterior puede generar nuevas sesiones o eventos de runtime.

## Salud de la aplicación actual

| Check | Resultado |
| --- | --- |
| `/up` local / público | 200 / 200 |
| `/login` público | 200 |
| Autenticación/rol admin vía `web` guard | PASS, sesión temporal revocada |
| `/admin` | 200 |
| `/admin/benefits` | 200, items 0 |
| `/admin/experiences` | 200, items 0 |
| `/admin/landings` | 200, presentations 0 |
| `/admin/retos` | 200, challenges 0 |

Smoke HTTP autenticado contra la aplicación en ejecución, respuestas Inertia y colecciones vacías comprobadas. No se conocía ni introdujo la contraseña del administrador: formulario de login, guard y acceso autorizado verificados; login interactivo con contraseña no ejecutado. Credenciales preservadas por comparación dentro de la transacción. No se creó inventario para QA.

## DEMO URLs retiradas

Las tres `/l/qa-calibration-benefit-value`, `/l/qa-calibration-experience-desire`, `/l/qa-calibration-challenge-action` y `/beneficios/demo-beneficio-04` devuelven **404 esperado**. Catálogo DEMO restante: **0**. Estos enlaces ya no son gates de release; no recrear registros para satisfacer smoke antiguo. Checks 200 de DEMOs en releases anteriores son evidencia histórica, no requisitos actuales.

## Operación

Sin código de aplicación modificado, sin migraciones/seeders ejecutados, sin eliminación de schema, DB o volúmenes, sin rebuild ni deploy. Sólo documentación actualizada. Reset confirmado: cargar ejemplos reales será una tarea posterior.
