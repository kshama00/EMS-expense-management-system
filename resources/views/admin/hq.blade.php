@extends('layouts.app')

@section('styles')
    @vite('resources/css/hq.css')
@endsection

@section('content')
    <div class="container mt-4">
        <h2>Headquarter Status Overview</h2>

        <form method="GET" class="mb-3">
            <label>Filter by Month:</label>
            <input type="month" name="month" value="{{ $selectedMonth }}">
            <button type="submit" class="btn btn-sm btn-primary">Apply</button>
        </form>

        <hr>

        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Headquarter</th>
                    <th>Total Expense Count</th>
                    <th>Approved </th>
                    <th>Partially Approved</th>
                    <th>Pending</th>
                    <th>Rejected</th>
                    <th>Overall Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($headquarters as $hqId => $data)
                    @php
                        $statuses = $data['statuses'];
                        $statusKeys = array_keys($statuses);
                        $statusSet = collect($statusKeys)->map(fn($s) => strtolower($s))->unique();

                        if ($statusSet->count() === 1) {
                            $overall = ucfirst($statusSet->first());
                        } elseif ($statusSet->contains('pending')) {
                            $overall = 'Pending';
                        } else {
                            $overall = 'Partially Approved';
                        }

                        $monthParam = $selectedMonth ?? now()->format('Y-m'); 
                    @endphp
                    <tr>
                        <td>{{ $hqNames[$hqId] ?? 'Unknown' }}</td>
                        <td>{{ $data['total'] }}</td>
                        <td>{{ $statuses['Approved'] ?? 0 }}</td>
                        <td>{{ $statuses['Partially_approved'] ?? 0 }}</td>
                        <td>{{ $statuses['Pending'] ?? 0 }}</td>
                        <td>{{ $statuses['Rejected'] ?? 0 }}</td>
                        <td><strong>{{ $overall }}</strong></td>
                        <td>
                            <a href="{{ route('admin.approval', ['hq' => $hqId, 'month' => $monthParam]) }}"
                                class="btn btn-sm btn-outline-primary">
                                View Details
                            </a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

    </div>
@endsection