<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Department;

class ResolutionLookupResolver
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function apply(array $data): array
    {
        if (array_key_exists('category', $data)) {
            $data['category_id'] = Category::findOrCreateByDescription($data['category']);
            unset($data['category']);
        }

        if (array_key_exists('department', $data)) {
            $data['department_id'] = Department::findOrCreateByDescription($data['department']);
            unset($data['department']);
        }

        return $data;
    }
}
