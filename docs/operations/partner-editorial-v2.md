# Partner editorial pilot V2

The release starts from verified COM runtime `baf8cbc093d3afc487a7987b6317c25ee475a49f` in an isolated worktree. It changes Partner presentation and scoped read queries only; the existing authorization, editorial mutation endpoints, publication rules and PRELAUNCH guard remain in force. No migration is needed.

The dashboard separates establishment identity/status, PRELAUNCH policy, profile/location shortcuts, editorial counts, priority actions and existing 30-day event metrics. Counts use the current Partner relation: published is `status=published` regardless of validity, while draft/review/changes/approved/rejected groups require `status=draft` plus their review status. Paused and archived content remains in the complete listing. Counts do not imply commercial enablement.

Benefits and Experiences share explicit editorial filters, review notes and read-only details for submitted/approved/published proposals. The form retains its existing payloads, uploads and persistence endpoints, groups fields into steps and prevents submission of an unsaved version. It does not introduce revisions for published content. Sessions remain editorial preparation with the existing validation and storage conventions.

The shell uses backend capabilities: staff can read permitted lists/history but receives no links to the owner/manager-only business editor, performance, Challenges or Unlocks. Paused/archived businesses do not receive edit shortcuts. The multi-Partner selection route is preserved. PRELAUNCH replaces operational navigation with an explanation; all server-side commercial protection remains unchanged.

The shared Admin business form adds Partner-only guidance and terminology behind `portalPartner`; Admin controls and behavior remain the same. Guidance highlights missing existing fields without creating publication requirements. Location state, publication, pause/archive and PIN remain Admin controls. Existing theme.css tokens supply all visual colors.

Validation: focused Partner/PRELAUNCH tests through bin/jakawi-test, TypeScript, manifest-checked build and isolated browser checks for owner/manager/staff at 390/1440px, multi-Partner roles, protected published proposals, editorial saves/submission and foreign Partner denial. QA fixtures and sessions exist only in the isolated test environment.

Release: only `/opt/sites/jakawi.com/release-control.py` with explicit release context, retained previous images/configuration/database backup and rollback dry-run. Promote main by fast-forward after LIVE PASS. No production commercial transaction or test login is required. Evidence is outside the source worktree at `/srv/workspaces/jakawi/com/partner-editorial-v2-evidence`.

Reservation, attendance, redemption and Unlock operational improvements are excluded from V2.
