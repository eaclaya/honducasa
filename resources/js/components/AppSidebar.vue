<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    Bell,
    Building2,
    Compass,
    Heart,
    Home,
    LayoutGrid,
    MessageCircle,
    Search,
    ShieldAlert,
    Users,
} from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import TeamSwitcher from '@/components/TeamSwitcher.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard, home } from '@/routes';
import { index as moderation } from '@/routes/admin/moderation';
import { index as adminProperties } from '@/routes/admin/properties';
import { index as adminTeams } from '@/routes/admin/teams';
import { index as adminUsers } from '@/routes/admin/users';
import { index as favorites } from '@/routes/favorites';
import { index as listings } from '@/routes/listings';
import { show as messages } from '@/routes/messages';
import { index as notifications } from '@/routes/notifications';
import { index as rentals } from '@/routes/rentals';
import { index as savedSearches } from '@/routes/saved-searches';
import { dashboard as userDashboard } from '@/routes/user';
import type { NavItem } from '@/types';

const page = usePage();

const dashboardUrl = computed(() =>
    page.props.currentTeam
        ? dashboard(page.props.currentTeam.slug).url
        : userDashboard().url,
);

const hasTeams = computed(() => page.props.teams.length > 0);

const mainNavItems = computed<NavItem[]>(() => [
    {
        title: 'Dashboard',
        href: dashboardUrl.value,
        icon: LayoutGrid,
    },
    {
        title:
            page.props.locale === 'es'
                ? 'Explorar propiedades'
                : 'Explore properties',
        href: rentals().url,
        icon: Compass,
    },
    ...(page.props.currentTeam
        ? [
              {
                  title:
                      page.props.locale === 'es'
                          ? 'Mis propiedades'
                          : 'My listings',
                  href: listings.url(page.props.currentTeam.slug),
                  icon: Building2,
              },
          ]
        : []),
    {
        title: page.props.locale === 'es' ? 'Favoritos' : 'Favorites',
        href: favorites().url,
        icon: Heart,
    },
    {
        title:
            page.props.locale === 'es'
                ? 'Búsquedas guardadas'
                : 'Saved searches',
        href: savedSearches().url,
        icon: Search,
    },
    {
        title: page.props.locale === 'es' ? 'Mensajes' : 'Messages',
        href: messages().url,
        icon: MessageCircle,
        badge: page.props.unreadMessages,
    },
    {
        title: page.props.locale === 'es' ? 'Notificaciones' : 'Notifications',
        href: notifications().url,
        icon: Bell,
        badge: page.props.unreadNotifications,
    },
]);

const isAdmin = computed(() => page.props.auth.user?.is_admin === true);

const adminNavItems = computed<NavItem[]>(() =>
    isAdmin.value
        ? [
              {
                  title: page.props.locale === 'es' ? 'Usuarios' : 'Users',
                  href: adminUsers().url,
                  icon: Users,
              },
              {
                  title: page.props.locale === 'es' ? 'Equipos' : 'Teams',
                  href: adminTeams().url,
                  icon: Building2,
              },
              {
                  title:
                      page.props.locale === 'es' ? 'Propiedades' : 'Properties',
                  href: adminProperties().url,
                  icon: Home,
              },
              {
                  title:
                      page.props.locale === 'es' ? 'Moderación' : 'Moderation',
                  href: moderation().url,
                  icon: ShieldAlert,
              },
          ]
        : [],
);
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="home()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
            <SidebarMenu v-if="hasTeams">
                <SidebarMenuItem>
                    <TeamSwitcher />
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
            <NavMain
                v-if="isAdmin"
                :items="adminNavItems"
                :label="
                    page.props.locale === 'es'
                        ? 'Administración'
                        : 'Administration'
                "
            />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
