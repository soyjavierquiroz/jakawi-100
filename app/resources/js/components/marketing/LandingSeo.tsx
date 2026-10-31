import { Head, usePage } from '@inertiajs/react';

type Metadata = { title: string; description: string; canonical: string; url: string; robots: string; image: string; imageAlt: string };

export function LandingSeo() {
    const { landingSeo: seo } = usePage().props as unknown as { landingSeo?: Metadata | null };
    if (!seo) return <Head><meta head-key="robots" name="robots" content="noindex,nofollow" /></Head>;
    return <Head title={seo.title}>
        <link head-key="canonical" rel="canonical" href={seo.canonical} />
        <meta head-key="description" name="description" content={seo.description} />
        <meta head-key="robots" name="robots" content={seo.robots} />
        <meta head-key="og:type" property="og:type" content="website" />
        <meta head-key="og:title" property="og:title" content={seo.title} />
        <meta head-key="og:description" property="og:description" content={seo.description} />
        <meta head-key="og:image" property="og:image" content={seo.image} />
        <meta head-key="og:image:width" property="og:image:width" content="1200" />
        <meta head-key="og:image:height" property="og:image:height" content="630" />
        <meta head-key="og:image:type" property="og:image:type" content="image/jpeg" />
        <meta head-key="og:image:alt" property="og:image:alt" content={seo.imageAlt} />
        <meta head-key="og:url" property="og:url" content={seo.url} />
        <meta head-key="og:site_name" property="og:site_name" content="JAKAWI" />
        <meta head-key="twitter:card" name="twitter:card" content="summary_large_image" />
        <meta head-key="twitter:title" name="twitter:title" content={seo.title} />
        <meta head-key="twitter:description" name="twitter:description" content={seo.description} />
        <meta head-key="twitter:image" name="twitter:image" content={seo.image} />
    </Head>;
}
