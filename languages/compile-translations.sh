#!/usr/bin/env sh
# Compile Persian translations after editing growth-orchestrator-fa_IR.po
set -e
cd "$(dirname "$0")"
msgfmt -o growth-orchestrator-fa_IR.mo growth-orchestrator-fa_IR.po
cp growth-orchestrator-fa_IR.mo growth-orchestrator-fa.mo
echo "Compiled growth-orchestrator-fa_IR.mo (and fa alias)"
