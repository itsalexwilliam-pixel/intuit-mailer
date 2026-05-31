# TODO

- [ ] Refactor ImportController to handle duplicates safely without DB unique-violation crashes
- [ ] Improve import performance for large CSV files (batch existing-email lookups)
- [ ] Increase CSV validation upload limit in ImportController
- [ ] Add migration to move contacts uniqueness from global email to (account_id, email)
- [ ] Add/adjust import feature tests for duplicate handling and account isolation
- [ ] Run targeted ContactManagementFeatureTest import tests
- [ ] Provide live-server large upload config (Nginx + PHP-FPM) commands
