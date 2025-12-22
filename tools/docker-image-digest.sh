#!/usr/bin/env bash

set -euo pipefail

if [ "$#" -ne 1 ]; then
  echo "Usage: $0 <image>" >&2
  exit 1
fi

ARCH="$(uname -m)"
case "$ARCH" in
  x86_64|amd64) ARCH="amd64";;
  aarch64|arm64) ARCH="arm64";;
  *) echo "Unsupported architecture: $ARCH" >&2; exit 1;;
esac

IMAGE="$1"
CACHEFILE="/tmp/${IMAGE//\//_}.json"

if [ ! -f "$CACHEFILE" ]; then
  docker buildx imagetools inspect "$IMAGE" --format "{{json .}}" > $CACHEFILE
fi

jq -r ".manifest.manifests.[] | select(.platform.os == \"linux\" and .platform.architecture == \"$ARCH\") | .digest" $CACHEFILE
