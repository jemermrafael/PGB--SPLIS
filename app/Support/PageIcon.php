<?php

namespace App\Support;

use App\Models\PageIconOverride;
use Illuminate\Support\Collection;

class PageIcon
{
    /**
     * @var array<string, ?string>|null
     */
    protected static ?array $urlCache = null;

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
            'my_committees' => ['label' => 'My Committees', 'default_icon' => 'users'],
            'board_member_committee_reports' => ['label' => 'BM Committee Reports', 'default_icon' => 'file-text'],
            'board_member_ordinances_all' => ['label' => 'All Ordinances (BM)', 'default_icon' => 'ordinances'],
            'board_member_ordinances_report' => ['label' => 'BM Authored Ordinances', 'default_icon' => 'ordinances'],
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
        $urls = self::urlMap();

        return $urls[$pageKey] ?? null;
    }

    /**
     * @return array<string, string>
     */
    public static function urlMap(): array
    {
        if (self::$urlCache !== null) {
            return array_filter(self::$urlCache, fn (?string $url) => $url !== null);
        }

        self::$urlCache = [];

        $overrides = PageIconOverride::query()
            ->with('iconLibraryItem')
            ->get();

        foreach ($overrides as $override) {
            $item = $override->iconLibraryItem;
            if ($item !== null && $item->existsLocally()) {
                self::$urlCache[$override->page_key] = $item->publicUrl();
            } else {
                self::$urlCache[$override->page_key] = null;
            }
        }

        return array_filter(self::$urlCache, fn (?string $url) => $url !== null);
    }

    public static function flushCache(): void
    {
        self::$urlCache = null;
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
