const fieldLabels: Record<string, string> = {
    name: 'Nombre interno',
    slug: 'Slug único',
    headline: 'Headline',
    headline_emphasis: 'Texto destacado',
    subheadline: 'Descripción editorial',
    reward_display_override: 'Copy complementario del valor',
    acquisition_headline: 'Headline al crear cuenta',
    acquisition_supporting_copy: 'Texto de apoyo',
    eyebrow: 'Eyebrow',
    campaign_key: 'Campaign key',
    hero_path: 'Referencia del visual',
    hero_alt: 'Descripción del visual',
    hero_layout: 'Hero',
    density: 'Densidad',
    template_key: 'Template',
    marketing_theme: 'Theme',
    default_scope: 'Audiencia al publicar',
    subject_type: 'Tipo de producto',
    subject_id: 'Producto',
    presentation: 'Landing',
    media_mode: 'Media',
    vsl: 'Video',
    final_cta_headline: 'Titular final',
    final_cta_description: 'Texto de apoyo final',
};

export function landingEditorFieldLabel(key: string): string {
    return (
        fieldLabels[key] ??
        (key.startsWith('vsl.')
            ? 'Configuración de video'
            : 'Configuración de la landing')
    );
}
