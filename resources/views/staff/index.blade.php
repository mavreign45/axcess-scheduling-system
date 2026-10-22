@extends('layouts.app')

@section('title', 'Staff directory')
@section('eyebrow', 'People & access')
@section('subtitle', 'Manage staff profiles, employment details, and scheduling eligibility.')

@section('actions')
    @can('create', \App\Models\User::class)
        <a href="{{ route('staff.create') }}" class="ax-btn-primary ax-btn-sm">
            <x-icon name="plus" class="h-4 w-4" />
            <span class="hidden sm:inline">Add staff</span>
        </a>
    @endcan
    @can('export-data')
        <button type="button" class="ax-btn-secondary ax-btn-sm" data-export-open="staff-export">
            <x-icon name="download" class="h-4 w-4" />
            <span class="hidden sm:inline">Export</span>
        </button>
    @endcan
@endsection

@section('content')
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="People in scope" :value="$summary['total']" meta="All staff profiles" icon="users" />
        <x-stat-card label="Active accounts" :value="$summary['active']" meta="Can sign in and be scheduled" icon="check-circle" tone="sky" />
        <x-stat-card label="Standby eligible" :value="$summary['standby']" meta="Available for on-call cover" icon="phone" tone="amber" />
        <x-stat-card label="Departments" :value="$summary['departments']" meta="Within your access scope" icon="building" tone="violet" />
    </div>

    <form method="GET" action="{{ route('staff.index') }}" class="ax-card mt-5 p-4">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-5">
            <div class="lg:col-span-2">
                <label class="ax-label" for="staff-search">Search</label>
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-slate-400">
                        <x-icon name="search" class="h-4 w-4" />
                    </span>
                    <input id="staff-search" type="search" name="search" value="{{ $filters['search'] }}" class="ax-input pl-9" placeholder="Name, employee ID, or email">
                </div>
            </div>
            @unless(auth()->user()->isManager())
                <div>
                    <label class="ax-label" for="staff-department">Department</label>
                    <select id="staff-department" name="department_id" class="ax-input" data-auto-submit>
                        <option value="">All departments</option>
                        @foreach($departments as $department)
                            <option value="{{ $department->id }}" @selected((int) $filters['department_id'] === $department->id)>{{ $department->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endunless
            <div>
                <label class="ax-label" for="staff-status">Employment status</label>
                <select id="staff-status" name="employment_status" class="ax-input" data-auto-submit>
                    <option value="">All statuses</option>
                    @foreach(\App\Support\Axcess::EMPLOYMENT_STATUSES as $value => $label)
                        <option value="{{ $value }}" @selected($filters['employment_status'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end gap-2">
                <label class="flex h-[42px] items-center gap-2 rounded-xl border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700">
                    <input type="checkbox" name="standby_eligible" value="1" class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500" @checked($filters['standby_eligible']) data-auto-submit>
                    Standby only
                </label>
                <button type="submit" class="ax-btn-dark ax-btn-sm h-[42px]">Filter</button>
            </div>
        </div>
        @if($filters['search'] !== '' || $filters['employment_status'] || $filters['standby_eligible'] || $filters['department_id'])
            <div class="mt-3 flex items-center gap-2 border-t border-slate-100 pt-3">
                <span class="text-[11px] font-semibold text-slate-400">Active filters</span>
                @if($filters['search'] !== '')<span class="ax-chip bg-slate-100 text-slate-600">“{{ $filters['search'] }}”</span>@endif
                @if($filters['employment_status'])<span class="ax-chip bg-slate-100 text-slate-600">{{ \App\Support\Axcess::label('employment_status', $filters['employment_status']) }}</span>@endif
                @if($filters['standby_eligible'])<span class="ax-chip bg-slate-100 text-slate-600">Standby eligible</span>@endif
                @if($filters['department_id'])<span class="ax-chip bg-slate-100 text-slate-600">{{ $departments->firstWhere('id', (int) $filters['department_id'])?->name }}</span>@endif
                <a href="{{ route('staff.index') }}" class="text-[11px] font-bold text-brand-700 hover:text-brand-800">Clear all</a>
            </div>
        @endif
    </form>

    <section class="ax-card mt-5 overflow-hidden">
        <div class="ax-scrollbar overflow-x-auto">
            <table class="ax-table">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Department</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Weekly limit</th>
                        <th>Standby</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($staff as $member)
                        <tr>
                            <td>
                                <div class="flex items-center gap-3">
                                    <span class="ax-avatar bg-slate-900">{{ $member->initials }}</span>
                                    <div class="min-w-0">
                                        <a href="{{ route('staff.show', $member) }}" class="block truncate text-sm font-bold text-slate-800 hover:text-brand-700">{{ $member->full_name }}</a>
                                        <p class="truncate text-[11px] text-slate-500">{{ $member->employee_id }}{{ $member->jobPosition ? ' · '.$member->jobPosition->title : '' }}</p>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="text-xs font-semibold text-slate-600">{{ $member->department?->name ?? '—' }}</span>
                            </td>
                            <td>
                                <span class="ax-chip bg-slate-100 text-slate-600">{{ $member->roleLabel() }}</span>
                            </td>
                            <td>
                                <x-status-badge :status="$member->employment_status" :label="\App\Support\Axcess::label('employment_status', $member->employment_status)" />
                                @unless($member->is_active)
                                    <span class="ml-1 text-[9px] font-bold uppercase text-rose-500">login off</span>
                                @endunless
                            </td>
                            <td><span class="text-xs font-semibold text-slate-600">{{ rtrim(rtrim(number_format((float) $member->max_weekly_hours, 1), '0'), '.') }} hrs</span></td>
                            <td>
                                @if($member->standby_eligible)
                                    <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-emerald-600"><x-icon name="check" class="h-3.5 w-3.5" /> Eligible</span>
                                @else
                                    <span class="text-[11px] text-slate-400">Not eligible</span>
                                @endif
                            </td>
                            <td>
                                <div class="flex items-center justify-end gap-1">
                                    <a href="{{ route('staff.show', $member) }}" class="rounded-lg p-2 text-slate-400 transition hover:bg-slate-100 hover:text-brand-700" title="View profile" aria-label="View {{ $member->full_name }}">
                                        <x-icon name="eye" class="h-4 w-4" />
                                    </a>
                                    @can('update', $member)
                                        <a href="{{ route('staff.edit', $member) }}" class="rounded-lg p-2 text-slate-400 transition hover:bg-slate-100 hover:text-brand-700" title="Edit staff" aria-label="Edit {{ $member->full_name }}">
                                            <x-icon name="edit" class="h-4 w-4" />
                                        </a>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <x-empty-state title="No staff match these filters" message="Try a different search, department, or status." icon="users">
                                    <a href="{{ route('staff.index') }}" class="ax-btn-secondary ax-btn-sm">Clear filters</a>
                                </x-empty-state>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $staff->links() }}
    </section>

    @can('export-data')
        <x-export-dialog id="staff-export" title="Export staff directory" description="Staff exports respect department scope. Contact details and account information are only included when explicitly selected." :url="route('exports.staff')" action="Export staff">
            <div class="grid gap-4 sm:grid-cols-2">
                @unless(auth()->user()->isManager())
                    <div>
                        <label class="ax-label" for="export-staff-department">Department</label>
                        <select id="export-staff-department" name="department_id" class="ax-input">
                            <option value="">All departments</option>
                            @foreach($departments as $department)
                                <option value="{{ $department->id }}">{{ $department->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endunless
                <div>
                    <label class="ax-label" for="export-staff-status">Employment status</label>
                    <select id="export-staff-status" name="employment_status" class="ax-input">
                        <option value="">All statuses</option>
                        @foreach(\App\Support\Axcess::EMPLOYMENT_STATUSES as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="space-y-2 rounded-xl border border-slate-200 bg-slate-50 p-3.5">
                <label class="flex items-start gap-2.5 text-xs font-semibold text-slate-700">
                    <input type="checkbox" name="standby_only" value="1" class="mt-0.5 h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                    Include standby-eligible staff only
                </label>
                <label class="flex items-start gap-2.5 text-xs font-semibold text-slate-700">
                    <input type="checkbox" name="include_contacts" value="1" class="mt-0.5 h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                    Include email addresses and phone numbers
                </label>
            </div>

            <p class="rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-[11px] leading-5 text-sky-800">
                Passwords, remember tokens, and authentication secrets are never included in staff exports.
            </p>
        </x-export-dialog>
    @endcan
@endsection
