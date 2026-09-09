<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\Request;

class AdminTenantController extends Controller
{
    public function __construct()
    {
        $this->middleware([
            'auth:sanctum',
            'role:admin,super_admin',
        ]);
    }

    private function companies()
    {
        return Company::query()
            ->withCount('products')
            ->with([
                'users' => function ($query) {
                    $query->where('role', 'company')
                        ->select(
                            'id',
                            'company_id',
                            'first_name',
                            'last_name',
                            'email',
                            'phone',
                            'status',
                            'email_verified_at'
                        )
                        ->orderBy('id');
                },
            ]);
    }

    private function companyData(Company $company): array
    {
        return [
            'id' => $company->id,
            'name' => $company->name,
            'email' => $company->email,
            'phone' => $company->phone,
            'address' => $company->address,
            'city' => $company->city,
            'created_at' => $company->created_at,
            'products_count' => (int) $company->products_count,

            'accounts' => $company->users->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => trim(
                        $user->first_name . ' ' . $user->last_name
                    ),
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'status' => $user->status,
                    'email_verified' => $user->email_verified_at !== null,
                ];
            })->values(),
        ];
    }

    public function index(Request $request)
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $search = trim($validated['search'] ?? '');
        $query = $this->companies();

        if ($search !== '') {
            $query->where(function ($query) use ($search) {
                $query->where('name', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%');
            });
        }

        $companies = $query
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(15);

        $companies->getCollection()->transform(
            fn ($company) => $this->companyData($company)
        );

        return response()->json([
            'data' => $companies,
        ]);
    }

    public function show(Request $request, int $id)
    {
        $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $company = $this->companies()->findOrFail($id);

        $products = $company->products()
            ->with('category:id,name')
            ->orderByDesc('id')
            ->paginate(10);

        $products->getCollection()->transform(function ($product) {
            return [
                'id' => $product->id,
                'name' => $product->name,
                'category' => $product->category?->name,
                'price' => (float) $product->price,
                'quantity' => (int) $product->quantity,
                'unit' => $product->unit,
                'status' => $product->status,
            ];
        });

        return response()->json([
            'company' => $this->companyData($company),
            'products' => $products,
        ]);
    }
}