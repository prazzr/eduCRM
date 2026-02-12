# Database Normalization Update

**Date:** January 5, 2026  
**Version:** 2.2.0  
**Updated:** January 5, 2026  
**Type:** Technical Reference (Visa Workflow + Document Checklist Enhancement)

---

## Overview

The EduCRM database has been optimized to follow **Third Normal Form (3NF)** industry standards. This update eliminates data redundancy, ensures referential integrity, and improves query performance while maintaining 100% backward compatibility with existing PHP code.

---

## Changes Summary

### New Lookup Tables Created

| Table | Records | Purpose |
|-------|---------|---------|
| `countries` | 13 | Country master data with ISO 3166-1 alpha-3 codes |
| `education_levels` | 9 | Standardized education level definitions |
| `communication_types` | 7 | Messaging channel types (sms, email, whatsapp, viber, call, meeting, note) |
| `visa_stages` | 5 | Visa workflow stages (Doc Collection → Approved/Rejected) |
| `application_statuses` | 6 | University application status values |
| `inquiry_statuses` | 4 | Inquiry pipeline statuses (new, contacted, converted, closed) |
| `priority_levels` | 3 | Lead priority with color codes (hot, warm, cold) |
| `test_types` | 6 | Standardized test definitions (IELTS, PTE, SAT, TOEFL, GRE, GMAT) |

### Tables Modified

| Table | New Columns Added |
|-------|-------------------|
| `users` | `country_id`, `education_level_id` |
| `inquiries` | `country_id`, `education_level_id`, `status_id`, `priority_id` |
| `university_applications` | `partner_id`, `country_id`, `status_id` |
| `visa_workflows` | `country_id`, `stage_id`, `workflow_progress_id`, `stage_started_at`, `expected_completion_date`, `priority` |
| `visa_stages` | `default_sla_days`, `allowed_next_stages` |
| `partners` | `country_id` |
| `test_scores` | `test_type_id` |
| `messaging_gateways` | `type_id` |
| `messaging_templates` | `type_id` |
| `messaging_queue` | `type_id` |
| `messaging_campaigns` | `type_id` |
| `communication_credits` | `type_id` |
| `communication_usage_logs` | `type_id` |
| `student_logs` | `type_id` |
| `workflow_templates` | `country_id` |

### New Tables Created (v2.1 - v2.2)

| Table | Purpose |
|-------|---------|
| `visa_workflow_history` | Audit trail for all visa stage transitions |
| `document_types` | Admin-managed document types for visa checklist |
| `student_documents` | Tracks student document uploads and verification status |

### Database Triggers Created

14 triggers auto-sync legacy columns with new FK columns:

| Trigger | Table | Syncs |
|---------|-------|-------|
| `trg_inquiries_before_insert` | inquiries | country_id, status_id, priority_id, education_level_id |
| `trg_inquiries_before_update` | inquiries | country_id, status_id, priority_id |
| `trg_users_before_insert` | users | country_id, education_level_id |
| `trg_users_before_update` | users | country_id, education_level_id |
| `trg_visa_workflows_before_insert` | visa_workflows | country_id, stage_id |
| `trg_visa_workflows_before_update` | visa_workflows | country_id, stage_id |
| `trg_university_applications_before_insert` | university_applications | country_id, status_id, partner_id |
| `trg_university_applications_before_update` | university_applications | country_id, status_id |
| `trg_messaging_gateways_before_insert` | messaging_gateways | type_id |
| `trg_messaging_templates_before_insert` | messaging_templates | type_id |
| `trg_messaging_queue_before_insert` | messaging_queue | type_id |
| `trg_messaging_campaigns_before_insert` | messaging_campaigns | type_id |
| `trg_student_logs_before_insert` | student_logs | type_id |

### Views Created

| View | Purpose |
|------|---------|
| `v_inquiries_full` | Denormalized inquiry data with all lookups |
| `v_university_applications_full` | Applications with partner and country names |
| `v_visa_workflows_full` | Visa workflows with country, stage, and overdue flag |
| `v_test_scores_full` | Test scores with test type details |
| `v_student_documents_full` | Student documents with type names and verifier info |

---

## Migration Files

| File | Description |
|------|-------------|
| `schema_normalized.sql` | Complete 3NF schema with all lookup tables, FK columns, data migration, and views |
| `schema_triggers.sql` | All 14 database triggers for auto-sync functionality |
| `fix_migration_data.sql` | Additional data fixes for edge cases (US/USA mapping, etc.) |
| `run_normalization_migration.php` | PHP migration runner with backup, dry-run, and rollback support |
| `sql/visa_workflow_enhancement.sql` | Visa workflow enhancement migration (v2.1) |
| `run_visa_enhancement_migration.php` | Visa enhancement migration runner |
| `sql/document_checklist_enhancement.sql` | Document types and student documents tables (v2.2) |
| `run_document_checklist_migration.php` | Document checklist migration runner |

---

## Backward Compatibility

### No Code Changes Required

The existing PHP codebase continues to work without modification:

```php
// This still works - trigger auto-populates country_id
$stmt = $pdo->prepare("INSERT INTO inquiries (intended_country) VALUES (?)");
$stmt->execute(['Australia']);
// country_id is automatically set to 1 by trigger
```

### Migration Strategy

1. **Old columns retained** - `intended_country`, `education_level`, `status`, etc. still exist
2. **New FK columns added** - `country_id`, `education_level_id`, `status_id`, etc.
3. **Triggers sync automatically** - Any INSERT/UPDATE on old columns populates new FK columns
4. **Views for reporting** - Use views for denormalized reporting queries

---

## Benefits

| Benefit | Description |
|---------|-------------|
| **Storage Reduction** | ~60% reduction on repeated string fields (INT FK vs VARCHAR) |
| **Data Consistency** | No more "Australia" vs "AUS" vs "australia" variations |
| **Query Performance** | JOINs on indexed INT columns are faster |
| **Referential Integrity** | Foreign key constraints prevent orphaned data |
| **Single Update Point** | Change country name in one place, all references update |
| **Extensibility** | Easy to add new countries, statuses, etc. without schema changes |

---

## Verification

After migration, verify with:

```sql
-- Check all lookup tables exist
SHOW TABLES LIKE '%_levels' OR '%_types' OR '%_statuses' OR '%_stages';

-- Verify triggers are active
SHOW TRIGGERS;

-- Test auto-sync
INSERT INTO inquiries (name, intended_country, status) 
VALUES ('Test', 'Canada', 'new');

SELECT name, intended_country, country_id, status, status_id 
FROM inquiries ORDER BY id DESC LIMIT 1;
-- Should show: country_id=4 (Canada), status_id=1 (new)
```

---

## Rollback

If needed, restore from backup:

```bash
# Backup location
F:\CRM\backups\pre_normalization_2026-01-04_*.sql

# Restore command
mysql -uroot edu_crm < F:\CRM\backups\pre_normalization_2026-01-04_HHMMSS.sql
```

---

## Future Considerations

### Phase 2 (Optional)

After verifying production stability, consider:

1. **Remove legacy columns** - Drop `intended_country`, `education_level`, etc.
2. **Update PHP code** - Use FK columns directly for better performance
3. **Remove triggers** - No longer needed after code updates

### Cleanup SQL (Uncomment when ready)

```sql
-- ALTER TABLE inquiries DROP COLUMN intended_country;
-- ALTER TABLE inquiries DROP COLUMN education_level;
-- ALTER TABLE inquiries DROP COLUMN status;
-- ALTER TABLE inquiries DROP COLUMN priority;
-- etc.
```

---

**Author:** System Migration  
**Reviewed:** January 5, 2026  
**Status:** Production Ready (v2.1)
