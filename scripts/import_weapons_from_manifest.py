#!/usr/bin/env python3

import argparse
import json
import mimetypes
import os
import sys
import urllib.error
import urllib.request
import uuid
from pathlib import Path


DEFAULT_MAX_BATCH_BYTES = 5_500_000


def read_env(path: Path) -> dict[str, str]:
    values: dict[str, str] = {}

    if not path.exists():
        return values

    for line in path.read_text(encoding="utf-8").splitlines():
        line = line.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue

        key, value = line.split("=", 1)
        values[key] = value.strip().strip('"').strip("'")

    return values


def manifest_groups(path: Path) -> list[dict]:
    data = json.loads(path.read_text(encoding="utf-8"))
    if isinstance(data, dict):
        groups = data.get("groups", [])
    else:
        groups = data

    if not isinstance(groups, list):
        raise ValueError("Manifest does not contain a groups array.")

    return groups


def photo_paths(group: dict, base_dir: Path) -> list[Path]:
    files = group.get("files", [])
    paths: list[Path] = []

    for item in files:
        if item.get("role") != "photo":
            continue

        relative_path = item.get("file") or item.get("path")
        if not relative_path:
            continue

        path = base_dir / relative_path
        if not path.exists():
            raise FileNotFoundError(f"Missing photo: {path}")

        paths.append(path)

    return paths


def chunks_by_size(paths: list[Path], max_bytes: int) -> list[list[Path]]:
    chunks: list[list[Path]] = []
    current: list[Path] = []
    current_size = 0

    for path in paths:
        size = path.stat().st_size
        if current and current_size + size > max_bytes:
            chunks.append(current)
            current = []
            current_size = 0

        current.append(path)
        current_size += size

    if current:
        chunks.append(current)

    return chunks


def encode_multipart(fields: dict[str, str], files: list[tuple[str, Path]]) -> tuple[bytes, str]:
    boundary = f"----CodexWeaponImport{uuid.uuid4().hex}"
    body = bytearray()

    for name, value in fields.items():
        body.extend(f"--{boundary}\r\n".encode())
        body.extend(f'Content-Disposition: form-data; name="{name}"\r\n\r\n'.encode())
        body.extend(str(value).encode())
        body.extend(b"\r\n")

    for field_name, path in files:
        mime_type = mimetypes.guess_type(path.name)[0] or "application/octet-stream"
        body.extend(f"--{boundary}\r\n".encode())
        body.extend(
            f'Content-Disposition: form-data; name="{field_name}"; filename="{path.name}"\r\n'.encode()
        )
        body.extend(f"Content-Type: {mime_type}\r\n\r\n".encode())
        body.extend(path.read_bytes())
        body.extend(b"\r\n")

    body.extend(f"--{boundary}--\r\n".encode())
    return bytes(body), f"multipart/form-data; boundary={boundary}"


def post_chunk(url: str, token: str, fields: dict[str, str], paths: list[Path]) -> dict:
    body, content_type = encode_multipart(fields, [("photos[]", path) for path in paths])
    request = urllib.request.Request(
        url,
        data=body,
        headers={
            "Accept": "application/json",
            "Authorization": f"Bearer {token}",
            "Content-Type": content_type,
        },
        method="POST",
    )

    try:
        with urllib.request.urlopen(request, timeout=120) as response:
            return json.loads(response.read().decode("utf-8"))
    except urllib.error.HTTPError as error:
        response_body = error.read().decode("utf-8", errors="replace")
        raise RuntimeError(f"HTTP {error.code}: {response_body}") from error


def main() -> int:
    parser = argparse.ArgumentParser(description="Import grouped weapon photos into the CRM import endpoint.")
    parser.add_argument("manifest", type=Path, help="Path to grouped_weapon_photos_no_bg/manifest.json")
    parser.add_argument("--url", default="http://localhost:8000/api/import/weapons")
    parser.add_argument("--token", default=os.environ.get("WEAPON_IMPORT_TOKEN"))
    parser.add_argument("--env", type=Path, default=Path(".env"), help="Laravel .env used to read WEAPON_IMPORT_TOKEN")
    parser.add_argument("--replace-existing", action="store_true")
    parser.add_argument("--dry-run", action="store_true")
    parser.add_argument("--max-batch-bytes", type=int, default=DEFAULT_MAX_BATCH_BYTES)
    args = parser.parse_args()

    manifest_path = args.manifest.resolve()
    base_dir = manifest_path.parent
    env_values = read_env(args.env)
    token = args.token or env_values.get("WEAPON_IMPORT_TOKEN")

    if not token and not args.dry_run:
        print("Missing token. Set WEAPON_IMPORT_TOKEN in .env or pass --token.", file=sys.stderr)
        return 2

    groups = manifest_groups(manifest_path)
    imported = 0
    imported_photos = 0

    for group_index, group in enumerate(groups, start=1):
        name = group.get("model") or group.get("name")
        if not name:
            print(f"[{group_index}/{len(groups)}] Skipping group without model name.")
            continue

        paths = photo_paths(group, base_dir)
        if not paths:
            print(f"[{group_index}/{len(groups)}] {name}: no weapon photos, skipping.")
            continue

        photo_chunks = chunks_by_size(paths, args.max_batch_bytes)
        description = group.get("notes_from_label") or ""

        if args.dry_run:
            print(f"[{group_index}/{len(groups)}] {name}: {len(paths)} photos in {len(photo_chunks)} request(s)")
            imported += 1
            imported_photos += len(paths)
            continue

        for chunk_index, chunk in enumerate(photo_chunks, start=1):
            fields = {
                "name": name,
                "description": description,
                "replace_existing": "1" if args.replace_existing and chunk_index == 1 else "0",
                "append": "1" if chunk_index > 1 else "0",
            }
            result = post_chunk(args.url, token, fields, chunk)
            photos_count = result.get("weapon", {}).get("photos_count", "?")
            print(
                f"[{group_index}/{len(groups)}] {name}: chunk {chunk_index}/{len(photo_chunks)} "
                f"sent {len(chunk)} photo(s), total in CRM: {photos_count}"
            )

        imported += 1
        imported_photos += len(paths)

    print(f"Done. Imported {imported} weapon(s), {imported_photos} photo(s).")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
