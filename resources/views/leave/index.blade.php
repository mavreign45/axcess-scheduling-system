@extends('layouts.app')

@section('title', 'Leave & absences')
@section('eyebrow', 'Time away')
@section('subtitle', 'Requests, approvals, and absence records across your team.')

@section('actions')
    <a href="{{ route('leave.create') }}" class="ax-btn-primary ax-btn-sm">
        <x-icon name="plus" class="h-4 w-4" />
        <span class="hidden sm:inline">Request leave</span>
    </a>
    @can('export-data')
        <button type="button" class="ax-btn-secondary ax-btn-sm" data-export-open="leave-export">
            <x-icon name="download" class="h-4 w-4" />
            <span class="hidden sm:inline">Export</span>
        </button>
    @endcan
@endsection

@section('content')
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="Pending requests" :value="$stats['pending']" meta="Awaiting a decision" icon="clock" tone="amber" />
        <x-stat-card label="Approved days" :value="number_format($stats['approved_days'], 1)" meta="Approved this year" icon="check-circle" />
        <x-stat-card label="On leave today" :value="$stats['on_leave_today']" meta="Approved leave in progress" icon="users" tone="sky" />
        <x-stat-card label="Rejected requests" :value="$stats['rejected']" meta="All recorded rejections" icon="x" tone="rose" />
    </div>

    <form method="GET" action="{{ route('leave.index') }}" class="ax-card mt-5 p-4">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-6">
            @unless(auth()->user()->isEmployee())
                <div>
                    <label class="ax-label" for="leave-employee">Employee</label>
                    <select id="leave-employee" name="user_id" class="ax-input" data-auto-submit>
                        <option value="">All employees</option>
                        @foreach($employees as $employee)
                            <option value="{{ $employee->id }}" @selected((int) $filters['user_id'] === $employee->id)>{{ $employee->full_name }}</option>
                        @endforeach
                    </select>
                </div>
            @endunless
            <div>
                <label class="ax-label" for="leave-status">Status</label>
                <select id="leave-status" name="status" class="ax-input" data-auto-submit>
                    <option value="">All statuses</option>
                    @foreach(\App\Support\Axcess::LEAVE_STATUSES as $value => $label)
                        <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="ax-label" for="leave-type">Leave type</label>
                <select id="leave-type" name="leave_type" class="ax-input" data-auto-submit>
                    <option value="">All types</option>
                    @foreach(\App\Support\Axcess::LEAVE_TYPES as $value => $label)
                        <option value="{{ $value }}" @selected($filters['leave_type'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="ax-label" for="leave-from">From</label>
                <input id="leave-from" type="date" name="from" value="{{ $filters['from'] }}" class="ax-input">
            </div>
            <div>
                <label class="ax-label" for="leave-to">To</label>
                <input id="leave-to" type="date" name="to" value="{{ $filters['to'] }}" class="ax-input">
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="ax-btn-dark ax-btn-sm w-full">Filter</button>
                @if(array_filter($filters))
                    <a href="{{ route('leave.index') }}" class="ax-btn-secondary ax-btn-sm">Clear</a>
                @endif
            </div>
        </div>
    </form>

    <section class="ax-card mt-5 overflow-hidden">
        <div class="ax-scrollbar overflow-x-auto">
            <table class="ax-table">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Type</th>
                        <th>Dates</th>
                        <th>Duration</th>
                        <th>Status</th>
                        <th>Approver</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($leaves as $leave)
                        <tr>
                            <td>
                                <div class="flex items-center gap-3">
                                    <span class="ax-avatar bg-slate-200 text-slate-700">{{ $leave->user?->initials }}</span>
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-bold text-slate-800">{{ $leave->user?->full_name }}</p>
                                        <p class="truncate text-[11px] text-slate-500">{{ $leave->user?->employee_id }} · {{ $leave->user?->department?->name }}</p>
                                    </div>
                                </div>
                            </td>
                            <td><span class="text-xs font-semibold text-slate-600">{{ $leave->leave_type_label }}</span></td>
                            <td>
                                <p class="text-xs font-semibold text-slate-700">{{ $leave->date_range_label }}</p>
                                <p class="text-[10px] text-slate-400">Requested {{ $leave->created_at?->diffForHumans() }}</p>
                            </td>
                            <td><span class="text-xs font-semibold text-slate-600">{{ $leave->duration_label }}</span></td>
                            <td><x-status-badge :status="$leave->status" :label="$leave->status_label" /></td>
                            <td>
                                <span class="text-xs text-slate-500">{{ $leave->approver?->full_name ?? '—' }}</span>
                                @if($leave->approved_at)
                                    <p class="text-[10px] text-slate-400">{{ $leave->approved_at->format('M j, Y') }}</p>
                                @endif
                            </td>
                            <td>
                                <div class="flex items-center justify-end gap-1">
                                    <a href="{{ route('leave.show', $leave) }}" class="rounded-lg p-2 text-slate-400 transition hover:bg-slate-100 hover:text-brand-700" title="View leave request" aria-label="View leave request">
                                        <x-icon name="eye" class="h-4 w-4" />
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <x-empty-state title="No leave requests found" message="There are no leave or absence records matching the current filters." icon="clock">
                                    <a href="{{ route('leave.create') }}" class="ax-btn-primary ax-btn-sm">
                                        <x-icon name="plus" class="h-4 w-4" />
                                        Request leave
                                    </a>
                                </x-empty-state>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $leaves->links() }}
    </section>

    @can('export-data')
        <x-export-dialog id="leave-export" title="Export leave &amp; absences" description="The workbook includes a detailed leave and absence sheet plus a summary by leave type and approval status." :url="route('exports.leave')" action="Export leave">
            <x-export-period-fields id="leave" mode="month" />

            <div class="grid gap-4 sm:grid-cols-2">
                @unless(auth()->user()->isManager())
                    <div>
                        <label class="ax-label" for="export-leave-department">Department</label>
                        <select id="export-leave-department" name="department_id" class="ax-input">
                            <option value="">All departments</option>
                            @foreach(\App\Models\Department::query()->where('is_active', true)->orderBy('name')->get() as $department)
                                <option value="{{ $department->id }}">{{ $department->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endunless
                <div>
                    <label class="ax-label" for="export-leave-employee">Employee</label>
                    <select id="export-leave-employee" name="user_id" class="ax-input">
                        <option value="">All employees</option>
                        @foreach($employees as $employee)
                            <option value="{{ $employee->id }}">{{ $employee->full_name }} · {{ $employee->employee_id }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="ax-label" for="export-leave-type">Leave type</label>
                    <select id="export-leave-type" name="leave_type" class="ax-input">
                        <option value="">All types</option>
                        @foreach(\App\Support\Axcess::LEAVE_TYPES as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="ax-label" for="export-leave-status">Approval status</label>
                    <select id="export-leave-status" name="leave_status" class="ax-input">
                        <option value="">All statuses</option>
                        @foreach(\App\Support\Axcess::LEAVE_STATUSES as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="space-y-2 rounded-xl border border-slate-200 bg-slate-50 p-3.5">
                <label class="flex items-start gap-2.5 text-xs font-semibold text-slate-700">
                    <input type="checkbox" name="include_absences" value="1" checked class="mt-0.5 h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                    Include recorded absences
                </label>
                @if(auth()->user()->isAdmin() || auth()->user()->isManager())
                    <label class="flex items-start gap-2.5 text-xs font-semibold text-slate-700">
                        <input type="checkbox" name="include_sensitive" value="1" class="mt-0.5 h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                        Include confidential reasons and supporting information
                    </label>
                @endif
            </div>
        </x-export-dialog>
    @endcan
@endsection
