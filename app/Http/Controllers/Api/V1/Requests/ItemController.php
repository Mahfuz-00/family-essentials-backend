<?php

namespace App\Http\Controllers\Api\V1\Requests;

use App\Http\Controllers\Controller;
use App\Http\Requests\Requests\StoreItemRequest;
use App\Http\Resources\ItemResource;
use App\Models\Item;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ItemController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $items = Item::query()
            ->when($request->filled('search'), fn ($query) => $query->where('name', 'like', '%'.$request->string('search').'%'))
            ->when($request->filled('category'), fn ($query) => $query->where('category', $request->string('category')))
            ->orderBy('name')
            ->get();

        return ItemResource::collection($items)->response();
    }

    public function store(StoreItemRequest $request): JsonResponse
    {
        $item = Item::create([
            'name' => $request->string('name'),
            'category' => $request->input('category'),
            'default_store' => $request->input('default_store'),
            'quantity_hint' => $request->input('quantity_hint'),
            'created_by' => $request->user()->getKey(),
        ]);

        return (new ItemResource($item))->response()->setStatusCode(201);
    }
}
