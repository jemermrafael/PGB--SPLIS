<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\PageBackgrounds;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PageBackgroundController extends Controller
{
    public function index(): View
    {
        $this->authorizePageSettings();

        return view('admin.pages.index', [
            'pageCatalog' => PageBackgrounds::catalog(),
            'backgrounds' => PageBackgrounds::allByPage(),
            'positions' => PageBackgrounds::positions(),
            'attachments' => PageBackgrounds::attachments(),
            'repeats' => PageBackgrounds::repeats(),
            'sizes' => PageBackgrounds::sizes(),
        ]);
    }

    public function update(Request $request, string $pageKey): RedirectResponse
    {
        $this->authorizePageSettings();
        abort_unless(PageBackgrounds::isValidPage($pageKey), 404);

        $validator = Validator::make($request->all(), [
            'background_type' => ['required', 'string', Rule::in(['classic', 'none'])],
            'color' => ['nullable', 'string', 'max:32'],
            'image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp,gif', 'max:5120'],
            'remove_image' => ['sometimes', 'boolean'],
            'position' => ['nullable', 'string', Rule::in(array_keys(PageBackgrounds::positions()))],
            'attachment' => ['nullable', 'string', Rule::in(array_keys(PageBackgrounds::attachments()))],
            'repeat' => ['nullable', 'string', Rule::in(array_keys(PageBackgrounds::repeats()))],
            'size' => ['nullable', 'string', Rule::in(array_keys(PageBackgrounds::sizes()))],
        ]);

        if ($validator->fails()) {
            return back()
                ->withErrors($validator)
                ->withInput()
                ->with('editing_page', $pageKey);
        }

        $validated = $validator->validated();

        PageBackgrounds::save(
            $pageKey,
            $validated,
            $request->file('image'),
            $request->boolean('remove_image'),
        );

        return redirect()
            ->route('admin.pages.index')
            ->with('status', 'Background saved for '.PageBackgrounds::label($pageKey).'.')
            ->with('editing_page', $pageKey);
    }

    protected function authorizePageSettings(): void
    {
        abort_unless(request()->user()?->canManageIconLibrary() === true, 403);
    }
}
