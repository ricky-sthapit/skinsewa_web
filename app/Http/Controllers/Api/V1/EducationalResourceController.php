<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\EducationalResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EducationalResourceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $search = mb_substr(trim((string) $request->query('q', '')), 0, 100);
        $perPage = min(25, max(5, (int) $request->query('per_page', 10)));
        $resources = EducationalResource::query()
            ->when($search !== '', fn ($query) => $query->where('display_name', 'like', '%'.$search.'%'))
            ->orderBy('display_name')->paginate($perPage)->withQueryString();
        return response()->json([
            'data' => $resources->getCollection()->map(fn (EducationalResource $resource) => $this->payload($resource))->values(),
            'meta' => ['current_page' => $resources->currentPage(), 'last_page' => $resources->lastPage(), 'per_page' => $resources->perPage(), 'total' => $resources->total()],
        ]);
    }

    public function show(EducationalResource $educationalResource): JsonResponse
    {
        return response()->json(['data' => $this->payload($educationalResource)]);
    }

    public function view(EducationalResource $educationalResource): BinaryFileResponse
    {
        abort_unless(Storage::disk('local')->exists($educationalResource->file_path), 404);
        $response = response()->file(Storage::disk('local')->path($educationalResource->file_path), [
            'Content-Type' => 'application/pdf',
            'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store',
        ]);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, $educationalResource->original_file_name, 'resource.pdf');
        return $response;
    }

    public function download(EducationalResource $educationalResource): StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists($educationalResource->file_path), 404);
        return Storage::disk('local')->download($educationalResource->file_path, $educationalResource->original_file_name, [
            'Content-Type' => 'application/pdf', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store',
        ]);
    }

    private function payload(EducationalResource $resource): array
    {
        return [
            'id' => $resource->id, 'display_name' => $resource->display_name,
            'file_name' => $resource->original_file_name, 'file_size_bytes' => $resource->file_size_bytes,
            'created_at' => $resource->created_at,
            'view_url' => route('api.v1.resources.view', $resource),
            'download_url' => route('api.v1.resources.download', $resource),
        ];
    }
}
