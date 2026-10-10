# Dominio

**STATUS: IMPLEMENTED.** Entidades centrales: `User`, `UserProfile`, `Partner`, `Location`, `Benefit`, `Membership`, `Redemption`, `Experience`, `ExperienceSession`, `ExperienceReservation`, `AttributionTouch`, `ReferralRelationship`, `Conversion`, `ProgramEnrollment`, `MembershipPurchase`, `RewardRule`, `RewardTransaction`, `RewardPayout`, `JpHold`, `Unlock`, `UnlockParticipation`, `ReputationEvent` y `AuditLog`, `Challenge`, `ChallengeParticipation`, `ChallengeRewardGrant`, `City`, `CityInterest`, `PartnerApplication`, `LandingPresentation`, `CrmDelivery`, `CrmContactLink` y `GrowthProviderDelivery`.

`Partner` no es User: posee Locations y Benefits y participa en Experiences. `partner_user` asigna `owner`, `manager` o `staff`. `Membership.source` distingue `purchase` y `admin_grant`. `Redemption` confirma ahorro. `RewardTransaction` es ledger CASH/JP; `RewardPayout` agrupa CASH para pago externo. JP y BOB no se mezclan.

Estados y reglas: [CURRENT.md](CURRENT.md). Arquitectura: [ARCHITECTURE.md](ARCHITECTURE.md).

Los límites entre Product Detail, marketing, economía y proyección están en [ARCHITECTURE.md](ARCHITECTURE.md); operación comercial en el [Manual de Inventario](operations/MANUAL_DE_INVENTARIO.md).
