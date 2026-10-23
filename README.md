# Axcess — Staff Scheduling & Workforce Management

Axcess is a Laravel workforce scheduling application for building weekly and monthly rotas, managing leave and absences, covering standby duties, detecting scheduling conflicts, and producing genuine, formatted Excel workbooks for managers.

The interface is a custom Blade + Tailwind application with a teal/slate visual identity, responsive layouts, and export dialogs on every operational screen.

## Highlights

- **Scheduling** — week and month calendars, create/edit shifts, overnight shifts, multiple breaks, standby duties, replacement history, and manager notes.
- **People** — staff directory, job positions, departments, employment details, and contact visibility controls.
- **Leave & absences** — request, approve, reject, cancel, overlap checks, and schedule-conflict visibility.
- **Conflict detection** — leave conflicts, double bookings, missing breaks, long shifts, weekly overtime, coverage gaps, standby eligibility, and unassigned shifts.
- **Reports** — staffing coverage, scheduled hours, break coverage, leave totals, conflict summaries, standby and replacement reporting.
- **Exports** — real `.xlsx` workbooks via Maatwebsite/Excel + PhpSpreadsheet, with a schedule workbook, staff, leave, and management reports. CSV is available as an explicitly labelled single-sheet format.
- **Security** — role-based authorization, department isolation for managers, employee self-scoping, sensitive-field gates, formula-injection protection, and no passwords or tokens in any export.

## Requirements

| Component | Supported / tested |
| --- | --- |
| PHP | 8.4.1+ (tested with **PHP 8.5.12**) |
| Laravel | **12.70.1** |
| Composer | 2.x |
| Node.js | 22.x (tested with **22.22.1**) |
| Database | SQLite by default; MySQL/PostgreSQL are supported by Laravel |
| PHP extensions | `pdo_sqlite`, `sqlite3`, `mbstring`, `xml`, `zip`, `gd`, `intl`, `fileinfo`, `openssl` |

Maatwebsite/Excel **4.0.5** is used with PhpSpreadsheet **5.10.0**. Laravel Excel 4 uses the `Maatwebsite\Excel\Concerns\Export` marker interface for multi-sheet exports; the workbook classes implement it explicitly.

## Quick start

```bash
git clone https://github.com/mavreign45/axcess-scheduling-system.git
cd axcess-scheduling-system

composer install
cp .env.example .env
php artisan key:generate

# SQLite is the default. Create the database file before migrating.
touch database/database.sqlite
php artisan migrate --seed

npm install
npm run build

php artisan serve
```

Open <http://127.0.0.1:8000>.

### Demo accounts

All seeded accounts use the password `password`.

| Role | Email | Scope |
| --- | --- | --- |
| Administrator | `admin@axcess.test` | All departments, staff, exports, and reports |
| Manager | `manager@axcess.test` | Operations department only |
| Manager | `manager.cs@axcess.test` | Customer Support department only |
| Employee | `maya.chen@axcess.test` | Own schedule and leave records |

Run `php artisan db:seed` again only after `migrate:fresh` if you want a clean demo dataset. The seeder is designed to populate a fresh database.

## Using Axcess

1. Sign in and open **Schedule** for the current week, or **Month** for a calendar view.
2. Create a shift with the **New shift** action. Add each break explicitly; Axcess stores and exports every break.
3. Use **Standby** for on-call cover and the replacement action to record who covered a shift.
4. Review leave in **Leave & absences**; managers and administrators can approve or reject requests.
5. Open **Reports & exports** for coverage, hours, breaks, leave, conflicts, standby, and replacement summaries.
6. Use **Export** on the schedule, staff, leave, standby, or reports screen to generate a workbook.

### Export dialogs

The dialogs submit using `fetch`, download the binary response as a blob, and use the server-provided filename. Errors from validation and generation are shown inline. See [docs/EXPORTS.md](docs/EXPORTS.md) for every filter, worksheet, column, filename, and security rule.

Filename examples:

```text
Axcess_Schedule_2026-10-19_to_2026-10-25.xlsx
Axcess_Staff_2026-10-22.xlsx
Axcess_Leave_2026-10-01_to_2026-10-31.xlsx
Axcess_Report_2026-10-01_to_2026-10-31.xlsx
```

## Roles and access

| Capability | Admin | Manager | Employee |
| --- | :---: | :---: | :---: |
| View all departments | ✓ | — | — |
| View own department | ✓ | ✓ | — |
| View own schedule/leave | ✓ | ✓ | ✓ |
| Create/edit shifts | ✓ | own department | — |
| Create standby duties | ✓ | own department | — |
| Manage staff directory | ✓ | own department | — |
| Approve/reject leave | ✓ | own department | — |
| Run reports | ✓ | own department | — |
| Download exports | ✓ | own department | — |
| Include sensitive manager fields | ✓ | own department | — |

Managers cannot request another department or an employee outside their department, even by changing query parameters. Employees receive HTTP 403 for export routes.

## Testing

```bash
php artisan test
```

The feature suite covers:

- overnight shifts and next-day end times;
- multiple breaks, including breaks after midnight;
- schedule period modes (date, week, month, custom);
- department, employee, shift-type, and status filters;
- empty reports and empty workbooks;
- manager department isolation and employee export denial;
- sensitive schedule/leave field exclusion and inclusion;
- password/token exclusion and formula-injection sanitisation;
- workbook branding, metadata, formatting, freeze panes, filters, widths, and formulas;
- real CSV downloads that are not mislabeled Excel files;
- export audit logging without confidential values.

See [docs/TESTING.md](docs/TESTING.md) for the coverage matrix and current results.

## Project structure

```text
app/
  Exports/                 Excel/CSV exports, sheets, formatting, options
  Http/Controllers/        Dashboard, schedule, staff, leave, standby, reports, exports
  Http/Requests/           Validation and export filters
  Models/                  Users, departments, shifts, breaks, leave, absences, conflicts
  Policies/                Record-level authorization
  Services/                Scheduling, conflict detection, reporting, period resolution
  Support/                 Axcess branding and label constants
database/
  factories/ seeders/ migrations/
resources/
  css/ js/ views/
routes/web.php
docs/
  EXPORTS.md  IMPLEMENTATION_PLAN.md  TESTING.md
```

## Deployment notes

- Set `APP_ENV=production`, `APP_DEBUG=false`, a strong `APP_KEY`, and real mail/database credentials.
- Run `php artisan migrate --force`, `npm ci && npm run build`, and `php artisan optimize`.
- Ensure `storage/` and `bootstrap/cache/` are writable.
- The export routes are synchronous. For very large workbooks, put a queue in front of them or raise the PHP memory/time limits.
- `public/build` is generated by Vite and intentionally ignored by Git; run the asset build during deployment.

## Configuration

Axcess-specific values live in `config/axcess.php` and can be set in `.env`:

```dotenv
APP_NAME=Axcess
AXCESS_SUPPORT_EMAIL=support@axcess.app

# Maximum date span for one export request (days).
AXCESS_EXPORT_MAX_RANGE_DAYS=366

# Above this estimated row count the UI recommends narrowing filters.
AXCESS_EXPORT_LARGE_THRESHOLD=5000

# Label shown in workbook metadata, e.g. UTC or Europe/London.
AXCESS_EXPORT_TIMEZONE_LABEL=UTC
```

## Dependency and compatibility notes

- **Maatwebsite/Excel 4.0.5** requires exports to implement `Maatwebsite\Excel\Concerns\Export` before `Excel::download()` or `Excel::raw()` accepts them.
- **PhpSpreadsheet 5.10.0** exposes zoom settings on `$worksheet->getSheetView()->setZoomScale(...)`, not directly on the worksheet.
- Laravel Excel 4 compacts empty rows when writing arrays. Axcess therefore places its header row at row 6 and styles all ranges accordingly; the blank spacer row is not relied upon.
- Multi-sheet workbooks always use `.xlsx`. CSV uses a dedicated primary-sheet export and is never presented as Excel.
- Scheduled hours are planned hours. They are not attendance, payroll, or clocking data.

## License

Axcess is open-source software licensed under the [MIT license](LICENSE).
