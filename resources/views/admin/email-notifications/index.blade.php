@extends('layouts.app')

@section('title', 'Email Notifications — '.config('app.name'))

@section('content')
@php
    $validTabs = array_merge($audiences, ['smtp']);
    $tab = in_array($activeTab, $validTabs, true) ? $activeTab : $audiences[0];
@endphp
<div class="max-w-4xl" id="email-notification-settings" data-active-tab="{{ $tab }}">
    <div class="splis-page-header">
        <div>
            <h1 class="splis-page-title">Email Notifications</h1>
            <p class="splis-page-subtitle">Configure important alerts, message templates, and SMTP delivery by user type.</p>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.email-notifications.update') }}" class="space-y-6">
        @csrf
        @method('PUT')
        <input type="hidden" name="active_tab" id="email-settings-active-tab" value="{{ $tab }}">

        <div class="splis-card p-6">
            <label class="flex items-start gap-3">
                <input
                    type="checkbox"
                    name="enabled"
                    value="1"
                    class="mt-1"
                    @checked(old('enabled', $settings['enabled']))
                >
                <span>
                    <span class="block text-sm font-medium text-slate-900 dark:text-slate-100">Enable email notifications</span>
                    <span class="mt-1 block text-sm text-slate-500">Master switch for all email notification types below.</span>
                </span>
            </label>
        </div>

        <div class="splis-card p-6">
            <div class="mb-4">
                <p class="text-base font-semibold text-slate-900 dark:text-slate-100">Email branding</p>
                <p class="mt-1 text-sm text-slate-500">Shared header and closing used on every notification email.</p>
            </div>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <label class="splis-label" for="branding_header_eyebrow">Header eyebrow</label>
                    <input
                        type="text"
                        id="branding_header_eyebrow"
                        name="branding[header_eyebrow]"
                        class="splis-input mt-1"
                        value="{{ old('branding.header_eyebrow', $settings['branding']['header_eyebrow']) }}"
                        data-email-branding="header_eyebrow"
                    >
                </div>
                <div>
                    <label class="splis-label" for="branding_header_title">Header title</label>
                    <input
                        type="text"
                        id="branding_header_title"
                        name="branding[header_title]"
                        class="splis-input mt-1"
                        value="{{ old('branding.header_title', $settings['branding']['header_title']) }}"
                        data-email-branding="header_title"
                    >
                </div>
                <div>
                    <label class="splis-label" for="branding_sign_off">Sign-off</label>
                    <input
                        type="text"
                        id="branding_sign_off"
                        name="branding[sign_off]"
                        class="splis-input mt-1"
                        value="{{ old('branding.sign_off', $settings['branding']['sign_off']) }}"
                        data-email-branding="sign_off"
                    >
                </div>
                <div>
                    <label class="splis-label" for="branding_signature">Signature</label>
                    <input
                        type="text"
                        id="branding_signature"
                        name="branding[signature]"
                        class="splis-input mt-1"
                        value="{{ old('branding.signature', $settings['branding']['signature']) }}"
                        data-email-branding="signature"
                    >
                </div>
            </div>
            <p class="mt-3 text-xs text-slate-500">The email header uses the same dashboard hero background image as the main portal.</p>
        </div>

        <div class="flex flex-wrap gap-2 border-b border-slate-200 pb-3 dark:border-slate-700" role="tablist" aria-label="Email notification settings">
            @foreach ($audiences as $audience)
                <button
                    type="button"
                    role="tab"
                    id="tab-{{ $audience }}"
                    data-email-tab="{{ $audience }}"
                    aria-controls="panel-{{ $audience }}"
                    aria-selected="{{ $tab === $audience ? 'true' : 'false' }}"
                    @class([
                        'splis-btn-secondary text-sm',
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
                    'splis-btn-secondary text-sm',
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
                @class(['space-y-4', 'hidden' => $tab !== $audience])
            >
                <div class="splis-card overflow-hidden p-0">
                    <div class="border-b border-slate-200 px-6 py-4 dark:border-slate-700">
                        <p class="text-base font-semibold text-slate-900 dark:text-slate-100">{{ $audienceLabels[$audience] }}</p>
                        <p class="mt-1 text-sm text-slate-500">Click a type to edit its template. Body supports HTML and images.</p>
                    </div>

                    <div class="divide-y divide-slate-200 dark:divide-slate-700">
                        @foreach ($typesByAudience[$audience] as $type)
                            @php
                                $template = old("templates.$audience.$type", $settings['templates'][$audience][$type] ?? []);
                                $enabled = (bool) old("types.$audience.$type", $settings['types'][$audience][$type] ?? false);
                            @endphp
                            <details class="splis-accordion group">
                                <summary class="splis-accordion-summary">
                                    <div class="splis-accordion-summary-top">
                                        <div class="flex min-w-0 flex-1 items-center gap-3">
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

                                <div class="splis-accordion-body">
                                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                        <div class="sm:col-span-2">
                                            <label class="splis-label" for="tpl-{{ $audience }}-{{ $type }}-subject">Subject</label>
                                            <input
                                                type="text"
                                                id="tpl-{{ $audience }}-{{ $type }}-subject"
                                                name="templates[{{ $audience }}][{{ $type }}][subject]"
                                                class="splis-input mt-1"
                                                value="{{ $template['subject'] ?? '' }}"
                                            >
                                        </div>
                                        <div class="sm:col-span-2" data-email-rich-wrap>
                                            <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                                                <label class="splis-label !mb-0" for="tpl-{{ $audience }}-{{ $type }}-body-editor">Body</label>
                                                <div class="flex flex-wrap items-center gap-2">
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
                                                        class="splis-btn-secondary text-sm"
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
                                            <p class="mt-2 text-xs text-slate-500">Placeholders like <code class="text-xs">@{{title}}</code> stay as text until send. Use Preview to check the result.</p>
                                        </div>
                                        <div class="sm:col-span-2">
                                            <label class="splis-label" for="tpl-{{ $audience }}-{{ $type }}-action">Button label</label>
                                            <input
                                                type="text"
                                                id="tpl-{{ $audience }}-{{ $type }}-action"
                                                name="templates[{{ $audience }}][{{ $type }}][action_label]"
                                                class="splis-input mt-1"
                                                value="{{ $template['action_label'] ?? '' }}"
                                            >
                                        </div>
                                    </div>
                                </div>
                            </details>
                        @endforeach
                    </div>

                    <div class="border-t border-slate-200 px-6 py-4 text-sm leading-relaxed text-slate-500 dark:border-slate-700">
                        <p class="font-medium text-slate-600 dark:text-slate-400">Tips</p>
                        <p class="mt-1">Use the toolbar for formatting. You can also paste HTML. Images need a public <code class="text-xs">https://</code> URL.</p>
                        <p class="mt-2">Placeholders: {{ implode(', ', $placeholders) }}</p>
                    </div>
                </div>
            </div>
        @endforeach

        <div
            id="panel-smtp"
            role="tabpanel"
            aria-labelledby="tab-smtp"
            data-email-panel="smtp"
            @class(['space-y-4', 'hidden' => $tab !== 'smtp'])
        >
            <div class="splis-card p-6">
                <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-base font-semibold text-slate-900 dark:text-slate-100">SMTP connection details</p>
                        <p class="mt-1 text-sm text-slate-500">Standard SMTP auth for your mail server (Postal, Microsoft 365, Gmail, etc.). These override <code class="text-xs">.env</code> when sending notification emails. Leave password blank to keep the current value.</p>
                    </div>
                    <button type="button" class="splis-btn-secondary text-sm" data-smtp-preset="gmail">
                        Gmail preset
                    </button>
                </div>

                <div class="mb-4 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm leading-relaxed text-slate-600 dark:border-slate-700 dark:bg-slate-900/40 dark:text-slate-400">
                    <p class="font-medium text-slate-700 dark:text-slate-300">How to connect</p>
                    <ol class="mt-2 list-decimal space-y-1 pl-5">
                        <li>Set Mailer to <strong>SMTP</strong>.</li>
                        <li>Enter your server host (e.g. <code class="text-xs">mx.bataan.gov.ph</code>), port, and encryption (TLS on 587 is common; SSL on 465).</li>
                        <li>Use the mailbox username and password from your mail server (Postal credentials, not a Gmail app password unless you chose Gmail).</li>
                        <li>Set From address to a mailbox/domain allowed by that server, save, then send a test.</li>
                    </ol>
                    <p class="mt-2 text-xs text-slate-500">Optional: <strong>Gmail preset</strong> fills smtp.gmail.com / 587 / TLS — then use a Google App Password as the password.</p>
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <label class="splis-label" for="smtp_mailer">Mailer</label>
                        <select name="smtp[mailer]" id="smtp_mailer" class="splis-input mt-1">
                            @foreach (['smtp' => 'SMTP', 'log' => 'Log (dev)', 'sendmail' => 'Sendmail'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('smtp.mailer', $settings['smtp']['mailer']) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="splis-label" for="smtp_encryption">Security</label>
                        <select name="smtp[encryption]" id="smtp_encryption" class="splis-input mt-1">
                            <option value="" @selected(old('smtp.encryption', $settings['smtp']['encryption']) === '')>None</option>
                            <option value="tls" @selected(old('smtp.encryption', $settings['smtp']['encryption']) === 'tls')>TLS (usually port 587)</option>
                            <option value="ssl" @selected(old('smtp.encryption', $settings['smtp']['encryption']) === 'ssl')>SSL (usually port 465)</option>
                        </select>
                    </div>
                    <div>
                        <label class="splis-label" for="smtp_host">SMTP server</label>
                        <input type="text" name="smtp[host]" id="smtp_host" class="splis-input mt-1" value="{{ old('smtp.host', $settings['smtp']['host']) }}" placeholder="mx.bataan.gov.ph" autocomplete="off">
                    </div>
                    <div>
                        <label class="splis-label" for="smtp_port">Port</label>
                        <input type="number" name="smtp[port]" id="smtp_port" class="splis-input mt-1" value="{{ old('smtp.port', $settings['smtp']['port']) }}" min="1" max="65535">
                        <div class="mt-2 flex flex-wrap gap-1.5">
                            @foreach ([25, 2525, 465, 587] as $portPreset)
                                <button
                                    type="button"
                                    class="rounded-full border border-slate-200 bg-white px-2.5 py-0.5 text-xs font-medium text-slate-600 transition hover:border-brand-300 hover:text-brand-800 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-300"
                                    data-smtp-port="{{ $portPreset }}"
                                >{{ $portPreset }}</button>
                            @endforeach
                        </div>
                    </div>
                    <div>
                        <label class="splis-label" for="smtp_username">Username</label>
                        <input type="text" name="smtp[username]" id="smtp_username" class="splis-input mt-1" value="{{ old('smtp.username', $settings['smtp']['username']) }}" placeholder="admin@mx.bataan.gov.ph" autocomplete="off">
                    </div>
                    <div>
                        <label class="splis-label" for="smtp_password">Password</label>
                        <div class="relative mt-1">
                            <input type="password" name="smtp[password]" id="smtp_password" class="splis-input pr-10" value="" placeholder="{{ filled($settings['smtp']['password']) ? '•••••••• (leave blank to keep)' : 'SMTP password' }}" autocomplete="new-password">
                            <button
                                type="button"
                                class="absolute inset-y-0 right-0 flex items-center px-3 text-slate-400 hover:text-slate-600"
                                data-smtp-password-toggle
                                aria-label="Show password"
                            >
                                <svg class="h-4 w-4" data-smtp-password-icon="show" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                <svg class="hidden h-4 w-4" data-smtp-password-icon="hide" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                                </svg>
                            </button>
                        </div>
                    </div>
                    <div>
                        <label class="splis-label" for="smtp_from_address">From email address</label>
                        <input type="email" name="smtp[from_address]" id="smtp_from_address" class="splis-input mt-1" value="{{ old('smtp.from_address', $settings['smtp']['from_address']) }}" placeholder="noreply@bataan.gov.ph">
                    </div>
                    <div>
                        <label class="splis-label" for="smtp_from_name">From name</label>
                        <input type="text" name="smtp[from_name]" id="smtp_from_name" class="splis-input mt-1" value="{{ old('smtp.from_name', $settings['smtp']['from_name']) }}" placeholder="SPLIS">
                    </div>
                </div>
            </div>
        </div>

        <div class="splis-form-actions">
            <button type="submit" class="splis-btn-primary">Save settings</button>
        </div>
    </form>

    <form method="POST" action="{{ route('admin.email-notifications.test') }}" class="mt-6 splis-card p-6">
        @csrf
        <input type="hidden" name="active_tab" value="smtp" data-email-test-tab>
        <p class="text-base font-semibold text-slate-900 dark:text-slate-100">Send test email</p>
        <p class="mt-1 mb-4 text-sm text-slate-500">Uses the currently saved SMTP settings (save first if you just changed them).</p>
        <div class="flex flex-wrap items-end gap-3">
            <div class="min-w-[16rem] flex-1">
                <label class="splis-label" for="test_email">Recipient</label>
                <input type="email" name="test_email" id="test_email" class="splis-input mt-1" value="{{ old('test_email', auth()->user()->email) }}" required>
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
                            <div class="min-w-0">
                                <p class="splis-email-brand-eyebrow" data-email-preview-eyebrow>{{ $settings['branding']['header_eyebrow'] }}</p>
                                <p class="splis-email-brand-title" data-email-preview-header-title>{{ $settings['branding']['header_title'] }}</p>
                            </div>
                        </div>

                        <div class="splis-email-message-body">
                            <h2 id="email-template-preview-heading" class="splis-email-message-heading"></h2>
                            <div id="email-template-preview-body" class="splis-email-preview-body"></div>

                            <div id="email-template-preview-action-wrap" class="hidden">
                                <a id="email-template-preview-action" href="#" class="splis-email-preview-button" onclick="return false;">View details</a>
                            </div>

                            <p class="splis-email-message-thanks">
                                <span data-email-preview-sign-off>{{ $settings['branding']['sign_off'] }}</span><br>
                                <span data-email-preview-signature>{{ $settings['branding']['signature'] }}</span>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
