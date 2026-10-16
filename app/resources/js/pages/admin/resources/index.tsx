import { ListPagination, type ListPage } from '@/components/list-pagination';
import { Link, router, usePage } from "@inertiajs/react";
import { useState } from "react";
import AdminLayout from "../layout";
import {
  actionClass,
  inputClass,
  primaryClass,
  StatusBadge,
  statusLabels,
} from "./partner-location-ui";

type Location = {
  id: number;
  name: string;
  status: string;
  city: string | null;
  address: string | null;
  is_primary: boolean;
  partner_id: number | null;
  maps_url: string | null;
  partner?: { name: string };
  city_entity?: { name: string };
};
type Partner = {
  id: number;
  name: string;
  slug: string;
  status: string;
  logoUrl: string | null;
  cover_path: string | null;
  gallery_count: number;
  referral_code: string | null;
  publicUrl: string | null;
  locations: Location[];
};
type Props = {
  title: string;
  resource: "partners" | "locations";
  items: ListPage & { data: (Partner & Location)[] };
  filters?: Record<string, string>;
  cities?: string[];
  partners?: { id: number; name: string }[];
};
export default function ResourceIndex({ title, resource, items: page, partners = [], filters = {}, cities = [] }: Props) {
  const items = page.data;
  const { url } = usePage();
  const isPartner = resource === "partners";
  const [q, setQ] = useState(filters.q ?? "");
  const [status, setStatus] = useState(filters.status ?? "");
  const [owner, setOwner] = useState(
    () => new URLSearchParams(url.split("?")[1] ?? "").get("partner") ?? "",
  );
  const [city, setCity] = useState(filters.city ?? "");
  const { flash } = usePage<{ flash: { success?: string } }>().props;
  const cityName = (location: Location) => location.city_entity?.name || location.city || "";
  const visible = items;
  const submit = (next: Record<string, string> = {}) => router.get(`/admin/${resource}`, { q, status, partner: owner, city, sort: filters.sort ?? 'name', ...next });
  const columns = isPartner
    ? "xl:grid-cols-[minmax(0,1.7fr)_minmax(0,1.2fr)_minmax(0,.8fr)_minmax(0,1fr)_minmax(0,.9fr)_minmax(0,1.2fr)]"
    : "xl:grid-cols-[minmax(0,1.5fr)_minmax(0,2fr)_minmax(0,.8fr)_minmax(0,1fr)_minmax(0,.6fr)]";
  return (
    <AdminLayout title={title}>
      <div className="flex flex-wrap items-start justify-between gap-4">
        <div>
          <p className="text-sm text-muted-foreground">
            {isPartner
              ? "Gestiona los negocios, sus perfiles públicos y sus ubicaciones en JAKAWI."
              : "Administra las sedes y direcciones asociadas a los Partners."}
          </p>

        </div>
        <Link className={primaryClass} href={`/admin/${resource}/create`}>
          CREAR {isPartner ? "PARTNER" : "LOCATION"}
        </Link>
      </div>
      {flash?.success && (
        <p
          role="status"
          className="mt-4 rounded-lg border border-[#24b47e]/40 bg-[#24b47e]/10 p-3 text-sm"
        >
          {flash.success}
        </p>
      )}
      <form
        onSubmit={(event) => { event.preventDefault(); submit(); }}
        className="my-5 grid gap-3 rounded-xl border border-border bg-card p-4 md:grid-cols-2 xl:grid-cols-[minmax(0,2fr)_1fr_1fr_auto] xl:items-end"
      >
        <label className="grid min-w-0 gap-1 text-sm font-medium">
          {isPartner ? "Buscar Partner" : "Buscar sede, Partner o dirección"}
          <input
            type="search"
            className={inputClass}
            value={q}
            onChange={(event) => setQ(event.target.value)}
            placeholder={isPartner ? "Nombre o slug" : "Nombre, Partner o dirección"}
          />
        </label>
        <label className="grid min-w-0 gap-1 text-sm font-medium">
          Estado
          <select
            className={inputClass}
            value={status}
            onChange={(event) => { setStatus(event.target.value); submit({ status: event.target.value }); }}
          >
            <option value="">Todos los estados</option>
            {[...new Set(["draft", "published", "paused", "archived", ...items.map((item) => item.status)])].map(
              (value) => (
                <option key={value} value={value}>
                  {statusLabels[value] ?? value}
                </option>
              ),
            )}
          </select>
        </label>
        {isPartner ? (
          <label className="grid min-w-0 gap-1 text-sm font-medium">
            Ciudad
            <select
              className={inputClass}
              value={city}
              onChange={(event) => { setCity(event.target.value); submit({ city: event.target.value }); } }
            >
              <option value="">Todas las ciudades</option>
              {cities.map((value) => (
                <option key={value}>{value}</option>
              ))}
            </select>
          </label>
        ) : (
          <label className="grid min-w-0 gap-1 text-sm font-medium">
            Partner
            <select
              className={inputClass}
              value={owner}
              onChange={(event) => { setOwner(event.target.value); submit({ partner: event.target.value }); } }
            >
              <option value="">Todos los Partners</option>
              {partners.map((partner) => (
                <option key={partner.id} value={partner.id}>
                  {partner.name}
                </option>
              ))}
            </select>
          </label>
        )}
        <button
          type="button"
          className={actionClass}
          onClick={() => {
            setQ("");
            setStatus("");
            setOwner("");
            setCity("");
            router.get(`/admin/${resource}`);
          }}
        >
          Limpiar
        </button>
        <button className={actionClass}>Buscar</button>
        <label className="grid gap-1 text-sm">Ordenar<select className={inputClass} value={filters.sort ?? 'name'} onChange={e => submit({ sort: e.target.value })}><option value="name">Nombre A–Z</option><option value="newest">Más recientes</option></select></label>
      </form>
      <p role="status" className="mb-3 text-sm text-muted-foreground">
        {visible.length} de {page.total} {isPartner ? "Partners" : "Locations"}
      </p>
      <div className="space-y-3 xl:space-y-0 xl:overflow-hidden xl:rounded-xl xl:border xl:border-border">
        <div
          aria-hidden="true"
          className={`hidden gap-4 bg-muted/50 px-4 py-3 text-xs font-semibold tracking-wide text-muted-foreground xl:grid ${columns}`}
        >
          {(isPartner
            ? ["PARTNER", "UBICACIÓN", "ESTADO", "MEDIA", "ATRIBUCIÓN", "ACCIONES"]
            : ["SEDE", "DIRECCIÓN", "ESTADO", "MAPS", "ACCIONES"]
          ).map((label) => (
            <span key={label}>{label}</span>
          ))}
        </div>
        {visible.map((item) => (
          <article
            key={item.id}
            aria-label={item.name}
            className={`grid min-w-0 gap-4 rounded-xl border border-border bg-card p-4 md:grid-cols-2 xl:items-center xl:rounded-none xl:border-0 xl:border-t ${columns}`}
          >
            <div className="flex min-w-0 items-center gap-3">
              {isPartner && (
                <div className="flex size-12 shrink-0 items-center justify-center overflow-hidden rounded-full border border-border bg-[#fff8f2]">
                  {item.logoUrl ? (
                    <img
                      src={item.logoUrl}
                      alt=""
                      loading="lazy"
                      className="size-full object-contain p-1"
                    />
                  ) : (
                    <span className="font-semibold text-muted-foreground">
                      {item.name.slice(0, 1)}
                    </span>
                  )}
                </div>
              )}
              <div className="min-w-0">
                <h2 className="font-bold break-words">{item.name}</h2>
                <p className="mt-1 text-sm break-all text-muted-foreground">
                  {isPartner ? `/${item.slug}` : item.partner?.name || "Independiente"}
                </p>
              </div>
            </div>
            <div className="min-w-0 text-sm">
              <p className="mb-1 text-xs font-semibold text-muted-foreground xl:hidden">
                {isPartner ? "UBICACIÓN" : "DIRECCIÓN"}
              </p>
              {isPartner ? (
                <>
                  <p className="break-words">
                    {[...new Set(item.locations.map(cityName).filter(Boolean))].join(", ") ||
                      "Sin ciudad"}
                  </p>
                  <p className="mt-1 text-xs text-muted-foreground">
                    {item.locations.length > 1 ? `${item.locations.length} sedes · ` : ""}
                    {item.locations.some((location) => location.is_primary)
                      ? `Principal: ${statusLabels[item.locations.find((location) => location.is_primary)!.status] ?? item.locations.find((location) => location.is_primary)!.status}`
                      : "Sin sede principal"}
                    {!item.locations.some((location) => location.status === "published") &&
                      " · Sin sede pública"}
                  </p>
                </>
              ) : (
                <>
                  <p className="break-words">{item.address || "Sin dirección"}</p>
                  <p className="mt-1 text-muted-foreground">{cityName(item) || "Sin ciudad"}</p>
                </>
              )}
            </div>
            <div>
              <StatusBadge status={item.status} />
            </div>
            <div className="min-w-0 text-sm">
              {isPartner ? (
                <>
                  <p className="mb-1 text-xs font-semibold text-muted-foreground xl:hidden">
                    MEDIA
                  </p>
                  <p>Logo: {item.logoUrl ? "Sí" : "Pendiente"}</p>
                  <p>Portada: {item.cover_path ? "Sí" : "Pendiente"}</p>
                  <p className="text-muted-foreground">{item.gallery_count}/3 editoriales</p>
                </>
              ) : item.maps_url ? (
                <a
                  className={`${actionClass} max-w-full`}
                  href={item.maps_url}
                  target="_blank"
                  rel="noreferrer"
                >
                  Abrir Maps ↗
                </a>
              ) : (
                <span className="text-muted-foreground">Sin enlace Maps</span>
              )}
            </div>
            {isPartner && (
              <div className="min-w-0 text-xs text-muted-foreground">
                <p className="break-all">{item.referral_code || "Sin código"}</p>
                <button
                  className="mt-1 min-h-11 underline focus-visible:outline-brand"
                  onClick={() =>
                    router.post(
                      `/admin/partners/${item.id}/referral-code`,
                      { regenerate: Boolean(item.referral_code) },
                      { preserveScroll: true },
                    )
                  }
                >
                  {item.referral_code ? "Regenerar" : "Generar código"}
                </button>
              </div>
            )}
            <div className="flex flex-wrap gap-2 xl:flex-col xl:items-start">
              <Link className={actionClass} href={`/admin/${resource}/${item.id}/edit${url.includes("?") ? `?${url.split("?")[1]}` : ""}`}>
                Editar
              </Link>
              {isPartner && (
                <>
                  <Link className={actionClass} href={`/admin/locations?partner=${item.id}`}>
                    Gestionar sedes
                  </Link>
                  {item.publicUrl && (
                    <a
                      className={actionClass}
                      href={item.publicUrl}
                      target="_blank"
                      rel="noreferrer"
                    >
                      Página pública ↗
                    </a>
                  )}
                </>
              )}
            </div>
          </article>
        ))}
        {!visible.length && (
          <p className="rounded-xl border border-border bg-card p-6 text-sm text-muted-foreground">
            {items.length ? "No hay resultados con estos filtros." : "Aún no hay registros."}
          </p>
        )}
      </div>
      <ListPagination page={page} />
    </AdminLayout>
  );
}
