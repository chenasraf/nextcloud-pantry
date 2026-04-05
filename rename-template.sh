#!/usr/bin/env bash
# Replace template names in all text files under the current directory.
# Prompts for target names and replaces these sources:
#   $SOURCE_PACKAGE   -> <kebab-case input>
#   $SOURCE_PASCAL    -> <PascalCase input>
#   $SOURCE_USER      -> <username input>         (default: your-user)
#   $SOURCE_FULL      -> <full-name input>        (default: Your Name)
#
# Defaults (override via env before running):
#   SOURCE_PACKAGE=nextcloudapptemplate
#   SOURCE_PASCAL=NextcloudAppTemplate
#   SOURCE_USER=your-user
#   SOURCE_FULL=Your Name

set -euo pipefail

# --- Resolve absolute path to this script (portable) ---
abs_path() { perl -MCwd=abs_path -e 'print abs_path(shift)' "$1"; }
SCRIPT_ABS="$(abs_path "$0")"

SOURCE_PACKAGE="${SOURCE_PACKAGE:-nextcloudapptemplate}"
SOURCE_APPNAME="${SOURCE_APPNAME:-Nextcloud App Template}"
SOURCE_PASCAL="${SOURCE_PASCAL:-NextcloudAppTemplate}"
SOURCE_USER="${SOURCE_USER:-your-user}"
SOURCE_FULL="${SOURCE_FULL:-Your Name}"
SOURCE_EMAIL="${SOURCE_EMAIL:-your@email.com}"
SOURCE_WEBSITE="${SOURCE_WEBSITE:-https://your.website}"

printf "Enter package name (e.g., mynextcloudapp): "
IFS= read PACKAGE
printf "Enter app name (e.g., My Nextcloud App): "
IFS= read APPNAME
printf "Enter PascalCase name (e.g., MyNextcloudApp): "
IFS= read PASCAL
printf "Enter GitHub username (e.g., myUsername): "
IFS= read DEST_USER
printf "Enter full name (e.g., My Full Name): "
IFS= read DEST_FULL
printf "Enter email (e.g., myemail@example.com): "
IFS= read DEST_EMAIL
printf "Enter website (e.g., https://mywebsite.com): "
IFS= read DEST_WEBSITE

if [[ -z "${PACKAGE}" || -z "${APPNAME}" || -z "${PASCAL}" || -z "${DEST_USER}" || -z "${DEST_FULL}" || -z "${DEST_EMAIL}" || -z "${DEST_WEBSITE}" ]]; then
  echo "All values are required." >&2
  exit 1
fi

echo "Replacing:"
echo "  ${SOURCE_PACKAGE}   -> ${PACKAGE}"
echo "  ${SOURCE_APPNAME} -> ${APPNAME}"
echo "  ${SOURCE_PASCAL}  -> ${PASCAL}"
echo "  ${SOURCE_USER}    -> ${DEST_USER}"
echo "  ${SOURCE_FULL}    -> ${DEST_FULL}"
echo "  ${SOURCE_EMAIL}   -> ${DEST_EMAIL}"
echo "  ${SOURCE_WEBSITE}  -> ${DEST_WEBSITE}"
echo

changed=0
checked=0

# Folders to skip
SKIP_DIRS=(
  "*/.git/*"
  "*/node_modules/*"
  "*/vendor/*"
  "*/dist/*"
  "*/build/*"
  "*/.next/*"
  "*/.pnpm-store/*"
  "*/.cache/*"
)

# Build the find prune expression
PRUNE_EXPR=()
for d in "${SKIP_DIRS[@]}"; do
  PRUNE_EXPR+=(-path "$d" -o)
done
unset 'PRUNE_EXPR[${#PRUNE_EXPR[@]}-1]'

# Export for Perl
export PACKAGE PASCAL DEST_USER DEST_FULL SOURCE_PACKAGE SOURCE_PASCAL SOURCE_USER SOURCE_FULL SOURCE_EMAIL DEST_EMAIL SOURCE_WEBSITE DEST_WEBSITE SOURCE_APPNAME APPNAME

# Iterate files safely (null-delimited), skip binaries, replace in place with perl
while IFS= read -r -d '' file; do
  [[ -f "$file" ]] || continue

  # Skip this script itself
  FILE_ABS="$(abs_path "$file")"
  if [[ "$FILE_ABS" == "$SCRIPT_ABS" ]]; then
    continue
  fi

  # Skip binary files
  if ! LC_ALL=C grep -Iq . "$file"; then
    continue
  fi

  checked=$((checked + 1))

  perl -0777 -i.bak -pe '
    BEGIN {
      $src_k   = $ENV{SOURCE_PACKAGE};
      $src_a   = $ENV{SOURCE_APPNAME};
      $src_p   = $ENV{SOURCE_PASCAL};
      $src_u   = $ENV{SOURCE_USER};
      $src_f   = $ENV{SOURCE_FULL};
      $src_e   = $ENV{SOURCE_EMAIL};
      $src_w   = $ENV{SOURCE_WEBSITE};

      $dst_k   = $ENV{PACKAGE};
      $dst_a   = $ENV{APPNAME};
      $dst_p   = $ENV{PASCAL};
      $dst_u   = $ENV{DEST_USER};
      $dst_f   = $ENV{DEST_FULL};
      $dst_e   = $ENV{DEST_EMAIL};
      $dst_w   = $ENV{DEST_WEBSITE};
    }
    s/\Q$src_k\E/$dst_k/g;
    s/\Q$src_a\E/$dst_a/g;
    s/\Q$src_p\E/$dst_p/g;
    s/\Q$src_u\E/$dst_u/g;
    s/\Q$src_f\E/$dst_f/g;
    s/\Q$src_e\E/$dst_e/g;
    s/\Q$src_w\E/$dst_w/g;
  ' -- "$file"

  if ! cmp -s "$file" "$file.bak"; then
    changed=$((changed + 1))
    echo "Updated: $file"
  fi

  rm -f -- "$file.bak"
done < <(find . \( "${PRUNE_EXPR[@]}" \) -prune -o -type f -print0)

echo
echo "Checked $checked text file(s). Updated $changed file(s). ✅"

if [ -d ".github/workflows.template" ]; then
  echo "Moving .github/workflows.template to .github/workflows"
  mkdir -p .github/workflows
  git mv .github/workflows.template/* .github/workflows/ 2>/dev/null || mv .github/workflows.template/* .github/workflows/
  rmdir .github/workflows.template 2>/dev/null || true
fi
