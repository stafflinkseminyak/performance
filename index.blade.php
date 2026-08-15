@extends('admin.layout')

@php
    $isViewingOther = $viewingOther ?? false;
    $viewedName = $isViewingOther ? ($employee->full_name ?? $employee->first_name) : null;
@endphp

@section('title', 'Performance')
@section('page-title', $isViewingOther ? $viewedName . '’s Performance' : 'My Performance')
@php
    $pageDesc = ($divisionName ?? '') === 'Finance & Accounts'
        ? 'Track invoice collections, revenue collected, and outstanding receivables. 💰'
        : ($isViewingOther
            ? 'Placements, revenue, client growth, and KPI progress for ' . $viewedName . '. 💪'
            : 'Track your placements, revenue, and client growth — keep pushing! 💪');
@endphp
@section('page-description', $pageDesc)

@section('content')
<div style="max-width:1100px;">

    @if($isViewingOther)
        <a href="{{ route('admin.linkers-hub.employee-profile', $employee->id) }}"
           style="display:inline-flex;align-items:center;gap:6px;font-size:0.82rem;font-weight:600;color:#1f5f46;text-decoration:none;margin-bottom:16px;">
            <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            Back to {{ $viewedName }}'s profile
        </a>
    @endif

    {{-- Company-wide YTD Dashboard — super_admin only. Rendered here, BEFORE the
         dashboardAvailable branch below, so it always shows for a super_admin
         regardless of whether the viewed account has a division-specific
         dashboard at all (a director with no division still sees this). --}}
    @if($isSuperAdmin ?? false)
        @include('admin.kpi.ytd-dashboard-cards', ['cards' => $ytdDashboardCards ?? []])
        @if(!empty($ytdDashboardCards) && ($dashboardAvailable ?? true))
            <div style="display:flex; align-items:center; gap:10px; margin:20px 0 22px;">
                <span style="font-size:0.72rem; font-weight:700; color:#9ca3af; text-transform:uppercase; letter-spacing:0.05em; white-space:nowrap;">{{ $divisionName ?? 'Personal' }} dashboard</span>
                <div style="flex:1; height:1px; background:#e5e7eb;"></div>
            </div>
        @endif
    @endif

    @if(!($dashboardAvailable ?? true))
        <div style="background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:48px 24px; text-align:center;">
            <p style="font-size:1rem; font-weight:700; color:#1b4332; margin:0 0 8px;">🚧 Performance dashboard not available yet</p>
            <p style="font-size:0.85rem; color:#6b7280; margin:0; max-width:420px; margin-left:auto; margin-right:auto;">
                @if($divisionName)
                    There isn't a Performance dashboard built for the <strong>{{ $divisionName }}</strong> division yet.
                @elseif($isViewingOther)
                    {{ $viewedName }} isn't linked to a division yet, so there's no Performance dashboard to show.
                @else
                    Your account isn't linked to a division yet, so there's no Performance dashboard to show.
                @endif
            </p>
        </div>
    @else

    <!-- ============ HEADER: division (left) + month picker + export (right) ============ -->
    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:22px; flex-wrap:wrap; gap:12px;">
        <div style="font-size:1.05rem; font-weight:800; color:#1f5f46; letter-spacing:0.02em;">{{ $divisionName }}</div>
        <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
            <div class="perf-no-print" style="position:relative; display:inline-block;">
                <button type="button" onclick="togglePerfMonthPicker(event)" id="perfMonthPickerBtn" style="display:flex; align-items:center; gap:8px; background:#fff; border:1px solid #d1d5db; border-radius:10px; padding:10px 18px; font-size:0.9rem; font-weight:700; color:#1b4332; cursor:pointer;">
                    <svg style="width:16px; height:16px;" fill="none" stroke="#1f5f46" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    {{ $selectedMonth->format('F Y') }}
                    <svg style="width:14px; height:14px;" fill="none" stroke="#9ca3af" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>

                <div id="perfMonthPickerPanel" class="hidden" style="position:absolute; right:0; top:calc(100% + 8px); background:#fff; border:1px solid #e5e7eb; border-radius:12px; box-shadow:0 12px 28px rgba(0,0,0,0.14); padding:16px; width:280px; z-index:400;">
                    @php $currentYear = (int) now()->format('Y'); @endphp
                    <div style="display:flex; gap:6px; margin-bottom:12px;">
                        @for ($y = $currentYear - 2; $y <= $currentYear; $y++)
                            <button type="button" onclick="showPerfPickerYear({{ $y }})" id="perfYearTab-{{ $y }}" class="perf-year-tab {{ $y === $selectedMonth->year ? 'active' : '' }}" style="flex:1; padding:6px 0; font-size:0.8rem; font-weight:700; border:none; border-radius:8px; cursor:pointer;">{{ $y }}</button>
                        @endfor
                    </div>

                    @for ($y = $currentYear - 2; $y <= $currentYear; $y++)
                        <div id="perfMonthGrid-{{ $y }}" style="display:{{ $y === $selectedMonth->year ? 'grid' : 'none' }}; grid-template-columns:repeat(3,1fr); gap:6px;">
                            @for ($m = 1; $m <= 12; $m++)
                                @php
                                    $cellDate = \Carbon\Carbon::create($y, $m, 1);
                                    $isFuture = $cellDate->greaterThan(now()->startOfMonth());
                                    $isSelected = $y === $selectedMonth->year && $m === $selectedMonth->month;
                                @endphp
                                @if($isFuture)
                                    <span class="perf-month-cell future">{{ $cellDate->format('M') }}</span>
                                @else
                                    <a href="{{ route('admin.performance.index', ['month' => sprintf('%04d-%02d', $y, $m)]) }}" class="perf-month-cell {{ $isSelected ? 'selected' : '' }}">{{ $cellDate->format('M') }}</a>
                                @endif
                            @endfor
                        </div>
                    @endfor
                </div>
            </div>

            <button type="button" onclick="window.print()" class="perf-no-print" style="display:flex; align-items:center; gap:6px; height:40px; padding:0 16px; font-size:0.85rem; font-weight:700; color:#fff; background-color:#1f5f46; border:none; border-radius:10px; cursor:pointer;" onmouseover="this.style.backgroundColor='#287854'" onmouseout="this.style.backgroundColor='#1f5f46'">
                <svg style="width:15px; height:15px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a1 1 0 001-1v-4a1 1 0 00-1-1H9a1 1 0 00-1 1v4a1 1 0 001 1zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                Export / Print
            </button>
        </div>
    </div>

    <style>
        .perf-year-tab { background:#f3f4f6; color:#6b7280; }
        .perf-year-tab.active { background:#1f5f46; color:#fff; }
        .perf-month-cell { display:block; padding:8px 0; text-align:center; font-size:0.8rem; font-weight:600; border-radius:8px; text-decoration:none; color:#374151; }
        .perf-month-cell:hover { background:#eaf5f0; }
        .perf-month-cell.selected { background:#1f5f46; color:#fff; }
        .perf-month-cell.selected:hover { background:#1f5f46; }
        .perf-month-cell.future { color:#d1d5db; }
        @media print {
            .perf-no-print { display: none !important; }
            .perf-print-only { display: block !important; margin-bottom: 16px; font-size: 0.85rem; color: #6b7280; }
            aside { display: none !important; }
            div.flex.h-screen { height: auto !important; }
            div.flex-1.flex.flex-col.overflow-hidden { overflow: visible !important; height: auto !important; }
        }
    </style>
    <div class="perf-print-only" style="display:none;">Report for {{ $selectedMonth->format('F Y') }} — printed {{ now()->format('d M Y, H:i') }}</div>

    <script>
        function togglePerfMonthPicker(e) {
            e.stopPropagation();
            document.getElementById('perfMonthPickerPanel').classList.toggle('hidden');
        }
        function showPerfPickerYear(year) {
            document.querySelectorAll('[id^="perfMonthGrid-"]').forEach(el => el.style.display = 'none');
            const grid = document.getElementById('perfMonthGrid-' + year);
            if (grid) grid.style.display = 'grid';
            document.querySelectorAll('.perf-year-tab').forEach(el => el.classList.remove('active'));
            const tab = document.getElementById('perfYearTab-' + year);
            if (tab) tab.classList.add('active');
        }
        document.addEventListener('click', function (e) {
            const panel = document.getElementById('perfMonthPickerPanel');
            const btn = document.getElementById('perfMonthPickerBtn');
            if (panel && !panel.classList.contains('hidden') && !panel.contains(e.target) && !btn.contains(e.target)) {
                panel.classList.add('hidden');
            }
        });
    </script>

    {{-- ====================================================================
         HUMAN RESOURCES & RECRUITMENT DASHBOARD
         ==================================================================== --}}
    @if($divisionName === 'Human Resources & Recruitment')

    <!-- ============ 1. PLACEMENTS KPI (blue accent) ============ -->
    <div style="background:#fff; border:1px solid #e5e7eb; border-top:4px solid #1d4ed8; border-radius:12px; padding:22px 24px; margin-bottom:20px;">
        <div style="display:flex; align-items:center; margin-bottom:18px;">
            <div style="width:28px; height:28px; border-radius:8px; background:#dbeafe; display:flex; align-items:center; justify-content:center; margin-right:10px; flex-shrink:0;">
                <svg style="width:15px; height:15px; color:#1d4ed8;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            </div>
            <h3 style="font-size:0.95rem; font-weight:700; color:#1b4332; margin:0;">🎯 Placements</h3>
            <span style="font-size:0.75rem; color:#9ca3af; margin-left:8px;">KPI target: {{ $monthlyTarget }} placements / month — you've got this! 🚀</span>
        </div>

        <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:16px;">
            @php
                $periods = [
                    ['label' => 'This Month',   'value' => $placementsThisMonth,   'target' => $monthlyTarget],
                    ['label' => 'This Quarter', 'value' => $placementsThisQuarter, 'target' => $quarterlyTarget],
                    ['label' => 'This Year',    'value' => $placementsThisYear,    'target' => $yearlyTarget],
                ];
            @endphp
            @foreach($periods as $p)
                @php
                    $pct       = $p['target'] > 0 ? min(100, round(($p['value'] / $p['target']) * 100)) : 0;
                    $remaining = max(0, $p['target'] - $p['value']);
                @endphp
                <div style="border:1px solid #eef0f2; border-left:3px solid #1d4ed8; background:#eff6ff; border-radius:10px; padding:16px;">
                    <div style="font-size:0.72rem; font-weight:600; color:#6b7280; text-transform:uppercase; letter-spacing:0.03em; margin-bottom:8px;">{{ $p['label'] }}</div>
                    <div style="display:flex; align-items:baseline; gap:6px; margin-bottom:10px;">
                        <span style="font-size:1.8rem; font-weight:700; color:#1d4ed8;">{{ $p['value'] }}</span>
                        <span style="font-size:0.85rem; color:#9ca3af;">/ {{ $p['target'] }}</span>
                    </div>
                    <div style="background:#fff; border-radius:20px; height:8px; overflow:hidden;">
                        <div style="background:#1d4ed8; width:{{ $pct }}%; height:100%; border-radius:20px;"></div>
                    </div>
                    @if($p['value'] >= $p['target'])
                        <div style="font-size:0.75rem; color:#15803d; font-weight:600; margin-top:8px;">🎉 Target reached! You crushed it!</div>
                    @else
                        <div style="font-size:0.75rem; font-weight:600; margin-top:8px; color:#d97706;">
                            @if($pct >= 80)
                                💪 {{ $pct }}% achieved — almost there! {{ $remaining }} to go!
                            @elseif($pct >= 50)
                                💪 {{ $pct }}% achieved — halfway and pushing! {{ $remaining }} to go
                            @elseif($pct >= 20)
                                💪 {{ $pct }}% achieved — keep the momentum! {{ $remaining }} to go
                            @else
                                💪 {{ $pct }}% achieved — let's get moving! {{ $remaining }} to go
                            @endif
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    <!-- ============ 2. VACANCY DURATION (amber/gold accent) ============ -->
    <div style="background:#fff; border:1px solid #e5e7eb; border-top:4px solid #D4A017; border-radius:12px; padding:22px 24px; margin-bottom:20px;">
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:16px;">
            <div style="display:flex; align-items:center;">
                <div style="width:28px; height:28px; border-radius:8px; background:#fef3c7; display:flex; align-items:center; justify-content:center; margin-right:10px; flex-shrink:0;">
                    <svg style="width:15px; height:15px; color:#b45309;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <h3 style="font-size:0.95rem; font-weight:700; color:#1b4332; margin:0;">⏳ Open Vacancies</h3>
            </div>
            @if($overdueVacancyCount > 0)
                <div style="display:flex; flex-direction:column; align-items:flex-end; gap:3px;">
                    <span style="font-size:0.78rem; padding:2px 10px; border-radius:20px; font-weight:600; background:#fef3c7; color:#92400e;">⚠️ {{ $overdueVacancyCount }} over 3 months</span>
                    <span style="font-size:0.7rem; color:#b45309;">These need your attention! ⏰</span>
                </div>
            @else
                <span style="font-size:0.78rem; padding:2px 10px; border-radius:20px; font-weight:600; background:#dcfce7; color:#15803d;">✅ All on track</span>
            @endif
        </div>

        <div style="display:flex; flex-direction:column; gap:8px;">
            @forelse($openVacancies as $v)
                <div style="display:flex; align-items:center; justify-content:space-between; padding:12px 14px; border-radius:8px; background:{{ $v->is_overdue ? '#fef2f2' : '#fffbeb' }}; border:1px solid {{ $v->is_overdue ? '#fecaca' : '#fde68a' }};">
                    <div>
                        <div style="font-size:0.88rem; font-weight:600; color:#1b4332;">{{ $v->role_title }} — {{ $v->company }}</div>
                        <div style="font-size:0.77rem; color:{{ $v->is_overdue ? '#991b1b' : '#92400e' }};">
                            Open since {{ $v->opened_at->format('M j, Y') }} ·
                            @if($v->months_open >= 1)
                                {{ $v->months_open }} {{ Str::plural('month', $v->months_open) }} open
                            @else
                                {{ $v->days_open }} {{ Str::plural('day', $v->days_open) }} open
                            @endif
                        </div>
                    </div>
                    @if($v->is_overdue)
                        <span style="font-size:0.75rem; font-weight:700; color:#dc2626;">⚠ Overdue — take action!</span>
                    @else
                        <span style="font-size:0.75rem; font-weight:600; color:#b45309;">🔄 In progress</span>
                    @endif
                </div>
            @empty
                <p style="font-size:0.85rem; color:#9ca3af; text-align:center; padding:20px 0;">🎉 No open vacancies right now — you're all caught up! Great work.</p>
            @endforelse
        </div>
    </div>

    <!-- ============ 3. REVENUE (indigo/purple accent) ============ -->
    <div style="background:#fff; border:1px solid #e5e7eb; border-top:4px solid #7c3aed; border-radius:12px; padding:22px 24px; margin-bottom:20px;">
        <div style="display:flex; align-items:center; margin-bottom:16px;">
            <div style="width:28px; height:28px; border-radius:8px; background:#f5f3ff; display:flex; align-items:center; justify-content:center; margin-right:10px; flex-shrink:0;">
                <svg style="width:15px; height:15px; color:#7c3aed;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <h3 style="font-size:0.95rem; font-weight:700; color:#1b4332; margin:0;">💰 Revenue Generated</h3>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:18px;">
            <div style="border:1px solid #eef0f2; border-left:3px solid #7c3aed; background:#f5f3ff; border-radius:10px; padding:16px;">
                <div style="font-size:0.72rem; font-weight:600; color:#6b7280; text-transform:uppercase; letter-spacing:0.03em; margin-bottom:8px;">This Month</div>
                <div style="font-size:1.6rem; font-weight:700; color:#5b21b6;">IDR {{ number_format($revenueThisMonth, 0, ',', '.') }}</div>
                @if($revenueChangePercent !== null)
                    @if($revenueChangePercent >= 0)
                        <div style="font-size:0.75rem; font-weight:600; margin-top:4px; color:#15803d;">↑ {{ abs($revenueChangePercent) }}% vs last month 🔥</div>
                    @else
                        <div style="font-size:0.75rem; font-weight:600; margin-top:4px; color:#dc2626;">↓ {{ abs($revenueChangePercent) }}% vs last month — let us bounce back!</div>
                    @endif
                @else
                    <div style="font-size:0.75rem; color:#9ca3af; margin-top:4px;">No data from last month to compare yet.</div>
                    <div style="font-size:0.75rem; color:#7c3aed; font-weight:500; margin-top:4px;">Keep closing those deals! 💼</div>
                @endif
            </div>
            <div style="border:1px solid #eef0f2; border-left:3px solid #db2777; background:#fdf2f8; border-radius:10px; padding:16px;">
                <div style="font-size:0.72rem; font-weight:600; color:#6b7280; text-transform:uppercase; letter-spacing:0.03em; margin-bottom:8px;">Year to Date</div>
                <div style="font-size:1.6rem; font-weight:700; color:#be185d;">IDR {{ number_format($revenueYTD, 0, ',', '.') }}</div>
                <div style="font-size:0.75rem; color:#6b7280; margin-top:4px;">Jan – {{ $selectedMonth->format('M Y') }}</div>
            </div>
        </div>

        <div style="display:flex; align-items:flex-end; gap:10px; height:100px; padding:0 4px;">
            @foreach($monthlyRevenueTrend as $i => $bar)
                @php
                    $barHeight = $maxMonthlyRevenue > 0 ? max(4, round(($bar['amount'] / $maxMonthlyRevenue) * 90)) : 4;
                    $isLast    = $i === count($monthlyRevenueTrend) - 1;
                @endphp
                <div style="flex:1; display:flex; flex-direction:column; align-items:center; gap:6px;">
                    <div style="width:100%; max-width:32px; height:{{ $barHeight }}px; background:{{ $isLast ? '#7c3aed' : '#ddd6fe' }}; border-radius:4px 4px 0 0;" title="IDR {{ number_format($bar['amount'], 0, ',', '.') }}"></div>
                    <span style="font-size:0.7rem; color:#9ca3af;">{{ $bar['label'] }}</span>
                </div>
            @endforeach
        </div>
    </div>

    <!-- ============ 4. NEW CLIENTS (green accent) ============ -->
    <div style="background:#fff; border:1px solid #e5e7eb; border-top:4px solid #1f5f46; border-radius:12px; padding:22px 24px; margin-bottom:20px;">
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:4px;">
            <div style="display:flex; align-items:center;">
                <div style="width:28px; height:28px; border-radius:8px; background:#e8f5e9; display:flex; align-items:center; justify-content:center; margin-right:10px; flex-shrink:0;">
                    <svg style="width:15px; height:15px; color:#1f5f46;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                </div>
                <h3 style="font-size:0.95rem; font-weight:700; color:#1b4332; margin:0;">🤝 New Clients This Month</h3>
            </div>
            <div style="display:flex; flex-direction:column; align-items:flex-end; gap:3px;">
                <span style="font-size:0.78rem; padding:2px 10px; border-radius:20px; font-weight:600; background:#dcfce7; color:#15803d;">{{ $newClientsThisMonth->count() }} new</span>
                @if($newClientsThisMonth->count() > 0)
                    <span style="font-size:0.7rem; color:#15803d;">Great client growth! 🌱</span>
                @endif
            </div>
        </div>
        <p style="font-size:0.75rem; color:#9ca3af; margin:0 0 16px;">Clients who came in this month — track their role status and stay on top of each one. 📋</p>

        @if($newClientsThisMonth->isEmpty())
            <p style="font-size:0.85rem; color:#9ca3af; text-align:center; padding:20px 0;">No new clients assigned to you yet this month — keep reaching out! 🌱</p>
        @else
            <table style="width:100%; border-collapse:collapse;">
                <thead>
                    <tr style="text-align:left; border-bottom:1px solid #e5e7eb;">
                        <th style="padding:8px 10px; font-size:0.72rem; font-weight:700; color:#6b7280; text-transform:uppercase;">Client</th>
                        <th style="padding:8px 10px; font-size:0.72rem; font-weight:700; color:#6b7280; text-transform:uppercase;">Role Needed</th>
                        <th style="padding:8px 10px; font-size:0.72rem; font-weight:700; color:#6b7280; text-transform:uppercase;">Added</th>
                        <th style="padding:8px 10px; font-size:0.72rem; font-weight:700; color:#6b7280; text-transform:uppercase;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($newClientsThisMonth as $c)
                        @php
                            $recruitmentService = $c->services->firstWhere('service_type', 'recruitment');
                            $roleNeeded = $recruitmentService->service_details['position_needed'] ?? null;

                            if ($c->status === 'ended') {
                                $statusBadge = ['bg' => '#fee2e2', 'color' => '#991b1b', 'label' => 'Client Discontinued'];
                            } elseif ($recruitmentService?->status === 'completed') {
                                $statusBadge = ['bg' => '#dcfce7', 'color' => '#15803d', 'label' => '✅ Role Filled'];
                            } elseif ($recruitmentService?->status === 'cancelled') {
                                $statusBadge = ['bg' => '#f3f4f6', 'color' => '#6b7280', 'label' => 'Role Cancelled'];
                            } else {
                                $statusBadge = ['bg' => '#fef3c7', 'color' => '#92400e', 'label' => '🔍 Role Open'];
                            }
                        @endphp
                        <tr style="border-bottom:1px solid #f3f4f6;">
                            <td style="padding:10px; font-size:0.85rem; color:#1b4332; font-weight:600;">{{ $c->business->name ?? $c->full_name }}</td>
                            <td style="padding:10px; font-size:0.85rem; color:#374151;">{{ $roleNeeded ?: '--' }}</td>
                            <td style="padding:10px; font-size:0.85rem; color:#6b7280;">{{ $c->created_at->format('M j, Y') }}</td>
                            <td style="padding:10px;"><span style="font-size:0.75rem; padding:2px 10px; border-radius:20px; font-weight:600; background:{{ $statusBadge['bg'] }}; color:{{ $statusBadge['color'] }};">{{ $statusBadge['label'] }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <!-- ============ 5. GOALS & KPI (teal accent) ============ -->
    <div style="background:#fff; border:1px solid #e5e7eb; border-top:4px solid #1f5f46; border-radius:12px; padding:22px 24px; margin-bottom:20px;">
        <div style="display:flex; align-items:center; margin-bottom:18px;">
            <div style="width:28px; height:28px; border-radius:8px; background:#e8f5e9; display:flex; align-items:center; justify-content:center; margin-right:10px; flex-shrink:0;">
                <svg style="width:15px; height:15px; color:#1f5f46;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <h3 style="font-size:0.95rem; font-weight:700; color:#1b4332; margin:0;">🎯 Goals & KPI</h3>
            <span style="font-size:0.75rem; color:#9ca3af; margin-left:8px;">From the KPI template set for your position — keep pushing! 💪</span>
        </div>
        @include('admin.kpi.goals-cards', ['groups' => $kpiGoalGroups ?? []])
    </div>

    {{-- ====================================================================
         FINANCE & ACCOUNTS DASHBOARD
         ==================================================================== --}}
    @elseif($divisionName === 'Finance & Accounts')

    @php
        $finRevenueMonthlyTarget = 80000000; // IDR 80 juta — dummy target, ganti sesuai aktual
        $finPendingCount  = $finOutstandingInvoices->where('is_overdue', false)->count();
        $finRevenueM      = $finRevenueThisMonth >= 1000000
                            ? number_format($finRevenueThisMonth / 1000000, 1) . ' jt'
                            : number_format($finRevenueThisMonth, 0, ',', '.');
        $finRevenuePct    = $finRevenueMonthlyTarget > 0
                            ? min(100, round(($finRevenueThisMonth / $finRevenueMonthlyTarget) * 100))
                            : 0;
        $finCollectionPct = $finMonthlyTarget > 0
                            ? min(100, round(($finCollectedThisMonth / $finMonthlyTarget) * 100))
                            : 0;

        // ── Dummy goals — ganti dengan query ke tabel goals setelah struktur tabel dikonfirmasi ──
        $finGoals = [
            ['emoji' => '📥', 'title' => 'Collect 15 invoices per month',                  'current' => 9,   'target' => 15,  'unit' => 'invoices', 'status' => 'progress', 'due' => 'Jul 31, 2026'],
            ['emoji' => '⚡', 'title' => 'Process all invoices within 3 business days',    'current' => 87,  'target' => 100, 'unit' => '%',        'status' => 'progress', 'due' => 'Ongoing'],
            ['emoji' => '✅', 'title' => 'Zero invoices overdue more than 30 days',         'current' => 100, 'target' => 100, 'unit' => '%',        'status' => 'achieved', 'due' => 'Jun 30, 2026'],
            ['emoji' => '📊', 'title' => 'Reconcile all accounts by 5th of the month',     'current' => 100, 'target' => 100, 'unit' => '%',        'status' => 'achieved', 'due' => 'Jul 5, 2026'],
            ['emoji' => '📉', 'title' => 'Reduce outstanding receivables by 20%',           'current' => 35,  'target' => 100, 'unit' => '%',        'status' => 'risk',     'due' => 'Jul 31, 2026'],
            ['emoji' => '💸', 'title' => 'Complete payroll processing before 25th',         'current' => 100, 'target' => 100, 'unit' => '%',        'status' => 'achieved', 'due' => 'Jul 25, 2026'],
            ['emoji' => '📋', 'title' => 'Keep monthly budget variance below 5%',           'current' => 72,  'target' => 100, 'unit' => '%',        'status' => 'progress', 'due' => 'Jul 31, 2026'],
        ];
        $finGoalsAchieved = count(array_filter($finGoals, fn($g) => $g['status'] === 'achieved'));
        $finGoalsProgress = count(array_filter($finGoals, fn($g) => $g['status'] === 'progress'));
        $finGoalsRisk     = count(array_filter($finGoals, fn($g) => $g['status'] === 'risk'));
    @endphp

    <!-- ============================================================
         OVERVIEW
         ============================================================ -->
    <div style="display:flex; align-items:center; gap:8px; margin-bottom:16px;">
        <span style="font-size:1rem; font-weight:800; color:#1f5f46;">📊 Overview</span>
        <span style="font-size:0.8rem; color:#9ca3af;">{{ $selectedMonth->format('F Y') }}</span>
    </div>

    <!-- 4 KPI TILES -->
    <div style="display:grid; grid-template-columns:repeat(4,1fr); gap:12px; margin-bottom:20px;">

        <div style="background:#fff; border-radius:10px; border:1px solid #e5e7eb; border-top:3px solid #1d4ed8; padding:14px 16px;">
            <div style="font-size:0.72rem; font-weight:600; color:#6b7280; text-transform:uppercase; letter-spacing:0.03em; margin-bottom:6px;">📥 Invoices collected</div>
            <div style="font-size:1.6rem; font-weight:700; color:#1d4ed8; line-height:1;">{{ $finCollectedThisMonth }}</div>
            <div style="font-size:0.72rem; color:#9ca3af; margin-top:4px;">Target: {{ $finMonthlyTarget }} / month</div>
            <div style="margin-top:8px; background:#eff6ff; border-radius:20px; height:6px; overflow:hidden;">
                <div style="background:#1d4ed8; width:{{ $finCollectionPct }}%; height:100%; border-radius:20px;"></div>
            </div>
            <div style="font-size:0.7rem; font-weight:600; color:#1d4ed8; margin-top:4px;">{{ $finCollectionPct }}% of target</div>
        </div>

        <div style="background:#fff; border-radius:10px; border:1px solid #e5e7eb; border-top:3px solid #7c3aed; padding:14px 16px;">
            <div style="font-size:0.72rem; font-weight:600; color:#6b7280; text-transform:uppercase; letter-spacing:0.03em; margin-bottom:6px;">💰 Revenue this month</div>
            <div style="font-size:1.4rem; font-weight:700; color:#5b21b6; line-height:1;">IDR {{ $finRevenueM }}</div>
            @if($finRevenueChangePercent !== null)
                @if($finRevenueChangePercent >= 0)
                    <div style="font-size:0.72rem; font-weight:600; margin-top:4px; color:#15803d;">↑ {{ abs($finRevenueChangePercent) }}% vs last month 🔥</div>
                @else
                    <div style="font-size:0.72rem; font-weight:600; margin-top:4px; color:#dc2626;">↓ {{ abs($finRevenueChangePercent) }}% vs last month</div>
                @endif
            @else
                <div style="font-size:0.72rem; color:#9ca3af; margin-top:4px;">No comparison yet</div>
            @endif
            <div style="margin-top:8px; background:#f5f3ff; border-radius:20px; height:6px; overflow:hidden;">
                <div style="background:#7c3aed; width:{{ $finRevenuePct }}%; height:100%; border-radius:20px;"></div>
            </div>
            <div style="font-size:0.7rem; font-weight:600; color:#7c3aed; margin-top:4px;">{{ $finRevenuePct }}% of target</div>
        </div>

        <div style="background:#fff; border-radius:10px; border:1px solid #e5e7eb; border-top:3px solid #D4A017; padding:14px 16px;">
            <div style="font-size:0.72rem; font-weight:600; color:#6b7280; text-transform:uppercase; letter-spacing:0.03em; margin-bottom:6px;">⚠️ Outstanding invoices</div>
            <div style="font-size:1.6rem; font-weight:700; color:#92400e; line-height:1;">{{ $finOutstandingInvoices->count() }}</div>
            @if($finOverdueInvoiceCount > 0)
                <div style="font-size:0.72rem; font-weight:600; margin-top:4px; color:#dc2626;">🔴 {{ $finOverdueInvoiceCount }} overdue</div>
            @else
                <div style="font-size:0.72rem; font-weight:600; margin-top:4px; color:#15803d;">✅ None overdue</div>
            @endif
            <div style="font-size:0.72rem; color:#9ca3af; margin-top:6px;">IDR {{ number_format($finTotalOutstandingAmount / 1000000, 1) }}jt unpaid</div>
        </div>

        <div style="background:#fff; border-radius:10px; border:1px solid #e5e7eb; border-top:3px solid #1f5f46; padding:14px 16px;">
            <div style="font-size:0.72rem; font-weight:600; color:#6b7280; text-transform:uppercase; letter-spacing:0.03em; margin-bottom:6px;">📋 New invoices raised</div>
            <div style="font-size:1.6rem; font-weight:700; color:#1f5f46; line-height:1;">{{ $finNewInvoicesThisMonth->count() }}</div>
            <div style="font-size:0.72rem; color:#9ca3af; margin-top:4px;">{{ $selectedMonth->format('F Y') }}</div>
            @if($finNewInvoicesThisMonth->count() >= 5)
                <div style="font-size:0.72rem; font-weight:600; margin-top:4px; color:#15803d;">📈 Active billing month!</div>
            @else
                <div style="font-size:0.72rem; color:#9ca3af; margin-top:4px;">Keep raising those invoices 💪</div>
            @endif
        </div>

    </div>

    <!-- CHARTS ROW: Revenue trend (3fr) + Invoice status donut (2fr) -->
    <div style="display:grid; grid-template-columns:3fr 2fr; gap:16px; margin-bottom:20px;">

        <div style="background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:20px 22px;">
            <div style="display:flex; align-items:center; gap:8px; margin-bottom:4px;">
                <div style="width:8px; height:8px; border-radius:50%; background:#7c3aed;"></div>
                <span style="font-size:0.88rem; font-weight:700; color:#1b4332;">Revenue collected — last 6 months</span>
            </div>
            <p style="font-size:0.75rem; color:#9ca3af; margin:0 0 14px;">Paid invoices in IDR (juta)</p>
            <div style="position:relative; height:160px;">
                <canvas id="finRevenueChart" role="img" aria-label="Bar chart of revenue collected over 6 months">Revenue trend over 6 months.</canvas>
            </div>
        </div>

        <div style="background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:20px 22px;">
            <div style="display:flex; align-items:center; gap:8px; margin-bottom:4px;">
                <div style="width:8px; height:8px; border-radius:50%; background:#1baf7a;"></div>
                <span style="font-size:0.88rem; font-weight:700; color:#1b4332;">Invoice status — current</span>
            </div>
            <p style="font-size:0.75rem; color:#9ca3af; margin:0 0 14px;">All invoices by payment status</p>
            <div style="display:flex; align-items:center; gap:16px;">
                <div style="position:relative; height:130px; width:130px; flex-shrink:0;">
                    <canvas id="finStatusChart" role="img" aria-label="Donut chart of invoice statuses">Invoice status breakdown.</canvas>
                </div>
                <div style="display:flex; flex-direction:column; gap:10px; font-size:0.8rem;">
                    <div style="display:flex; align-items:center; gap:8px;">
                        <span style="width:10px; height:10px; border-radius:2px; background:#1baf7a; display:inline-block; flex-shrink:0;"></span>
                        <span style="color:#1b4332; font-weight:700;">{{ $finCollectedThisMonth }}</span>
                        <span style="color:#9ca3af;">Paid ✅</span>
                    </div>
                    <div style="display:flex; align-items:center; gap:8px;">
                        <span style="width:10px; height:10px; border-radius:2px; background:#eda100; display:inline-block; flex-shrink:0;"></span>
                        <span style="color:#1b4332; font-weight:700;">{{ $finPendingCount }}</span>
                        <span style="color:#9ca3af;">Pending 🟡</span>
                    </div>
                    <div style="display:flex; align-items:center; gap:8px;">
                        <span style="width:10px; height:10px; border-radius:2px; background:#e34948; display:inline-block; flex-shrink:0;"></span>
                        <span style="color:#1b4332; font-weight:700;">{{ $finOverdueInvoiceCount }}</span>
                        <span style="color:#9ca3af;">Overdue 🔴</span>
                    </div>
                    @php $finDonutTotal = $finCollectedThisMonth + $finPendingCount + $finOverdueInvoiceCount; @endphp
                    @if($finDonutTotal === 0)
                        <div style="font-size:0.72rem; color:#9ca3af; font-style:italic;">No invoice data yet</div>
                    @endif
                </div>
            </div>
        </div>

    </div>

    <!-- TARGET PROGRESS BARS -->
    <div style="background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:20px 22px; margin-bottom:28px;">
        <div style="display:flex; align-items:center; gap:8px; margin-bottom:16px;">
            <div style="width:8px; height:8px; border-radius:50%; background:#1d4ed8;"></div>
            <span style="font-size:0.88rem; font-weight:700; color:#1b4332;">🎯 Target progress — {{ $selectedMonth->format('F Y') }}</span>
        </div>
        @php
            $finTargetBars = [
                [
                    'label' => '📥 Invoices collected (monthly)',
                    'val'   => $finCollectedThisMonth,
                    'max'   => $finMonthlyTarget,
                    'color' => '#1d4ed8',
                    'unit'  => 'invoices',
                ],
                [
                    'label' => '📊 Invoices collected (quarterly)',
                    'val'   => $finCollectedThisQuarter,
                    'max'   => $finQuarterlyTarget,
                    'color' => '#1d4ed8',
                    'unit'  => 'invoices',
                ],
                [
                    'label' => '💰 Revenue collected (monthly)',
                    'val'   => round($finRevenueThisMonth / 1000000, 1),
                    'max'   => 80,
                    'color' => '#7c3aed',
                    'unit'  => 'IDR jt',
                ],
            ];
        @endphp
        <div style="display:flex; flex-direction:column; gap:16px;">
            @foreach($finTargetBars as $bar)
                @php
                    $bpct = $bar['max'] > 0 ? min(100, round(($bar['val'] / $bar['max']) * 100)) : 0;
                @endphp
                <div>
                    <div style="display:flex; justify-content:space-between; font-size:0.82rem; margin-bottom:6px;">
                        <span style="color:#374151; font-weight:600;">{{ $bar['label'] }}</span>
                        <span style="color:#1b4332; font-weight:700;">
                            {{ $bar['val'] }} / {{ $bar['max'] }} {{ $bar['unit'] }}
                            @if($bpct >= 100) 🎉 @elseif($bpct >= 70) 💪 @elseif($bpct >= 40) 🔄 @else ⚡ @endif
                        </span>
                    </div>
                    <div style="background:#f3f4f6; border-radius:20px; height:10px; overflow:hidden;">
                        <div style="background:{{ $bar['color'] }}; width:{{ $bpct }}%; height:100%; border-radius:20px;"></div>
                    </div>
                    @if($bpct >= 100)
                        <div style="font-size:0.72rem; color:#15803d; font-weight:600; margin-top:4px;">🎉 Target reached!</div>
                    @else
                        <div style="font-size:0.72rem; color:#9ca3af; margin-top:4px;">{{ $bpct }}% achieved · {{ max(0, $bar['max'] - $bar['val']) }} {{ $bar['unit'] }} to go</div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    <!-- ============================================================
         GOALS & KPIs
         ============================================================ -->
    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:16px;">
        <div style="display:flex; align-items:center; gap:8px;">
            <span style="font-size:1rem; font-weight:800; color:#1f5f46;">🎯 Goals & KPIs</span>
            <span style="font-size:0.78rem; color:#6b7280; background:#f3f4f6; padding:2px 10px; border-radius:20px;">{{ count($finGoals) }} goals</span>
        </div>
        <span style="font-size:0.72rem; color:#92400e; background:#fef3c7; padding:3px 10px; border-radius:20px; border:1px solid #fde68a;">📌 Dummy data — connect to DB later</span>
    </div>

    <!-- Goals Summary Tiles -->
    <div style="display:grid; grid-template-columns:repeat(4,1fr); gap:10px; margin-bottom:16px;">
        <div style="background:#f9fafb; border-radius:10px; border:1px solid #e5e7eb; padding:12px 14px; text-align:center;">
            <div style="font-size:1.4rem; font-weight:700; color:#1b4332;">{{ count($finGoals) }}</div>
            <div style="font-size:0.72rem; color:#6b7280; margin-top:3px;">🎯 Total goals</div>
        </div>
        <div style="background:#f0fdf4; border-radius:10px; border:1px solid #bbf7d0; padding:12px 14px; text-align:center;">
            <div style="font-size:1.4rem; font-weight:700; color:#15803d;">{{ $finGoalsAchieved }}</div>
            <div style="font-size:0.72rem; color:#15803d; margin-top:3px;">✅ Achieved</div>
        </div>
        <div style="background:#f5f3ff; border-radius:10px; border:1px solid #ddd6fe; padding:12px 14px; text-align:center;">
            <div style="font-size:1.4rem; font-weight:700; color:#5b21b6;">{{ $finGoalsProgress }}</div>
            <div style="font-size:0.72rem; color:#5b21b6; margin-top:3px;">💪 In progress</div>
        </div>
        <div style="background:#fef2f2; border-radius:10px; border:1px solid #fecaca; padding:12px 14px; text-align:center;">
            <div style="font-size:1.4rem; font-weight:700; color:#dc2626;">{{ $finGoalsRisk }}</div>
            <div style="font-size:0.72rem; color:#dc2626; margin-top:3px;">⚠️ At risk</div>
        </div>
    </div>

    <!-- Filter Buttons -->
    <div style="display:flex; gap:8px; margin-bottom:16px; flex-wrap:wrap;" class="perf-no-print">
        <button type="button" onclick="finFilter('all', this)" style="padding:6px 16px; font-size:0.8rem; font-weight:600; border-radius:20px; border:1px solid #1b4332; background:#1b4332; color:#fff; cursor:pointer;">🔍 All</button>
        <button type="button" onclick="finFilter('achieved', this)" style="padding:6px 16px; font-size:0.8rem; font-weight:600; border-radius:20px; border:1px solid #d1d5db; background:#fff; color:#374151; cursor:pointer;">✅ Achieved</button>
        <button type="button" onclick="finFilter('progress', this)" style="padding:6px 16px; font-size:0.8rem; font-weight:600; border-radius:20px; border:1px solid #d1d5db; background:#fff; color:#374151; cursor:pointer;">💪 In progress</button>
        <button type="button" onclick="finFilter('risk', this)" style="padding:6px 16px; font-size:0.8rem; font-weight:600; border-radius:20px; border:1px solid #d1d5db; background:#fff; color:#374151; cursor:pointer;">⚠️ At risk</button>
    </div>

    <!-- Goal Cards Grid -->
    <div id="finGoalsGrid" style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:20px;">
        @foreach($finGoals as $gi => $goal)
            @php
                $gpct        = $goal['target'] > 0 ? min(100, round(($goal['current'] / $goal['target']) * 100)) : 0;
                $gColors     = ['achieved' => '#1baf7a', 'progress' => '#534AB7', 'risk' => '#e34948'];
                $gBadgeBg    = ['achieved' => '#EAF3DE', 'progress' => '#EEEDFE', 'risk' => '#FCEBEB'];
                $gBadgeTxt   = ['achieved' => '#27500A', 'progress' => '#3C3489', 'risk' => '#A32D2D'];
                $gBadgeLabel = ['achieved' => '✅ Achieved', 'progress' => '💪 In progress', 'risk' => '⚠️ At risk'];
                $gValDisplay = $goal['unit'] === '%'
                               ? $goal['current'] . '% complete'
                               : $goal['current'] . ' / ' . $goal['target'] . ' ' . $goal['unit'];
                $gc          = $gColors[$goal['status']];
            @endphp
            <div class="fin-goal-card" data-status="{{ $goal['status'] }}"
                 style="background:#fff; border:1px solid #e5e7eb; border-left:4px solid {{ $gc }}; border-radius:12px; padding:16px 18px; transition:opacity .15s;">
                <div style="display:flex; align-items:flex-start; justify-content:space-between; margin-bottom:12px; gap:8px;">
                    <div style="font-size:0.88rem; font-weight:600; color:#1b4332; line-height:1.45; flex:1;">
                        <span style="font-size:1.1rem; margin-right:6px;">{{ $goal['emoji'] }}</span>{{ $goal['title'] }}
                    </div>
                    <span style="font-size:0.72rem; padding:3px 9px; border-radius:20px; font-weight:600; white-space:nowrap; flex-shrink:0;
                                 background:{{ $gBadgeBg[$goal['status']] }}; color:{{ $gBadgeTxt[$goal['status']] }};">
                        {{ $gBadgeLabel[$goal['status']] }}
                    </span>
                </div>
                <div style="display:flex; align-items:center; gap:10px; margin-bottom:8px;">
                    <div style="flex:1; height:8px; background:#f3f4f6; border-radius:4px; overflow:hidden;">
                        <div class="fin-goal-bar" data-pct="{{ $gpct }}"
                             style="width:0%; height:100%; background:{{ $gc }}; border-radius:4px; transition:width 1s cubic-bezier(.4,0,.2,1);"></div>
                    </div>
                    <span style="font-size:0.85rem; font-weight:700; min-width:34px; text-align:right; color:{{ $gc }};">{{ $gpct }}%</span>
                </div>
                <div style="display:flex; align-items:center; justify-content:space-between;">
                    <span style="font-size:0.75rem; color:#6b7280;">{{ $gValDisplay }}</span>
                    <span style="font-size:0.72rem; color:#9ca3af;">📅 {{ $goal['due'] }}</span>
                </div>
            </div>
        @endforeach
    </div>

    <!-- ── Chart.js + interactive scripts ── -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.js"></script>
    <script>
        // Revenue bar chart
        (function () {
            const ctx = document.getElementById('finRevenueChart');
            if (!ctx) return;
            const raw     = @json($finMonthlyRevenueTrend);
            const labels  = raw.map(d => d.label);
            const amounts = raw.map(d => Math.round(d.amount / 1000000));
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels,
                    datasets: [{
                        data: amounts,
                        backgroundColor: amounts.map((_, i) => i === amounts.length - 1 ? '#7c3aed' : '#ddd6fe'),
                        borderRadius: 5,
                        borderSkipped: 'bottom',
                    }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: { callbacks: { label: c => ' IDR ' + c.raw + ' jt' } }
                    },
                    scales: {
                        x: { grid: { display: false }, ticks: { color: '#9ca3af', font: { size: 11 } } },
                        y: { grid: { color: '#f3f4f6' }, border: { display: false },
                             ticks: { color: '#9ca3af', font: { size: 11 }, callback: v => v + 'M' } }
                    }
                }
            });
        })();

        // Invoice status donut
        (function () {
            const ctx = document.getElementById('finStatusChart');
            if (!ctx) return;
            const paid    = {{ $finCollectedThisMonth }};
            const pending = {{ $finPendingCount }};
            const overdue = {{ $finOverdueInvoiceCount }};
            if (paid + pending + overdue === 0) return;
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Paid', 'Pending', 'Overdue'],
                    datasets: [{
                        data: [paid, pending, overdue],
                        backgroundColor: ['#1baf7a', '#eda100', '#e34948'],
                        borderWidth: 2,
                        borderColor: '#ffffff',
                    }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false, cutout: '68%',
                    plugins: { legend: { display: false } }
                }
            });
        })();

        // Animate goal bars on load
        document.addEventListener('DOMContentLoaded', function () {
            setTimeout(function () {
                document.querySelectorAll('.fin-goal-bar').forEach(function (bar) {
                    bar.style.width = bar.dataset.pct + '%';
                });
            }, 200);
        });

        // Goal filter
        function finFilter(status, btn) {
            document.querySelectorAll('[onclick^="finFilter"]').forEach(function (b) {
                b.style.background = '#fff';
                b.style.color      = '#374151';
                b.style.border     = '1px solid #d1d5db';
            });
            btn.style.background = '#1b4332';
            btn.style.color      = '#fff';
            btn.style.border     = '1px solid #1b4332';
            var cards = document.querySelectorAll('.fin-goal-card');
            cards.forEach(function (card) {
                card.style.display = (status === 'all' || card.dataset.status === status) ? '' : 'none';
            });
        }
    </script>

    @endif {{-- end division block --}}

    @endif {{-- end dashboardAvailable --}}

</div>
@endsection
