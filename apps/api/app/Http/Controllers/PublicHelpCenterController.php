<?php

namespace App\Http\Controllers;

use App\Models\HelpCenterArticle;
use App\Models\Portal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicHelpCenterController extends Controller
{
    public function articles(Request $request, string $portal, string $locale): JsonResponse
    {
        $item = Portal::where('slug', $portal)->where('archived', false)->firstOrFail();
        $query = $item->articles()->where('status', 'published')->where('locale', $locale)->whereNotNull('published_at');
        if ($request->filled('query')) {
            $query->where(fn ($q) => $q->where('title', 'like', '%'.trim($request->string('query')).'%')->orWhere('content', 'like', '%'.trim($request->string('query')).'%'));
        }

        return response()->json(['payload' => $query->orderBy('position')->get()->map(fn ($article) => $this->payload($article))]);
    }

    public function show(string $portal, string $locale, string $article): JsonResponse
    {
        $item = Portal::where('slug', $portal)->where('archived', false)->firstOrFail();
        $record = $item->articles()->where('status', 'published')->where('locale', $locale)->where(fn ($q) => $q->where('slug', $article)->orWhere('id', $article))->firstOrFail();

        return response()->json(['article' => $this->payload($record)]);
    }

    private function payload(HelpCenterArticle $article): array
    {
        return ['id' => $article->id, 'title' => $article->title, 'slug' => $article->slug, 'content' => $article->content, 'locale' => $article->locale, 'category_id' => $article->help_center_category_id, 'published_at' => $article->published_at?->toISOString()];
    }
}
