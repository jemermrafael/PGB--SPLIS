@php
    $isLinked = $incoming->isLinked() && $incoming->resolution;
    $canPublish = auth()->user()?->can('publish', $incoming);

    if ($isLinked) {
        $currentStep = 3;
    } elseif (($currentStep ?? null) === 2) {
        $currentStep = 2;
    } else {
        $currentStep = 1;
    }

    $steps = [
        [
            'label' => 'Review incoming',
            'description' => 'Check details and PDF links',
            'url' => route('incoming.show', $incoming),
        ],
        [
            'label' => 'Publish to resolution',
            'description' => 'Create the final SP resolution',
            'url' => $canPublish ? route('incoming.publish', $incoming) : ($isLinked ? null : route('incoming.show', $incoming)),
        ],
        [
            'label' => 'Final resolution',
            'description' => $isLinked ? $incoming->resolution->resolution_no : 'Available after publishing',
            'url' => $isLinked ? route('resolutions.show', $incoming->resolution) : null,
        ],
    ];
@endphp

<x-workflow-steps :steps="$steps" :current="$currentStep" />
