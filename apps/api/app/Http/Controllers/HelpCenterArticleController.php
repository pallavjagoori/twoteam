<?php

namespace App\Http\Controllers;

use App\Models\HelpCenterArticle;
use App\Models\HelpCenterCategory;
use App\Models\Portal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class HelpCenterArticleController extends Controller
{
    public function index(Request $request, Portal $portal): JsonResponse
    {
        $this->auth($request, $portal, 'view');
        $query = $portal->articles()->with(['category', 'author']);
        foreach (['locale', 'status', 'author_id'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->input($filter));
            }
        }
        if ($request->filled('query')) {
            $query->where(fn ($q) => $q->where('title', 'like', '%'.$request->string('query').'%')->orWhere('content', 'like', '%'.$request->string('query').'%'));
        }

        return response()->json(['payload' => $query->orderBy('position')->orderBy('id')->paginate(min(100, max(1, $request->integer('per_page', 25))))->through(fn ($article) => $this->payload($article))]);
    }

    public function store(Request $request, Portal $portal): JsonResponse
    {
        $this->auth($request, $portal, 'update');
        $data = $this->validated($request);
        $this->category($portal, $data['category_id'] ?? null);
        $data['help_center_category_id'] = $data['category_id'] ?? null;
        unset($data['category_id']);
        $article = $portal->articles()->create($data + ['author_id' => $request->user()->id, 'slug' => $data['slug'] ?? Str::slug($data['title']), 'published_at' => ($data['status'] ?? 'draft') === 'published' ? now() : null]);

        return response()->json($this->payload($article->load(['category', 'author'])));
    }

    public function show(Request $request, Portal $portal, HelpCenterArticle $article): JsonResponse
    {
        $this->auth($request, $portal, 'view');
        $this->scoped($portal, $article);

        return response()->json($this->payload($article->load(['category', 'author'])));
    }

    public function update(Request $request, Portal $portal, HelpCenterArticle $article): JsonResponse
    {
        $this->auth($request, $portal, 'update');
        $this->scoped($portal, $article);
        $data = $this->validated($request, true);
        $this->category($portal, $data['category_id'] ?? null);
        if (array_key_exists('category_id', $data)) {
            $data['help_center_category_id'] = $data['category_id'];
            unset($data['category_id']);
        } if (($data['status'] ?? null) === 'published' && ! $article->published_at) {
            $data['published_at'] = now();
        } $article->update($data);

        return response()->json($this->payload($article->load(['category', 'author'])));
    }

    public function destroy(Request $request, Portal $portal, HelpCenterArticle $article): JsonResponse
    {
        $this->auth($request, $portal, 'update');
        $this->scoped($portal, $article);
        $article->delete();

        return response()->json(['message' => 'Article deleted']);
    }

    private function validated(Request $request, bool $sometimes = false): array
    {
        $presence = $sometimes ? 'sometimes' : 'required';

        return $request->validate(['title' => [$presence, 'string'], 'slug' => ['sometimes', 'alpha_dash'], 'content' => [$presence, 'string'], 'locale' => ['sometimes', 'string'], 'status' => ['sometimes', 'in:draft,published'], 'category_id' => ['sometimes', 'nullable', 'integer'], 'position' => ['sometimes', 'integer', 'min:0']]);
    }

    private function category(Portal $portal, ?int $id): void
    {
        if ($id) {
            abort_unless(HelpCenterCategory::whereKey($id)->where('portal_id', $portal->id)->exists(), 422);
        }
    }

    private function payload(HelpCenterArticle $article): array
    {
        return ['id' => $article->id, 'title' => $article->title, 'slug' => $article->slug, 'content' => $article->content, 'locale' => $article->locale, 'status' => $article->status, 'position' => $article->position, 'category_id' => $article->help_center_category_id, 'author_id' => $article->author_id, 'published_at' => $article->published_at?->toISOString()];
    }

    private function auth(Request $request, Portal $portal, string $ability): void
    {
        Gate::forUser($request->user())->authorize($ability, $portal->account);
    }

    private function scoped(Portal $portal, HelpCenterArticle $article): void
    {
        abort_unless($article->portal_id === $portal->id, 404);
    }
}
