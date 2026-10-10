# Desbloqueos V1

**STATUS: IMPLEMENTED.** Un Unlock agrega compromisos colectivos. Interest no cuenta; Commitment puede reservar JP mediante `JpHold`. Al llegar la meta se solicita confirmación; fulfillment devuelve la garantía y puede añadir bonus. Cancelación válida libera; confirmación expirada libera y es neutral; no-show forfeits.

Estados operativos de participación incluyen `INTERESTED`, `COMMITTED`, `UNLOCKED_PENDING_CONFIRMATION`, `CONFIRMED`, `FULFILLED`, `CANCELLED_ON_TIME`, `EXPIRED`, `NO_SHOW`, `REMOVED` y `WAITLISTED`. Admin/Partner tienen operaciones delimitadas y auditadas. Compartir atribuye; no genera JP.

Reputación y límites de configuración: [CURRENT.md](CURRENT.md). **Desbloqueos Comerciales es diseño estratégico no implementado.**

Ruta nativa: `/d/{slug}`. Lifecycle del objeto y preparación comercial: [Manual de Inventario](operations/MANUAL_DE_INVENTARIO.md).
