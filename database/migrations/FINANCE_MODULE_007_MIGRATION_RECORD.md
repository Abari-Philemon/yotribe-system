# Finance Module 007 - Migration Execution Record

Date: 2026-10-13
Database: yotribe_ifms
Database engine: MariaDB 10.4.32

## Confirmed applied migrations

1. `011a_financial_year_company_constraint.sql`
   - Added unique key `uq_financial_year_company_id`.
   - Added foreign key `fk_financial_year_company`.
   - Schema verified.

2. `011b_financial_period_company_year_constraint.sql`
   - Added composite foreign key `fk_financial_period_company_year`.
   - Schema verified.

3. `012a_financial_year_current_unique.sql`
   - Added generated column `current_company_id`.
   - Added unique key `uq_financial_year_current_company`.
   - Schema verified.

4. `012b_financial_period_current_unique.sql`
   - Added generated column `current_financial_year_id`.
   - Added unique key `uq_financial_period_current_year`.
   - Schema verified.

## Integrity and regression verification

- Financial-year service: 15 passed, 0 failed.
- Financial-period service: 5 passed, 0 failed.
- Financial-period generation: 11 passed, 0 failed.
- Existing-data integrity checks: 3 checks passed, no violations found.
- Generated-column uniqueness compatibility tests passed.

## Do not execute as-is

- `011_finance_integrity_constraints.sql`
- `012_finance_current_uniqueness.sql`

These consolidated scripts contain statements already applied through the individual migrations listed above. Executing them again would attempt to recreate existing schema objects.

## Migration tracking

A search of information_schema found no migration-tracking table matching `%migration%` or `%schema_version%`. This is not proof that no differently named tracking mechanism exists.

## Important

This note documents the confirmed execution history from the maintenance session. It is not a replacement for a formal migration ledger.
