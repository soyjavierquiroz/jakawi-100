# Unlocks V1

An Unlock is collective demand, not a Benefit, Experience, or Campaign. It may link to a Benefit or Experience as a future result without duplicating either domain.

Interest is weak intent and never affects progress. A commitment is the only valid progress unit. `minimum_commitments` reaches the irreversible GOAL_REACHED milestone; `maximum_capacity` is a separate hard limit.

Slice 2 treats JP as a non-monetary commitment guarantee. `RewardTransaction` remains the sole source of earned JP; `jp_holds` is an auditable reservation ledger linked one-to-one to an Unlock participation. Spendable JP is available earned JP minus active `HELD` rows. A hold is released exactly once on timely participant cancellation, `GOAL_NOT_REACHED`, or Unlock cancellation. It remains held at `GOAL_REACHED`; no bonus or forfeiture is operational in this slice.

Positive-deposit Unlocks cannot be activated while the canonical `UNLOCK_JP_COMMITMENTS_ENABLED` capability is false (production remains false until Slice 3 delivers confirmation/fulfillment). Zero-deposit Unlocks are unaffected. JP is never money and has no BOB conversion.

Secret fields are removed on the server from public data before GOAL_REACHED: configured partner, locations, and offers never appear in Inertia props. Status history is append-only. The scheduler runs `unlocks:expire` to mark missed deadlines as GOAL_NOT_REACHED.

Future slices: 2 JP Commitment, 3 Confirmation/Fulfillment, 4 Growth/Operations.
