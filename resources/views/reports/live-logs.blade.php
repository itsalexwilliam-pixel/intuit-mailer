@extends('layouts.app')

@section('page_title', 'Live Logs')

@section('content')
<div class="space-y-6">
    <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-2 shadow-sm">
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('reports.single-email') }}"
               class="inline-flex items-center rounded-lg px-4 py-2 text-sm font-medium border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800">
                Single Email Report
            </a>
            <a href="{{ route('reports.index') }}"
               class="inline-flex items-center rounded-lg px-4 py-2 text-sm font-medium border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800">
                Campaign Report
            </a>
            <a href="{{ route('reports.warmup') }}"
               class="inline-flex items-center rounded-lg px-4 py-2 text-sm font-medium border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800">
                Warmup Report
            </a>
            <a href="{{ route('reports.smtp') }}"
               class="inline-flex items-center rounded-lg px-4 py-2 text-sm font-medium border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800">
                SMTP Report
            </a>
            <a href="{{ route('reports.live-logs') }}"
               class="inline-flex items-center rounded-lg px-4 py-2 text-sm font-medium bg-indigo-600 text-white border border-indigo-600">
                Live Logs
            </a>
        </div>
    </div>

    <form method="GET" action="{{ route('reports.live-logs') }}" class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 sm:p-5 shadow-sm">
        <div class="grid grid-cols-1 md:grid-cols-7 gap-3">
            <div>
                <label for="date_range" class="block text-xs font-medium text-slate-600 dark:text-slate-300 mb-1">Date Range</label>
                <select id="date_range" name="date_range" class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm px-3 py-2">
                    <option value="today" {{ ($filters['date_range'] ?? '7d') === 'today' ? 'selected' : '' }}>Today</option>
                    <option value="7d" {{ ($filters['date_range'] ?? '7d') === '7d' ? 'selected' : '' }}>Last 7 days</option>
                    <option value="30d" {{ ($filters['date_range'] ?? '7d') === '30d' ? 'selected' : '' }}>Last 30 days</option>
                    <option value="custom" {{ ($filters['date_range'] ?? '7d') === 'custom' ? 'selected' : '' }}>Custom</option>
                </select>
            </div>
            <div>
                <label for="from" class="block text-xs font-medium text-slate-600 dark:text-slate-300 mb-1">From</label>
                <input id="from" type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm px-3 py-2">
            </div>
            <div>
                <label for="to" class="block text-xs font-medium text-slate-600 dark:text-slate-300 mb-1">To</label>
                <input id="to" type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm px-3 py-2">
            </div>
            <div>
                <label for="status" class="block text-xs font-medium text-slate-600 dark:text-slate-300 mb-1">Status</label>
                <select id="status" name="status" class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm px-3 py-2">
                    <option value="all" {{ ($filters['status'] ?? 'all') === 'all' ? 'selected' : '' }}>All</option>
                    <option value="queued" {{ ($filters['status'] ?? '') === 'queued' ? 'selected' : '' }}>Queued</option>
                    <option value="pending" {{ ($filters['status'] ?? '') === 'pending' ? 'selected' : '' }}>Pending</option>
                    <option value="sent" {{ ($filters['status'] ?? '') === 'sent' ? 'selected' : '' }}>Sent</option>
                    <option value="failed" {{ ($filters['status'] ?? '') === 'failed' ? 'selected' : '' }}>Failed</option>
                </select>
            </div>
            <div>
                <label for="campaign_id" class="block text-xs font-medium text-slate-600 dark:text-slate-300 mb-1">Campaign</label>
                <select id="campaign_id" name="campaign_id" class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm px-3 py-2">
                    <option value="">All campaigns</option>
                    @foreach($campaignOptions as $campaign)
                        <option value="{{ $campaign->id }}" {{ (int) ($filters['campaign_id'] ?? 0) === (int) $campaign->id ? 'selected' : '' }}>
                            {{ $campaign->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="smtp_id" class="block text-xs font-medium text-slate-600 dark:text-slate-300 mb-1">SMTP</label>
                <select id="smtp_id" name="smtp_id" class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm px-3 py-2">
                    <option value="">All SMTP</option>
                    @foreach($smtpOptions as $smtp)
                        <option value="{{ $smtp->id }}" {{ (int) ($filters['smtp_id'] ?? 0) === (int) $smtp->id ? 'selected' : '' }}>
                            {{ $smtp->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="recipient" class="block text-xs font-medium text-slate-600 dark:text-slate-300 mb-1">Recipient</label>
                <input id="recipient" type="text" name="recipient" value="{{ $filters['recipient'] ?? '' }}" placeholder="search@email.com" class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm px-3 py-2">
            </div>
        </div>
        <div class="mt-3 flex flex-wrap gap-2">
            <button type="submit" class="inline-flex items-center rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-sm px-4 py-2">Apply</button>
            <a href="{{ route('reports.live-logs') }}" class="inline-flex items-center rounded-lg border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 text-sm px-4 py-2">Reset</a>
            <button type="button" id="toggle-refresh" class="inline-flex items-center rounded-lg border border-emerald-300 text-emerald-700 hover:bg-emerald-50 dark:border-emerald-700 dark:text-emerald-300 dark:hover:bg-emerald-900/30 text-sm px-4 py-2">
                Auto Refresh: ON (10s)
            </button>
        </div>
    </form>

    <div class="grid grid-cols-2 xl:grid-cols-4 gap-4">
        <x-saas-stat-card title="Total Logs" :value="$summary['total'] ?? 0" />
        <x-saas-stat-card title="Queued/Pending" :value="$summary['queued'] ?? 0" />
        <x-saas-stat-card title="Sent" :value="$summary['sent'] ?? 0" />
        <x-saas-stat-card title="Failed" :value="$summary['failed'] ?? 0" />
    </div>

    <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 shadow-sm">
        <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-100 mb-4">Sending Live Logs</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800">
                        <th class="py-2 pr-3">Time</th>
                        <th class="py-2 pr-3">Recipient</th>
                        <th class="py-2 pr-3">Subject</th>
                        <th class="py-2 pr-3">Campaign</th>
                        <th class="py-2 pr-3">SMTP</th>
                        <th class="py-2 pr-3">Status</th>
                        <th class="py-2 pr-3">Error</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                        <tr class="border-b border-slate-100 dark:border-slate-800/70">
                            <td class="py-2 pr-3">{{ $log->sent_at ?: $log->created_at }}</td>
                            <td class="py-2 pr-3">{{ $log->email }}</td>
                            <td class="py-2 pr-3 max-w-[260px] truncate" title="{{ $log->subject }}">{{ $log->subject ?: '—' }}</td>
                            <td class="py-2 pr-3">{{ $log->campaign_name ?: 'N/A' }}</td>
                            <td class="py-2 pr-3">{{ $log->smtp_name ? $log->smtp_name . ' (' . $log->smtp_host . ')' : 'N/A' }}</td>
                            <td class="py-2 pr-3">
                                @if($log->status === 'sent')
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">Sent</span>
                                @elseif(in_array($log->status, ['queued', 'pending']))
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300">{{ ucfirst($log->status) }}</span>
                                @elseif($log->status === 'failed')
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-300">Failed</span>
                                @else
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300">{{ ucfirst($log->status ?? 'unknown') }}</span>
                                @endif
                            </td>
                            <td class="py-2 pr-3 max-w-[340px] truncate" title="{{ $log->last_error }}">{{ $log->last_error ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="py-6 text-center text-slate-500">No logs found for selected filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">
            {{ $logs->links() }}
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const dateRange = document.getElementById('date_range');
    const fromInput = document.getElementById('from');
    const toInput = document.getElementById('to');
    const toggleBtn = document.getElementById('toggle-refresh');

    function syncCustomDate() {
        const isCustom = dateRange?.value === 'custom';
        if (fromInput) fromInput.disabled = !isCustom;
        if (toInput) toInput.disabled = !isCustom;
    }

    let autoRefresh = true;
    let timer = setInterval(() => {
        if (autoRefresh) {
            window.location.reload();
        }
    }, 10000);

    toggleBtn?.addEventListener('click', function () {
        autoRefresh = !autoRefresh;
        this.textContent = autoRefresh ? 'Auto Refresh: ON (10s)' : 'Auto Refresh: OFF';
    });

    dateRange?.addEventListener('change', syncCustomDate);
    syncCustomDate();
})();
</script>
@endpush
