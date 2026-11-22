import { createElement } from 'react';
import { createRoot } from 'react-dom/client';
import { AppearanceMenu } from '@/components/appearance-menu';
import { initializeTheme } from '@/hooks/use-appearance';

initializeTheme();
document
    .querySelectorAll<HTMLElement>('[data-appearance-menu]')
    .forEach((node) => {
        createRoot(node).render(
            createElement(AppearanceMenu, {
                authenticated: Boolean(
                    document.documentElement.dataset.appearanceUser,
                ),
            }),
        );
    });
