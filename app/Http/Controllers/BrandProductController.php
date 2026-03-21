<?php

namespace App\Http\Controllers;

use App\Models\BrandProduct;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class BrandProductController extends Controller
{
    public function index()
    {
        $products = BrandProduct::withCount('companyStatuses')->orderBy('name')->get();

        return Inertia::render('BrandProducts/Index', [
            'products' => $products,
        ]);
    }

    public function create()
    {
        return Inertia::render('BrandProducts/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'variant' => 'nullable|string|max:255',
            'slug' => 'nullable|string|max:255|unique:brand_products,slug',
        ]);

        $data['slug'] ??= Str::slug($data['name'].(! empty($data['variant']) ? '-'.$data['variant'] : ''));

        BrandProduct::create($data);

        return redirect()->route('segmentation.index')->with('success', 'Segmentation created.');
    }

    public function edit(BrandProduct $brandProduct)
    {
        return Inertia::render('BrandProducts/Edit', [
            'brandProduct' => $brandProduct,
        ]);
    }

    public function update(Request $request, BrandProduct $brandProduct): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'variant' => 'nullable|string|max:255',
            'slug' => 'required|string|max:255|unique:brand_products,slug,'.$brandProduct->id,
        ]);

        $brandProduct->update($data);

        return redirect()->route('segmentation.index')->with('success', 'Segmentation updated.');
    }

    public function destroy(BrandProduct $brandProduct): RedirectResponse
    {
        $brandProduct->companyStatuses()->delete();
        $brandProduct->delete();

        return redirect()->route('segmentation.index')->with('success', 'Segmentation deleted.');
    }
}
