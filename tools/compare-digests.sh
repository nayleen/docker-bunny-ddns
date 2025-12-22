#!/usr/bin/env bash

set -euo pipefail

if [ "$#" -ne 2 ]; then
  echo "Usage: $0 <image> <base_image>" >&2
  exit 1
fi

IMAGE="$1"
BASE_IMAGE="$2"

BASE_IMAGE_DIGEST=$(tools/docker-image-digest.sh "$BASE_IMAGE")
IMAGE_DIGEST=$(tools/base-image-digest.sh "$IMAGE")

if [ "$BASE_IMAGE_DIGEST" = "$IMAGE_DIGEST" ]; then
  echo "The base image digest matches the image digest." >&2
else
  echo "The base image digest does not match the image digest." >&2
  echo "Base Image Digest: $BASE_IMAGE_DIGEST" >&2
  echo "Image Digest: $IMAGE_DIGEST" >&2
  exit 1
fi
