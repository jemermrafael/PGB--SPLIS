@extends('layouts.print')

@section('title', 'Print — '.$document->title)

@push('head')
<style>
    @page {
        size: 8.5in 13in;
        margin: 0.6in 0.65in;

        @bottom-right {
            content: counter(page);
            font-family: Arial, sans-serif;
            font-size: 9pt;
            color: #475569;
        }
    }

    @media print {
        .ob-print-toolbar { display: none !important; }
        .ob-print-page-break { page-break-before: always; break-before: page; }
    }

    .ob-print-document--legal {
        max-width: 8.5in;
        min-height: 13in;
    }
</style>
@endpush

@php
    $isEmbeddedPreview = request()->boolean('embed');
@endphp

@section('content')
<div class="ob-print-toolbar sticky top-0 z-10 flex items-center justify-between gap-4 border-b border-slate-200 bg-slate-50 px-4 py-3 print:hidden">
    <div>
        <p class="font-semibold text-slate-900">{{ $document->title }}</p>
        <p class="text-sm text-slate-600">{{ $session->displayTitle() }}</p>
    </div>
    <div class="flex gap-2">
        @unless ($isEmbeddedPreview)
            @can('update', $document)
                <button
                    type="button"
                    class="splis-btn-secondary inline-flex items-center gap-2"
                    data-ob-close-preview
                    data-ob-maker-url="{{ route('ob.document.maker', $session) }}"
                >
                    <x-icon name="arrow-left" class="h-4 w-4" />
                    Close Preview
                </button>
            @else
                <a href="{{ route('ob.sessions.show', $session) }}" class="splis-btn-secondary inline-flex items-center gap-2">
                    <x-icon name="arrow-left" class="h-4 w-4" />
                    Back
                </a>
            @endcan
        @endunless
        <button type="button" class="splis-btn-primary inline-flex items-center gap-2" onclick="window.print()">
            <x-icon name="printer" class="h-4 w-4" />
            Print / Save as PDF
        </button>
    </div>
</div>

<article class="ob-print-document ob-print-document--legal mx-auto px-6 py-8">
    @php
        $printTime = $session->formattedSessionTimeForPrint();
        $sessionAndVenue = trim(implode(' ', array_filter([
            $session->session_number,
            $session->venue,
        ])));
        $headerMetaLine = collect([$printTime, $sessionAndVenue])->filter()->implode(' ');
    @endphp
    <header class="ob-print-header mb-8 text-center">
        <div class="ob-print-header-brand">
            <img
                src="{{ asset('images/bataan-seal.png') }}"
                alt="Province of Bataan official seal"
                class="ob-print-logo"
            >
            <h1 class="ob-print-header-title">ORDER OF BUSINESS</h1>
        </div>
        <p class="ob-print-header-date">{{ $session->session_date->format('F j, Y') }}</p>
        @if ($headerMetaLine !== '')
            <p class="ob-print-header-meta">{{ $headerMetaLine }}</p>
        @endif
    </header>

    @include('order-of-business.partials.print-segments', ['segments' => $segments])
</article>

@unless ($isEmbeddedPreview)
<script>
    (function () {
        var closeBtn = document.querySelector('[data-ob-close-preview]');
        if (! closeBtn) {
            return;
        }

        closeBtn.addEventListener('click', function () {
            if (window.parent && window.parent !== window) {
                window.parent.postMessage({ type: 'splis-close-document-modal' }, window.location.origin);
                return;
            }

            window.close();
            window.setTimeout(function () {
                window.location.href = closeBtn.getAttribute('data-ob-maker-url') || '/';
            }, 150);
        });
    })();
</script>
@endunless
@endsection
