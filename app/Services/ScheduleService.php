<?php

namespace App\Services;

use App\Models\ScheduleAssignment;
use App\Models\ScheduleBreak;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ScheduleService
{
    public function __construct(private readonly ConflictDetector $conflicts) {}

    public function create(array $data, User $actor): ScheduleAssignment
    {
        $assignment = DB::transaction(function () use ($data, $actor): ScheduleAssignment {
            $payload = $this->preparePayload($data, $actor);

            $assignment = ScheduleAssignment::create($payload += [
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            $this->syncBreaks($assignment, $data['breaks'] ?? []);

            return $assignment;
        });

        $this->detectAround($assignment);

        return $assignment->refresh();
    }

    public function update(ScheduleAssignment $assignment, array $data, User $actor): ScheduleAssignment
    {
        DB::transaction(function () use ($assignment, $data, $actor): void {
            $payload = $this->preparePayload($data, $actor);

            if (($payload['user_id'] ?? null) === $assignment->replacement_user_id) {
                $payload['replacement_user_id'] = null;
                $payload['replacement_reason'] = null;
                $payload['replaced_at'] = null;
            }

            $assignment->update($payload + ['updated_by' => $actor->id]);
            $assignment->breaks()->delete();
            $this->syncBreaks($assignment, $data['breaks'] ?? []);
        });

        $this->detectAround($assignment->refresh());

        return $assignment->refresh();
    }

    public function delete(ScheduleAssignment $assignment): void
    {
        $date = $assignment->schedule_date->copy();
        $departmentId = $assignment->department_id;
        $assignment->delete();

        $this->conflicts->detect($date->copy()->startOfWeek(), $date->copy()->endOfWeek(), $departmentId);
    }

    public function recordReplacement(ScheduleAssignment $assignment, User $replacement, ?string $reason, User $actor): ScheduleAssignment
    {
        if ($assignment->user_id === $replacement->id) {
            throw ValidationException::withMessages([
                'replacement_user_id' => 'The replacement employee must be different from the assigned employee.',
            ]);
        }

        if (! $replacement->is_active || $replacement->department_id !== $assignment->department_id) {
            throw ValidationException::withMessages([
                'replacement_user_id' => 'The replacement employee must be active and belong to the same department.',
            ]);
        }

        DB::transaction(function () use ($assignment, $replacement, $reason, $actor): void {
            $assignment->replacementHistory()->create([
                'original_user_id' => $assignment->user_id,
                'replacement_user_id' => $replacement->id,
                'replaced_by' => $actor->id,
                'replaced_at' => now(),
                'reason' => $reason,
            ]);

            $assignment->update([
                'replacement_user_id' => $replacement->id,
                'replacement_reason' => $reason,
                'replaced_at' => now(),
                'updated_by' => $actor->id,
            ]);
        });

        $this->detectAround($assignment->refresh());

        return $assignment->refresh();
    }

    private function preparePayload(array $data, User $actor): array
    {
        $employee = isset($data['user_id']) && $data['user_id'] ? User::find($data['user_id']) : null;

        if ($employee && ! $actor->isAdmin() && $employee->department_id !== $actor->department_id) {
            throw ValidationException::withMessages([
                'user_id' => 'You can only schedule employees in your own department.',
            ]);
        }

        $departmentId = $employee?->department_id ?? ($data['department_id'] ?? null);

        if (! $departmentId) {
            throw ValidationException::withMessages([
                'department_id' => 'Select a department for an unassigned shift.',
            ]);
        }

        if (! $actor->isAdmin() && (int) $departmentId !== (int) $actor->department_id) {
            throw ValidationException::withMessages([
                'department_id' => 'You can only schedule shifts for your own department.',
            ]);
        }

        $start = Carbon::parse($data['start_at']);
        $end = Carbon::parse($data['end_at']);

        if ($end->lessThanOrEqualTo($start)) {
            $end->addDay();
        }

        $scheduleDate = Carbon::parse($data['schedule_date'] ?? $start->toDateString());

        $durationHours = $start->diffInMinutes($end) / 60;

        if ($durationHours > (float) config('axcess.scheduling.max_shift_hours', 24)) {
            throw ValidationException::withMessages([
                'end_at' => 'A shift cannot be longer than 24 hours.',
            ]);
        }

        $breaks = collect($data['breaks'] ?? [])
            ->filter(fn ($break) => ! empty($break['start_at']) && ! empty($break['end_at']));

        $breakMinutes = $breaks->sum(function ($break) use ($start, $end): int {
            $breakStart = Carbon::parse($break['start_at']);
            $breakEnd = Carbon::parse($break['end_at']);

            if ($breakEnd->lessThanOrEqualTo($breakStart)) {
                $breakEnd->addDay();
            }

            if ($breakStart->lt($start) || $breakEnd->gt($end)) {
                throw ValidationException::withMessages([
                    'breaks' => 'Break periods must fall inside the shift.',
                ]);
            }

            return (int) $breakStart->diffInMinutes($breakEnd);
        });

        if ($breakMinutes >= $start->diffInMinutes($end)) {
            throw ValidationException::withMessages([
                'breaks' => 'Total break time must be shorter than the shift duration.',
            ]);
        }

        $isStandby = (bool) ($data['is_standby'] ?? false);
        $standbyStatus = $isStandby
            ? ($data['standby_status'] ?? 'available')
            : 'not_applicable';

        if ($isStandby && $standbyStatus === 'not_applicable') {
            $standbyStatus = 'available';
        }

        return [
            'user_id' => $employee?->id,
            'department_id' => $departmentId,
            'shift_type_id' => $data['shift_type_id'],
            'schedule_date' => $scheduleDate->toDateString(),
            'start_at' => $start,
            'end_at' => $end,
            'status' => $data['status'] ?? 'published',
            'is_standby' => $isStandby,
            'standby_status' => $standbyStatus,
            'notes' => $data['notes'] ?? null,
        ];
    }

    private function syncBreaks(ScheduleAssignment $assignment, array $breaks): void
    {
        foreach ($breaks as $break) {
            if (empty($break['start_at']) || empty($break['end_at'])) {
                continue;
            }

            $start = Carbon::parse($break['start_at']);
            $end = Carbon::parse($break['end_at']);

            if ($end->lessThanOrEqualTo($start)) {
                $end->addDay();
            }

            ScheduleBreak::create([
                'schedule_assignment_id' => $assignment->id,
                'label' => $break['label'] ?? 'Break',
                'start_at' => $start,
                'end_at' => $end,
                'duration_minutes' => (int) $start->diffInMinutes($end),
            ]);
        }
    }

    private function detectAround(ScheduleAssignment $assignment): void
    {
        $this->conflicts->detect(
            $assignment->schedule_date->copy()->startOfWeek(),
            $assignment->schedule_date->copy()->endOfWeek(),
            $assignment->department_id,
        );
    }
}
