# Axcess testing guide

## Commands

```bash
# Full application test suite
php artisan test

# Export, scheduling, and authorization suites
php artisan test tests/Feature/Exports tests/Feature/ScheduleServiceTest.php

# Unit checks for branding and period resolution
php artisan test tests/Unit

# PHP style check
vendor/bin/pint --test

# Front-end production build
npm run build
```

Tests use SQLite `:memory:` via `phpunit.xml`, so no demo database is modified.

## Test suites

| File | Focus |
| --- | --- |
| `tests/Feature/ApplicationTest.php` | Guest redirect, branded login, authenticated dashboard |
| `tests/Feature/ScheduleServiceTest.php` | Overnight persistence and complete break replacement |
| `tests/Feature/Exports/ScheduleWorkbookExportTest.php` | Overnight rows, multiple breaks, empty periods, filters, branding, sensitive fields, injection protection, worksheet selection |
| `tests/Feature/Exports/StaffLeaveReportExportTest.php` | Staff directory/contact controls, filters, leave + absence sheets, summary formulas, empty leave export, report type sheet sets, report formula totals |
| `tests/Feature/Exports/ExportAuthorizationTest.php` | Guest/employee denial, manager isolation, admin access, validation responses, real CSV, audit-log privacy |
| `tests/Unit/AxcessTest.php` | Brand/title constants and label formatting |
| `tests/Unit/PeriodResolverTest.php` | Date, week, month, custom, reversed, and maximum-range periods |

## Requirement coverage

| Requirement | Covered by |
| --- | --- |
| Genuine `.xlsx` workbooks | Workbook tests load output with PhpSpreadsheet; HTTP tests assert XLSX MIME and `.xlsx` filename |
| CSV is not mislabeled Excel | `test_csv_download_is_a_real_csv_with_filename_and_safe_contents` asserts `text/csv`, `.csv`, and non-zip body |
| Date/week/month/custom ranges | `PeriodResolverTest` and schedule filter test |
| Department/employee/shift/status filters | `test_schedule_filters_limit_rows_by_period_department_and_employee`; request validation tests |
| Overnight shifts | Workbook and `ScheduleService` tests assert next-day end and overnight flag |
| Multiple breaks | Workbook asserts both break rows and summary; service asserts both persisted |
| Never discard extra breaks | Service update test asserts all old breaks replaced by all new breaks |
| Schedule workbook fields | Column-level assertions and full schedule headings |
| Relevant worksheets | Asserts all six schedule sheet names and report type subsets |
| Staff/leave/report exports | Dedicated staff, leave, and reports tests |
| Sensitive-field restriction | Schedule and leave sensitive column tests; audit-log privacy test |
| Formatting | Header bold/fill, freeze pane, filter range, widths, date formats, merged title, footer, properties |
| Formulas | Staff Summary and Leave Summary formulas and calculated values |
| Formula-injection protection | Sanitisation test with `=HYPERLINK(...)` and phone `+` |
| Filenames | Exact schedule/staff/leave/report/CSV filenames |
| Server validation and errors | Invalid format, reversed range, cross-department employee |
| Authorization on every export | Guest and employee loops across all four endpoints; manager and admin scope tests |
| No passwords/tokens | Staff export secret exclusion and heading assertions |

## Empty-report checks

The following are explicitly generated and inspected:

- empty schedule workbook with all six worksheets;
- empty Leave & Absences and Leave Summary sheets;
- empty management report overview where applicable;
- empty sheets still have branded title rows, header row, filter range, and valid XLSX structure.

## Current result

Latest local run: **30 tests passed, 223 assertions** (see the final run output in the delivery transcript). The suite typically completes in about a minute on the development machine.

Recorded composer/PHP versions for this run:

- PHP 8.5.4
- Laravel 12.70.1
- Maatwebsite/Excel 4.0.5
- PhpSpreadsheet 5.10.0
- PHPUnit 11.5.x
- Node 22.22.1
