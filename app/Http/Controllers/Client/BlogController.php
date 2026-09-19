<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Blog;
use Illuminate\Http\Request;

class BlogController extends Controller
{
    public function index(Request $request)
    {
        $query = Blog::query()
            ->published()
            ->orderByDesc('published_at')
            ->orderByDesc('created_at');

        if ($search = trim((string) $request->input('q', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('excerpt', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%");
            });
        }

        if ($category = trim((string) $request->input('category', ''))) {
            $query->where('category', $category);
        }

        $posts = $query->paginate(9)->withQueryString();

        // Distinct blog category strings. Named `blogCategories` to avoid
        // clashing with `$categories` shared by AppServiceProvider's view
        // composer (which holds product categories for the layout).
        $blogCategories = Blog::query()
            ->published()
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        return view('client.blog.index', [
            'posts'          => $posts,
            'blogCategories' => $blogCategories,
            'search'         => $search,
            'activeCategory' => $category,
        ]);
    }

    public function show(string $slug)
    {
        $post = Blog::query()
            ->published()
            ->where('slug', $slug)
            ->firstOrFail();

        // Prefer same-category posts; if we can't fill 3, top it up with the
        // latest posts from other categories so the section is never sparse.
        $related = Blog::query()
            ->published()
            ->where('id', '!=', $post->id)
            ->when($post->category, fn ($q) => $q->where('category', $post->category))
            ->orderByDesc('published_at')
            ->limit(3)
            ->get();

        if ($related->count() < 3) {
            $fill = Blog::query()
                ->published()
                ->where('id', '!=', $post->id)
                ->whereNotIn('id', $related->pluck('id'))
                ->orderByDesc('published_at')
                ->limit(3 - $related->count())
                ->get();
            $related = $related->concat($fill);
        }

        // Category chip cloud in the sidebar.
        $allCategories = Blog::query()
            ->published()
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        return view('client.blog.show', [
            'post'          => $post,
            'related'       => $related,
            'allCategories' => $allCategories,
        ]);
    }
}
