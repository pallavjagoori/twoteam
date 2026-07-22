<?php

namespace App\Http\Controllers;

use App\Models\HelpCenterCategory;
use App\Models\Portal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class HelpCenterCategoryController extends Controller
{
    public function index(Request $request, Portal $portal): JsonResponse
    {
        $this->auth($request, $portal, 'view');

        return response()->json(['payload' => $portal->categories()->orderBy('position')->get()]);
    }

    public function store(Request $request, Portal $portal): JsonResponse
    {
        $this->auth($request, $portal, 'update');

        return response()->json($portal->categories()->create($this->validated($request)));
    }

    public function update(Request $request, Portal $portal, HelpCenterCategory $category): JsonResponse
    {
        $this->auth($request, $portal, 'update');
        $this->scoped($portal, $category);
        $category->update($this->validated($request, true));

        return response()->json($category);
    }

    public function destroy(Request $request, Portal $portal, HelpCenterCategory $category): JsonResponse
    {
        $this->auth($request, $portal, 'update');
        $this->scoped($portal, $category);
        $category->delete();

        return response()->json(['message' => 'Category deleted']);
    }

    private function validated(Request $request, bool $sometimes = false): array
    {
        $presence = $sometimes ? 'sometimes' : 'required';

        return $request->validate(['name' => [$presence, 'string'], 'slug' => [$presence, 'alpha_dash'], 'locale' => ['sometimes', 'string'], 'position' => ['sometimes', 'integer', 'min:0']]);
    }

    private function auth(Request $request, Portal $portal, string $ability): void
    {
        Gate::forUser($request->user())->authorize($ability, $portal->account);
    }

    private function scoped(Portal $portal, HelpCenterCategory $category): void
    {
        abort_unless($category->portal_id === $portal->id, 404);
    }
}
