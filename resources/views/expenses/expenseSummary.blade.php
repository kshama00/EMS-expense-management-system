@extends('layouts.app')

@section('styles')
    @vite('resources/css/summary.css')
@endsection

@section('content')
    <div class="container mt-4">
        <h2>My Monthly Expense Summary</h2>

        <div class="button-row">
            <a href="{{ route('expenses.create') }}" class="btn-add">Add Expense</a>
            <a href="{{ route('expenses.view') }}" class="btn-view">View Expenses</a>
        </div>

        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Month</th>
                    <th>Submitted Amount</th>
                    <th>Approved Amount</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($monthlyData as $row)
                    <tr>
                        <td data-label="Month">{{ \Carbon\Carbon::createFromFormat('Y-m', $row->month)->format('F Y') }}</td>
                        <td data-label="Submitted Amount">₹{{ number_format($row->submitted_amount, 2) }}</td>
                        <td data-label="Approved Amount">₹{{ number_format($row->approved_amount, 2) }}</td>
                        <td data-label="Action">
                            <a href="{{ route('expenses.view', ['month' => $row->month]) }}"
                                class="btn btn-sm btn-outline-primary">
                                View
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-center">No expenses found</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection