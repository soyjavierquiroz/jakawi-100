# Desbloqueos V1

**STATUS: IMPLEMENTED.** Un Unlock agrega compromisos colectivos. Interest no cuenta; Commitment puede reservar JP mediante `JpHold`. Al llegar la meta se solicita confirmación; fulfillment devuelve la garantía y puede añadir bonus. Cancelación válida libera; confirmación expirada libera y es neutral; no-show forfeits.

Estados operativos de participación incluyen `interested`, `committed`, `unlocked_pending_confirmation`, `confirmed`, `fulfilled`, `cancelled_on_time`, `expired`, `no_show`, `removed` y `waitlisted`. Admin/Partner tienen operaciones delimitadas y auditadas. Compartir atribuye; no genera JP.

Reputación y límites de configuración: [CURRENT.md](CURRENT.md). **Desbloqueos Comerciales es diseño estratégico no implementado.**
