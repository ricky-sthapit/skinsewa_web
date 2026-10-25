<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\EducationalResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\File;

class EducationalResourceController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'display_name' => ['required', 'string', 'max:160'],
            'resource_file' => ['required', File::types(['pdf'])->max(20480)],
        ]);
        $file = $data['resource_file'];
        $resource = EducationalResource::query()->create([
            'display_name' => trim($data['display_name']), 'original_file_name' => $file->getClientOriginalName(),
            'file_path' => $file->store('resources', 'local'), 'file_size_bytes' => $file->getSize(),
        ]);
        return response()->json(['message' => 'Resource created.', 'data' => $this->payload($resource)], 201);
    }

    public function update(Request $request, EducationalResource $educationalResource): JsonResponse
    {
        $data = $request->validate([
            'display_name' => ['required', 'string', 'max:160'],
            'resource_file' => ['nullable', File::types(['pdf'])->max(20480)],
        ]);
        $newFile = $data['resource_file'] ?? null;
        $oldPath = $educationalResource->file_path;
        if ($newFile) {
            $educationalResource->file_path = $newFile->store('resources', 'local');
            $educationalResource->original_file_name = $newFile->getClientOriginalName();
            $educationalResource->file_size_bytes = $newFile->getSize();
        }
        $educationalResource->display_name = trim($data['display_name']);
        $educationalResource->save();
        if ($newFile) Storage::disk('local')->delete($oldPath);
        return response()->json(['message' => 'Resource updated.', 'data' => $this->payload($educationalResource)]);
    }

    public function destroy(EducationalResource $educationalResource): JsonResponse
    {
        Storage::disk('local')->delete($educationalResource->file_path);
        $educationalResource->delete();
        return response()->json(['message' => 'Resource deleted.']);
    }

    private function payload(EducationalResource $resource): array
    {
        return [
            'id' => $resource->id, 'display_name' => $resource->display_name,
            'file_name' => $resource->original_file_name, 'file_size_bytes' => $resource->file_size_bytes,
            'view_url' => route('api.v1.resources.view', $resource),
            'download_url' => route('api.v1.resources.download', $resource),
        ];
    }
}
