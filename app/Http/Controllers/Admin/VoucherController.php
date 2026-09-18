<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Voucher;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class VoucherController extends Controller
{
    public function index() { return view('admin.vouchers', ['vouchers' => Voucher::latest()->paginate(20)]); }
    public function store(Request $request)
    {
        $data = $request->validate(['code' => 'required|string|max:40|unique:vouchers', 'name' => 'required|string|max:100', 'discount_type' => 'required|in:fixed,percent', 'discount_value' => 'required|integer|min:1', 'minimum_amount' => 'nullable|integer|min:0', 'usage_limit' => 'nullable|integer|min:1', 'per_user_limit' => 'required|integer|min:1', 'starts_at' => 'nullable|date', 'expires_at' => 'nullable|date|after:starts_at']);
        $data['code'] = Str::upper($data['code']);
        Voucher::create($data);
        return back()->with('status', 'Voucher ditambahkan.');
    }
    public function update(Request $request, Voucher $voucher)
    {
        $data = $request->validate(['is_active' => 'required|boolean']);
        $voucher->update($data);
        return back()->with('status', 'Status voucher diperbarui.');
    }
}