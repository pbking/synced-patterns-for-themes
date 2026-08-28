#!/usr/bin/env bash
#
# Put the SVN working copy back the way wp.org has it.
#
# Reverts every scheduled change and deletes anything unversioned, which is
# what you want after a --dry-run or a run that stopped part way through.
#
# Usage: npm run plugin-ship:reset

set -euo pipefail

readonly ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
readonly SVN_DIR="${ROOT}/svn"

[ -d "${SVN_DIR}/.svn" ] || { echo "No SVN working copy at ${SVN_DIR}; nothing to reset." >&2; exit 0; }

if [ -z "$(svn status "$SVN_DIR")" ]; then
	echo 'SVN working copy is already clean.'
	exit 0
fi

echo 'Reverting scheduled changes...'
svn revert --recursive --quiet "$SVN_DIR"

# Reverting unschedules adds but leaves the files on disk; clear those out too.
echo 'Removing unversioned files...'
svn status "$SVN_DIR" | sed -n -E 's/^\?[[:space:]]+//p' | while IFS= read -r extra; do
	[ -n "$extra" ] && rm -rf "$extra"
done

if [ -n "$(svn status "$SVN_DIR")" ]; then
	echo
	svn status "$SVN_DIR"
	echo
	echo 'Some changes remain; inspect them by hand.' >&2
	exit 1
fi

echo 'Clean.'
