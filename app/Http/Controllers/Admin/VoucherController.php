<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Voucher;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class VoucherController extends Controller
{
    public function index()
    {
        $vouchers       = Voucher::latest()->paginate(15);
        $totalVouchers  = Voucher::count();
        $activeVouchers = Voucher::where('is_active', true)->count();
        $totalUsage     = Voucher::sum('usage_count');
        $fixedCount     = Voucher::where('discount_type', 'fixed')->count();
        $percentCount   = Voucher::where('discount_type', 'percent')->count();

        return view('admin.vouchers', compact(
            'vouchers',
            'totalVouchers',
            'activeVouchers',
            'totalUsage',
            'fixedCount',
            'percentCount'
        ));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'code'           => 'required|string|max:40|unique:vouchers,code',
            'name'           => 'required|string|max:100',
            'discount_type'  => 'required|in:fixed,percent',
            'discount_value' => 'required|integer|min:1',
            'minimum_amount' => 'nullable|integer|min:0',
            'usage_limit'    => 'nullable|integer|min:1',
            'per_user_limit' => 'required|integer|min:1',
            'starts_at'      => 'nullable|date',
            'expires_at'     => 'nullable|date|after:starts_at',
        ]);

        $data['code'] = Str::upper(trim($data['code']));
        $data['is_active'] = $request->boolean('is_active', true);

        // Pastikan input tanggal dan batas kosong disimpan sebagai NULL
        if (empty($data['starts_at'])) {
            $data['starts_at'] = null;
        }
        if (empty($data['expires_at'])) {
            $data['expires_at'] = null;
        }
        if (!isset($data['minimum_amount']) || $data['minimum_amount'] === '') {
            $data['minimum_amount'] = 0;
        }
        if (empty($data['usage_limit'])) {
            $data['usage_limit'] = null;
        }

        Voucher::create($data);

        return back()->with('status', 'Voucher baru berhasil dibuat dan siap digunakan!');
    }

    public function update(Request $request, Voucher $voucher)
    {
        $data = $request->validate([
            'is_active' => 'required|boolean'
        ]);

        $voucher->update($data);

        $statusText = $voucher->is_active ? 'diaktifkan' : 'dinonaktifkan';
        return back()->with('status', "Voucher {$voucher->code} berhasil {$statusText}.");
    }

    public function destroy(Voucher $voucher)
    {
        $code = $voucher->code;
        $voucher->delete();

        return back()->with('status', "Voucher {$code} berhasil dihapus.");
    }
}