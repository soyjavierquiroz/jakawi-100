# Campaigns V1

Una Campaign convierte `utm_campaign` en contexto comercial administrable. El UTM sigue siendo evidencia cruda: si no existe una campaña activa con ese código, el touch y la analítica se conservan sin aplicar reglas de campaña.

Una campaña es operativa únicamente con estado `active` y dentro de `start_at`/`end_at`. Su ventana se evalúa contra `Conversion.occurred_at`; la ventana de atribución de referrals continúa siendo independiente.

El alcance V1 es `membership_purchased` y el producto canónico `jakawi_annual`. La elegibilidad se normaliza por participante: PROMOTER, AFFILIATE, CREATOR, MEMBER y PARTNER.

## Recompensas

Se crea una sola recompensa ganadora. Para una conversión asociada a campaña, una regla de campaña específica del beneficiario gana sobre una regla de campaña del participante. Si no hay regla aplicable de campaña, se usa la resolución existente no-campaña: individual, programa y global. No hay stacking.

La conversión conserva `campaign_id` y `campaign_code` en su snapshot de atribución. Desactivar o renombrar una Campaign no reescribe conversiones ni recompensas históricas. Crear una Campaign no reprocesa conversiones anteriores.

## Tracking y métricas

Los enlaces usan `/r/{code}?utm_campaign={campaign-code}&utm_content={content}`. `utm_content` queda libre y sanitizado por los portales. Métricas de Campaign se calculan desde AttributionTouch, Conversion confirmada y RewardTransaction no cancelada; conversiones reembolsadas no cuentan para revenue activo.

`campaign_key` de LandingPresentation es contexto de adquisición controlado, no activa Campaign económica ni acepta override arbitrario de URL. Taxonomía canónica: [Growth](growth-measurement-v1.md).
