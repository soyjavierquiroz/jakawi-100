export const inputClass =
  "min-h-11 w-full min-w-0 rounded-lg border border-border bg-background px-3 py-2 text-base focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand";
export const actionClass =
  "inline-flex min-h-11 items-center justify-center rounded-lg border border-border px-3 text-sm font-semibold hover:bg-muted focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand disabled:opacity-50";
export const primaryClass = `${actionClass} border-transparent bg-brand text-brand-foreground hover:bg-brand/90`;
export const statusLabels: Record<string, string> = {
  draft: "Borrador",
  published: "Publicado",
  paused: "Pausado",
  archived: "Archivado",
};
export function StatusBadge({ status }: { status: string }) {
  return (
    <span
      className={`inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold ${status === "published" ? "border-[#24b47e]/40 bg-[#24b47e]/10" : status === "draft" ? "border-[#ffc83d]/60 bg-[#ffc83d]/20" : "border-border bg-muted text-muted-foreground"}`}
    >
      {statusLabels[status] ?? status}
    </span>
  );
}
