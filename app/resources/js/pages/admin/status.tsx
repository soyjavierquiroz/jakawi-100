/** Presentation only: preserve the server's state and displayed label. */
export function AdminStatus({ status, children }: { status: string; children?: React.ReactNode }) {
  const state = status.toLowerCase();
  const tone = [
    "active",
    "approved",
    "confirmed",
    "completed",
    "paid",
    "published",
    "processed",
    "reconciled",
    "auto_reconciled",
    "fulfilled",
    "granted",
    "delivered",
  ].includes(state)
    ? "success"
    : ["failed", "error"].includes(state)
      ? "danger"
      : [
            "pending",
            "requested",
            "submitted",
            "draft",
            "under_review",
            "awaiting_payment",
            "payment_reported",
            "review_required",
            "rejected",
            "changes_requested",
          ].includes(state)
        ? "warning"
        : "neutral";
  return <span className={`admin-status admin-status-${tone}`}>{children ?? status}</span>;
}
