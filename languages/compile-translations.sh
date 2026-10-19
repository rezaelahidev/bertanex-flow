#!/usr/bin/env sh
# Compile Persian translations after editing bertanex-flow-fa_IR.po
set -e
cd "$(dirname "$0")"
msgfmt -o bertanex-flow-fa_IR.mo bertanex-flow-fa_IR.po
cp bertanex-flow-fa_IR.mo bertanex-flow-fa.mo
echo "Compiled bertanex-flow-fa_IR.mo (and fa alias)"
