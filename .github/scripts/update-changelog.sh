#!/bin/sh

set -eu

php_binary=$(git config --get kagg.phpPath || echo php)

case "$(uname -s)" in
  # Resolve Windows PHP launchers such as php.bat through cmd.exe.
  Windows_NT|MINGW*|MSYS*|CYGWIN*) MSYS2_ARG_CONV_EXCL='*' exec cmd.exe /d /c "$php_binary" "$(dirname "$0")/update-changelog.php" "$@" ;;
  *) exec "$php_binary" "$(dirname "$0")/update-changelog.php" "$@" ;;
esac
