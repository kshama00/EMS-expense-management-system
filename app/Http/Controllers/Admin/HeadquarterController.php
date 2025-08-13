<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Headquarter;
use App\Models\User;
use App\Models\Expense;
use Illuminate\Support\Facades\DB;

class HeadquarterController extends Controller
{
    public function index(Request $request)
    {
        $selectedMonth = $request->input('month', now()->format('Y-m'));

        // Parse month input
        $startOfMonth = \Carbon\Carbon::parse($selectedMonth)->startOfMonth();
        $endOfMonth = \Carbon\Carbon::parse($selectedMonth)->endOfMonth();

        // Fetch and group expense counts by hq and status
        $rawData = Expense::select(
            'users.hq_code',
            'expenses.status',
            DB::raw('COUNT(*) as count')
        )
            ->join('users', 'expenses.user_id', '=', 'users.id')
            ->whereBetween('expenses.created_at', [$startOfMonth, $endOfMonth])
            ->groupBy('users.hq_code', 'expenses.status')
            ->get();

        // Format into $headquarters array
        $headquarters = [];
        foreach ($rawData as $row) {
            $hq = $row->hq_code ?? 'Unknown';
            $statusMap = [
                '1' => 'Pending',
                '2' => 'Approved',
                '3' => 'Rejected',
                '4' => 'Partially Approved',
                '5' => 'Cancelled',
            ];
            $status = $statusMap[(string) $row->status] ?? 'unknown';

            if (!isset($headquarters[$hq])) {
                $headquarters[$hq] = [
                    'total' => 0,
                    'statuses' => [],
                ];
            }

            $headquarters[$hq]['statuses'][$status] = $row->count;
            $headquarters[$hq]['total'] += $row->count;
        }

        // Optionally fetch HQ names
        $hqNames = Headquarter::pluck('hq_name', 'hq_code')->toArray();

        return view('admin.hq', compact('headquarters', 'hqNames', 'selectedMonth'));
    }
}