# Release v1.1.0-beta.2

## Goal
Publish an installable, verified GitHub prerelease for `v1.1.0-beta.2` from an immutable merged `main` commit.

## Constraints
- Link delivery to approved issue #148 and use exactly one `type:*` label.
- Change only release metadata in `style.css`, `package.json`, and `package-lock.json`; do not update dependencies.
- Keep generated `dist/`, ZIPs, checksums, backups, secrets, and temporary files outside Git.
- Preserve the beta.1 runtime package whitelist: root theme PHP files, `style.css`, `acf-json/`, `assets/`, generated `dist/`, `inc/`, `pages/`, and `templates/`.
- Preserve the existing bundled ACF PRO archive convention.
- Create an annotated tag only after verifying it targets freshly fetched immutable `origin/main`.
- Publish a GitHub prerelease; do not publish to npm or change the private Tailscale deployment.

## Tasks
- [ ] **RB2-01 — Prepare and review release metadata.** Bump all four active version fields to `1.1.0-beta.2`, run clean source/build/regression/package-candidate checks, commit, independently verify, complete native review, and merge the approved release PR.
- [ ] **RB2-02 — Tag, package, publish, and verify.** Create the annotated tag from the immutable release commit, rebuild/package the reviewed runtime whitelist, publish the prerelease ZIP and SHA-256 checksum, download and verify both assets, then record closure evidence.

## Package Contract

| Include | Exclude |
|---|---|
| Root runtime PHP files and `style.css` | `.git*`, `.github/`, `.agents/`, `.codegraph/` |
| `acf-json/`, `assets/`, generated `dist/` | `node_modules/`, `src/`, `tests/`, `scripts/` |
| `inc/`, `pages/`, `templates/` | `docs/`, `odd/`, `openspec/`, dev manifests/config |
| Bundled `inc/plugins/advanced-custom-fields-pro.zip` | `.env*`, `hot`, logs, archives, backups, scratch files |

## Evidence
- Approved release issue: #148.
- Baseline: `main` at `619313557e5e06e0a313ef48d2fa3e7b8083cb01`.
- Prior convention: annotated `v1.1.0-beta.1`, GitHub prerelease, asset `simple-rms-theme-v1.1.0-beta.1.zip`, one `simple-rms-theme/` package root, 155 entries, runtime-only whitelist.
- Requested tag `v1.1.0-beta.2` and release are currently available.
- Pre-merge candidate verification: `npm ci`, TypeScript, Vite build (53-entry manifest), 112 production PHP lints, 7 tracked JSON parses, 61 PHP harnesses, 5 JS harnesses, and 3 script suites PASS; landing orchestrator 293/293.
- Candidate package verification: deterministic Python ZIP has 183 entries/168 files, 108 packaged PHP lints, exact runtime whitelist and tree inventory, no unsafe paths/symlinks/credential-shaped values, and SHA-256 `da9d8bb367678c05832fb17bac6570a3df8744635d8251298c11d011502c9159`. This candidate is evidence only and will not be published in place of the immutable tag artifact.
