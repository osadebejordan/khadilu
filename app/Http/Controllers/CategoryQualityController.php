<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Quality;
use Illuminate\Http\Request;

class CategoryQualityController extends Controller
{
    public function index()
    {
        $categories = Category::all();
        $qualities = Quality::all();

        return view('inventory.attributes', compact('categories', 'qualities'));
    }

    public function storeCategory(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:categories,name',
        ]);

        Category::create($validated);
        logActivity('Create Category', 'categories', null, "Added category: {$validated['name']}");

        return back()->with('success', 'Category added successfully.');
    }

    public function destroyCategory(Category $category)
    {
        logActivity('Delete Category', 'categories', $category->id, "Deleted category: {$category->name}");
        $category->delete();

        return back()->with('success', 'Category deleted successfully.');
    }

    public function storeQuality(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:qualities,name',
            'category_name' => 'nullable|string|max:60',
        ]);

        Quality::create($validated);
        logActivity('Create Quality', 'qualities', null, "Added quality: {$validated['name']}");

        return back()->with('success', 'Quality grade added successfully.');
    }

    public function destroyQuality(Quality $quality)
    {
        logActivity('Delete Quality', 'qualities', $quality->id, "Deleted quality: {$quality->name}");
        $quality->delete();

        return back()->with('success', 'Quality grade deleted successfully.');
    }
}
