#!/usr/bin/env bash

set -Eeuo pipefail

deployment_root="${1:?Deployment root is required}"
release_id="${2:?Release ID is required}"
archive_path="/tmp/honducasa-${release_id}.tar.gz"
release_path="${deployment_root}/releases/${release_id}"
shared_path="${deployment_root}/shared"

if [[ ! -f "${shared_path}/.env" ]]; then
    echo "Missing production environment file: ${shared_path}/.env" >&2
    exit 1
fi

if [[ ! -f "${archive_path}" ]]; then
    echo "Missing uploaded release archive: ${archive_path}" >&2
    exit 1
fi

mkdir -p \
    "${deployment_root}/releases" \
    "${shared_path}/storage/app/public" \
    "${shared_path}/storage/framework/cache/data" \
    "${shared_path}/storage/framework/sessions" \
    "${shared_path}/storage/framework/views" \
    "${shared_path}/storage/logs"

rm -rf "${release_path}"
mkdir -p "${release_path}"
tar -xzf "${archive_path}" -C "${release_path}"

ln -s "${shared_path}/.env" "${release_path}/.env"
ln -s "${shared_path}/storage" "${release_path}/storage"

cd "${release_path}"

php artisan storage:link --force
php artisan optimize
php artisan migrate --force

ln -sfn "${release_path}" "${deployment_root}/current"

php artisan reload

if [[ -x "${shared_path}/restart.sh" ]]; then
    "${shared_path}/restart.sh" "${release_path}"
fi

rm -f "${archive_path}"

mapfile -t expired_releases < <(
    find "${deployment_root}/releases" -mindepth 1 -maxdepth 1 -type d -printf '%T@ %p\n' \
        | sort -rn \
        | tail -n +6 \
        | cut -d' ' -f2-
)

for expired_release in "${expired_releases[@]}"; do
    rm -rf "${expired_release}"
done

echo "Activated release ${release_id}"
