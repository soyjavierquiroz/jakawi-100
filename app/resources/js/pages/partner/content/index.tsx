import { Head, Link } from '@inertiajs/react';
import PartnerLayout from '../layout';

export default function ContentIndex({ partner, kind, items }: any) {
    const isBenefit = kind === 'benefit'; const label = isBenefit ? 'Promociones' : 'Experiencias';
    return <PartnerLayout title={label} partner={partner}><Head title={label}/><div className="flex items-center justify-between"><h1 className="text-2xl font-semibold">{label}</h1><Link className="rounded bg-primary px-3 py-2 text-primary-foreground" href={`/partner/${partner.slug}/${isBenefit ? 'promociones' : 'experiencias'}/crear`}>Crear {isBenefit ? 'promoción' : 'experiencia'}</Link></div><div className="mt-5 space-y-3">{items.map((item: any) => <div className="rounded border p-4" key={item.id}><Link className="font-semibold underline" href={`/partner/${partner.slug}/${isBenefit ? 'promociones' : 'experiencias'}/${item.slug}/editar`}>{item.title}</Link><p className="mt-1 text-sm">Estado editorial: {item.review_status}</p>{item.review_notes && <p className="mt-1 text-sm text-amber-700">JAKAWI solicitó cambios: {item.review_notes}</p>}{!isBenefit && <p className="text-sm text-muted-foreground">{item.sessions_count} sesiones</p>}</div>)}{!items.length && <p className="text-muted-foreground">Aún no hay contenido.</p>}</div></PartnerLayout>;
}
