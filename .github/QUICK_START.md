# Quick Start: Release Workflow

## TL;DR

To create a new release:

1. Go to **GitHub → Actions → Release Plugin**
2. Click **"Run workflow"**
3. Select bump type: `patch` | `minor` | `major`
4. Wait 1-2 minutes
5. Download from **Releases** page

---

## Visual Flow

```
┌─────────────────────────────────────────────────────────────┐
│  1. Manual Trigger (GitHub Actions)                         │
│     Choose: patch / minor / major                           │
└────────────────────┬────────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────────┐
│  2. Extract Current Version                                 │
│     From: ocpay-woocommerce.php                            │
│     Example: 1.0.1                                          │
└────────────────────┬────────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────────┐
│  3. Calculate New Version                                   │
│     patch: 1.0.1 → 1.0.2                                   │
│     minor: 1.0.1 → 1.1.0                                   │
│     major: 1.0.1 → 2.0.0                                   │
└────────────────────┬────────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────────┐
│  4. Update Version in Files                                 │
│     - ocpay-woocommerce.php (header + constant)            │
│     - composer.json                                         │
└────────────────────┬────────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────────┐
│  5. Commit & Push                                           │
│     Message: "chore: bump version to X.Y.Z"                │
└────────────────────┬────────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────────┐
│  6. Build Clean ZIP Package                                 │
│     Excludes: .git, .github, node_modules, tests, etc.     │
│     Output: ocpay-woocommerce-X.Y.Z.zip                    │
└────────────────────┬────────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────────┐
│  7. Create Git Tag                                          │
│     Tag: vX.Y.Z                                             │
│     Push to repository                                      │
└────────────────────┬────────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────────┐
│  8. Create GitHub Release                                   │
│     - Title: Release vX.Y.Z                                 │
│     - Asset: ocpay-woocommerce-X.Y.Z.zip                   │
│     - Auto-generated release notes                          │
└─────────────────────────────────────────────────────────────┘
```

---

## Example Usage Scenarios

### Scenario 1: Bug Fix Release
**Current Version**: 1.0.1  
**Action**: Select `patch`  
**Result**: 1.0.2  
**Use Case**: Fixed payment validation bug

### Scenario 2: New Feature
**Current Version**: 1.0.2  
**Action**: Select `minor`  
**Result**: 1.1.0  
**Use Case**: Added support for recurring payments

### Scenario 3: Breaking Change
**Current Version**: 1.1.0  
**Action**: Select `major`  
**Result**: 2.0.0  
**Use Case**: Redesigned API integration (breaking)

---

## What Gets Included in the ZIP?

### ✅ Included
- `ocpay-woocommerce.php` (main plugin file)
- `includes/` (all PHP classes)
- `assets/` (CSS, JS, images)
- `templates/` (payment pages)
- `composer.json`
- `package.json`

### ❌ Excluded
- `.git/` and `.github/` directories
- `node_modules/` and `vendor/`
- `tests/` directory
- `.gitignore`, `.distignore`
- Log files and IDE files
- Any markdown files except README.md

---

## Troubleshooting

### Issue: "Version not updating"
- Check that version format in `ocpay-woocommerce.php` is `X.Y.Z`
- Ensure no manual changes are uncommitted

### Issue: "Workflow fails to push"
- Verify repository has GitHub Actions write permissions enabled
- Check Actions settings in repository

### Issue: "Release not created"
- Ensure `GITHUB_TOKEN` has proper permissions
- Check workflow logs for detailed error messages

---

## Next Steps After Release

1. **Download the ZIP**: Go to Releases page
2. **Test Installation**: Try installing on a test WordPress site
3. **Update Documentation**: Add changelog entry if needed
4. **Announce**: Share release notes with users

---

For detailed technical documentation, see:
- [WORKFLOW_DOCS.md](WORKFLOW_DOCS.md) - Complete workflow documentation
- [../README.md](../README.md) - Plugin documentation
