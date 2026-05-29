# Branding Update TODO

- [x] Read `FEATURES.md` and identify all branding mentions to update
- [x] Update default/fallback app name to `Intuit Inc.` in backend config touchpoints
- [x] Update unsubscribe page branding text to include company name + address
- [x] Update tracked email footer branding text to include company name + address
- [x] Update documentation branding in `README.md` and `FEATURES.md`
- [x] Verify replacements with a final repo-wide search for old branding terms

# Account Context Fix TODO

- [x] Update `database/seeders/TempAdminSeeder.php` to assign admin role and valid `account_id`
- [x] Re-run seeder
- [x] Re-test login/dashboard for 403 resolution

# Deep validation and test-hardening TODO

- [x] Run full test suite and collect failures
- [ ] Patch failing feature tests for account-scoped behavior and updated UI text
- [ ] Re-run full test suite until green
- [ ] Run SMTP rotation and sending-limit deep verification scenarios
- [ ] Confirm campaign/app throttling behavior under queue processing

# Merge tags fix TODO

- [x] Investigate campaign/single-email placeholder parsing issue from screenshot
- [ ] Add shared merge-tag resolver supporting multiple token styles + current_date
- [ ] Wire resolver into CampaignMail, DripMail, and SingleEmailMail
- [ ] Add/update targeted tests for campaign + single-email merge replacements
- [ ] Run targeted tests and fix any regressions

# SMTP rotation + pacing TODO

- [x] Inspect and patch `app/Console/Commands/WorkMailsQueueCommand.php` for strict SMTP round-robin sequencing
- [x] Add persisted per-account rotation pointer so sending cycles 1..N then wraps to 1
- [x] Enforce 10-second gap between each sent email in worker processing path
- [x] Keep fallback/retry behavior intact when an SMTP fails or reaches daily limit
- [ ] Provide EC2 diagnostic commands to verify SMTP id sequence and 10-second pacing
