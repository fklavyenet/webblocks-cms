<?php

namespace WebBlocks\Cms\Support\Admin;

use WebBlocks\Cms\Policies\CmsNavigationPolicy;
use WebBlocks\Cms\Policies\JobPolicy;
use WebBlocks\Cms\Support\Plugins\PluginSidebarGroups;
use WebBlocks\Cms\Support\Translations\CmsTranslator;

final class AdminNavigation
{
  public function build($user, string $adminLocale): array
  {
    $adminTranslator = app(CmsTranslator::class);
    $adminText = fn ($key) => $adminTranslator->admin($key, $adminLocale);
            $menuItems = [
                ['label' => $adminText('navigation.dashboard'), 'route' => 'admin.dashboard', 'active' => ['admin.dashboard'], 'icon' => 'wb-icon-layout-dashboard'],
                ['label' => $adminText('navigation.sites'), 'route' => 'admin.sites.index', 'active' => ['admin.sites.index', 'admin.sites.create', 'admin.sites.store', 'admin.sites.edit', 'admin.sites.update', 'admin.sites.clone', 'admin.sites.clone.prefill', 'admin.sites.clone.store', 'admin.sites.promote', 'admin.sites.promote.*', 'admin.sites.delete', 'admin.sites.destroy'], 'icon' => 'wb-icon-globe'],
                ['label' => $adminText('navigation.pages'), 'route' => 'admin.pages.index', 'active' => ['admin.pages.*'], 'icon' => 'wb-icon-file-text'],
                ['label' => $adminTranslator->get('dynamic-content.title', $adminLocale), 'route' => 'admin.dynamic-content.index', 'active' => ['admin.dynamic-content.*', 'admin.automatic-pages.*'], 'icon' => 'wb-icon-database'],
                ['label' => $adminText('navigation.shared_slots'), 'route' => 'admin.shared-slots.index', 'active' => ['admin.shared-slots.*'], 'icon' => 'wb-icon-component'],
                ['label' => $adminText('navigation.navigation'), 'route' => 'admin.navigation.index', 'active' => ['admin.navigation.*'], 'icon' => 'wb-icon-network'],
                ['label' => $adminText('navigation.media'), 'route' => 'admin.media.index', 'active' => ['admin.media.*'], 'icon' => 'wb-icon-image'],
                ['label' => $adminTranslator->get('notes.title', $adminLocale), 'route' => 'admin.notes.index', 'active' => ['admin.notes.*'], 'icon' => 'wb-icon-sticky-note'],
                ['label' => $adminText('navigation.assets'), 'route' => 'admin.site-assets.index', 'active' => ['admin.site-assets.*'], 'icon' => 'wb-icon-code'],
            ];

            if ($user?->can('manage-users')) {
                $menuItems[] = ['label' => __('webblocks-cms::acl.title'), 'route' => 'admin.access.roles.index', 'active' => ['admin.access.*'], 'icon' => 'wb-icon-users'];
            }

            if ($user?->can('manage-site-operations')) {
                $menuItems[] = ['label' => $adminText('navigation.contact_messages'), 'route' => 'admin.contact-messages.index', 'active' => ['admin.contact-messages.*'], 'icon' => 'wb-icon-mail'];
                $menuItems[] = ['label' => $adminText('navigation.engagement'), 'route' => 'admin.engagement.index', 'active' => ['admin.engagement.*'], 'icon' => 'wb-icon-mouse-pointer-click'];
            }

            if ($user?->can('view-visitor-reports')) {
                $menuItems[] = ['label' => $adminText('navigation.visitor_reports'), 'route' => 'admin.reports.visitors.index', 'active' => ['admin.reports.visitors.*'], 'icon' => 'wb-icon-bar-chart'];
            }

            if ($user?->can('access-system')) {
                array_splice($menuItems, 3, 0, [[
                    'label' => $adminText('navigation.blocks'),
                    'route' => 'admin.blocks.index',
                    'active' => ['admin.blocks.index'],
                    'icon' => 'wb-icon-blocks',
                ]]);
            }

            $menuItems = array_values(array_filter($menuItems, fn ($item) => app(CmsNavigationPolicy::class)->visible($user, $item['route'])));

            $contentRoutes = ['admin.blocks.index', 'admin.dynamic-content.index', 'admin.shared-slots.index', 'admin.navigation.index'];
            $audienceRoutes = ['admin.contact-messages.index', 'admin.engagement.index', 'admin.reports.visitors.index'];
            $coreGroups = [
                [
                    'key' => 'content-tools',
                    'label' => $adminText('navigation.content_tools'),
                    'icon' => 'wb-icon-blocks',
                    'items' => array_values(array_filter($menuItems, fn ($item) => in_array($item['route'], $contentRoutes, true))),
                ],
                [
                    'key' => 'audience',
                    'label' => $adminText('navigation.communication_analytics'),
                    'icon' => 'wb-icon-bar-chart',
                    'items' => array_values(array_filter($menuItems, fn ($item) => in_array($item['route'], $audienceRoutes, true))),
                ],
                [
                    'key' => 'access',
                    'label' => $adminText('navigation.users_access'),
                    'icon' => 'wb-icon-users',
                    'items' => array_values(array_filter($menuItems, fn ($item) => $item['route'] === 'admin.access.roles.index')),
                ],
            ];
            $menuItems = array_values(array_filter($menuItems, fn ($item) => in_array($item['route'], ['admin.dashboard', 'admin.sites.index', 'admin.pages.index', 'admin.media.index', 'admin.site-assets.index', 'admin.notes.index'], true)));
            $sidebarGroups = [];

            if ($user?->can('access-system')) {
                $coreGroups[2]['items'] = [
                    ['label' => $adminText('navigation.users'), 'route' => 'admin.users.index', 'active' => ['admin.users.*'], 'icon' => 'wb-icon-user-round'],
                    ...$coreGroups[2]['items'],
                    ['label' => $adminText('navigation.api_tokens'), 'route' => 'admin.system.api-tokens.index', 'active' => ['admin.system.api-tokens.*'], 'icon' => 'wb-icon-key-round'],
                ];

                $sidebarGroups[] = [
                    'key' => 'system',
                    'label' => $adminText('navigation.system'),
                    'icon' => 'wb-icon-palette',
                        'items' => [
                            ['label' => $adminText('navigation.domains'), 'route' => 'admin.domains.index', 'active' => ['admin.domains.*', 'admin.sites.domains.*'], 'icon' => 'wb-icon-globe'],
                            ['label' => $adminText('navigation.icons'), 'route' => 'admin.system.icons.index', 'active' => ['admin.system.icons.*'], 'icon' => 'wb-icon-sparkles'],
                            ...($user->can('manage-panel-languages') ? [[
                                'label' => $adminTranslator->get('panel-languages.title', $adminLocale), 'route' => 'admin.system.panel-languages.index',
                                'active' => ['admin.system.panel-languages.*'], 'icon' => 'wb-icon-languages',
                            ]] : []),
                            ['label' => $adminText('navigation.locales'), 'route' => 'admin.locales.index', 'active' => ['admin.locales.*'], 'icon' => 'wb-icon-languages'],
                            ['label' => $adminText('navigation.page_layouts'), 'route' => 'admin.page-layouts.index', 'active' => ['admin.page-layouts.*'], 'icon' => 'wb-icon-layout'],
                            ['label' => $adminText('navigation.slot_types'), 'route' => 'admin.slot-types.index', 'active' => ['admin.slot-types.*'], 'icon' => 'wb-icon-columns2'],
                            ['label' => $adminText('navigation.block_types'), 'route' => 'admin.block-types.index', 'active' => ['admin.block-types.*'], 'icon' => 'wb-icon-box'],
                            ['label' => $adminText('navigation.embedded_applications'), 'route' => 'admin.embedded-applications.index', 'active' => ['admin.embedded-applications.*'], 'icon' => 'wb-icon-square-terminal'],
                            ['label' => $adminText('navigation.settings'), 'route' => 'admin.system.settings.edit', 'active' => ['admin.system.settings.*'], 'icon' => 'wb-icon-settings'],
                            ['label' => $adminText('navigation.plugins'), 'route' => 'admin.system.plugins.index', 'active' => ['admin.system.plugins.*'], 'icon' => 'wb-icon-plug'],
                        ],
                    ];

                $sidebarGroups[] = [
                    'key' => 'maintenance',
                    'label' => $adminTranslator->get('system_health.navigation', $adminLocale),
                    'icon' => 'wb-icon-file',
                    'items' => [
                        ['label' => $adminTranslator->get('system_health.title', $adminLocale), 'route' => 'admin.system.health.index', 'active' => ['admin.system.health.*', 'admin.system.diagnostics.*'], 'icon' => 'wb-icon-activity'],
                        ['label' => $adminText('navigation.search_rebuild'), 'route' => 'admin.system.search.index', 'active' => ['admin.system.search.*'], 'icon' => 'wb-icon-search'],
                        ['label' => $adminText('navigation.backups'), 'route' => 'admin.system.backups.index', 'active' => ['admin.system.backups.*'], 'icon' => 'wb-icon-database-backup'],
                        ['label' => $adminText('navigation.cleanup'), 'route' => 'admin.system.cleanup.index', 'active' => ['admin.system.cleanup.*'], 'icon' => 'wb-icon-trash2'],
                        ['label' => $adminText('navigation.export_import'), 'route' => 'admin.site-transfers.exports.index', 'active' => ['admin.site-transfers.*'], 'icon' => 'wb-icon-arrow-up-down'],
                        ['label' => $adminText('navigation.update'), 'route' => 'admin.system.updates.index', 'active' => ['admin.system.updates.*'], 'icon' => 'wb-icon-download'],
                    ],
                ];
            }

            if (app(CmsNavigationPolicy::class)->visible($user, 'admin.consent.settings')) {
                $coreGroups[] = [
                    'key' => 'consent', 'label' => $adminTranslator->get('consent.menu', $adminLocale), 'icon' => 'wb-icon-shield-check',
                    'items' => [
                        ['label' => $adminTranslator->get('consent.general', $adminLocale), 'route' => 'admin.consent.settings', 'active' => ['admin.consent.settings', 'admin.consent.settings.*'], 'icon' => 'wb-icon-settings'],
                        ['label' => $adminTranslator->get('consent.services', $adminLocale), 'route' => 'admin.consent.services', 'active' => ['admin.consent.services', 'admin.consent.services.*'], 'icon' => 'wb-icon-blocks'],
                        ['label' => $adminTranslator->get('consent.texts', $adminLocale), 'route' => 'admin.consent.texts', 'active' => ['admin.consent.texts', 'admin.consent.texts.*'], 'icon' => 'wb-icon-languages'],
                    ],
                ];
            }

            // Keep the established plugin buckets independent of the new core headings.
            $sidebarGroups = app(PluginSidebarGroups::class)->appendTo($sidebarGroups, $user, $adminLocale);
            $sidebarGroups = array_values(array_filter([...$coreGroups, ...$sidebarGroups], fn ($group) => $group['items'] !== []));
    $structureRoutes = ['admin.page-layouts.index', 'admin.slot-types.index', 'admin.block-types.index', 'admin.system.icons.index'];
    $structure = [];
    foreach ($sidebarGroups as &$group) {
      if ($group['key'] === 'content-tools') {
        $group['label'] = $adminTranslator->get('jobs.content', $adminLocale);
      }
      if ($group['key'] === 'system') {
        $structure = array_values(array_filter($group['items'], fn ($item) => in_array($item['route'], $structureRoutes, true)));
        $group['items'] = array_values(array_filter($group['items'], fn ($item) => ! in_array($item['route'], $structureRoutes, true)));
        foreach ($group['items'] as &$item) {
          if ($item['route'] === 'admin.system.plugins.index') {
          $item['label'] = $adminTranslator->get('jobs.plugin_management', $adminLocale);
          }
        }
        unset($item);
      }
    }
    unset($group);
    if ($structure) {
    $sidebarGroups[] = ['key' => 'structure', 'label' => $adminTranslator->get('jobs.structure', $adminLocale), 'icon' => 'wb-icon-layout', 'items' => $structure];
    }
    $canJobs = app(JobPolicy::class)->system($user) || app(JobPolicy::class)->sites(request()) !== [];
    if ($canJobs) {
      $maintenance = collect($sidebarGroups)->search(fn ($group) => $group['key'] === 'maintenance');
      if ($maintenance === false) {
        $sidebarGroups[] = ['key' => 'maintenance', 'label' => $adminTranslator->get('system_health.navigation', $adminLocale), 'icon' => 'wb-icon-activity', 'items' => []];
        $maintenance = array_key_last($sidebarGroups);
      }
      array_unshift($sidebarGroups[$maintenance]['items'], ['label' => $adminTranslator->get('jobs.title', $adminLocale), 'route' => 'admin.jobs.index', 'active' => ['admin.jobs.index', 'admin.jobs.store', 'admin.jobs.step', 'admin.jobs.transition', 'admin.jobs.automation', 'admin.system.scheduler.*'], 'icon' => 'wb-icon-clock']);
      $audience = collect($sidebarGroups)->search(fn ($group) => $group['key'] === 'audience');
      if ($audience === false) {
        $sidebarGroups[] = ['key' => 'audience', 'label' => $adminText('navigation.communication_analytics'), 'icon' => 'wb-icon-bar-chart', 'items' => []];
        $audience = array_key_last($sidebarGroups);
      }
      $sidebarGroups[$audience]['items'][] = ['label' => $adminTranslator->get('jobs.seo', $adminLocale), 'route' => 'admin.jobs.seo', 'active' => ['admin.jobs.seo', 'admin.sites.seo-audits.*'], 'icon' => 'wb-icon-search'];
    }
    $order = ['content-tools', 'audience', 'access', 'consent', 'structure', 'system', 'maintenance'];
    usort($sidebarGroups, fn ($a, $b) => (array_search($a['key'], $order, true) === false ? 100 : array_search($a['key'], $order, true)) <=> (array_search($b['key'], $order, true) === false ? 100 : array_search($b['key'], $order, true)));

    return ['menuItems' => $menuItems, 'sidebarGroups' => array_values(array_filter($sidebarGroups, fn ($group) => $group['items'] !== []))];
  }
}
