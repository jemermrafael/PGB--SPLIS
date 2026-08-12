<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\EmailNotificationService;
use App\Services\EmailNotificationSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class EmailNotificationSettingsController extends Controller
{
    public function index(EmailNotificationSettings $settings): View
    {
        $this->authorizeManage();

        return view('admin.email-notifications.index', [
            'settings' => $settings->get(),
            'audiences' => EmailNotificationSettings::audiences(),
            'audienceLabels' => EmailNotificationSettings::audienceLabels(),
            'typesByAudience' => EmailNotificationSettings::typesByAudience(),
            'typeLabels' => EmailNotificationSettings::typeLabels(),
            'placeholders' => EmailNotificationSettings::templatePlaceholders(),
            'activeTab' => request()->string('tab')->toString() ?: EmailNotificationSettings::AUDIENCE_BOARD_MEMBER,
        ]);
    }

    public function update(Request $request, EmailNotificationSettings $settings): RedirectResponse
    {
        $this->authorizeManage();

        $data = $request->validate([
            'enabled' => ['nullable', 'boolean'],
            'types' => ['nullable', 'array'],
            'templates' => ['nullable', 'array'],
            'templates.*.*.subject' => ['nullable', 'string', 'max:255'],
            'templates.*.*.body' => ['nullable', 'string', 'max:30000'],
            'templates.*.*.action_label' => ['nullable', 'string', 'max:100'],
            'branding' => ['nullable', 'array'],
            'branding.sign_off' => ['nullable', 'string', 'max:100'],
            'branding.signature' => ['nullable', 'string', 'max:255'],
            'branding.header_eyebrow' => ['nullable', 'string', 'max:255'],
            'branding.header_title' => ['nullable', 'string', 'max:255'],
            'smtp' => ['nullable', 'array'],
            'smtp.mailer' => ['required', 'string', 'in:smtp,log,sendmail'],
            'smtp.host' => ['nullable', 'string', 'max:255'],
            'smtp.port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'smtp.username' => ['nullable', 'string', 'max:255'],
            'smtp.password' => ['nullable', 'string', 'max:255'],
            'smtp.encryption' => ['nullable', 'string', 'in:tls,ssl,'],
            'smtp.from_address' => ['nullable', 'email', 'max:255'],
            'smtp.from_name' => ['nullable', 'string', 'max:255'],
            'active_tab' => ['nullable', 'string', 'max:50'],
        ]);

        $types = [];
        foreach (EmailNotificationSettings::typesByAudience() as $audience => $audienceTypes) {
            foreach ($audienceTypes as $type) {
                $types[$audience][$type] = $request->boolean("types.$audience.$type");
            }
        }

        $settings->update([
            'enabled' => $request->boolean('enabled'),
            'types' => $types,
            'templates' => $data['templates'] ?? [],
            'branding' => $data['branding'] ?? [],
            'smtp' => $data['smtp'] ?? [],
        ]);

        $tab = $data['active_tab'] ?? EmailNotificationSettings::AUDIENCE_BOARD_MEMBER;

        return redirect()
            ->route('admin.email-notifications.index', ['tab' => $tab])
            ->with('status', 'Email notification settings saved.');
    }

    public function sendTest(Request $request, EmailNotificationService $emails): RedirectResponse
    {
        $this->authorizeManage();

        $data = $request->validate([
            'test_email' => ['required', 'email', 'max:255'],
            'active_tab' => ['nullable', 'string', 'max:50'],
        ]);

        try {
            $emails->sendTest(
                $data['test_email'],
                'SPLIS test email',
                'This is a test message from SPLIS email notification settings. If you received this, mail delivery is working.',
                route('admin.email-notifications.index'),
            );
        } catch (Throwable) {
            return redirect()
                ->route('admin.email-notifications.index', ['tab' => $data['active_tab'] ?? 'smtp'])
                ->withErrors(['test_email' => 'Could not send test email. Check SMTP settings and try again.']);
        }

        return redirect()
            ->route('admin.email-notifications.index', ['tab' => $data['active_tab'] ?? 'smtp'])
            ->with('status', 'Test email sent to '.$data['test_email'].'.');
    }

    protected function authorizeManage(): void
    {
        abort_unless(auth()->user()?->canManageEmailNotifications(), 403);
    }
}
