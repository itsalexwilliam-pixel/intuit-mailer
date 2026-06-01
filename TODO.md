# TODO

- [x] Fix PostgreSQL parameter limit issue in contact CSV import by chunking contact inserts.
- [x] Add upload processing UX in import page (disable submit, show "Uploading/Processing..." indicator while request runs).
- [x] Optimize ImportController to remove expensive pre-check loops and rely on chunked insertOrIgnore with account-scoped uniqueness.
- [x] Keep import summary accuracy (imported/skipped/failedRows) while minimizing DB queries.
- [ ] Run contact feature tests.
