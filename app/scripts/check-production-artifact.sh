#!/bin/sh
set -eu
public_dir=${1:-public}
if [ -e "$public_dir/hot" ] || [ -L "$public_dir/hot" ]; then
    echo "Production artifact FAIL: $public_dir/hot must not exist" >&2
    exit 1
fi
printf '%s\n' "Production artifact PASS: Vite hot file absent"
