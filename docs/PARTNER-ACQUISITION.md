# Partner Acquisition V1

Partners are acquisition sources, never program-enrolled users. An eligible Partner has one globally checked referral code and canonical `/r/{CODE}` link. The link records an anonymous attribution touch, approved UTMs, and is associated to the registering user.

The first valid touch creates one active relationship. It records either a User referrer or `acquisition_partner_id`; it never substitutes a Partner owner or manager. Partner dashboard metrics are aggregate only: valid referral opens, attributed registrations, confirmed `membership_purchased` conversions, and their gross revenue. Refunded conversions remain in history but are excluded from current totals.

V1 creates no Partner RewardTransaction, payout, CREDIT, JP, or Partner commission. A manual Promoter collector remains the paid beneficiary; Partner marketing attribution remains attached to the conversion. Future Partner rewards can be expressed through generic RewardRule work.
# Partner Acquisition and Rewards

Partner attribution (`acquisition_partner_id`) is marketing attribution, not a guaranteed reward. A confirmed membership may earn one CASH reward only after acquisition precedence: promoter, creator, affiliate, member, then eligible published Partner.

Partner rewards use the generic RewardRule/RewardTransaction beneficiary subject `PARTNER:<id>`. Specific Partner rules override PARTNER defaults, which override global rules. CREDIT is deferred pending an explicit credit ledger and consumption model. Partner payouts are deferred; V1 records CASH rewards through PENDING and AVAILABLE without a Partner payout workflow.
