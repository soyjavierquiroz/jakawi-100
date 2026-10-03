import type { ImgHTMLAttributes } from 'react';

export default function AppLogoIcon(props: ImgHTMLAttributes<HTMLImageElement>) {
    return (
        <img src="/jakawi-mark.svg" alt="JAKAWI" {...props} />
    );
}
