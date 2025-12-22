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
BASE_IMAGE="${2:-}"
CACHEFILE="/tmp/${IMAGE//\//_}.json"

if [ ! -f "$CACHEFILE" ]; then
  docker buildx imagetools inspect "$IMAGE" --format "{{json .}}" > $CACHEFILE
fi

BASE_IMAGE_DIGEST=$(jq -r ".manifest.manifests.[] | select(.platform.os == \"linux\" and .platform.architecture == \"$ARCH\") | .annotations | .\"org.opencontainers.image.base.digest\"" $CACHEFILE)

if [ "$BASE_IMAGE_DIGEST" = "null" ]; then
  echo "Image $IMAGE does not have a org.opencontainers.image.base.digest annotation for architecture: $ARCH" >&2
  exit 1
fi

echo $BASE_IMAGE_DIGEST
