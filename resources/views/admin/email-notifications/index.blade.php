@extends('layouts.app')

@section('title', 'Email Notifications — '.config('app.name'))

@section('content')
@php
    $validTabs = array_merge($audiences, ['smtp']);
    $tab = in_array($activeTab, $validTabs, true) ? $activeTab : $audiences[0];
@endphp
<div class="max-w-4xl" id="email-notification-settings" data-active-tab="{{ $tab }}">
    <div class="splis-page-header !mb-4">
        <div>
            <h1 class="splis-page-title">Email Notifications</h1>
            <p class="splis-page-subtitle">Configure important alerts, message templates, and SMTP delivery by user type.</p>
        </div>
    </div>

    @if (session('status'))
        <div class="mb-3 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800 dark:border-emerald-900/50 dark:bg-emerald-950/40 dark:text-emerald-200">
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700 dark:border-red-900/50 dark:bg-red-950/40 dark:text-red-200">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('admin.email-notifications.update') }}" class="space-y-4">
        @csrf
        @method('PUT')
        <input type="hidden" name="active_tab" id="email-settings-active-tab" value="{{ $tab }}">

        <div class="splis-card px-4 py-3">
            <label class="flex items-start gap-2.5">
                <input
                    type="checkbox"
                    name="enabled"
                    value="1"
                    class="mt-0.5"
                    @checked(old('enabled', $settings['enabled']))
                >
                <span>
                    <span class="block text-sm font-medium text-slate-900 dark:text-slate-100">Enable email notifications</span>
                    <span class="block text-xs text-slate-500">Master switch for all email notification types below.</span>
                </span>
            </label>
        </div>

        <div class="flex flex-wrap gap-1.5 border-b border-slate-200 pb-2 dark:border-slate-700" role="tablist" aria-label="Email notification settings">
            @foreach ($audiences as $audience)
                <button
                    type="button"
                    role="tab"
                    id="tab-{{ $audience }}"
                    data-email-tab="{{ $audience }}"
                    aria-controls="panel-{{ $audience }}"
                    aria-selected="{{ $tab === $audience ? 'true' : 'false' }}"
                    @class([
                        'splis-btn-secondary !px-2.5 !py-1 text-sm',
                        'ring-2 ring-brand-200' => $tab === $audience,
                    ])
                >{{ $audienceLabels[$audience] }}</button>
            @endforeach
            <button
                type="button"
                role="tab"
                id="tab-smtp"
                data-email-tab="smtp"
                aria-controls="panel-smtp"
                aria-selected="{{ $tab === 'smtp' ? 'true' : 'false' }}"
                @class([
                    'splis-btn-secondary !px-2.5 !py-1 text-sm',
                    'ring-2 ring-brand-200' => $tab === 'smtp',
                ])
            >SMTP</button>
        </div>

        @foreach ($audiences as $audience)
            <div
                id="panel-{{ $audience }}"
                role="tabpanel"
                aria-labelledby="tab-{{ $audience }}"
                data-email-panel="{{ $audience }}"
                @class(['space-y-2', 'hidden' => $tab !== $audience])
            >
                <div class="splis-card overflow-hidden p-0">
                    <div class="border-b border-slate-200 px-3 py-2 dark:border-slate-700">
                        <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $audienceLabels[$audience] }}</p>
                        <p class="text-xs text-slate-500">Click a type to edit its template. Body supports HTML and images.</p>
                    </div>

                    <div class="divide-y divide-slate-200 dark:divide-slate-700">
                        @foreach ($typesByAudience[$audience] as $type)
                            @php
                                $template = old("templates.$audience.$type", $settings['templates'][$audience][$type] ?? []);
                                $enabled = (bool) old("types.$audience.$type", $settings['types'][$audience][$type] ?? false);
                            @endphp
                            <details class="splis-accordion group">
                                <summary class="splis-accordion-summary !gap-0 !px-3 !py-2">
                                    <div class="splis-accordion-summary-top !items-center">
                                        <div class="flex min-w-0 flex-1 items-center gap-2">
                                            <span class="inline-flex shrink-0" onclick="event.stopPropagation();">
                                                <input
                                                    type="checkbox"
                                                    name="types[{{ $audience }}][{{ $type }}]"
                                                    value="1"
                                                    class="mt-0"
                                                    @checked($enabled)
                                                    aria-label="Enable {{ $typeLabels[$type] ?? $type }}"
                                                    onclick="event.stopPropagation();"
                                                >
                                            </span>
                                            <span class="truncate text-sm font-medium text-slate-900 dark:text-slate-100">{{ $typeLabels[$type] ?? $type }}</span>
                                            @unless ($enabled)
                                                <span class="hidden shrink-0 rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-medium uppercase tracking-wide text-slate-500 sm:inline dark:bg-slate-800">Off</span>
                                            @endunless
                                        </div>
                                        <svg class="splis-accordion-chevron" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </div>
                                </summary>

                                <div class="splis-accordion-body !space-y-2 !px-3 !py-2.5">
                                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                        <div class="sm:col-span-2">
                                            <label class="splis-label !mb-0.5 !text-xs" for="tpl-{{ $audience }}-{{ $type }}-subject">Subject</label>
                                            <input
                                                type="text"
                                                id="tpl-{{ $audience }}-{{ $type }}-subject"
                                                name="templates[{{ $audience }}][{{ $type }}][subject]"
                                                class="splis-input !py-1.5 text-sm"
                                                value="{{ $template['subject'] ?? '' }}"
                                            >
                                        </div>
                                        <div class="sm:col-span-2" data-email-rich-wrap>
                                            <div class="mb-1 flex flex-wrap items-center justify-between gap-2">
                                                <label class="splis-label !mb-0 !text-xs" for="tpl-{{ $audience }}-{{ $type }}-body-editor">Body</label>
                                                <div class="flex flex-wrap items-center gap-1">
                                                    <div class="splis-email-rich-toolbar" role="toolbar" aria-label="Body formatting">
                                                        <button type="button" class="splis-email-rich-btn" data-email-rich-command="bold" title="Bold" aria-label="Bold"><strong>B</strong></button>
                                                        <button type="button" class="splis-email-rich-btn" data-email-rich-command="italic" title="Italic" aria-label="Italic"><em>I</em></button>
                                                        <button type="button" class="splis-email-rich-btn" data-email-rich-command="underline" title="Underline" aria-label="Underline"><span class="underline">U</span></button>
                                                        <button type="button" class="splis-email-rich-btn" data-email-rich-command="insertUnorderedList" title="Bullet list" aria-label="Bullet list">• List</button>
                                                        <button type="button" class="splis-email-rich-btn" data-email-rich-command="insertOrderedList" title="Numbered list" aria-label="Numbered list">1. List</button>
                                                        <button type="button" class="splis-email-rich-btn" data-email-rich-command="createLink" title="Insert link" aria-label="Insert link">Link</button>
                                                        <button type="button" class="splis-email-rich-btn" data-email-rich-command="removeFormat" title="Clear formatting" aria-label="Clear formatting">Clear</button>
                                                    </div>
                                                    <button
                                                        type="button"
                                                        class="splis-btn-secondary !px-2 !py-1 text-xs"
                                                        data-email-preview
                                                        data-preview-subject="#tpl-{{ $audience }}-{{ $type }}-subject"
                                                        data-preview-body="#tpl-{{ $audience }}-{{ $type }}-body"
                                                        data-preview-action="#tpl-{{ $audience }}-{{ $type }}-action"
                                                        data-preview-title="{{ $typeLabels[$type] ?? $type }}"
                                                    >Preview</button>
                                                </div>
                                            </div>
                                            <div class="splis-email-rich-shell">
                                                <div
                                                    id="tpl-{{ $audience }}-{{ $type }}-body-editor"
                                                    class="splis-email-rich-editor"
                                                    contenteditable="true"
                                                    role="textbox"
                                                    aria-multiline="true"
                                                    data-email-rich-editor
                                                ></div>
                                            </div>
                                            <textarea
                                                id="tpl-{{ $audience }}-{{ $type }}-body"
                                                name="templates[{{ $audience }}][{{ $type }}][body]"
                                                class="hidden"
                                                rows="6"
                                                data-email-rich-input
                                                spellcheck="false"
                                            >{{ $template['body'] ?? '' }}</textarea>
                                            <p class="mt-1 text-[11px] text-slate-500">Placeholders like <code class="text-[10px]">@{{title}}</code> stay as text until send. Use Preview to check the result.</p>
                                        </div>
                                        <div class="sm:col-span-2">
                                            <label class="splis-label !mb-0.5 !text-xs" for="tpl-{{ $audience }}-{{ $type }}-action">Button label</label>
                                            <input
                                                type="text"
                                                id="tpl-{{ $audience }}-{{ $type }}-action"
                                                name="templates[{{ $audience }}][{{ $type }}][action_label]"
                                                class="splis-input !py-1.5 text-sm"
                                                value="{{ $template['action_label'] ?? '' }}"
                                            >
                                        </div>
                                    </div>
                                </div>
                            </details>
                        @endforeach
                    </div>

                    <div class="border-t border-slate-200 px-3 py-2 text-[11px] leading-relaxed text-slate-500 dark:border-slate-700">
                        <p class="font-medium text-slate-600 dark:text-slate-400">Tips</p>
                        <p>Use the toolbar for formatting. You can also paste HTML. Images need a public <code class="text-[10px]">https://</code> URL.</p>
                        <p class="mt-1">Placeholders: {{ implode(', ', $placeholders) }}</p>
                    </div>
                </div>
            </div>
        @endforeach

        <div
            id="panel-smtp"
            role="tabpanel"
            aria-labelledby="tab-smtp"
            data-email-panel="smtp"
            @class(['space-y-3', 'hidden' => $tab !== 'smtp'])
        >
            <div class="splis-card px-4 py-3">
                <div class="mb-3 flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">SMTP settings</p>
                        <p class="text-xs text-slate-500">These override <code class="text-[10px]">.env</code> mail settings when sending notification emails. Leave password blank to keep the current value.</p>
                    </div>
                    <button type="button" class="splis-btn-secondary !px-2.5 !py-1 text-xs" data-smtp-preset="gmail">
                        Use Gmail preset
                    </button>
                </div>

                <div class="mb-3 rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-[11px] leading-relaxed text-slate-600 dark:border-slate-700 dark:bg-slate-900/40 dark:text-slate-400">
                    <p class="font-medium text-slate-700 dark:text-slate-300">Gmail App Password</p>
                    <ol class="mt-1 list-decimal space-y-0.5 pl-4">
                        <li>Enable 2-Step Verification on the Google account.</li>
                        <li>Create an App Password (Google Account → Security → App passwords).</li>
                        <li>Click <strong>Use Gmail preset</strong>, then enter your full Gmail address as username and the 16-character app password.</li>
                        <li>Set From address to the same Gmail (or a verified send-as address), save, then send a test.</li>
                    </ol>
                </div>

                <div class="grid grid-cols-1 gap-2.5 md:grid-cols-2">
                    <div>
                        <label class="splis-label !mb-0.5 !text-xs" for="smtp_mailer">Mailer</label>
                        <select name="smtp[mailer]" id="smtp_mailer" class="splis-input !py-1.5 text-sm">
                            @foreach (['smtp' => 'SMTP', 'log' => 'Log (dev)', 'sendmail' => 'Sendmail'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('smtp.mailer', $settings['smtp']['mailer']) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="splis-label !mb-0.5 !text-xs" for="smtp_encryption">Encryption</label>
                        <select name="smtp[encryption]" id="smtp_encryption" class="splis-input !py-1.5 text-sm">
                            <option value="" @selected(old('smtp.encryption', $settings['smtp']['encryption']) === '')>None</option>
                            <option value="tls" @selected(old('smtp.encryption', $settings['smtp']['encryption']) === 'tls')>TLS (port 587)</option>
                            <option value="ssl" @selected(old('smtp.encryption', $settings['smtp']['encryption']) === 'ssl')>SSL (port 465)</option>
                        </select>
                    </div>
                    <div>
                        <label class="splis-label !mb-0.5 !text-xs" for="smtp_host">Host</label>
                        <input type="text" name="smtp[host]" id="smtp_host" class="splis-input !py-1.5 text-sm" value="{{ old('smtp.host', $settings['smtp']['host']) }}" placeholder="smtp.gmail.com">
                    </div>
                    <div>
                        <label class="splis-label !mb-0.5 !text-xs" for="smtp_port">Port</label>
                        <input type="number" name="smtp[port]" id="smtp_port" class="splis-input !py-1.5 text-sm" value="{{ old('smtp.port', $settings['smtp']['port']) }}" min="1" max="65535">
                    </div>
                    <div>
                        <label class="splis-label !mb-0.5 !text-xs" for="smtp_username">Username</label>
                        <input type="text" name="smtp[username]" id="smtp_username" class="splis-input !py-1.5 text-sm" value="{{ old('smtp.username', $settings['smtp']['username']) }}" placeholder="you@gmail.com" autocomplete="off">
                    </div>
                    <div>
                        <label class="splis-label !mb-0.5 !text-xs" for="smtp_password">App password</label>
                        <input type="password" name="smtp[password]" id="smtp_password" class="splis-input !py-1.5 text-sm" value="" placeholder="{{ filled($settings['smtp']['password']) ? '••••••••' : '16-character Gmail app password' }}" autocomplete="new-password">
                    </div>
                    <div>
                        <label class="splis-label !mb-0.5 !text-xs" for="smtp_from_address">From address</label>
                        <input type="email" name="smtp[from_address]" id="smtp_from_address" class="splis-input !py-1.5 text-sm" value="{{ old('smtp.from_address', $settings['smtp']['from_address']) }}" placeholder="you@gmail.com">
                    </div>
                    <div>
                        <label class="splis-label !mb-0.5 !text-xs" for="smtp_from_name">From name</label>
                        <input type="text" name="smtp[from_name]" id="smtp_from_name" class="splis-input !py-1.5 text-sm" value="{{ old('smtp.from_name', $settings['smtp']['from_name']) }}" placeholder="SPLIS">
                    </div>
                </div>
            </div>
        </div>

        <div class="flex flex-wrap gap-2">
            <button type="submit" class="splis-btn-primary">Save settings</button>
        </div>
    </form>

    <form method="POST" action="{{ route('admin.email-notifications.test') }}" class="mt-4 splis-card px-4 py-3">
        @csrf
        <input type="hidden" name="active_tab" value="smtp" data-email-test-tab>
        <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">Send test email</p>
        <p class="mb-2 text-xs text-slate-500">Uses the currently saved SMTP settings (save first if you just changed them).</p>
        <div class="flex flex-wrap items-end gap-2">
            <div class="min-w-[16rem] flex-1">
                <label class="splis-label !mb-0.5 !text-xs" for="test_email">Recipient</label>
                <input type="email" name="test_email" id="test_email" class="splis-input !py-1.5 text-sm" value="{{ old('test_email', auth()->user()->email) }}" required>
            </div>
            <button type="submit" class="splis-btn-secondary">Send test</button>
        </div>
    </form>
</div>

<div id="email-template-preview-modal" class="splis-modal" hidden>
    <div class="splis-modal-backdrop" data-email-preview-close tabindex="-1" aria-hidden="true"></div>
    <div class="splis-modal-panel !max-w-2xl" role="dialog" aria-modal="true" aria-labelledby="email-template-preview-title">
        <div class="splis-modal-header">
            <h3 id="email-template-preview-title" class="splis-modal-title">Email preview</h3>
            <button type="button" class="splis-modal-close" data-email-preview-close aria-label="Close">×</button>
        </div>
        <div class="splis-modal-body space-y-3">
            <p class="text-xs text-slate-500">Shows how the message will look in an inbox. Sample placeholder values are filled in for preview only.</p>

            <div class="splis-email-client-chrome">
                <div class="splis-email-client-meta">
                    <div class="splis-email-client-row">
                        <span class="splis-email-client-label">From</span>
                        <span class="splis-email-client-value">{{ $settings['smtp']['from_name'] ?: config('app.name') }} &lt;{{ $settings['smtp']['from_address'] ?: 'noreply@'.parse_url((string) config('app.url'), PHP_URL_HOST) }}&gt;</span>
                    </div>
                    <div class="splis-email-client-row">
                        <span class="splis-email-client-label">Subject</span>
                        <span id="email-template-preview-subject" class="splis-email-client-value font-semibold text-slate-900"></span>
                    </div>
                </div>

                <div class="splis-email-client-canvas">
                    <div class="splis-email-message-card">
                        <div class="splis-email-brand-header">
                            <img
                                src="{{ asset('images/bataan-seal.png') }}"
                                width="48"
                                height="48"
                                alt="Province of Bataan official seal"
                                class="splis-email-brand-seal"
                            >
                            <div>
                                <p class="splis-email-brand-eyebrow">Legislative Information System</p>
                                <p class="splis-email-brand-title">Sangguniang Panlalawigan</p>
                            </div>
                        </div>

                        <h2 id="email-template-preview-heading" class="splis-email-message-heading"></h2>
                        <div id="email-template-preview-body" class="splis-email-preview-body"></div>

                        <div id="email-template-preview-action-wrap" class="hidden">
                            <a id="email-template-preview-action" href="#" class="splis-email-preview-button" onclick="return false;">View details</a>
                        </div>

                        <p class="splis-email-message-thanks">
                            Thanks,<br>{{ config('app.name') }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
