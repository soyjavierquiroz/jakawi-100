# Mapa UX/UI MVP v1

## Objetivo

JAKAWI existe para que más miembros reciban valor real al vivir Cochabamba: descubrir, desear, acceder, usar, recibir valor y volver. La promesa es **Vive más. Gasta menos.** La North Star es **miembros que reciben valor real**, no descargas, registros ni tiempo en app.

## Arquitectura y navegación

El MVP consumidor tiene cuatro destinos principales: Inicio, Explorar, Mi JAKAWI y Perfil. Cerca es una faceta de Explorar. Perfil contiene identidad y ajustes; Mi JAKAWI contiene membresía y valor. Inicio es editorial y fotográfico; Explorar mezcla Lugar, Beneficio y Experiencia, no tres catálogos aislados.

## Las 25 pantallas o estados

1. Inicio
2. Inicio sin contenido
3. Búsqueda desde Inicio
4. Categoría de Inicio
5. Explorar
6. Explorar con filtros
7. Explorar sin resultados
8. Resultado Lugar
9. Resultado Beneficio
10. Resultado Experiencia
11. Partner
12. Partner con una ubicación
13. Partner con ubicación contextual
14. Beneficio disponible
15. Beneficio no disponible o limitado
16. Beneficio para no miembro
17. Confirmación antes del canje
18. Código temporal de canje
19. Código QR vencido
20. Canje confirmado
21. Mi JAKAWI de cuenta gratuita
22. Mi JAKAWI de miembro activo
23. Mi JAKAWI con payback alcanzado
24. Actividad de canjes real
25. Error de red o carga contextual

La carga prioriza skeletons y los estados nunca dejan una pantalla vacía ni un dead end.

## System States MVP V1

Explorar comunica búsquedas sin resultados y permite limpiar filtros reales. Benefit conserva su contexto cuando está fuera de disponibilidad, sin ofrecer canje para un límite alcanzado, una membresía inactiva o una ubicación no usable. Un canje toma `expires_at` como fuente de verdad: al expirar deja de mostrar QR/código válido; al volver confirmado mantiene el éxito idempotente.

Experience comunica ausencia de fechas o destino de reserva sin CTA muerto. Mi JAKAWI diferencia cuenta gratuita, membresía activa y vencida preservando valor histórico. Promotor y ventas manuales incluyen vacío inicial, validación contextual, doble envío idempotente, recompensa pendiente/no aplicable y reembolso inequívoco. Media rota o ausente conserva la geometría con fallback JAKAWI neutral.

## Flujos canónicos

**A. Nuevo usuario → miembro.** Descubre en Inicio o Explorar, entiende el Beneficio y llega a una conversión contextual que conserva ese Benefit; checkout/pagos pertenecen al siguiente bloque de implementación. El contrato y la secuencia están definidos en [CONVERSION.md](CONVERSION.md).

**B. Canje.** Miembro activo abre Beneficio → confirma que está en el establecimiento → se crea o reutiliza el canje pendiente → muestra QR y código manual durante diez minutos → el establecimiento valida → recibe confirmación y ahorro real.

**C. Experiencia.** Inicio o Explorar → detalle cinematográfico de Experience → reserva mediante el flujo existente. Este pass sólo conserva su consistencia visual.

**D. Usuario gratuito.** Puede descubrir Partners, Benefits y Experiences; al querer usar un Benefit va a Mi JAKAWI en estado de cuenta gratuita, sin dead end.

## Lo que no existe en este MVP

No hay splash, onboarding, checkout/pagos implementados, Guardados, mapa avanzado, referidos, puntos, IA, gamificación, motor nuevo de recomendaciones ni nuevas reservas. La próxima fase de checkout se rige por [CONVERSION.md](CONVERSION.md). **JAKAWI Desbloqueos** es un experimento externo; no es una navegación ni feature del MVP.

## Métricas y analytics

Métricas MVP: miembros activos, canjes confirmados, ahorro confirmado por miembro, miembros que alcanzan payback y repetición posterior al valor recibido. La canonicalización de analytics está pendiente: no se introducen eventos duplicados durante este pass.

| Evento actual | Semántica MVP propuesta |
| --- | --- |
| `home_view` | Inicio visto |
| `partner_view` | Partner visto |
| `benefit_view` | Beneficio visto |
| `redeem_started` | Canje activado |
| `redeem_confirmed` | Valor recibido / canje confirmado |
| `experience_reserve_click` | Intención de reserva de experiencia |

## Orden de diseño y regla final

El orden es Inicio → Explorar → Partner → Beneficio → confirmación → código → éxito → Mi JAKAWI. Primero se cierra el Core; luego se aborda conversión/checkout y el resto del roadmap. La regla final: no desarrollar una feature si no mejora de forma directa el ciclo descubrir → valor real → volver.
