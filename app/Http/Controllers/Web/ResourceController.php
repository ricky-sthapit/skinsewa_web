<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\EducationalResource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\File;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class ResourceController extends Controller
{
    public function index(Request $request): View
    {
        $search = mb_substr(trim((string) $request->query('q', '')), 0, 100);
        $resources = EducationalResource::query()
            ->when($search !== '', fn ($query) => $query->where('display_name', 'like', '%'.$search.'%'))
            ->orderBy('display_name')
            ->paginate(10)
            ->withQueryString();

        return view('resources.index', compact('resources', 'search'));
    }

    public function view(EducationalResource $educationalResource): BinaryFileResponse
    {
        abort_unless(Storage::disk('local')->exists($educationalResource->file_path), 404);

        $response = response()->file(Storage::disk('local')->path($educationalResource->file_path), [
            'Content-Type' => 'application/pdf',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_INLINE,
            $educationalResource->original_file_name,
            'resource.pdf',
        );

        return $response;
    }

    public function download(EducationalResource $educationalResource): mixed
    {
        abort_unless(Storage::disk('local')->exists($educationalResource->file_path), 404);

        return Storage::disk('local')->download($educationalResource->file_path, $educationalResource->original_file_name, [
            'Content-Type' => 'application/pdf',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function create(): View
    {
        return view('resources.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'display_name' => ['required', 'string', 'max:160'],
            'resource_file' => ['required', File::types(['pdf'])->max(20480)],
        ]);

        $file = $data['resource_file'];
        $path = $file->store('resources', 'local');
        EducationalResource::create([
            'display_name' => trim($data['display_name']),
            'original_file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'file_size_bytes' => $file->getSize(),
        ]);

        return redirect()->route('resources.index')->with('status', 'Resource added successfully.');
    }

    public function edit(EducationalResource $educationalResource): View
    {
        return view('resources.edit', ['resource' => $educationalResource]);
    }

    public function update(Request $request, EducationalResource $educationalResource): RedirectResponse
    {
        $data = $request->validate([
            'display_name' => ['required', 'string', 'max:160'],
            'resource_file' => ['nullable', File::types(['pdf'])->max(20480)],
        ]);

        $oldPath = $educationalResource->file_path;
        $newFile = $data['resource_file'] ?? null;
        if ($newFile) {
            $educationalResource->file_path = $newFile->store('resources', 'local');
            $educationalResource->original_file_name = $newFile->getClientOriginalName();
            $educationalResource->file_size_bytes = $newFile->getSize();
        }

        $educationalResource->display_name = trim($data['display_name']);
        $educationalResource->save();

        if ($newFile) {
            Storage::disk('local')->delete($oldPath);
        }

        return redirect()->route('resources.index')->with('status', 'Resource updated successfully.');
    }

    public function destroy(EducationalResource $educationalResource): RedirectResponse
    {
        Storage::disk('local')->delete($educationalResource->file_path);
        $educationalResource->delete();

        return redirect()->route('resources.index')->with('status', 'Resource deleted.');
    }
}
