<?php

namespace App\Http\Controllers\Admin;

use App\Helper\CommonHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\BlogRequest;
use App\Models\Blog;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BlogController extends Controller
{
    public function index(Request $request)
    {
        $query = Blog::query()->orderBy('created_at', 'desc');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%")
                    ->orWhere('author', 'like', "%{$search}%");
            });
        }

        if ($request->input('status') === 'active') {
            $query->where('is_active', true);
        } elseif ($request->input('status') === 'inactive') {
            $query->where('is_active', false);
        }

        return Inertia::render('Admin/Blog/Index', [
            'blogs'   => $query->paginate(20)->withQueryString(),
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Blog/Create');
    }

    public function store(BlogRequest $request)
    {
        $inputs = $request->validated();

        if ($request->file('image')) {
            $inputs['image'] = CommonHelper::uploadFile($request->file('image'), 'blog');
        }

        Blog::query()->create($inputs);

        return redirect()->route('admin.blogs.index')->with('success', 'Blog post created.');
    }

    public function edit(Blog $blog)
    {
        return Inertia::render('Admin/Blog/Create', [
            'blog' => $blog,
        ]);
    }

    public function update(BlogRequest $request, Blog $blog)
    {
        $inputs = $request->validated();

        if ($request->file('image')) {
            $inputs['image'] = CommonHelper::uploadFile($request->file('image'), 'blog', $blog->image);
        }

        $blog->update($inputs);

        return redirect()->route('admin.blogs.index')->with('success', 'Blog post updated.');
    }

    public function delete(Blog $blog)
    {
        if (! empty($blog->image)) {
            CommonHelper::removeOldFile('public/blog/' . $blog->image);
        }

        $blog->delete();

        return back()->with('success', 'Blog post deleted.');
    }

    public function toggle(Blog $blog)
    {
        $blog->update(['is_active' => ! $blog->is_active]);

        return back()->with('success', $blog->is_active ? 'Blog post published.' : 'Blog post unpublished.');
    }
}
