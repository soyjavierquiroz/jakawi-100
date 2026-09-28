# Dominio

**STATUS: IMPLEMENTED.** Entidades centrales: `User`, `UserProfile`, `Partner`, `Location`, `Benefit`, `Membership`, `Redemption`, `Experience`, `ExperienceSession`, `ExperienceReservation`, `AttributionTouch`, `ReferralRelationship`, `Conversion`, `ProgramEnrollment`, `MembershipPurchase`, `RewardRule`, `RewardTransaction`, `RewardPayout`, `JpHold`, `Unlock`, `UnlockParticipation`, `ReputationEvent` y `AuditLog`.

`Partner` no es User: posee Locations y Benefits y participa en Experiences. `partner_user` asigna `owner`, `manager` o `staff`. `Membership.source` distingue `purchase` y `admin_grant`. `Redemption` confirma ahorro. `RewardTransaction` es ledger CASH/JP; `RewardPayout` agrupa CASH para pago externo. JP y BOB no se mezclan.

Estados y reglas: [CURRENT.md](CURRENT.md). Arquitectura: [ARCHITECTURE.md](ARCHITECTURE.md).
