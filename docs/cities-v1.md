# Cities V1

Cities separate the national product from local discovery. `selected_city` is a
server-resolved cookie containing only an ACTIVE city slug. Unknown, deleted,
inactive, or stale values fall back to an ACTIVE city; clients never submit an
arbitrary city ID. An inactive city landing never selects that city.

## States

ACTIVE enables normal discovery. UNLOCKING, COMING_SOON, and PREPARING show the
city landing, accept one CityInterest per visitor/user per city, and allow
PartnerApplication submissions. PAUSED shows temporary unavailability and
accepts neither interest nor partner applications. Activation is always manual.

## Local geography and operations

`locations.city_id` is canonical geography. A partner is local only through a
published Location in the selected city. Benefits apply there either through a
selected published benefit-location pivot or, for all-location benefits, a
published partner Location there. Experience geography comes only from the
relevant session Location; `venue_label` never infers geography. Unlocks need
an explicit unlock-location in the selected city.

CityInterest and PartnerApplication preserve the first available attribution
touch and immutable snapshot; retries are idempotent and do not rewrite them.
Applications derive city from the server route, create no Partner or Location,
and are operationally reviewed. Expansion metrics use City IDs and Location
city IDs, distinct published partners, real interests, and application counts;
an APPROVED application is not a live partner.

Cities V1 has no direct effects on Membership, MembershipPurchase, JP,
RewardTransaction, Reputation, UnlockParticipation, or affiliate, creator, or
promoter payouts.

Deferred: home_city_id, geolocation, city memberships/wallets/JP/pricing,
automatic expansion scores or activation, application-to-partner creation,
interest rewards, pre-sales, multi-country mechanics, advanced applicant CRM
or contact merge, and national search.
