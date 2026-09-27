# Partner Acquisition V1

Partners are acquisition sources, never program-enrolled users. An eligible Partner has one globally checked referral code and canonical `/r/{CODE}` link. The link records an anonymous attribution touch, approved UTMs, and is associated to the registering user.

The first valid touch creates one active relationship. It records either a User referrer or `acquisition_partner_id`; it never substitutes a Partner owner or manager. Partner dashboard metrics are aggregate only: valid referral opens, attributed registrations, confirmed `membership_purchased` conversions, and their gross revenue. Refunded conversions remain in history but are excluded from current totals.

Partner rewards use the generic `RewardRule`/`RewardTransaction` subject `PARTNER:<id>`. Partner CASH payout V1 reuses `RewardPayout`: the financial beneficiary remains `PARTNER:<id>` and an owner or manager is only the separately recorded request actor. Eligible BOB CASH is paid manually by Admin with an external reference; rejection releases it, while PAID history is corrected only through operational adjustments.
# Partner Acquisition and Rewards

Partner attribution (`acquisition_partner_id`) is marketing attribution, not a guaranteed reward. A confirmed membership may earn one CASH reward only after acquisition precedence: promoter, creator, affiliate, member, then eligible published Partner.

Specific Partner rules override PARTNER defaults, which override global rules. CREDIT remains deferred pending an explicit credit ledger and consumption model. `partner_minimum_payout` is an independently configurable BOB threshold; it is not the affiliate threshold or membership price.
