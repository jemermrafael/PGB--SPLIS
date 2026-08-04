<?php

namespace App\Services;

use Illuminate\Support\Facades\File;

class EmailNotificationSettings
{
    public const AUDIENCE_BOARD_MEMBER = 'board_member';

    public const AUDIENCE_MUNICIPAL = 'municipal';

    public const AUDIENCE_STAFF = 'staff';

    public const TYPE_COMMITTEE_REPORT_SUBMITTED = 'committee_report_submitted';

    public const TYPE_RESOLUTION_PUBLISHED = 'resolution_published';

    public const TYPE_ORDINANCE_PUBLISHED = 'ordinance_published';

    public const TYPE_APPROPRIATION_ORDINANCE_PUBLISHED = 'appropriation_ordinance_published';

    public function path(): string
    {
        return storage_path('app/email-notification-settings.json');
    }

    /**
     * @return list<string>
     */
    public static function audiences(): array
    {
        return [
            self::AUDIENCE_BOARD_MEMBER,
            self::AUDIENCE_MUNICIPAL,
            self::AUDIENCE_STAFF,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function audienceLabels(): array
    {
        return [
            self::AUDIENCE_BOARD_MEMBER => 'Board Members',
            self::AUDIENCE_MUNICIPAL => 'Municipal Accounts',
            self::AUDIENCE_STAFF => 'Encoders & Admins',
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    public static function typesByAudience(): array
    {
        return [
            self::AUDIENCE_BOARD_MEMBER => [
                \App\Models\UserNotification::TYPE_COMMITTEE_REFERRAL,
                \App\Models\UserNotification::TYPE_SCHEDULED_COMMITTEE_REFERRAL,
                \App\Models\UserNotification::TYPE_AGENDA_PUBLISHED,
                self::TYPE_RESOLUTION_PUBLISHED,
                self::TYPE_ORDINANCE_PUBLISHED,
                self::TYPE_APPROPRIATION_ORDINANCE_PUBLISHED,
                \App\Models\UserNotification::TYPE_AGENDA_ADDED_TO_OB,
                \App\Models\UserNotification::TYPE_SESSION_CREATED,
                \App\Models\UserNotification::TYPE_OB_DOCUMENT_CREATED,
                \App\Models\UserNotification::TYPE_AGENDA_EXPIRING_SOON,
            ],
            self::AUDIENCE_MUNICIPAL => [
                \App\Models\UserNotification::TYPE_COMMITTEE_REFERRAL,
                self::TYPE_RESOLUTION_PUBLISHED,
                self::TYPE_ORDINANCE_PUBLISHED,
                self::TYPE_APPROPRIATION_ORDINANCE_PUBLISHED,
                \App\Models\UserNotification::TYPE_AGENDA_ADDED_TO_OB,
                \App\Models\UserNotification::TYPE_AGENDA_EXPIRING_SOON,
            ],
            self::AUDIENCE_STAFF => [
                self::TYPE_COMMITTEE_REPORT_SUBMITTED,
                \App\Models\UserNotification::TYPE_ACTIVITY_LOG,
                \App\Models\UserNotification::TYPE_SESSION_CREATED,
                \App\Models\UserNotification::TYPE_OB_DOCUMENT_CREATED,
                \App\Models\UserNotification::TYPE_AGENDA_EXPIRING_SOON,
            ],
        ];
    }

    /**
     * Types enabled by default (important alerts). All other listed types stay off until turned on.
     *
     * @return array<string, list<string>>
     */
    public static function defaultEnabledTypesByAudience(): array
    {
        return [
            self::AUDIENCE_BOARD_MEMBER => [
                \App\Models\UserNotification::TYPE_COMMITTEE_REFERRAL,
                \App\Models\UserNotification::TYPE_SCHEDULED_COMMITTEE_REFERRAL,
                \App\Models\UserNotification::TYPE_AGENDA_PUBLISHED,
                \App\Models\UserNotification::TYPE_AGENDA_ADDED_TO_OB,
                \App\Models\UserNotification::TYPE_SESSION_CREATED,
                \App\Models\UserNotification::TYPE_OB_DOCUMENT_CREATED,
                \App\Models\UserNotification::TYPE_AGENDA_EXPIRING_SOON,
            ],
            self::AUDIENCE_MUNICIPAL => [
                \App\Models\UserNotification::TYPE_COMMITTEE_REFERRAL,
                self::TYPE_RESOLUTION_PUBLISHED,
                self::TYPE_ORDINANCE_PUBLISHED,
                \App\Models\UserNotification::TYPE_AGENDA_ADDED_TO_OB,
                \App\Models\UserNotification::TYPE_AGENDA_EXPIRING_SOON,
            ],
            self::AUDIENCE_STAFF => [
                self::TYPE_COMMITTEE_REPORT_SUBMITTED,
            ],
        ];
    }

    public static function typeEnabledByDefault(string $audience, string $type): bool
    {
        return in_array($type, self::defaultEnabledTypesByAudience()[$audience] ?? [], true);
    }

    /**
     * @return array<string, string>
     */
    public static function typeLabels(): array
    {
        return [
            \App\Models\UserNotification::TYPE_COMMITTEE_REFERRAL => 'Committee Referral',
            \App\Models\UserNotification::TYPE_SCHEDULED_COMMITTEE_REFERRAL => 'Scheduled Committee Referral',
            \App\Models\UserNotification::TYPE_AGENDA_PUBLISHED => 'Agenda published (any target)',
            \App\Models\UserNotification::TYPE_AGENDA_ADDED_TO_OB => 'Agenda added to Order of Business',
            \App\Models\UserNotification::TYPE_SESSION_CREATED => 'New Session scheduled',
            \App\Models\UserNotification::TYPE_OB_DOCUMENT_CREATED => 'Order of Business created',
            \App\Models\UserNotification::TYPE_AGENDA_EXPIRING_SOON => 'Agenda deadline approaching',
            \App\Models\UserNotification::TYPE_ACTIVITY_LOG => 'Activity log events',
            self::TYPE_RESOLUTION_PUBLISHED => 'Agenda was published to Resolution',
            self::TYPE_ORDINANCE_PUBLISHED => 'New Ordinance published',
            self::TYPE_APPROPRIATION_ORDINANCE_PUBLISHED => 'New Appropriation Ordinance published',
            self::TYPE_COMMITTEE_REPORT_SUBMITTED => 'Board Member Committee Report submitted',
        ];
    }

    /**
     * @return array{subject: string, body: string, action_label: string}
     */
    public static function defaultTemplate(string $audience, string $type): array
    {
        $defaults = [
            self::AUDIENCE_BOARD_MEMBER => [
                \App\Models\UserNotification::TYPE_COMMITTEE_REFERRAL => [
                    'subject' => 'Agenda referred to your Committee',
                    'body' => "{{label}} was referred to {{committee}}.\n\nOpen SPLIS for details.",
                    'action_label' => 'View Agenda',
                ],
                \App\Models\UserNotification::TYPE_SCHEDULED_COMMITTEE_REFERRAL => [
                    'subject' => 'Incoming agenda for referral',
                    'body' => "{{label}} from Regular Unassigned Business is ready for referral to {{committee}} (you are Chair).\n\nOpen SPLIS for details.",
                    'action_label' => 'View Agenda',
                ],
                \App\Models\UserNotification::TYPE_AGENDA_PUBLISHED => [
                    'subject' => 'Agenda published',
                    'body' => "{{label}} was published to {{target}}.\n\nOpen SPLIS for details.",
                    'action_label' => 'View published item',
                ],
                self::TYPE_RESOLUTION_PUBLISHED => [
                    'subject' => 'Agenda was published to Resolution',
                    'body' => "{{label}} was published as a Resolution{{number_suffix}}.\n\nOpen SPLIS for details.",
                    'action_label' => 'View Resolution',
                ],
                self::TYPE_ORDINANCE_PUBLISHED => [
                    'subject' => 'New Ordinance published',
                    'body' => "{{label}} was published as an Ordinance{{number_suffix}}.\n\nOpen SPLIS for details.",
                    'action_label' => 'View Ordinance',
                ],
                self::TYPE_APPROPRIATION_ORDINANCE_PUBLISHED => [
                    'subject' => 'New Appropriation Ordinance published',
                    'body' => "{{label}} was published as an Appropriation Ordinance{{number_suffix}}.\n\nOpen SPLIS for details.",
                    'action_label' => 'View ordinance',
                ],
                \App\Models\UserNotification::TYPE_AGENDA_ADDED_TO_OB => [
                    'subject' => '{{email_subject}}',
                    'body' => "{{summary}}\n\nOpen SPLIS for details.",
                    'action_label' => 'View Session',
                ],
                \App\Models\UserNotification::TYPE_SESSION_CREATED => [
                    'subject' => 'New Session scheduled',
                    'body' => "{{session}}\n\nA New Session has been scheduled.",
                    'action_label' => 'View Session',
                ],
                \App\Models\UserNotification::TYPE_OB_DOCUMENT_CREATED => [
                    'subject' => 'Order of Business created',
                    'body' => "{{document_title}}\n\nThe Order of Business is now available.",
                    'action_label' => 'View Order of Business',
                ],
                \App\Models\UserNotification::TYPE_AGENDA_EXPIRING_SOON => [
                    'subject' => 'Agenda deadline approaching',
                    'body' => "{{label}} is due on {{due_date}}{{days_left_suffix}}.\n\nPlease take action before the deadline.",
                    'action_label' => 'View Agenda',
                ],
            ],
            self::AUDIENCE_MUNICIPAL => [
                \App\Models\UserNotification::TYPE_COMMITTEE_REFERRAL => [
                    'subject' => 'Your request was referred to a Committee',
                    'body' => "{{label}} was referred to {{committee}}.\n\nYou can track this request in SPLIS.",
                    'action_label' => 'View Request',
                ],
                self::TYPE_RESOLUTION_PUBLISHED => [
                    'subject' => 'Agenda was published to Resolution',
                    'body' => "{{label}} was published as a Resolution{{number_suffix}}.\n\nYou can view the published Resolution in SPLIS.",
                    'action_label' => 'View Request',
                ],
                self::TYPE_ORDINANCE_PUBLISHED => [
                    'subject' => 'New Ordinance published',
                    'body' => "{{label}} was published as {{target}}{{number_suffix}}.\n\nYou can view the published Ordinance in SPLIS.",
                    'action_label' => 'View Request',
                ],
                self::TYPE_APPROPRIATION_ORDINANCE_PUBLISHED => [
                    'subject' => 'New appropriation ordinance published',
                    'body' => "{{label}} was published as an Appropriation Ordinance{{number_suffix}}.\n\nYou can view the published Ordinance in SPLIS.",
                    'action_label' => 'View Request',
                ],
                \App\Models\UserNotification::TYPE_AGENDA_ADDED_TO_OB => [
                    'subject' => '{{email_subject}}',
                    'body' => "{{summary}}\n\nYou can track this in SPLIS.",
                    'action_label' => 'View Request',
                ],
                \App\Models\UserNotification::TYPE_AGENDA_EXPIRING_SOON => [
                    'subject' => 'Request deadline approaching',
                    'body' => "{{label}} is due on {{due_date}}{{days_left_suffix}}.\n\nPlease follow up before the deadline.",
                    'action_label' => 'View Request',
                ],
            ],
            self::AUDIENCE_STAFF => [
                self::TYPE_COMMITTEE_REPORT_SUBMITTED => [
                    'subject' => 'Committee Report submitted',
                    'body' => "{{member_name}} submitted a Committee Report{{report_title_suffix}}.\n\nReview it in the Committee Reports list.",
                    'action_label' => 'View Committee Reports',
                ],
                \App\Models\UserNotification::TYPE_ACTIVITY_LOG => [
                    'subject' => '{{title}}',
                    'body' => "{{body}}\n\nReview this activity in SPLIS.",
                    'action_label' => 'View Activity',
                ],
                \App\Models\UserNotification::TYPE_SESSION_CREATED => [
                    'subject' => 'New Session scheduled',
                    'body' => "{{session}}\n\nA new Session has been scheduled.",
                    'action_label' => 'View Session',
                ],
                \App\Models\UserNotification::TYPE_OB_DOCUMENT_CREATED => [
                    'subject' => 'Order of Business created',
                    'body' => "{{document_title}}\n\nThe Order of Business is now available.",
                    'action_label' => 'View Order of Business',
                ],
                \App\Models\UserNotification::TYPE_AGENDA_EXPIRING_SOON => [
                    'subject' => 'Agenda deadline approaching',
                    'body' => "{{label}} is due on {{due_date}}{{days_left_suffix}}.\n\nPlease take action before the deadline.",
                    'action_label' => 'View Agenda',
                ],
            ],
        ];

        return $defaults[$audience][$type] ?? [
            'subject' => '{{title}}',
            'body' => '{{body}}',
            'action_label' => 'View details',
        ];
    }

    /**
     * @return list<string>
     */
    public static function templatePlaceholders(): array
    {
        return [
            '{{title}}',
            '{{body}}',
            '{{label}}',
            '{{committee}}',
            '{{target}}',
            '{{session}}',
            '{{document_title}}',
            '{{due_date}}',
            '{{days_left_suffix}}',
            '{{number_suffix}}',
            '{{member_name}}',
            '{{report_title_suffix}}',
            '{{app_name}}',
        ];
    }

    /**
     * @return array{
     *     enabled: bool,
     *     types: array<string, array<string, bool>>,
     *     templates: array<string, array<string, array{subject: string, body: string, action_label: string}>>,
     *     smtp: array{
     *         mailer: string,
     *         host: string,
     *         port: int,
     *         username: string,
     *         password: string,
     *         encryption: string,
     *         from_address: string,
     *         from_name: string
     *     }
     * }
     */
    public function defaults(): array
    {
        $types = [];
        $templates = [];

        foreach (self::typesByAudience() as $audience => $audienceTypes) {
            foreach ($audienceTypes as $type) {
                $types[$audience][$type] = self::typeEnabledByDefault($audience, $type);
                $templates[$audience][$type] = self::defaultTemplate($audience, $type);
            }
        }

        return [
            'enabled' => true,
            'types' => $types,
            'templates' => $templates,
            'smtp' => [
                'mailer' => (string) config('mail.default', 'log'),
                'host' => (string) config('mail.mailers.smtp.host', '127.0.0.1'),
                'port' => (int) config('mail.mailers.smtp.port', 2525),
                'username' => (string) config('mail.mailers.smtp.username', ''),
                'password' => (string) config('mail.mailers.smtp.password', ''),
                'encryption' => (string) (config('mail.mailers.smtp.scheme') ?: ''),
                'from_address' => (string) config('mail.from.address', ''),
                'from_name' => (string) config('mail.from.name', config('app.name')),
            ],
        ];
    }

    /**
     * @return array{
     *     enabled: bool,
     *     types: array<string, array<string, bool>>,
     *     templates: array<string, array<string, array{subject: string, body: string, action_label: string}>>,
     *     smtp: array{
     *         mailer: string,
     *         host: string,
     *         port: int,
     *         username: string,
     *         password: string,
     *         encryption: string,
     *         from_address: string,
     *         from_name: string
     *     }
     * }
     */
    public function get(): array
    {
        $defaults = $this->defaults();
        $stored = $this->readFile();
        $legacyTypes = $this->migrateLegacyTypes($stored);

        $types = $defaults['types'];
        foreach ($types as $audience => $audienceTypes) {
            foreach ($audienceTypes as $type => $defaultOn) {
                if (array_key_exists($type, $legacyTypes[$audience] ?? [])) {
                    $types[$audience][$type] = (bool) $legacyTypes[$audience][$type];
                } elseif (array_key_exists($type, $stored['types'][$audience] ?? [])) {
                    $types[$audience][$type] = (bool) $stored['types'][$audience][$type];
                }
            }
        }

        $templates = $defaults['templates'];
        foreach ($templates as $audience => $audienceTemplates) {
            foreach ($audienceTemplates as $type => $defaultTemplate) {
                $storedTemplate = $stored['templates'][$audience][$type] ?? null;
                if (! is_array($storedTemplate)) {
                    continue;
                }

                $templates[$audience][$type] = [
                    'subject' => (string) ($storedTemplate['subject'] ?? $defaultTemplate['subject']),
                    'body' => (string) ($storedTemplate['body'] ?? $defaultTemplate['body']),
                    'action_label' => (string) ($storedTemplate['action_label'] ?? $defaultTemplate['action_label']),
                ];
            }
        }

        $smtp = array_merge($defaults['smtp'], is_array($stored['smtp'] ?? null) ? $stored['smtp'] : []);
        $smtp['port'] = (int) ($smtp['port'] ?? $defaults['smtp']['port']);
        $smtp['password'] = (string) ($smtp['password'] ?? '');

        return [
            'enabled' => array_key_exists('enabled', $stored)
                ? (bool) $stored['enabled']
                : $defaults['enabled'],
            'types' => $types,
            'templates' => $templates,
            'smtp' => [
                'mailer' => (string) ($smtp['mailer'] ?: 'log'),
                'host' => (string) $smtp['host'],
                'port' => (int) $smtp['port'],
                'username' => (string) $smtp['username'],
                'password' => (string) $smtp['password'],
                'encryption' => (string) ($smtp['encryption'] ?? ''),
                'from_address' => (string) $smtp['from_address'],
                'from_name' => (string) $smtp['from_name'],
            ],
        ];
    }

    public function isEnabled(): bool
    {
        return $this->get()['enabled'];
    }

    public function typeEnabled(string $audience, string $type): bool
    {
        $settings = $this->get();

        if (! $settings['enabled']) {
            return false;
        }

        return (bool) ($settings['types'][$audience][$type] ?? false);
    }

    /**
     * @return array{subject: string, body: string, action_label: string}
     */
    public function template(string $audience, string $type): array
    {
        return $this->get()['templates'][$audience][$type] ?? self::defaultTemplate($audience, $type);
    }

    /**
     * @param  array{
     *     enabled?: bool,
     *     types?: array<string, array<string, bool|int|string>>,
     *     templates?: array<string, array<string, array<string, mixed>>>,
     *     smtp?: array<string, mixed>
     * }  $values
     */
    public function update(array $values): void
    {
        $current = $this->get();

        if (array_key_exists('enabled', $values)) {
            $current['enabled'] = (bool) $values['enabled'];
        }

        if (isset($values['types']) && is_array($values['types'])) {
            foreach (self::typesByAudience() as $audience => $audienceTypes) {
                foreach ($audienceTypes as $type) {
                    $current['types'][$audience][$type] = filter_var(
                        $values['types'][$audience][$type] ?? false,
                        FILTER_VALIDATE_BOOLEAN,
                    );
                }
            }
        }

        if (isset($values['templates']) && is_array($values['templates'])) {
            foreach (self::typesByAudience() as $audience => $audienceTypes) {
                foreach ($audienceTypes as $type) {
                    $incoming = $values['templates'][$audience][$type] ?? null;
                    if (! is_array($incoming)) {
                        continue;
                    }

                    $fallback = self::defaultTemplate($audience, $type);
                    $current['templates'][$audience][$type] = [
                        'subject' => trim((string) ($incoming['subject'] ?? $fallback['subject'])) ?: $fallback['subject'],
                        'body' => trim((string) ($incoming['body'] ?? $fallback['body'])) ?: $fallback['body'],
                        'action_label' => trim((string) ($incoming['action_label'] ?? $fallback['action_label'])) ?: $fallback['action_label'],
                    ];
                }
            }
        }

        if (isset($values['smtp']) && is_array($values['smtp'])) {
            $smtp = $values['smtp'];
            $current['smtp']['mailer'] = (string) ($smtp['mailer'] ?? $current['smtp']['mailer']);
            $current['smtp']['host'] = (string) ($smtp['host'] ?? $current['smtp']['host']);
            $current['smtp']['port'] = (int) ($smtp['port'] ?? $current['smtp']['port']);
            $current['smtp']['username'] = (string) ($smtp['username'] ?? $current['smtp']['username']);
            $current['smtp']['encryption'] = (string) ($smtp['encryption'] ?? $current['smtp']['encryption']);
            $current['smtp']['from_address'] = (string) ($smtp['from_address'] ?? $current['smtp']['from_address']);
            $current['smtp']['from_name'] = (string) ($smtp['from_name'] ?? $current['smtp']['from_name']);

            if (array_key_exists('password', $smtp) && trim((string) $smtp['password']) !== '') {
                $current['smtp']['password'] = (string) $smtp['password'];
            }
        }

        File::ensureDirectoryExists(dirname($this->path()));
        File::put(
            $this->path(),
            json_encode($current, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        );
    }

    /**
     * Migrate flat v1 `types` map into audience-scoped toggles.
     *
     * @param  array<string, mixed>  $stored
     * @return array<string, array<string, bool>>
     */
    protected function migrateLegacyTypes(array $stored): array
    {
        $flat = $stored['types'] ?? null;
        if (! is_array($flat) || $flat === []) {
            return [];
        }

        $firstKey = array_key_first($flat);
        if (in_array($firstKey, self::audiences(), true)) {
            return [];
        }

        $mapped = [
            self::AUDIENCE_BOARD_MEMBER => [],
            self::AUDIENCE_MUNICIPAL => [],
            self::AUDIENCE_STAFF => [],
        ];

        foreach (self::typesByAudience()[self::AUDIENCE_BOARD_MEMBER] as $type) {
            if (array_key_exists($type, $flat)) {
                $mapped[self::AUDIENCE_BOARD_MEMBER][$type] = (bool) $flat[$type];
            }
        }

        foreach (self::typesByAudience()[self::AUDIENCE_MUNICIPAL] as $type) {
            if ($type === self::TYPE_RESOLUTION_PUBLISHED || $type === self::TYPE_ORDINANCE_PUBLISHED) {
                if (array_key_exists(\App\Models\UserNotification::TYPE_AGENDA_PUBLISHED, $flat)) {
                    $mapped[self::AUDIENCE_MUNICIPAL][$type] = (bool) $flat[\App\Models\UserNotification::TYPE_AGENDA_PUBLISHED];
                }

                continue;
            }

            if (array_key_exists($type, $flat)) {
                $mapped[self::AUDIENCE_MUNICIPAL][$type] = (bool) $flat[$type];
            }
        }

        if (array_key_exists(self::TYPE_COMMITTEE_REPORT_SUBMITTED, $flat)) {
            $mapped[self::AUDIENCE_STAFF][self::TYPE_COMMITTEE_REPORT_SUBMITTED] = (bool) $flat[self::TYPE_COMMITTEE_REPORT_SUBMITTED];
        }

        return $mapped;
    }

    /**
     * @return array<string, mixed>
     */
    protected function readFile(): array
    {
        $path = $this->path();

        if (! is_file($path)) {
            return [];
        }

        $data = json_decode((string) file_get_contents($path), true);

        return is_array($data) ? $data : [];
    }
}
