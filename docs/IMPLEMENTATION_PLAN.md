# Axcess implementation plan and delivery record

## 1. Status

Axcess is implemented as a Laravel 12 workforce scheduling application with a custom Blade/Tailwind UI, role-scoped operations, conflict detection, and genuine Excel exports.

| Phase | Scope | Status |
| --- | --- | --- |
| 0 | Laravel scaffold, branding, database, dependencies | Complete |
| 1 | Authentication, roles, policies, gates, scoping | Complete |
| 2 | Departments, job positions, staff directory | Complete |
| 3 | Schedule week/month views, shift CRUD, breaks, standby | Complete |
| 4 | Leave requests, approvals, absences | Complete |
| 5 | Conflict detection and report surfaces | Complete |
| 6 | Export services, genuine `.xlsx` workbooks, CSV | Complete |
| 7 | Branded responsive UI and export dialogs | Complete |
| 8 | Automated tests, documentation, build and delivery | Complete |

## 2. Product definition

**Official name:** Axcess  
**Full title:** Axcess — Staff Scheduling & Workforce Management  
**Audience:** Administrators, department managers, and employees in shift-based operations.

### Goals

- Replace spreadsheet-based rota planning with a scoped, auditable web workflow.
- Surface operational risks such as leave clashes, double bookings, insufficient breaks, overtime, and coverage gaps.
- Give managers reliable, formatted Excel workbooks rather than HTML tables renamed as spreadsheets.
- Keep sensitive notes and personal data behind explicit authorization and inclusion controls.

### Non-goals

- Payroll, clocking, or attendance capture. Scheduled hours are planned hours only.
- Multi-tenant SaaS billing or organization onboarding.
- Public API integrations or calendar sync.

## 3. Technical decisions

| Area | Decision | Reason |
| --- | --- | --- |
| Framework | Laravel 12.70.1 | Modern Laravel baseline, first-party auth primitives, testing tools |
| PHP | PHP 8.5.12 tested | Current environment; code targets PHP 8.4.1+ |
| Database | SQLite default, portable migrations | Zero-config local setup; MySQL/PostgreSQL compatible |
| Auth | Hand-rolled session authentication + middleware + gates/policies | Small role model; no external package needed |
| UI | Blade + Tailwind CSS 4 + small vanilla JS modules | Fast server-rendered workflows and no SPA overhead |
| Build | Vite 7 + Laravel Vite plugin | Standard Laravel asset pipeline |
| Excel | Maatwebsite/Excel 4.0.5 + PhpSpreadsheet 5.10.0 | Multi-sheet workbooks, styling, formulas, number formats |
| Excel marker | Every workbook implements `Maatwebsite\Excel\Concerns\Export` | Required by Laravel Excel 4 `download()` / `raw()` |
| CSV | Dedicated primary-sheet export | Prevents multi-sheet data loss and mislabeled formats |
| Testing | PHPUnit 11 feature/unit tests on in-memory SQLite | Fast, repeatable export and authorization coverage |
| Scheduling | Weekly master schedule with per-day rows | Clear manager workflow and straightforward range exports |

## 4. Domain model

```text
Department 1 ── * User
Department 1 ── * JobPosition
Department 1 ── * ScheduleAssignment
Department 1 ── * ScheduleConflict

User 1 ── * ScheduleAssignment (employee)
User 1 ── * ScheduleAssignment (replacement)
User 1 ── * LeaveRequest
User 1 ── * Absence
User 1 ── * OffDay

ScheduleAssignment 1 ── * ScheduleBreak
ScheduleAssignment 1 ── * ReplacementHistory
ScheduleAssignment 1 ── * ScheduleConflict

LeaveRequest * ── 1 User (approver)
Absence * ── 1 User (recorder)
ExportLog * ── 1 User
```

### Core invariants

- A schedule assignment always has a department and shift type.
- `user_id` may be null for an unassigned shift.
- A shift whose end is at or before its start ends on the following day.
- Breaks reference the parent assignment and preserve their exact start/end timestamps.
- Scheduled minutes = elapsed shift time; break minutes = sum of stored break durations; net = scheduled − breaks.
- Standby duties are assignments with `is_standby = true` and a standby status.
- Replacements update the assignment and append immutable `replacement_history`.
- Conflicts are deduplicated by fingerprint and resolved/ignored rather than deleted blindly.
- Managers are bound to one department. Employees are bound to their own records.

## 5. Authorization model

### Roles

- `admin` — all departments, all records, all exports/reports.
- `manager` — own department only; can schedule, manage staff, approve leave, export, and report.
- `employee` — own schedule, leave, and profile visibility only.

### Enforcement layers

1. `auth` and `active` middleware on the authenticated route group.
2. Gates: `export-data`, `manage-operations`, `view-reports`, `view-staff-directory`.
3. Policies: `ScheduleAssignmentPolicy`, `LeaveRequestPolicy`, `UserPolicy`, `ScheduleConflictPolicy`.
4. Query scopes: `visibleTo()` on assignments, leave, users.
5. Export-specific cross-checks in `ExportController`:
   - managers cannot select another department;
   - selected employees must belong to the effective department;
   - sensitive fields remain opt-in.

## 6. Scheduling workflow

1. Manager opens **Schedule** (week) or **Month**.
2. Create/edit form collects employee, department, shift type, date, start/end, status, standby fields, notes, and a dynamic break repeater.
3. `ScheduleService` validates actor scope, overnight normalization, shift length, break containment, and total break time.
4. Assignment and all breaks are saved in one transaction.
5. `ConflictDetector` rescans the affected week/department.
6. Replacement action validates same-department active employee, records history, and updates current cover.
7. Delete action rescans conflicts after removal.

### Overnight handling

- End <= start → add one day.
- Export displays the actual next-day end timestamp and `Overnight Shift = Yes`.
- Breaks may fall on either date and are never truncated.
- Duration calculations use absolute elapsed minutes.

## 7. Leave and absence workflow

- Employees/managers create leave requests with type, dates, duration, reason, and supporting information.
- Overlap validation prevents duplicate active requests.
- Managers/admins approve or reject; decisions set approver and timestamp.
- Employees can cancel their own requests where permitted.
- Absences are recorded separately with type, date, notes, and recorder.
- Both surfaces show schedule conflicts using the affected dates.

## 8. Conflict detection

`App\Services\ConflictDetector` evaluates scoped assignments and related records for:

| Type | Severity | Rule |
| --- | --- | --- |
| `leave_conflict` | Error | Assignment overlaps approved leave |
| `double_booking` | Error | Overlapping non-standby assignments for the same person |
| `unassigned_shift` | Warning | Active shift has no employee/replacement |
| `coverage_gap` | Error | Department-day below `min_staff_per_day` |
| `missing_break` | Warning | Long shift with no break or <30 minutes total |
| `weekly_overtime` | Warning | Weekly scheduled hours exceed user maximum |
| `long_shift` | Warning | Shift exceeds configured long-shift threshold |
| `standby_ineligible` | Warning | Standby assigned to non-eligible employee |

Checks are deduplicated by a stable fingerprint. Standby duties are excluded from missing-break and long-shift checks because they are availability windows, not worked shifts. A console command and daily schedule run scans for new issues:

```bash
php artisan axcess:detect-conflicts --days=60
```

## 9. Export architecture

```text
Export dialogs (Blade + fetch)
        │
        ▼
Form requests (format, period, filters, authorization)
        │
        ▼
ExportController (department scope, filename, audit log)
        │
        ├── CsvExport ───────────────► primary rows only
        │
        └── Workbook exports
              ├── ScheduleWorkbookExport
              ├── StaffWorkbookExport
              ├── LeaveWorkbookExport
              └── ReportsWorkbookExport
                    │
                    ▼
              ExportDataService ── ReportService / scoped queries
                    │
                    ▼
              GenericSheet → BaseSheet formatting
                    │
                    ▼
              Maatwebsite\Excel 4 + PhpSpreadsheet 5
```

### Key implementation details

- `BaseSheet` writes title, period, generation timestamp, filter summary, and header at row 6, with data from row 7. Laravel Excel 4 compacts empty array rows, so no blank spacer row is assumed.
- Header styling, freeze pane, AutoFilter, widths, date/time/decimal formats, banding, print setup, and confidential footer are applied in `AfterSheet`.
- Staff Summary and Leave Summary use real `SUM()` formulas over the data rows, excluding the total row.
- Sensitive schedule fields append Replacement Reason and Manager Notes only when requested; leave adds Reason, Supporting Information, and Notes.
- `SanitizesSpreadsheetData` prefixes dangerous leading characters (`= + - @ tab CR`) with an apostrophe.
- PHP date objects become Excel serial values with date/time formats. Formula objects are written as formulas intentionally.
- Empty reports produce valid branded workbooks with headers but no data or totals.
- Filenames include the report type and resolved period; MIME types distinguish XLSX from CSV.

See [EXPORTS.md](EXPORTS.md) for complete fields, filters, and instructions.

## 10. UI delivery

- Custom Tailwind 4 theme with teal brand, slate surfaces, cards, tables, badges, and responsive navigation.
- Views: login, dashboard, schedule week/month, shift form, staff index/form/show, leave index/form/show, standby index/create, reports, export dialogs, pagination, empty states.
- JS: dialog open/close, period-mode visibility, export fetch/download/error handling, break repeater, mobile nav, confirmations.
- Accessibility: labelled controls, focus states, live export error/success regions, semantic tables, and keyboard-friendly dialogs.
- Export buttons appear on schedule week, schedule month, staff, leave, standby, and reports screens. The UI shows only allowed actions by gate.

## 11. Testing and verification

Run:

```bash
php artisan test
```

Coverage includes:

- overnight shifts and next-day timestamps;
- multiple breaks across midnight;
- schedule date/week/month/custom periods;
- department, employee, shift-type, status, standby, employment, and leave filters;
- empty schedule, leave, and report workbooks;
- manager department isolation and employee denial;
- invalid ranges and formats returning useful validation errors;
- sensitive column exclusion/inclusion for schedule and leave;
- no password hashes, plaintext passwords, or tokens in exports;
- formula-injection sanitisation;
- branded properties, titles, headers, freeze panes, filters, widths, formats, and formulas;
- real CSV downloads with correct MIME type and filename;
- export audit logs containing no confidential text;
- service-level persistence and replacement of multiple breaks.

See [TESTING.md](TESTING.md) for the coverage matrix and latest recorded run.

### Manual verification completed

- All main pages return HTTP 200 for the admin demo account.
- Schedule, staff, leave, report XLSX downloads return HTTP 200 with correct MIME type and filenames.
- Overnight row with two breaks was inspected in the generated workbook; both breaks and next-day end time were present.
- Empty and sensitive exports were opened with PhpSpreadsheet and inspected.
- Tailwind production build passes.

## 12. Dependencies

### Runtime

- `laravel/framework` 12.70.1
- `maatwebsite/excel` 4.0.5
- `phpoffice/phpspreadsheet` 5.10.0
- `laravel/tinker` 2.x

### Development

- `phpunit/phpunit` 11.5.x
- `laravel/pint` 1.x
- `fakerphp/faker`
- `mockery/mockery`
- `nunomaduro/collision`

### Front-end

- `tailwindcss` 4.x
- `@tailwindcss/vite` 4.x
- `vite` 7.x
- `laravel-vite-plugin` 2.x

## 13. Environment and deployment

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
npm ci
npm run build
php artisan serve
```

Production:

```bash
php artisan migrate --force
npm ci && npm run build
php artisan optimize
```

Required PHP extensions include `pdo_sqlite`, `sqlite3`, `mbstring`, `xml`, `zip`, `gd`, `fileinfo`, and `openssl`. Ensure `storage/` and `bootstrap/cache/` are writable. Keep `APP_DEBUG=false` and configure mail/database secrets in the environment.

## 14. Risks and follow-ups

| Risk | Current mitigation | Follow-up |
| --- | --- | --- |
| Very large exports block the request | Range cap, row-count log, test coverage | Queue large exports and notify by email |
| SQLite write contention under load | Local/demo default | Use MySQL/PostgreSQL in production |
| Sensitive notes in workbooks | Opt-in + role checks + audit | Add per-field audit retention policy |
| Importing old roster data | Not in scope | Add validated CSV/XLSX importer |
| Calendar integrations | Not in scope | Add ICS feed and optional Google/Microsoft sync |
| Timezone display | Configurable label; app timezone used | Add per-department timezone configuration |

## 15. Delivery checklist

- [x] Laravel app scaffolded and branded as Axcess.
- [x] Migrations, models, factories, relationships, and seeder.
- [x] Authentication, authorization, policies, and scoped queries.
- [x] Schedule, staff, leave, standby, reports, conflict detection.
- [x] Excel/CSV exports with filters, formatting, formulas, and logs.
- [x] Responsive branded UI and export dialogs.
- [x] Automated tests and documentation.
- [x] Production asset build.
- [ ] Final commit and push to `origin/main` (pending repository credentials).
