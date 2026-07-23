<?php

namespace App\Http\Controllers;

use App\Models\BoardMember;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BoardMemberPhotoController extends Controller
{
    public function __invoke(Request $request, BoardMember $boardMember): StreamedResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        abort_unless($user !== null, 403);

        $canView = $user->canEncode()
            || $user->canAdmin()
            || ($user->isBoardMember() && (int) $user->board_member_id === (int) $boardMember->id);

        abort_unless($canView, 403);
        abort_unless(filled($boardMember->photo_path), 404);
        abort_unless(Storage::disk('local')->exists($boardMember->photo_path), 404);

        return Storage::disk('local')->response(
            $boardMember->photo_path,
            basename($boardMember->photo_path),
            ['Content-Type' => Storage::disk('local')->mimeType($boardMember->photo_path) ?: 'image/jpeg'],
        );
    }
}
