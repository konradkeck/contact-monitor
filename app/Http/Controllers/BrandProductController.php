<?php

namespace App\Http\Controllers;

use App\Models\BrandProduct;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class BrandProductController extends Controller
{
    public function index(): View
    {
        $products = BrandProduct::withCount('companyStatuses')->orderBy('name')->get();

        return view('brand-products.index', compact('products'));
    }

    public function create(): View
    {
        return view('brand-products.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'variant' => 'nullable|string|max:255',
            'slug' => 'nullable|string|max:255|unique:brand_products,slug',
        ]);

        $data['slug'] ??= Str::slug($data['name'] . ($data['variant'] ? '-' . $data['variant'] : ''));

        BrandProduct::create($data);

        return redirect()->route('brand-products.index')->with('success', 'Brand product created.');
    }

    public function show(BrandProduct $brandProduct): View
    {
        $brandProduct->load(['companyStatuses.company']);

        return view('brand-products.show', compact('brandProduct'));
    }

    public function edit(BrandProduct $brandProduct): View
    {
        return view('brand-products.edit', compact('brandProduct'));
    }

    public function update(Request $request, BrandProduct $brandProduct): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'variant' => 'nullable|string|max:255',
            'slug' => 'required|string|max:255|unique:brand_products,slug,' . $brandProduct->id,
        ]);

        $brandProduct->update($data);

        return redirect()->route('brand-products.show', $brandProduct)->with('success', 'Brand product updated.');
    }

    public function destroy(BrandProduct $brandProduct): RedirectResponse
    {
        $brandProduct->companyStatuses()->delete();
        $brandProduct->delete();

        return redirect()->route('brand-products.index')->with('success', 'Brand product deleted.');
    }
}
