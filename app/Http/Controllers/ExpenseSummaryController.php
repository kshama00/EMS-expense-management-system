<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Expense;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ExpenseSummaryController extends Controller
{
    public function index(Request $request)
    {
        $userId = 1;
        $page = (int) $request->get('page', 1);
        $monthsPerPage = 3;
        $maxMonths = 9;

        $monthlyData = Expense::select(
            DB::raw("DATE_FORMAT(date, '%Y-%m') as month"),
            DB::raw("SUM(amount) as submitted_amount"),
            DB::raw("SUM(CASE WHEN status = '2' THEN approved_amount ELSE 0 END) as approved_amount")
        )
            ->where('user_id', $userId)
            ->groupBy('month')
            ->orderBy('month', 'desc')
            ->limit($maxMonths)
            ->get();

        $chunks = $monthlyData->chunk($monthsPerPage);
        $currentChunk = $chunks->get($page - 1, collect());

        return view('expenses.expenseSummary', [
            'monthlyData' => $currentChunk,
            'totalPages' => $chunks->count(),
            'currentPage' => $page,
        ]);
    }
}
