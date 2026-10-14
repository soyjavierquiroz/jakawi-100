import {
    Facebook,
    Globe,
    Instagram,
    MessageCircle,
    Music2,
} from 'lucide-react';

type SocialFields = {
    instagram?: string | null;
    tiktok?: string | null;
    facebook?: string | null;
    website?: string | null;
    whatsapp?: string | null;
};
function externalUrl(value?: string | null): string | null {
    if (!value) return null;
    try {
        const url = new URL(value.trim());
        return ['https:', 'http:'].includes(url.protocol) &&
            !url.username &&
            !url.password
            ? url.href
            : null;
    } catch {
        return null;
    }
}
export function PartnerSocialLinks({ partner }: { partner: SocialFields }) {
    const whatsapp =
        externalUrl(partner.whatsapp) ||
        (partner.whatsapp &&
        /^\+?[1-9][\d\s()-]{6,20}$/.test(partner.whatsapp.trim())
            ? `https://wa.me/${partner.whatsapp.replace(/\D/g, '')}`
            : null);
    const links = [
        {
            label: 'Instagram',
            href: externalUrl(partner.instagram),
            icon: Instagram,
        },
        { label: 'TikTok', href: externalUrl(partner.tiktok), icon: Music2 },
        {
            label: 'Facebook',
            href: externalUrl(partner.facebook),
            icon: Facebook,
        },
        { label: 'Website', href: externalUrl(partner.website), icon: Globe },
        { label: 'WhatsApp', href: whatsapp, icon: MessageCircle },
    ].filter((link) => link.href);
    if (!links.length) return null;
    return (
        <nav
            data-partner-socials
            aria-label="Enlaces de este Partner"
            className="mt-5 flex flex-wrap gap-x-4 gap-y-1"
        >
            {links.map(({ label, href, icon: Icon }) => (
                <a
                    key={label}
                    href={href!}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="inline-flex min-h-11 items-center gap-1.5 text-xs font-semibold text-muted-foreground transition hover:text-foreground"
                >
                    <Icon className="size-4 shrink-0" aria-hidden="true" />
                    {label}
                </a>
            ))}
        </nav>
    );
}
