@props([
    'title' => 'Nothing here yet',
    'description' => null,
])

<div {{ $attributes->class(['rounded-2xl border border-dashed border-slate-300 px-4 py-10 text-center dark:border-slate-600']) }}>
    <p class="text-sm font-medium text-slate-700 dark:text-slate-200">{{ $title }}</p>
    @if ($description)
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $description }}</p>
    @endif
    @isset($actions)
        <div class="mt-4 flex flex-wrap justify-center gap-2">
            {{ $actions }}
        </div>
    @endisset
</div>
