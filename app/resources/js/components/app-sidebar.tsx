import { Link, usePage } from '@inertiajs/react';
import { BadgeCheck, Gift, House, Shield, Sparkles } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import type { NavItem } from '@/types';

const mainNavItems = (isAdmin: boolean): NavItem[] => [
    {
        title: 'Inicio',
        href: '/',
        icon: House,
    },
    {
        title: 'Beneficios',
        href: '/beneficios',
        icon: Gift,
    },
    { title: 'Experiencias', href: '/experiencias', icon: Sparkles },
    {
        title: 'Mi JAKAWI',
        href: '/mi-jakawi',
        icon: BadgeCheck,
    },
    ...(isAdmin
        ? [
              {
                  title: 'Admin',
                  href: '/admin',
                  icon: Shield,
              },
          ]
        : []),
];

export function AppSidebar() {
    const { auth } = usePage().props;

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href="/" prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems(Boolean(auth.user?.is_admin))} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
