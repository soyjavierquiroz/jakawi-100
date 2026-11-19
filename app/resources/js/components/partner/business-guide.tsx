import { actionClass } from './editorial';
export default function BusinessGuide({
    item,
    isPartner,
}: {
    item?: any;
    isPartner: boolean;
}) {
    const missing = (
        isPartner
            ? [
                  ['description', 'Descripción del negocio'],
                  ['category', 'Categoría'],
                  ['phone', 'Teléfono'],
                  ['email', 'Email'],
              ]
            : [
                  ['address', 'Dirección'],
                  ['phone', 'Teléfono'],
                  ['opening_hours', 'Horarios'],
              ]
    )
        .filter(([key]) => !item?.[key])
        .map(([, label]) => label);
    return (
        <aside className="space-y-4 rounded-2xl border border-border bg-surface p-5">
            <div>
                <h2 className="font-semibold">
                    {isPartner
                        ? 'Prepara la información de tu establecimiento'
                        : 'Datos de la sede'}
                </h2>
                <p className="mt-2 text-sm leading-relaxed text-muted-foreground">
                    Puedes editar identidad, contacto, imágenes y{' '}
                    {isPartner ? 'descripción' : 'dirección y horarios'}. JAKAWI
                    administra publicación, pausa, archivo y{' '}
                    {isPartner ? 'visibilidad destacada' : 'PIN de canje'}.
                    Guardar estos datos no habilita operaciones comerciales.
                </p>
            </div>
            {missing.length > 0 && (
                <p className="text-sm">
                    <strong>Información por completar:</strong>{' '}
                    {missing.join(' · ')}. Esta orientación no añade requisitos
                    de publicación.
                </p>
            )}
            <nav
                aria-label="Secciones del formulario"
                className="flex flex-wrap gap-2"
            >
                {[
                    ['identidad', 'Identidad'],
                    [
                        isPartner ? 'contacto' : 'direccion',
                        isPartner ? 'Contacto' : 'Dirección',
                    ],
                    [
                        isPartner ? 'media' : 'contacto',
                        isPartner ? 'Imágenes' : 'Contacto',
                    ],
                    ...(isPartner
                        ? [['sedes', 'Sedes']]
                        : [['maps', 'Ubicación']]),
                ].map(([id, label]) => (
                    <a key={id} className={actionClass} href={`#${id}`}>
                        {label}
                    </a>
                ))}
            </nav>
        </aside>
    );
}
