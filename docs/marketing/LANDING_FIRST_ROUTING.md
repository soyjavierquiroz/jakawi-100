# Landing-first public product routing

For Benefit, Experience and Challenge, a PUBLISHED applicable LandingPresentation is the primary public product presentation. Generic Product Detail is the fallback when no published applicable Landing exists. Draft, archived and audience-mismatched presentations are never selected.

`ProductLandingResolver::navigationUrl()` is the canonical card target. It reuses `forViewer()`: guests prefer GUESTS over ALL; authenticated viewers use ALL. NONE remains an explicitly unselected presentation. Partner inventory and Explore use this resolver; neither stores a presentation URL on Partner nor constructs a campaign URL in React.

Direct generic product GET routes retain `ResolveProductLanding`, which forwards to the applicable Landing with incoming query parameters. Without one, the existing detail controller renders normally. Unlock keeps its existing presentation contract.

A Landing CTA is a domain action, never a presentation route. Guests open the existing Micro Opt-in/login flow and retain Partner acquisition context through the session. Authenticated viewers use `landingDirectAction` (Benefit: POST `redemptions.start`) or the Landing activation stage for required choices. Activation renders the existing conversion controls and does not run presentation selection. Existing users do not acquire new Partner ownership.

PRELAUNCH_MODE, PrelaunchNotice, direct-page noindex,nofollow and the Coming Soon root's index,follow remain unchanged. This routing change requires no migration or product-data change.
