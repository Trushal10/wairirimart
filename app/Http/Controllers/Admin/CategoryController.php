<?php

namespace App\Http\Controllers\Admin;

use App\Helper\CommonHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\CategoryRequest;
use App\Models\Category;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $query = Category::query();

        if ($request->has('search')) {
            $search = $request->search;
            $query->whereLike('name', "%$search%")
                ->orWhereLike('status', $search == 'active' ? 1 : ($search === 'inactive' ? 0 : null));
        }

        $categories = $query->paginate(10)->withQueryString();

        return Inertia::render('Admin/Category/Index', [
            'categories' => $categories,
            'filters' => $request->only(['search']),
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Category/Create');
    }

    public function store(CategoryRequest $request)
    {
        $inputs = $request->validated();
        if ($request->file('image')) {
            $inputs['image'] = CommonHelper::uploadFile($request->file('image'), 'category');
        }
        Category::query()->create($inputs);

        return redirect()->route('admin.category')->with('success', 'Category Created Successfully');
    }

    public function edit(Category $category)
    {
        return Inertia::render('Admin/Category/Create', [
            'category' => $category,
        ]);
    }

    public function update(CategoryRequest $request, Category $category)
    {
        $inputs = $request->validated();

        if ($request->file('image')) {
            $inputs['image'] = CommonHelper::uploadFile($request->file('image'), 'category', $category->image);
        }

        $category->update($inputs);

        return redirect()->route('admin.category')->with('success', 'Category Updated Successfully');
    }

    public function delete(Category $category)
    {
        // Reject deletes when this category still has products attached — the
        // FK from product_categories.category_id blocks the delete anyway, but
        // checking here lets us return a friendly message instead of a 23000.
        if ($category->productCategory()->exists()) {
            return redirect()->back()->with(
                'error',
                'Category is used by one or more products. Remove those product links first.'
            );
        }

        $image = $category->image;

        try {
            $category->delete();
        } catch (\Throwable $e) {
            $message = 'Something went wrong';
            if (method_exists($e, 'getCode') && $e->getCode() == 23000) {
                $message = 'Category associated with other records cannot be deleted';
            }

            return redirect()->back()->with('error', $message);
        }

        // File cleanup is best-effort — never block the delete on it. The
        // helper's s3 branch can throw when the aws-sdk isn't installed;
        // catching \Throwable keeps a PHP Error from bubbling as a 500.
        if (! empty($image)) {
            try {
                CommonHelper::removeOldFile("public/category/{$image}");
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return redirect()->back()->with('success', 'Category Deleted Successfully');
    }
}
