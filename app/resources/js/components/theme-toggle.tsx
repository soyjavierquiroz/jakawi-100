import { usePage } from '@inertiajs/react';
import { AppearanceMenu } from '@/components/appearance-menu';

export default function ThemeToggle() {
    const { auth } = usePage().props;
    return <AppearanceMenu authenticated={Boolean(auth?.user)} />;
}
