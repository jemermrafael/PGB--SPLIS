<?php

namespace App\Http\Controllers;

use App\Models\LegislativeSession;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BoardMemberSessionCalendarController extends Controller
{
    public function __invoke(Request $request, LegislativeSession $session): StreamedResponse|Response
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->isBoardMember(), 403);
        abort_unless($session->isVisibleToBoardMembers(), 403);

        $date = $session->session_date?->format('Ymd') ?? now()->format('Ymd');
        $timeRaw = (string) ($session->session_time ?? '09:00:00');
        $time = preg_replace('/\D+/', '', substr($timeRaw, 0, 8)) ?: '090000';
        if (strlen($time) === 4) {
            $time .= '00';
        }
        $dtStart = $date.'T'.$time;
        $summary = $this->escapeIcs($session->displayTitle());
        $location = $this->escapeIcs((string) ($session->venue ?? ''));
        $description = $this->escapeIcs('Sangguniang Panlalawigan — Order of Business session');
        $uid = 'session-'.$session->id.'@'.parse_url((string) config('app.url'), PHP_URL_HOST);

        $ics = implode("\r\n", [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//SPLIS//Board Member//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:'.$uid,
            'DTSTAMP:'.now()->utc()->format('Ymd\THis\Z'),
            'DTSTART:'.$dtStart,
            'SUMMARY:'.$summary,
            'DESCRIPTION:'.$description,
            'LOCATION:'.$location,
            'END:VEVENT',
            'END:VCALENDAR',
            '',
        ]);

        $filename = 'session-'.$session->id.'.ics';

        return response($ics, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    protected function escapeIcs(string $value): string
    {
        return str_replace(
            ["\\", ';', ',', "\n", "\r"],
            ['\\\\', '\\;', '\\,', '\\n', ''],
            $value,
        );
    }
}
