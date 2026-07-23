@extends('layouts.app')

@php
    $isEdit = $entry->exists;
@endphp

@section('title', ($isEdit ? 'Edit Directory Entry' : 'New Directory Entry').' — '.config('app.name'))

@section('content')
<div class="max-w-2xl">
    <div class="splis-page-header !mb-6">
        <div>
            <h1 class="splis-page-title">{{ $isEdit ? 'Edit directory entry' : 'New Directory Entry' }}</h1>
        </div>
    </div>

    <form method="POST" action="{{ $isEdit ? route('directory.update', $entry) : route('directory.store') }}" class="splis-card splis-card-body space-y-5">
        @csrf
        @if ($isEdit)
            @method('PUT')
        @endif

        <div>
            <label class="splis-label" for="name">Name</label>
            <input type="text" name="name" id="name" value="{{ old('name', $entry->name) }}" required class="splis-input">
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <label class="splis-label" for="contact_number">Contact number</label>
                <input type="text" name="contact_number" id="contact_number" value="{{ old('contact_number', $entry->contact_number) }}" class="splis-input">
            </div>
            <div>
                <label class="splis-label" for="email">Email</label>
                <input type="email" name="email" id="email" value="{{ old('email', $entry->email) }}" class="splis-input">
            </div>
        </div>

        <div>
            <label class="splis-label" for="designation">Designation</label>
            <input type="text" name="designation" id="designation" value="{{ old('designation', $entry->designation) }}" class="splis-input">
        </div>

        <div>
            <label class="splis-label" for="sort_order">Sort order</label>
            <input type="number" name="sort_order" id="sort_order" value="{{ old('sort_order', $entry->sort_order ?? 0) }}" min="0" class="splis-input w-32">
        </div>

        <div class="flex gap-2 pt-2">
            <button type="submit" class="splis-btn-primary">Save</button>
            <a href="{{ route('directory.index') }}" class="splis-btn-secondary inline-flex items-center gap-2">
                <x-icon name="arrow-left" class="h-4 w-4" />
                Cancel
            </a>
        </div>
    </form>
</div>
@endsection
