<?php

namespace App\Services;

use App\Models\Voucher;

class VoucherService
{
    public function generateVoucherNumber(): string
    {
        $prefix = 'VCH-' . date('Ymd');

        $lastVoucher = Voucher::where('voucher_id', 'like', "{$prefix}%")
            ->orderBy('voucher_id', 'desc')
            ->first();

        $sequence = $lastVoucher
            ? intval(substr($lastVoucher->voucher_id, -4)) + 1
            : 1;

        return $prefix . '-' . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }

    public function createVoucher(array $data): Voucher
    {
        $data['voucher_id'] = $this->generateVoucherNumber();
        return Voucher::create($data);
    }
}