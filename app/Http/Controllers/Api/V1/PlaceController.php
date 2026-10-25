<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Place;
use Illuminate\Http\JsonResponse;

class PlaceController extends Controller
{
    public function provinces(): JsonResponse
    {
        return response()->json(['data' => Place::query()->where('type', 'Province')->orderBy('name')->get(['id', 'name', 'name_ne', 'type', 'parent'])]);
    }

    public function children(Place $place): JsonResponse
    {
        abort_unless(in_array($place->type, ['Province', 'District'], true), 404);
        return response()->json(['data' => $place->children()->orderBy('name')->get(['id', 'name', 'name_ne', 'type', 'parent'])]);
    }
}
