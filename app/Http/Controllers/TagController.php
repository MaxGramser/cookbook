<?php

namespace App\Http\Controllers;

use App\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TagController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tags = Tag::query()
            ->availableTo($request->user())
            ->orderBy('group')
            ->orderBy('sort')
            ->orderBy('name')
            ->get(['id', 'group', 'slug', 'name', 'color', 'is_system']);

        return response()->json(['tags' => $tags]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'group' => ['required', 'string', Rule::in(Tag::GROUPS)],
            'name' => ['required', 'string', 'max:80'],
            'color' => ['nullable', 'string', Rule::in(['cream', 'lime', 'pink', 'sky', 'accent', 'ink'])],
        ]);

        $user = $request->user();
        $name = trim($data['name']);
        $slug = Str::slug($name);

        $existing = Tag::query()
            ->where('group', $data['group'])
            ->where('slug', $slug)
            ->where(function ($q) use ($user) {
                $q->where('is_system', true)->orWhere('user_id', $user->id);
            })
            ->first();

        if ($existing !== null) {
            return response()->json(['tag' => $existing->only(['id', 'group', 'slug', 'name', 'color', 'is_system'])]);
        }

        // Avoid colliding with another user's slug in the same group.
        $finalSlug = $slug;
        $i = 2;
        while (Tag::query()->where('group', $data['group'])->where('slug', $finalSlug)->exists()) {
            $finalSlug = $slug.'-'.$i;
            $i++;
        }

        $tag = Tag::query()->create([
            'group' => $data['group'],
            'slug' => $finalSlug,
            'name' => Str::ucfirst($name),
            'color' => $data['color'] ?? 'cream',
            'sort' => 9000,
            'is_system' => false,
            'user_id' => $user->id,
        ]);

        return response()->json(['tag' => $tag->only(['id', 'group', 'slug', 'name', 'color', 'is_system'])], 201);
    }
}
