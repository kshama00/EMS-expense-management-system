@extends('layouts.app')
@php
    $today = date('Y-m-d');
    $firstOfMonth = date('Y-m-01');
    $isResubmitting = isset($prefillExpense);
    $globalDate = $isResubmitting
        ? \Carbon\Carbon::parse($prefillExpense['date'])->format('Y-m-d')
        : old('global_date', $today);
@endphp

@section('styles')
    @vite('resources/css/form.css')
@endsection

@section('content')


    <div class="form-wrapper">
        <h2 class="form-title"> {{ $isResubmitting ? "Resubmit Expense" : 'Add Expenses' }}</h2>

        <div class="view-expense-wrapper">
            <a href="{{ route('expenses.view') }}" class="view-expense-button">View Expenses</a>
        </div><br>

        <div id="capacity-info">
            <p>Monthly Limit: ₹<span id="max-limit">{{ $maxLimit}}</span></p>
            <p>Used This Month: ₹<span id="used-amount">{{ $usedAmount }}</span></p>
            <p>Remaining: ₹<span id="remaining-amount">{{ $remaining }}</span></p>
        </div>


        <div class="global-date-wrapper">
            <label for="global-date">Select Date:</label>
            <input type="date" id="global-date" name="global_date" min="{{ $firstOfMonth }}" max="{{ $today }}"
                value="{{ $isResubmitting ? \Carbon\Carbon::parse($prefillExpense['date'])->format('Y-m-d') : old('global_date', $today) }}"
                {{ $isResubmitting ? 'readonly' : '' }} onclick="this.showPicker()" style="cursor: pointer;" />
        </div><br>

        <form id="expense-form" action="{{ route('expenses.store') }}" method="POST" enctype="multipart/form-data">
            @csrf

            @if(!empty($resubmitId))
                <input type="hidden" name="original_expense_id" value="{{ $resubmitId }}">
            @endif

            <div id="expense-rows">
                <div class="expense-row">
                    <button type="button" class="remove-row-btn" style="display: none;">
                        <i class="fas fa-trash"></i>
                    </button>
                    <div class="form">
                        <label>Type</label>
                        <select name="expenses[0][type]" {{ $isResubmitting ? 'disabled' : '' }} required>
                            <option value="">Select Type</option>
                            <option value="Travel" {{ $isResubmitting && $prefillExpense['type'] === 'Travel' ? 'selected' : '' }}>Travel</option>
                            <option value="Lodging" {{ $isResubmitting && $prefillExpense['type'] === 'Lodging' ? 'selected' : '' }}>Lodging</option>
                            <option value="Food" {{ $isResubmitting && $prefillExpense['type'] === 'Food' ? 'selected' : '' }}>Food</option>
                            <option value="Mobile" {{ $isResubmitting && $prefillExpense['type'] === 'Mobile' ? 'selected' : '' }}>Mobile</option>
                            <option value="Printing" {{ $isResubmitting && $prefillExpense['type'] === 'Printing' ? 'selected' : '' }}>Printing</option>
                            <option value="Miscellaneous" {{ $isResubmitting && $prefillExpense['type'] === 'Miscellaneous' ? 'selected' : '' }}>Miscellaneous</option>
                        </select>
                    </div>

                    @if ($isResubmitting)
                        <input type="hidden" name="expenses[0][type]" value="{{ $prefillExpense['type'] }}">
                    @endif

                    {{-- Travel Subtype --}}
                    <div class="form travel" style="display: none;">
                        <label>Subtype</label>
                        <select name="expenses[0][subtype]" {{ $isResubmitting ? 'disabled' : '' }}>
                            <option value="">Select subtype</option>
                            <option value="2_wheeler" {{ $isResubmitting && ($prefillExpense['subtype'] ?? '') === '2_wheeler' ? 'selected' : '' }}>Bike</option>
                            <option value="bus" {{ $isResubmitting && ($prefillExpense['subtype'] ?? '') === 'bus' ? 'selected' : '' }}>Bus</option>
                            <option value="cab" {{ $isResubmitting && ($prefillExpense['subtype'] ?? '') === 'cab' ? 'selected' : '' }}>Cab</option>
                            <option value="train" {{ $isResubmitting && ($prefillExpense['subtype'] ?? '') === 'train' ? 'selected' : '' }}>Train</option>

                        </select>
                        @if ($isResubmitting)
                            <input type="hidden" name="expenses[0][subtype]" value="{{ $prefillExpense['subtype'] ?? '' }}">
                        @endif
                    </div>
                    {{-- Two Wheeler Fields --}}
                    <div class="form twoWheeler"
                        style="{{ $isResubmitting && ($prefillExpense['subtype'] ?? '') === '2_wheeler' ? '' : 'display: none;' }}">
                        <label>Start Reading</label>
                        <input type="number" name="expenses[0][start_reading]"
                            value="{{ $isResubmitting ? ($prefillExpense['start_reading'] ?? '') : '' }}" />
                    </div>
                    <div class="form twoWheeler"
                        style="{{ $isResubmitting && ($prefillExpense['subtype'] ?? '') === '2_wheeler' ? '' : 'display: none;' }}">
                        <label>End Reading</label>
                        <input type="number" name="expenses[0][end_reading]"
                            value="{{ $isResubmitting ? ($prefillExpense['end_reading'] ?? '') : '' }}" />
                    </div>

                    {{-- Four Wheeler Fields --}}
                    <div class="form fourWheeler" style="display: none;">
                        <label>From Location</label>
                        <input type="text" name="expenses[0][from_location]" placeholder="Enter from location"
                            value="{{ $isResubmitting ? ($prefillExpense['from_location'] ?? '') : '' }}" />
                    </div>

                    <div class="form fourWheeler" style="display: none;">
                        <label>To Location</label>
                        <input type="text" name="expenses[0][to_location]" placeholder="Enter to location"
                            value="{{ $isResubmitting ? ($prefillExpense['to_location'] ?? '') : '' }}" />
                    </div>

                    {{-- Lodging Fields --}}
                    <div class="form lodging" style="display: none;">
                        <label>Checkin Date</label>
                        <input type="date" name="expenses[0][checkin_date]" class="limit-dates" value="{{ $globalDate }}"
                            readonly />
                    </div>


                    <div class="form lodging" style="display: none;">
                        <label>Checkout Date</label>
                        <input type="date" name="expenses[0][checkout_date]" class="limit-dates" min="{{ $today}}"
                            value="{{ $isResubmitting && isset($prefillExpense['checkout_date']) ? \Carbon\Carbon::parse($prefillExpense['checkout_date'])->format('Y-m-d') : '$globalDate' }}"
                            onclick="this.showPicker()" style="cursor: pointer;" />
                    </div>


                    <div class="form">
                        <label>Amount</label>
                        <input type="number" step="0.01" name="expenses[0][amount]" placeholder="Enter amount"
                            value="{{ $isResubmitting ? $prefillExpense['amount'] : '' }}" required>
                    </div>

                    <div class="form">
                        <label>Attachments (max 2 files)</label>
                        <input type="file" name="expenses[0][attachments][]" class="file-input"
                            value="{{ $isResubmitting ? $prefillExpense['amount'] : '' }}" multiple required>
                        <ul class="file-names" id="selected_files_0"></ul>
                    </div>

                    <div class="form">
                        <label>Remarks</label>
                        <input type="text" name="expenses[0][remarks]" placeholder="Enter remarks"
                            value="{{ $isResubmitting ? $prefillExpense['remarks'] : '' }}">
                    </div>

                    <div class="form">
                        <label>Location</label>
                        <input type="text" id="location" name="expenses[0][location]" placeholder="Fetching location..."
                            required>
                    </div>
                </div>
            </div>

            <div class="form-actions dual-buttons">


                <button type="button" id="addRow" {{ $isResubmitting ? 'disabled' : '' }}>
                    <i class="fas fa-plus"></i>
                </button>

                <button type="submit" id="submit">
                    Submit
                </button>

            </div>

        </form>
    </div>
@endsection

@section('scripts')
    <script>
        window.remainingLimit = {{ $remaining ?? 0 }};
        window.hasMobile = {{ $hasMobile ? 'true' : 'false' }};
        window.existingExpenses = @json($existingExpenses ?? []);


    </script>

    @if(isset($prefillExpense))
        <script>

            window.resubmitData = @json($prefillExpense);
        </script>
    @endif

    <script>
        const resubmitData = @json($isResubmitting ? $prefillExpense : null);
    </script>

    @vite('resources/js/form.js')
@endsection