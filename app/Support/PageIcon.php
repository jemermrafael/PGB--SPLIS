<?php

namespace App\Support;

use App\Models\PageIconOverride;
use Illuminate\Support\Collection;

class PageIcon
{
    /**
     * @var array<string, array{url: string, preserve_colors: bool}|null>|null
     */
    protected static ?array $iconCache = null;

    /**
     * Pages with a title icon that can be overridden from the Icon Library.
     *
     * @return array<string, array{label: string, default_icon: string}>
     */
    public static function catalog(): array
    {
        return [
            'resolutions' => ['label' => 'Resolutions', 'default_icon' => 'file-text'],
            'ordinances' => ['label' => 'Ordinances', 'default_icon' => 'ordinances'],
            'appropriation_ordinances' => ['label' => 'Appropriation Ordinances', 'default_icon' => 'ordinances'],
            'agenda' => ['label' => 'Agenda', 'default_icon' => 'agenda'],
            'order_of_business' => ['label' => 'Order of Business', 'default_icon' => 'calendar'],
            'references' => ['label' => 'Reference Materials', 'default_icon' => 'book'],
            'directory' => ['label' => 'Directory', 'default_icon' => 'notebook'],
            'incoming' => ['label' => 'Incoming', 'default_icon' => 'inbox'],
            'committees' => ['label' => 'Committees', 'default_icon' => 'meeting'],
            'board_members' => ['label' => 'Board Members', 'default_icon' => 'user'],
            'committee_monitoring' => ['label' => 'Committee Monitoring', 'default_icon' => 'monitor'],
            'committee_reports' => ['label' => 'Committee Reports', 'default_icon' => 'file-text'],
            'committee_terms' => ['label' => 'Election Terms', 'default_icon' => 'calendar'],
            'attendance_monthly' => ['label' => 'Monthly Attendance', 'default_icon' => 'clipboard-check'],
            'my_committees' => ['label' => 'My Committees', 'default_icon' => 'meeting'],
            'board_member_committee_reports' => ['label' => 'BM Committee Reports', 'default_icon' => 'file-text'],
            'board_member_ordinances_all' => ['label' => 'All Ordinances (BM)', 'default_icon' => 'ordinances'],
            'board_member_ordinances_report' => ['label' => 'BM Authored Ordinances', 'default_icon' => 'ordinances'],
            'scheduled_committee_referrals' => ['label' => 'Schedule Committee Referral', 'default_icon' => 'meeting'],
        ];
    }

    public static function isValidPage(string $pageKey): bool
    {
        return array_key_exists($pageKey, self::catalog());
    }

    public static function defaultIcon(string $pageKey): string
    {
        return self::catalog()[$pageKey]['default_icon'] ?? 'file-text';
    }

    public static function label(string $pageKey): string
    {
        return self::catalog()[$pageKey]['label'] ?? $pageKey;
    }

    public static function customUrl(string $pageKey): ?string
    {
        return self::customIcon($pageKey)['url'] ?? null;
    }

    /**
     * @return array{url: string, preserve_colors: bool}|null
     */
    public static function customIcon(string $pageKey): ?array
    {
        $icons = self::iconMap();

        return $icons[$pageKey] ?? null;
    }

    /**
     * @return array<string, array{url: string, preserve_colors: bool}>
     */
    public static function iconMap(): array
    {
        if (self::$iconCache !== null) {
            return array_filter(self::$iconCache);
        }

        self::$iconCache = [];

        $overrides = PageIconOverride::query()
            ->with('iconLibraryItem')
            ->get();

        foreach ($overrides as $override) {
            $item = $override->iconLibraryItem;
            if ($item !== null && $item->existsLocally()) {
                self::$iconCache[$override->page_key] = [
                    'url' => $item->publicUrl(),
                    'preserve_colors' => $item->preservesOriginalColors(),
                ];
            } else {
                self::$iconCache[$override->page_key] = null;
            }
        }

        return array_filter(self::$iconCache);
    }

    /**
     * @deprecated Use iconMap()
     * @return array<string, string>
     */
    public static function urlMap(): array
    {
        return collect(self::iconMap())
            ->map(fn (array $icon) => $icon['url'])
            ->all();
    }

    public static function flushCache(): void
    {
        self::$iconCache = null;
    }

    /**
     * @return Collection<string, PageIconOverride>
     */
    public static function overridesByPage(): Collection
    {
        return PageIconOverride::query()
            ->with('iconLibraryItem')
            ->get()
            ->keyBy('page_key');
    }

    public static function assign(string $pageKey, ?int $libraryId): void
    {
        if (! self::isValidPage($pageKey)) {
            throw new \InvalidArgumentException('Unknown page key: '.$pageKey);
        }

        if ($libraryId === null) {
            PageIconOverride::query()->where('page_key', $pageKey)->delete();
            self::flushCache();

            return;
        }

        PageIconOverride::query()->updateOrCreate(
            ['page_key' => $pageKey],
            ['icon_library_id' => $libraryId],
        );

        self::flushCache();
    }
}
