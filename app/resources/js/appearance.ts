import { initializeTheme, updateAppearance, type Appearance } from '@/hooks/use-appearance';
initializeTheme();
const selectors = document.querySelectorAll<HTMLSelectElement>('[data-appearance-selector]');
const refresh = () => selectors.forEach((select) => { select.value = document.documentElement.dataset.appearance ?? 'system'; });
refresh();
document.addEventListener('appearancechange', refresh);
selectors.forEach((select) => select.addEventListener('change', () => updateAppearance(select.value as Appearance, Boolean(document.documentElement.dataset.appearanceUser))));
