<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\Request;

class UserWiseSalesReportController extends Controller
{
    public function filters()
    {
        return response()->json([
            'branches' => Branch::orderBy('name')->get(['id', 'name']),
            'users'    => User::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function data(Request $request)
    {
        $query = Booking::with(['user.branch', 'invoice'])
            ->where('is_cancelled', false)
            ->whereHas('invoice');

        if ($request->filled('branch_id')) {
            $userIds = User::where('branch_id', $request->branch_id)->pluck('id');
            $query->whereIn('user_id', $userIds);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $bookings = $query->get();

        $grouped = $bookings->groupBy('user_id');

        $grandTotalValue = 0;

        $rows = $grouped->map(function ($userBookings) use (&$grandTotalValue) {
            $user = $userBookings->first()->user;
            $totalValue = (float) $userBookings->sum(fn($b) => $b->invoice?->total_amount ?? 0);
            $grandTotalValue += $totalValue;

            return [
                'user_id'             => (int) $user->id,
                'user'                => $user->name,
                'branch'              => $user->branch?->name ?? 'Central',
                'total_invoices'      => $userBookings->count(),
                'total_passengers'    => $userBookings->sum('pax_qty'),
                'total_package_value' => $totalValue,
            ];
        })->values();

        $rawPercents = $rows->map(
            fn($r) => $grandTotalValue > 0 ? ($r['total_package_value'] / $grandTotalValue) * 100 : 0
        );

        $roundedPercents = $rawPercents->map(fn($p) => round($p, 2));
        $remainders = $rawPercents->map(fn($raw, $i) => $raw - $roundedPercents[$i]);
        $diff = round(100 - $roundedPercents->sum(), 2);

        if ($diff != 0) {
            $largestIndex = $remainders->search($remainders->max());
            $roundedPercents[$largestIndex] = round($roundedPercents[$largestIndex] + $diff, 2);
        }

        $rows = $rows->map(fn($row, $i) => array_merge($row, [
            'sales_percent' => (float) $roundedPercents[$i],
        ]));

        $summary = [
            'total_users'         => $rows->count(),
            'total_branches'      => $rows->pluck('branch')->unique()->count(),
            'total_invoices'      => $rows->sum('total_invoices'),
            'total_passengers'    => $rows->sum('total_passengers'),
            'total_package_value' => $grandTotalValue,
        ];

        return response()->json([
            'rows'    => $rows,
            'summary' => $summary,
        ]);
    }
}
