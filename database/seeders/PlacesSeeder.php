<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PlacesSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('seeders/data/places.json');
        $places = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        $validTypes = ['Province', 'District', 'Metropolitan City', 'Municipality', 'Rural Municipality', 'Sub-Metropolitan City'];

        if (! is_array($places) || count($places) !== 837) {
            throw new RuntimeException('The location dataset must contain 7 provinces, 77 districts, and 753 local levels.');
        }

        foreach ($places as $place) {
            if (! in_array($place['type'], $validTypes, true) || empty($place['name_ne'])) {
                throw new RuntimeException('The location dataset contains an invalid type or missing Nepali name.');
            }
        }

        DB::transaction(function () use ($places): void {
            foreach (array_chunk($places, 500) as $chunk) {
                DB::table('places')->upsert($chunk, ['id'], ['name', 'name_ne', 'type', 'parent', 'level']);
            }
        });
    }
}
