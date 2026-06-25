@php
    $formMethod = $formMethod ?? 'POST';
    $incoming = $incoming ?? null;
@endphp

<div class="grid grid-cols-1 gap-4 md:grid-cols-2">
    <div>
        <label class="splis-label" for="resolution_no">Resolution No. *</label>
        <input type="text" name="resolution_no" id="resolution_no" value="{{ old('resolution_no', $resolution->resolution_no ?? '') }}" required class="splis-input">
    </div>
    <div>
        <label class="splis-label" for="series">Series (Year) *</label>
        <input type="number" name="series" id="series" value="{{ old('series', $resolution->series ?? date('Y')) }}" required min="1900" max="2100" class="splis-input">
    </div>
</div>

<div>
    <label class="splis-label" for="resolution_title">Title *</label>
    <textarea name="resolution_title" id="resolution_title" rows="3" required class="splis-textarea">{{ old('resolution_title', $resolution->resolution_title ?? '') }}</textarea>
</div>

<div class="grid grid-cols-1 gap-4 md:grid-cols-2">
    <div>
        <label class="splis-label" for="category_id">Category</label>
        <select name="category_id" id="category_id" class="splis-select">
            <option value="">—</option>
            @foreach ($categories as $cat)
                <option value="{{ $cat->id }}" @selected(old('category_id', $resolution->category_id ?? '') == $cat->id)>{{ $cat->description }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="splis-label" for="department_id">Department</label>
        <select name="department_id" id="department_id" class="splis-select">
            <option value="">—</option>
            @foreach ($departments as $dept)
                <option value="{{ $dept->id }}" @selected(old('department_id', $resolution->department_id ?? '') == $dept->id)>{{ $dept->description }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="splis-label" for="municipality_id">Municipality</label>
        <select name="municipality_id" id="municipality_id" class="splis-select">
            <option value="">—</option>
            @foreach ($municipalities as $mun)
                <option value="{{ $mun->id }}" @selected(old('municipality_id', $resolution->municipality_id ?? '') == $mun->id)>{{ $mun->description }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="splis-label" for="date_approved">Date Approved</label>
        <input type="date" name="date_approved" id="date_approved" value="{{ old('date_approved', isset($resolution) && $resolution->date_approved ? $resolution->date_approved->format('Y-m-d') : '') }}" class="splis-input">
    </div>
    <div>
        <label class="splis-label" for="sponsored_by">Sponsored By</label>
        <input type="text" name="sponsored_by" id="sponsored_by" value="{{ old('sponsored_by', $resolution->sponsored_by ?? '') }}" class="splis-input">
    </div>
    <div>
        <label class="splis-label" for="status">Status</label>
        <select name="status" id="status" class="splis-select">
            @foreach (['draft', 'approved', 'archived'] as $status)
                <option value="{{ $status }}" @selected(old('status', $resolution->status ?? 'draft') === $status)>{{ ucfirst($status) }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="splis-label" for="keyword">Keyword</label>
        <input type="text" name="keyword" id="keyword" value="{{ old('keyword', $resolution->keyword ?? '') }}" class="splis-input">
    </div>
    <div>
        <label class="splis-label" for="committee">Committee</label>
        <input type="text" name="committee" id="committee" value="{{ old('committee', $resolution->committee ?? '') }}" class="splis-input">
    </div>
    <div>
        <label class="splis-label" for="app_ord_no">App/Ord No.</label>
        <input type="text" name="app_ord_no" id="app_ord_no" value="{{ old('app_ord_no', $resolution->app_ord_no ?? '') }}" class="splis-input">
    </div>
    <div>
        <label class="splis-label" for="amount">Amount</label>
        <input type="number" name="amount" id="amount" value="{{ old('amount', $resolution->amount ?? '') }}" min="0" class="splis-input">
    </div>
</div>

<label class="flex items-center gap-2.5 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300">
    <input type="hidden" name="province" value="0">
    <input type="checkbox" name="province" value="1" @checked(old('province', $resolution->province ?? false)) class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
    Province-wide resolution
</label>

<div>
    <label class="splis-label" for="pdf">PDF Document</label>
    <input type="file" name="pdf" id="pdf" accept="application/pdf" class="splis-input !py-2 file:mr-4 file:rounded-lg file:border-0 file:bg-brand-500/10 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-brand-700 hover:file:bg-brand-500/20">
    <p class="mt-1.5 text-xs text-slate-400">Saved as {series}/{resolution_no}.pdf — used for the embedded PDF viewer on the resolution page.</p>
    @if (! empty($pdfHint))
        <p class="mt-1 text-xs text-slate-500">{{ $pdfHint }}</p>
    @endif
</div>
