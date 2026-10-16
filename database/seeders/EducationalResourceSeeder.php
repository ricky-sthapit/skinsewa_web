<?php

namespace Database\Seeders;

use App\Models\EducationalResource;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class EducationalResourceSeeder extends Seeder
{
    private const RESOURCES = [
        'acne-and-pimples.pdf' => 'Acne and Pimples',
        'atopic-dermatitis-eczema.pdf' => 'Atopic Dermatitis (Eczema)',
        'psoriasis.pdf' => 'Psoriasis',
        'ringworm.pdf' => 'Ringworm (Tinea)',
        'scabies.pdf' => 'Scabies',
        'impetigo.pdf' => 'Impetigo',
        'rosacea.pdf' => 'Rosacea',
        'vitiligo.pdf' => 'Vitiligo',
        'hives-urticaria.pdf' => 'Hives (Urticaria)',
        'sun-protection.pdf' => 'Sun Protection and Skin Health',
    ];

    public function run(): void
    {
        $disk = Storage::disk('local');

        foreach (self::RESOURCES as $fileName => $displayName) {
            $sourcePath = resource_path('seed-resources/'.$fileName);
            if (! is_file($sourcePath)) {
                $this->command?->warn('Skipping missing educational resource: '.$sourcePath);
                continue;
            }

            $filePath = 'resources/seed/'.$fileName;
            $disk->put($filePath, file_get_contents($sourcePath));

            EducationalResource::updateOrCreate(
                ['display_name' => $displayName],
                [
                    'original_file_name' => $fileName,
                    'file_path' => $filePath,
                    'file_size_bytes' => filesize($sourcePath),
                ],
            );
        }
    }
}
