#!/usr/bin/env bash
#
# Ship a release to the WordPress.org plugin directory.
#
# Syncs the plugin files into the SVN working copy at ./svn, refreshes the
# wp.org assets, creates the version tag, and commits all three in one go.
#
# Usage: npm run plugin-ship [-- <options>]
#
#   --dry-run       Do everything except the commit, then stop and report.
#   --yes           Skip the confirmation prompt (for unattended runs).
#   --skip-build    Reuse the existing build/ instead of rebuilding.
#   --allow-dirty   Ship even though the git working tree has changes.
#   --help          Show this message.
#
# The set of files that ship is defined by .distignore.
# The wp.org assets (icon, banner, screenshots) live in .wordpress-org/.

set -euo pipefail

readonly SLUG='synced-patterns-for-themes'
readonly SVN_URL="https://plugins.svn.wordpress.org/${SLUG}"

readonly ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
readonly SVN_DIR="${ROOT}/svn"
readonly ASSETS_SRC="${ROOT}/.wordpress-org"

DRY_RUN=0
ASSUME_YES=0
SKIP_BUILD=0
ALLOW_DIRTY=0

# --- output helpers ----------------------------------------------------------

if [ -t 1 ]; then
	C_RESET=$'\033[0m'; C_BOLD=$'\033[1m'; C_DIM=$'\033[2m'
	C_RED=$'\033[31m'; C_GREEN=$'\033[32m'; C_YELLOW=$'\033[33m'
else
	C_RESET=''; C_BOLD=''; C_DIM=''; C_RED=''; C_GREEN=''; C_YELLOW=''
fi

step() { printf '\n%s==>%s %s%s%s\n' "$C_BOLD$C_GREEN" "$C_RESET" "$C_BOLD" "$*" "$C_RESET"; }
info() { printf '    %s\n' "$*"; }
dim()  { printf '    %s%s%s\n' "$C_DIM" "$*" "$C_RESET"; }
warn() { printf '%s!!  %s%s\n' "$C_YELLOW" "$*" "$C_RESET" >&2; }
die()  { printf '\n%sxx  %s%s\n\n' "$C_RED" "$*" "$C_RESET" >&2; exit 1; }

usage() { sed -n '2,/^set -euo/p' "${BASH_SOURCE[0]}" | sed -e 's/^# \{0,1\}//' -e '/^set -euo/d'; }

# --- arguments ---------------------------------------------------------------

while [ $# -gt 0 ]; do
	case "$1" in
		--dry-run)     DRY_RUN=1 ;;
		--yes|-y)      ASSUME_YES=1 ;;
		--skip-build)  SKIP_BUILD=1 ;;
		--allow-dirty) ALLOW_DIRTY=1 ;;
		--help|-h)     usage; exit 0 ;;
		*)             die "Unknown option: $1  (try --help)" ;;
	esac
	shift
done

cd "$ROOT"

# --- 1. preflight ------------------------------------------------------------

step 'Preflight'

for cmd in svn rsync npm node git; do
	command -v "$cmd" >/dev/null 2>&1 || die "Required command not found: ${cmd}"
done
dim "tooling ok: svn, rsync, npm, node, git"

# Version must agree in all three places wp.org and WordPress read it from.
pkg_version="$(node -p "require('./package.json').version")"
hdr_version="$(sed -n -E 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*(.+[^[:space:]])[[:space:]]*$/\1/p' "${SLUG}.php" | head -n1)"
txt_version="$(sed -n -E 's/^Stable tag:[[:space:]]*(.+[^[:space:]])[[:space:]]*$/\1/p' readme.txt | head -n1)"

[ -n "$pkg_version" ] || die 'Could not read version from package.json'
[ -n "$hdr_version" ] || die "Could not read Version: from ${SLUG}.php"
[ -n "$txt_version" ] || die 'Could not read Stable tag: from readme.txt'

if [ "$pkg_version" != "$hdr_version" ] || [ "$pkg_version" != "$txt_version" ]; then
	printf '\n' >&2
	printf '    package.json  version     %s\n' "$pkg_version" >&2
	printf '    %s.php  Version:    %s\n' "$SLUG" "$hdr_version" >&2
	printf '    readme.txt    Stable tag: %s\n' "$txt_version" >&2
	die 'Version mismatch. Make all three agree before shipping.'
fi

readonly VERSION="$pkg_version"
dim "version ${VERSION} (package.json, plugin header, readme.txt all agree)"

# The release should correspond to a commit, so it can be reproduced later.
if [ -n "$(git status --porcelain)" ]; then
	if [ "$ALLOW_DIRTY" -eq 1 ]; then
		warn 'git working tree is dirty; shipping anyway (--allow-dirty)'
	else
		printf '\n' >&2
		git status --short >&2
		die 'git working tree is not clean. Commit first, or pass --allow-dirty.'
	fi
fi
GIT_SHA="$(git rev-parse --short HEAD)"
dim "git ${GIT_SHA} on $(git rev-parse --abbrev-ref HEAD)"

[ -d "$ASSETS_SRC" ] || die "Missing assets directory: ${ASSETS_SRC}"
[ -n "$(find "$ASSETS_SRC" -type f -print -quit)" ] || die "No files in ${ASSETS_SRC}"
dim "assets source: .wordpress-org/ ($(find "$ASSETS_SRC" -type f | wc -l | tr -d ' ') files)"

# --- 2. svn working copy -----------------------------------------------------

step 'Preparing SVN working copy'

if [ ! -d "${SVN_DIR}/.svn" ]; then
	info "No checkout at svn/ yet, fetching ${SVN_URL}"
	svn checkout "$SVN_URL" "$SVN_DIR"
else
	actual_url="$(svn info --show-item url "$SVN_DIR" | tr -d '[:space:]')"
	[ "$actual_url" = "$SVN_URL" ] || die "svn/ points at ${actual_url}, expected ${SVN_URL}"
fi

# A dirty working copy means a previous run left something behind; refuse to
# build a release on top of it rather than committing whatever is lying around.
if [ -n "$(svn status "$SVN_DIR")" ]; then
	printf '\n' >&2
	svn status "$SVN_DIR" >&2
	printf '\n    Reset it with:\n      %s\n' "npm run plugin-ship:reset" >&2
	die 'The SVN working copy has uncommitted changes.'
fi

info 'Updating from wp.org'
svn update --quiet "$SVN_DIR"
dim "at r$(svn info --show-item revision "$SVN_DIR" | tr -d '[:space:]')"

if svn ls "${SVN_URL}/tags/${VERSION}" >/dev/null 2>&1; then
	die "Tag ${VERSION} already exists on wp.org. Bump the version to ship again."
fi
dim "tags/${VERSION} is free"

# --- 3. build ----------------------------------------------------------------

if [ "$SKIP_BUILD" -eq 1 ]; then
	step 'Build (skipped)'
	warn 'Shipping the existing build/ without rebuilding'
else
	step 'Building'
	npm run build
fi

[ -f "${ROOT}/build/index.js" ] || die 'build/index.js is missing; the build did not produce output.'

# --- 4. sync -----------------------------------------------------------------

step 'Syncing files into svn/trunk'

# Two hops on purpose. rsync protects excluded files on the receiving side from
# --delete, so syncing the repo straight into trunk would strand any file that
# used to ship and has since been added to .distignore. Assembling the release
# in a clean staging directory first means the second hop excludes nothing but
# .svn, so --delete can prune trunk down to exactly what was staged.
STAGING="$(mktemp -d)"
trap 'rm -rf "$STAGING"' EXIT

rsync -a --delete \
	--exclude='.svn' \
	--exclude-from="${ROOT}/.distignore" \
	"${ROOT}/" "${STAGING}/"

rsync -a --delete \
	--exclude='.svn' \
	"${STAGING}/" "${SVN_DIR}/trunk/"

info "$(find "${SVN_DIR}/trunk" -type f -not -path '*/.svn/*' | wc -l | tr -d ' ') files in trunk"

step 'Syncing wp.org assets'

rsync -a --delete \
	--exclude='.svn' \
	"${ASSETS_SRC}/" "${SVN_DIR}/assets/"

info "$(find "${SVN_DIR}/assets" -type f -not -path '*/.svn/*' | wc -l | tr -d ' ') files in assets"

# Teach svn about anything rsync added or removed.
reconcile() {
	local dir="$1"

	# --force adds unversioned children without complaining about the rest.
	svn add --force --quiet "$dir"

	# Files rsync deleted show up as missing (!) and must be removed explicitly.
	svn status "$dir" | sed -n -E 's/^![[:space:]]+//p' | while IFS= read -r missing; do
		[ -n "$missing" ] && svn rm --quiet --force "$missing"
	done
}

step 'Reconciling adds and deletes'
reconcile "${SVN_DIR}/trunk"
reconcile "${SVN_DIR}/assets"
dim 'done'

# --- 5. tag ------------------------------------------------------------------

step "Tagging ${VERSION}"

[ -e "${SVN_DIR}/tags/${VERSION}" ] && die "svn/tags/${VERSION} already exists locally."
svn copy --quiet "${SVN_DIR}/trunk" "${SVN_DIR}/tags/${VERSION}"
dim "trunk copied to tags/${VERSION}"

# --- 6. review ---------------------------------------------------------------

step 'Pending changes'

# Suppress the tag's per-file noise; the tag copy is one logical change.
( cd "$SVN_DIR" && svn status | grep -v "tags/${VERSION}/" ) || true
printf '\n'
info "plus the whole of tags/${VERSION} (copied from trunk)"

readonly COMMIT_MSG="Release ${VERSION} (git ${GIT_SHA})"

if [ "$DRY_RUN" -eq 1 ]; then
	step 'Dry run: stopping before commit'
	info "Would commit with message: ${COMMIT_MSG}"
	printf '\n    Undo everything staged above with:\n      %s\n\n' "npm run plugin-ship:reset"
	exit 0
fi

# --- 7. commit ---------------------------------------------------------------

if [ "$ASSUME_YES" -eq 0 ]; then
	printf '\n%sThis publishes %s to wordpress.org and cannot be undone.%s\n' "$C_BOLD" "$VERSION" "$C_RESET"
	printf 'Type %syes%s to commit: ' "$C_BOLD" "$C_RESET"
	read -r reply
	[ "$reply" = 'yes' ] || die 'Aborted. Nothing was committed; run with --dry-run notes to reset.'
fi

step 'Committing to wp.org'
info 'SVN will ask for your wordpress.org credentials if they are not cached.'

svn commit "$SVN_DIR" -m "$COMMIT_MSG"

step "Shipped ${VERSION}"
info "https://wordpress.org/plugins/${SLUG}/"
dim 'wp.org rebuilds the download zip from the Stable tag; it can take a few minutes.'
