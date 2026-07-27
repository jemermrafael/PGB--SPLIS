<?php

namespace App\Support;

use App\Models\PageBackground;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PageBackgrounds
{
    /**
     * @var array<string, PageBackground|null>|null
     */
    protected static ?array $cache = null;

    /**
     * @return array<string, string>
     */
    public static function positions(): array
    {
        return [
            'default' => 'Default',
            'center center' => 'Center Center',
            'center left' => 'Center Left',
            'center right' => 'Center Right',
            'top center' => 'Top Center',
            'top left' => 'Top Left',
            'top right' => 'Top Right',
            'bottom center' => 'Bottom Center',
            'bottom left' => 'Bottom Left',
            'bottom right' => 'Bottom Right',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function attachments(): array
    {
        return [
            'default' => 'Default',
            'scroll' => 'Scroll',
            'fixed' => 'Fixed',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function repeats(): array
    {
        return [
            'default' => 'Default',
            'no-repeat' => 'No-repeat',
            'repeat' => 'Repeat',
            'repeat-x' => 'Repeat-x',
            'repeat-y' => 'Repeat-y',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function sizes(): array
    {
        return [
            'default' => 'Default',
            'auto' => 'Auto',
            'cover' => 'Cover',
            'contain' => 'Contain',
        ];
    }

    public static function isValidPage(string $pageKey): bool
    {
        if (($committeeId = self::committeeIdFromPageKey($pageKey)) !== null) {
            return \App\Models\Committee::withTrashed()
                ->whereKey($committeeId)
                ->exists();
        }

        return array_key_exists($pageKey, self::moduleCatalog());
    }

    public static function committeePageKey(int $committeeId): string
    {
        return 'committee_'.$committeeId;
    }

    public static function committeeIdFromPageKey(string $pageKey): ?int
    {
        if (preg_match('/^committee_(\d+)$/', $pageKey, $matches) !== 1) {
            return null;
        }

        return (int) $matches[1];
    }

    /**
     * Module pages (not per-committee).
     *
     * @return array<string, array{label: string, default_icon?: string, group?: string}>
     */
    public static function moduleCatalog(): array
    {
        return [
            'dashboard' => ['label' => 'Dashboard'],
            ...PageIcon::catalog(),
        ];
    }

    /**
     * Pages that can receive a classic background (Settings → Pages).
     *
     * @return array<string, array{label: string, default_icon?: string, group?: string}>
     */
    public static function catalog(): array
    {
        $pages = self::moduleCatalog();

        $committees = \App\Models\Committee::query()
            ->ordered()
            ->get(['id', 'name']);

        foreach ($committees as $committee) {
            $pages[self::committeePageKey((int) $committee->id)] = [
                'label' => $committee->name,
                'group' => 'committees',
            ];
        }

        return $pages;
    }

    public static function label(string $pageKey): string
    {
        if (isset(self::catalog()[$pageKey]['label'])) {
            return self::catalog()[$pageKey]['label'];
        }

        $committeeId = self::committeeIdFromPageKey($pageKey);
        if ($committeeId !== null) {
            $name = \App\Models\Committee::withTrashed()->whereKey($committeeId)->value('name');

            return $name ? (string) $name : $pageKey;
        }

        return $pageKey;
    }

    public static function resolvePageKey(?string $routeName = null): ?string
    {
        $routeName ??= request()->route()?->getName();
        if (! is_string($routeName) || $routeName === '') {
            return null;
        }

        if (
            request()->routeIs('committees.show', 'committees.edit')
            || in_array($routeName, ['committees.show', 'committees.edit'], true)
        ) {
            $committee = request()->route('committee');
            if ($committee instanceof \App\Models\Committee) {
                return self::committeePageKey((int) $committee->id);
            }
            if (is_numeric($committee)) {
                return self::committeePageKey((int) $committee);
            }

            return null;
        }

        $checks = [
            'dashboard' => 'dashboard',
            'dashboard.*' => 'dashboard',
            'ob.sessions.attendance.monthly' => 'attendance_monthly',
            'admin.board-member-ordinances' => 'board_member_ordinances_report',
            'admin.board-member-ordinances.*' => 'board_member_ordinances_report',
            'board-member.ordinances.all' => 'board_member_ordinances_all',
            'board-member.ordinances.all.*' => 'board_member_ordinances_all',
            'board-member.committee-reports.*' => 'board_member_committee_reports',
            'board-member.committees.*' => 'my_committees',
            'resolutions.*' => 'resolutions',
            'ordinances.*' => 'ordinances',
            'appropriation-ordinances.*' => 'appropriation_ordinances',
            'agenda.*' => 'agenda',
            'ob.*' => 'order_of_business',
            'references.*' => 'references',
            'directory.*' => 'directory',
            'incoming.*' => 'incoming',
            'committees.*' => 'committees',
            'board-members.*' => 'board_members',
            'committee-monitoring.*' => 'committee_monitoring',
            'committee-reports.*' => 'committee_reports',
            'committee-terms.*' => 'committee_terms',
        ];

        foreach ($checks as $pattern => $pageKey) {
            if (request()->routeIs($pattern) || self::routeMatches($routeName, $pattern)) {
                return $pageKey;
            }
        }

        return null;
    }

    protected static function routeMatches(string $routeName, string $pattern): bool
    {
        if (! str_contains($pattern, '*')) {
            return $routeName === $pattern;
        }

        $prefix = rtrim($pattern, '.*');

        return $routeName === $prefix || str_starts_with($routeName, $prefix.'.');
    }

    public static function forPage(string $pageKey): ?PageBackground
    {
        $map = self::map();

        return $map[$pageKey] ?? null;
    }

    public static function forCurrentRequest(?string $explicitPageKey = null): ?PageBackground
    {
        $pageKey = filled($explicitPageKey) ? $explicitPageKey : self::resolvePageKey();
        if ($pageKey === null || ! self::isValidPage($pageKey)) {
            return null;
        }

        $bg = self::forPage($pageKey);
        if ($bg === null) {
            return null;
        }

        if ($bg->background_type !== 'classic') {
            return null;
        }

        if (! filled($bg->color) && ! $bg->hasImage()) {
            return null;
        }

        return $bg;
    }

    /**
     * @return array<string, PageBackground>
     */
    public static function map(): array
    {
        if (self::$cache !== null) {
            return array_filter(self::$cache);
        }

        self::$cache = PageBackground::query()
            ->get()
            ->keyBy('page_key')
            ->all();

        return self::$cache;
    }

    /**
     * @return Collection<string, PageBackground>
     */
    public static function allByPage(): Collection
    {
        return PageBackground::query()->get()->keyBy('page_key');
    }

    public static function flushCache(): void
    {
        self::$cache = null;
    }

    /**
     * Inline CSS for the main content area.
     */
    public static function cssStyle(PageBackground $background): string
    {
        $parts = [];

        if (filled($background->color)) {
            $parts[] = 'background-color: '.self::sanitizeColor((string) $background->color);
        }

        if ($background->hasImage()) {
            $url = $background->imageUrl();
            if ($url !== null) {
                $parts[] = 'background-image: url('.$url.')';
            }

            $position = $background->position !== 'default' ? $background->position : 'center center';
            $parts[] = 'background-position: '.$position;

            $attachment = $background->attachment !== 'default' ? $background->attachment : 'scroll';
            $parts[] = 'background-attachment: '.$attachment;

            $repeat = $background->repeat !== 'default' ? $background->repeat : 'no-repeat';
            $parts[] = 'background-repeat: '.$repeat;

            $size = $background->size !== 'default' ? $background->size : 'cover';
            $parts[] = 'background-size: '.$size;
        } else {
            $parts[] = 'background-image: none';
        }

        return implode('; ', $parts);
    }

    public static function sanitizeColor(string $color): string
    {
        $color = trim($color);
        if (preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $color) === 1) {
            return $color;
        }

        if (preg_match('/^rgba?\([\d\s.,%]+\)$/', $color) === 1) {
            return $color;
        }

        return 'transparent';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function save(string $pageKey, array $data, ?UploadedFile $image = null, bool $removeImage = false): PageBackground
    {
        if (! self::isValidPage($pageKey)) {
            throw new \InvalidArgumentException('Unknown page key: '.$pageKey);
        }

        $background = PageBackground::query()->firstOrNew(['page_key' => $pageKey]);

        $type = ($data['background_type'] ?? 'classic') === 'classic' ? 'classic' : 'none';

        if ($type === 'none') {
            self::deleteStoredImage($background);
            if ($background->exists) {
                $background->delete();
            }
            self::flushCache();

            return new PageBackground(['page_key' => $pageKey, 'background_type' => 'none']);
        }

        $background->background_type = 'classic';
        $background->color = filled($data['color'] ?? null) ? self::sanitizeColor((string) $data['color']) : null;
        $background->position = self::pickOption((string) ($data['position'] ?? 'default'), self::positions());
        $background->attachment = self::pickOption((string) ($data['attachment'] ?? 'default'), self::attachments());
        $background->repeat = self::pickOption((string) ($data['repeat'] ?? 'default'), self::repeats());
        $background->size = self::pickOption((string) ($data['size'] ?? 'default'), self::sizes());

        if ($removeImage) {
            self::deleteStoredImage($background);
            $background->image_path = null;
            $background->image_original_filename = null;
            $background->mime_type = null;
        }

        if ($image !== null) {
            self::deleteStoredImage($background);
            $stored = self::storeImage($image, $pageKey);
            $background->image_path = $stored['path'];
            $background->image_original_filename = $stored['original'];
            $background->mime_type = $stored['mime'];
        }

        $background->save();
        self::flushCache();

        return $background;
    }

    /**
     * @return array{path: string, original: string, mime: string}
     */
    protected static function storeImage(UploadedFile $file, string $pageKey): array
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: 'jpg');
        if (! in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
            $extension = 'jpg';
        }

        $directory = 'page-backgrounds';
        Storage::disk('local')->makeDirectory($directory);

        $filename = Str::slug($pageKey).'-'.Str::lower(Str::random(8)).'.'.$extension;
        $path = "{$directory}/{$filename}";
        $file->storeAs($directory, $filename, 'local');

        return [
            'path' => $path,
            'original' => mb_substr($file->getClientOriginalName() ?: $filename, 0, 255),
            'mime' => $file->getMimeType() ?: 'image/jpeg',
        ];
    }

    protected static function deleteStoredImage(PageBackground $background): void
    {
        if (filled($background->image_path) && Storage::disk('local')->exists($background->image_path)) {
            Storage::disk('local')->delete($background->image_path);
        }
    }

    /**
     * @param  array<string, string>  $options
     */
    protected static function pickOption(string $value, array $options): string
    {
        return array_key_exists($value, $options) ? $value : 'default';
    }
}
