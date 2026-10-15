import { ImageUpload } from "@/components/media/image-upload";
import { Link, useForm } from "@inertiajs/react";
import { useState } from "react";
import AdminLayout from "../layout";
import {
  actionClass,
  inputClass,
  primaryClass,
  StatusBadge,
  statusLabels,
} from "./partner-location-ui";

type Props = {
  resource: "partners" | "locations";
  item?: any;
  partners?: { id: number; name: string }[];
  cities?: { id: number; name: string }[];
  locations?: any[];
  publicUrl?: string;
  initialPartnerId?: number | null;
};
const labels: Record<string, string> = {
  name: "Nombre",
  slug: "Slug",
  entity_type: "Tipo de entidad",
  partner_type: "Tipo de Partner",
  legal_name: "Nombre legal (opcional)",
  tax_id: "Identificación fiscal (opcional)",
  category: "Categoría (opcional)",
  description: "Descripción",
  phone: "Teléfono",
  whatsapp: "WhatsApp",
  email: "Email",
  contact_name: "Contacto comercial (interno)",
  contact_phone: "Teléfono comercial (interno)",
  contact_email: "Email comercial (interno)",
  instagram: "Instagram",
  tiktok: "TikTok",
  facebook: "Facebook",
  website: "Website",
  status: "Estado",
  featured: "Destacado",
  sort_order: "Orden de aparición (opcional)",
  published_at: "Fecha de publicación (opcional)",
  internal_notes: "Notas internas (solo administración)",
  partner_id: "Partner propietario",
  location_type: "Tipo de sede",
  is_primary: "Sede principal",
  country_code: "Código de país",
  region: "Departamento / región",
  city_id: "Ciudad",
  zone: "Zona",
  address: "Dirección",
  address_reference: "Referencia de dirección",
  latitude: "Latitud (opcional)",
  longitude: "Longitud (opcional)",
  maps_url: "Enlace a Google Maps / ubicación",
  google_place_id: "Google Place ID (opcional)",
  timezone: "Zona horaria",
  opening_hours: "Horarios (JSON, opcional)",
  manager_name: "Responsable (interno)",
  manager_phone: "Teléfono del responsable (interno)",
  manager_email: "Email del responsable (interno)",
  redemption_pin: "Nuevo PIN de canje (6 dígitos)",
};
const choices: Record<string, Record<string, string>> = {
  status: statusLabels,
  entity_type: { organization: "Organización", individual: "Persona" },
  partner_type: {
    business: "Negocio",
    professional: "Profesional",
    creator: "Creador",
    organizer: "Organizador",
    brand: "Marca",
    other: "Otro",
  },
  location_type: {
    branch: "Sucursal",
    venue: "Espacio",
    meeting_point: "Punto de encuentro",
    online: "Online",
    mobile: "Móvil",
    other: "Otro",
  },
  category: {
    food: "Comida",
    cafe: "Café",
    fitness: "Fitness",
    wellness: "Bienestar",
    beauty: "Belleza",
    entertainment: "Entretenimiento",
    nightlife: "Vida nocturna",
    shopping: "Compras",
    services: "Servicios",
    experiences: "Experiencias",
  },
};
const helps: Record<string, string> = {
  slug: "Identificador de la URL. Editar el nombre no cambia este valor.",
  instagram: "Opcional. Puedes mantener el usuario o enlace que ya utilizas.",
  website: "URL completa, incluyendo https://.",
  whatsapp: "Opcional. Usa el contacto confirmado por el negocio.",
  opening_hours: "Conserva la estructura JSON existente. Déjalo vacío si no está configurado.",
  published_at: "Dato editorial. El estado de publicación se controla por separado.",
  redemption_pin: "Déjalo vacío para conservar el PIN actual.",
};
function Section({
  id,
  title,
  description,
  children,
}: {
  id: string;
  title: string;
  description?: string;
  children: React.ReactNode;
}) {
  return (
    <section
      id={id}
      aria-labelledby={`${id}-title`}
      className="min-w-0 rounded-xl border border-border bg-card p-4 sm:p-6"
    >
      <h2 id={`${id}-title`} className="text-lg font-bold">
        {title}
      </h2>
      {description && <p className="mt-1 text-sm text-muted-foreground">{description}</p>}
      <div className="mt-5 grid min-w-0 gap-5 sm:grid-cols-2">{children}</div>
    </section>
  );
}
export default function PartnerLocationForm({
  resource,
  item,
  partners = [],
  cities = [],
  locations = [],
  publicUrl,
  initialPartnerId,
}: Props) {
  const isPartner = resource === "partners";
  const title = `${item ? "Editar" : "Crear"} ${isPartner ? "Partner" : "Location"}`;
  const [uploading, setUploading] = useState<Record<string, boolean>>({});
  const [copied, setCopied] = useState("");
  // Keep relationships and stored media outside the save payload. ImageUpload owns tokens/removals.
  const { gallery_paths: _gallery, ...editable } = item ?? {};
  const form = useForm<any>({
    status: "draft",
    entity_type: "organization",
    partner_type: "business",
    location_type: "branch",
    featured: false,
    is_primary: false,
    partner_id: initialPartnerId ?? null,
    ...editable,
    redemption_pin: "",
    published_at: item?.published_at ? item.published_at.slice(0, 16) : "",
    opening_hours: item?.opening_hours ? JSON.stringify(item.opening_hours, null, 2) : "",
  });
  const busy = form.processing || Object.values(uploading).some(Boolean);
  const submit = (event: React.FormEvent) => {
    event.preventDefault();
    if (busy) return;
    let hours: unknown = null;
    if (!isPartner && form.data.opening_hours.trim()) {
      try {
        hours = JSON.parse(form.data.opening_hours);
        if (hours === null || typeof hours !== "object") throw new Error();
      } catch {
        form.setError("opening_hours", "Introduce un objeto o una lista JSON válidos.");
        return;
      }
    }
    form.clearErrors();
    form.transform((data: any) => ({
      ...data,
      published_at:
        data.published_at === (item?.published_at?.slice(0, 16) ?? "")
          ? (item?.published_at ?? null)
          : data.published_at,
      ...(!isPartner ? { opening_hours: hours } : {}),
      ...(item ? { _method: "put" } : {}),
    }));
    form.post(`/admin/${resource}${item ? `/${item.id}` : ""}`, {
      onError: () => document.querySelector<HTMLElement>('[aria-invalid="true"]')?.focus(),
    });
  };
  const field = (name: string) => {
    const error = form.errors[name];
    const common = {
      id: name,
      name,
      "aria-invalid": Boolean(error),
      "aria-describedby":
        [helps[name] ? `${name}-help` : "", error ? `${name}-error` : ""]
          .filter(Boolean)
          .join(" ") || undefined,
      className: inputClass,
    };
    const value = form.data[name] ?? "";
    const change = (value: any) => form.setData(name, value);
    const options =
      name === "partner_id"
        ? Object.fromEntries(partners.map((partner) => [partner.id, partner.name]))
        : name === "city_id"
          ? Object.fromEntries(cities.map((city) => [city.id, city.name]))
          : choices[name];
    return (
      <div
        key={name}
        className={`grid min-w-0 content-start gap-2 ${["description", "internal_notes", "opening_hours", "address", "address_reference"].includes(name) ? "sm:col-span-2" : ""}`}
      >
        <label htmlFor={name} className="text-sm font-semibold">
          {labels[name]}
        </label>
        {["featured", "is_primary"].includes(name) ? (
          <input
            {...common}
            type="checkbox"
            className="size-5 accent-brand focus-visible:outline-brand"
            checked={Boolean(value)}
            onChange={(event) => change(event.target.checked)}
          />
        ) : options ? (
          <select
            {...common}
            value={value}
            onChange={(event) => change(event.target.value || null)}
          >
            {["category", "partner_id", "city_id"].includes(name) && (
              <option value="">
                {name === "partner_id"
                  ? "Independiente"
                  : name === "city_id"
                    ? "Sin ciudad (solo online)"
                    : "Sin categoría"}
              </option>
            )}
            {Object.entries(options).map(([key, label]) => (
              <option key={key} value={key}>
                {label}
              </option>
            ))}
          </select>
        ) : ["description", "internal_notes", "opening_hours"].includes(name) ? (
          <textarea
            {...common}
            rows={name === "opening_hours" ? 5 : 4}
            value={value}
            onChange={(event) => change(event.target.value)}
          />
        ) : (
          <input
            {...common}
            type={
              name.includes("email")
                ? "email"
                : name.includes("phone") || name === "whatsapp"
                  ? "tel"
                  : ["website", "maps_url"].includes(name)
                    ? "url"
                    : name === "published_at"
                      ? "datetime-local"
                      : ["sort_order", "latitude", "longitude"].includes(name)
                        ? "number"
                        : "text"
            }
            step={["latitude", "longitude"].includes(name) ? "any" : undefined}
            inputMode={name === "redemption_pin" ? "numeric" : undefined}
            value={value}
            onChange={(event) => change(event.target.value)}
          />
        )}
        {helps[name] && (
          <p id={`${name}-help`} className="text-sm text-muted-foreground">
            {helps[name]}
          </p>
        )}
        {error && (
          <p id={`${name}-error`} role="alert" className="text-sm text-red-700">
            {error}
          </p>
        )}
      </div>
    );
  };
  const media = (name: string, label: string) => (
    <ImageUpload
      key={name}
      name={name}
      label={label}
      profileAvatar={isPartner && name === "logo"}
      currentCropUrl={name === "logo" ? item?.logoCropUrl : null}
      currentUrl={
        name.startsWith("gallery_")
          ? item?.galleryUrls?.[Number(name.slice(-1)) - 1]
          : item?.[`${name}Url`]
      }
      error={form.errors[name] || form.errors[`${name}_upload`] || form.errors[`${name}_remove`]}
      disabled={form.processing}
      onBusy={(value) => setUploading((current) => ({ ...current, [name]: value }))}
      onChange={(token, remove) =>
        form.setData((data: any) => ({
          ...data,
          [`${name}_upload`]: token,
          [`${name}_remove`]: remove,
        }))
      }
    />
  );
  const save = (
    <button form="partner-location-editor" type="submit" disabled={busy} className={primaryClass}>
      {form.processing
        ? "Guardando…"
        : Object.values(uploading).some(Boolean)
          ? "Procesando imágenes…"
          : "Guardar cambios"}
    </button>
  );
  return (
    <AdminLayout title={title}>
      <div className="mx-auto max-w-5xl space-y-6">
        <Link href={`/admin/${resource}`} className={`${actionClass} border-transparent px-0`}>
          ← Volver a {isPartner ? "Partners" : "Locations"}
        </Link>
        <div className="flex flex-wrap items-start justify-between gap-4">
          <div className="min-w-0">
            <div className="flex flex-wrap items-center gap-3">
              {item && <p className="text-xl font-semibold break-words">{item.name}</p>}
              <StatusBadge status={form.data.status} />
            </div>
            {!isPartner && (
              <p className="mt-2 text-sm text-muted-foreground">
                Partner propietario:{" "}
                {partners.find((partner) => String(partner.id) === String(form.data.partner_id))
                  ?.name || "Independiente"}
              </p>
            )}
            {isPartner && publicUrl && (
              <div className="mt-3 flex min-w-0 flex-wrap items-center gap-2">
                <a
                  className="min-w-0 break-all text-sm underline"
                  href={publicUrl}
                  target="_blank"
                  rel="noreferrer"
                >
                  {publicUrl}
                </a>
                <button
                  type="button"
                  className={actionClass}
                  onClick={async () => {
                    try {
                      await navigator.clipboard.writeText(publicUrl);
                      setCopied("Enlace copiado.");
                    } catch {
                      setCopied("No se pudo copiar. Selecciona el enlace para copiarlo.");
                    }
                  }}
                >
                  Copiar enlace
                </button>
                <span role="status" className="text-sm">
                  {copied}
                </span>
              </div>
            )}
          </div>
          {save}
        </div>
        <form id="partner-location-editor" onSubmit={submit} className="space-y-6">
          <fieldset disabled={form.processing} className="min-w-0 space-y-6">
            {isPartner ? (
              <>
                <Section id="identidad" title="1. Identidad del negocio">
                  {[
                    "name",
                    "slug",
                    "entity_type",
                    "partner_type",
                    "legal_name",
                    "tax_id",
                    "category",
                    "description",
                  ].map(field)}
                </Section>
                <Section
                  id="contacto"
                  title="2. Contacto y presencia digital"
                  description="Los canales son opcionales. Los datos comerciales internos no se muestran en el perfil público."
                >
                  {[
                    "phone",
                    "whatsapp",
                    "email",
                    "contact_name",
                    "contact_phone",
                    "contact_email",
                    "instagram",
                    "tiktok",
                    "facebook",
                    "website",
                  ].map(field)}
                </Section>
                <Section
                  id="media"
                  title="3. Perfil público / media"
                  description="Guarda el formulario para aplicar los cambios de imágenes."
                >
                  <div className="min-w-0 space-y-3">
                    <p className="text-sm text-muted-foreground">
                      Logo / foto de perfil: fuente recomendada 1:1. Usa el editor para ajustar el
                      encuadre.
                    </p>
                    {media("logo", "Logo / foto de perfil")}
                  </div>
                  <div className="min-w-0 space-y-3 [&_img]:aspect-[4/5] [&_img]:max-h-80 [&_img]:object-contain">
                    <p className="text-sm text-muted-foreground">
                      Portada 4:5. Desktop conserva la composición; mobile utiliza recorte
                      responsive.
                    </p>
                    {media("cover", "Portada")}
                  </div>
                  <div className="min-w-0 space-y-4 sm:col-span-2">
                    <h3 className="font-semibold">Imágenes editoriales</h3>
                    <p className="text-sm text-muted-foreground">
                      Máximo 3 imágenes. Recomendado: 4:5 · 1080 × 1350 px.
                    </p>
                    <div className="grid min-w-0 gap-4 lg:grid-cols-3 [&_img]:aspect-[4/5] [&_img]:max-h-72">
                      {[1, 2, 3].map((slot) => media(`gallery_${slot}`, `Imagen ${slot}`))}
                    </div>
                    {form.errors.gallery_paths && (
                      <p role="alert" className="text-sm text-red-700">
                        {form.errors.gallery_paths}
                      </p>
                    )}
                  </div>
                </Section>
              </>
            ) : (
              <>
                <Section id="identidad" title="1. Identidad y Partner asociado">
                  {["name", "slug", "partner_id", "location_type"].map(field)}
                </Section>
                <Section id="direccion" title="2. Dirección y ciudad">
                  {[
                    "city_id",
                    "zone",
                    "country_code",
                    "region",
                    "address",
                    "address_reference",
                    "latitude",
                    "longitude",
                  ].map(field)}
                </Section>
                <Section id="contacto" title="3. Contacto y presencia digital">
                  {[
                    "phone",
                    "whatsapp",
                    "email",
                    "website",
                    "instagram",
                    "facebook",
                    "tiktok",
                    "manager_name",
                    "manager_phone",
                    "manager_email",
                  ].map(field)}
                </Section>
                <Section
                  id="maps"
                  title="4. Google Maps / enlace de ubicación"
                  description="Enlace opcional para abrir la ubicación en Maps."
                >
                  {["maps_url", "google_place_id"].map(field)}
                  <div className="min-w-0 sm:col-span-2">{media("image", "Imagen de la sede")}</div>
                </Section>
              </>
            )}
            <Section
              id="publicacion"
              title={
                isPartner ? "4. Publicación y control editorial" : "5. Publicación y configuración"
              }
              description={
                form.data.status === "draft"
                  ? "El perfil no está disponible públicamente como ficha publicada."
                  : form.data.status === "published"
                    ? isPartner
                      ? "El perfil público puede mostrarse, pero sus ofertas y sedes tienen estados independientes."
                      : "Esta sede está publicada. El Partner y sus ofertas tienen estados independientes."
                    : "Este registro conserva su estado editorial actual."
              }
            >
              {(isPartner
                ? ["status", "featured", "sort_order", "published_at", "internal_notes"]
                : [
                    "status",
                    "is_primary",
                    "sort_order",
                    "published_at",
                    "timezone",
                    "opening_hours",
                    "redemption_pin",
                  ]
              ).map(field)}
              {!isPartner && item && (
                <p className="text-sm text-muted-foreground sm:col-span-2">
                  PIN configurado: {item.has_redemption_pin ? "Sí" : "No"}
                </p>
              )}
            </Section>
          </fieldset>
          {isPartner && item && (
            <Section
              id="sedes"
              title="5. Sedes asociadas"
              description="Cada sede conserva su propio estado de publicación."
            >
              {item.status === "published" &&
                !locations.some((location) => location.status === "published") && (
                  <p className="text-sm text-muted-foreground sm:col-span-2">
                    La ubicación todavía no se muestra en el perfil público.
                  </p>
                )}
              <div className="divide-y divide-border sm:col-span-2">
                {locations.map((location) => (
                  <div
                    key={location.id}
                    className="flex flex-wrap items-center justify-between gap-3 py-4"
                  >
                    <div className="min-w-0">
                      <p className="mb-2 font-semibold break-words">{location.name}</p>
                      <StatusBadge status={location.status} />
                      <p className="mt-2 text-sm break-words text-muted-foreground">
                        {[location.address, location.city].filter(Boolean).join(" · ") ||
                          "Sin dirección configurada"}
                      </p>
                    </div>
                    <Link className={actionClass} href={`/admin/locations/${location.id}/edit`}>
                      Editar sede
                    </Link>
                  </div>
                ))}
                {!locations.length && (
                  <p className="py-3 text-sm text-muted-foreground">
                    Todavía no hay sedes asociadas.
                  </p>
                )}
              </div>
              <div className="sm:col-span-2">
                <Link className={actionClass} href={`/admin/locations/create?partner=${item.id}`}>
                  Crear Location asociada
                </Link>
              </div>
            </Section>
          )}
          {Object.keys(form.errors).length > 0 && (
            <div
              role="alert"
              className="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800"
            >
              <p className="font-semibold">
                No se guardaron los cambios. Revisa los campos indicados.
              </p>
              {Object.entries(form.errors).map(([key, error]) => (
                <p key={key}>
                  {labels[key] || key}: {String(error)}
                </p>
              ))}
            </div>
          )}
          <div className="flex flex-wrap items-center justify-between gap-3 border-t border-border pt-4">
            <Link className={actionClass} href={`/admin/${resource}`}>
              Volver al listado
            </Link>
            {save}
          </div>
          <p role="status" className="text-sm text-muted-foreground">
            {form.processing
              ? "Esperando confirmación del servidor…"
              : form.isDirty
                ? "Cambios pendientes de guardar."
                : "Sin cambios pendientes."}
          </p>
        </form>
      </div>
    </AdminLayout>
  );
}
