<?php

namespace Tests\Unit;

use App\Models\ActivityLog;
use App\Models\ReferenceMaterial;
use App\Support\ActivityLogPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityLogPresenterLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_activity_log_links_are_relative_paths(): void
    {
        $reference = ReferenceMaterial::query()->create([
            'title' => 'Sample ordinance reference',
            'document_type' => array_key_first(config('reference_materials.document_types', ['ordinance' => 'Ordinance'])),
            'status' => 'active',
        ]);

        $log = ActivityLog::query()->create([
            'action' => 'reference_material.created',
            'subject_type' => ReferenceMaterial::class,
            'subject_id' => $reference->id,
            'properties' => ['title' => $reference->title],
        ]);

        $link = ActivityLogPresenter::link($log);

        $this->assertSame('/references/'.$reference->id, $link);
        $this->assertStringNotContainsString('http', (string) $link);
    }
}
