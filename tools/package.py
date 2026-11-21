"""Build and verify the MoodleX Hub distribution ZIP."""

from pathlib import Path
from zipfile import ZIP_DEFLATED, ZipFile, ZipInfo
import argparse
import hashlib
import re


ROOT = Path(__file__).resolve().parent.parent
RUNTIME_DIRS = ("classes", "db", "lang", "templates", "pix", "amd", "tests", "docs", "cli")
RUNTIME_FILES = ("README.md", "LICENSE", "CHANGELOG.md", "composer.json", "styles.css")
REQUIRED = {"moodlexhub/version.php", "moodlexhub/db/install.xml", "moodlexhub/lang/en/local_moodlexhub.php"}


def package(output_dir: Path) -> Path:
    """Create a deterministic archive and validate its installable root."""
    version_php = (ROOT / "version.php").read_text(encoding="utf-8")
    match = re.search(r"\$plugin->release\s*=\s*'([A-Za-z0-9.-]+)'", version_php)
    if not match:
        raise ValueError("Cannot determine the plugin release from version.php")
    output_dir.mkdir(parents=True, exist_ok=True)
    archive = output_dir / f"moodlexhub-{match.group(1)}.zip"

    files = [*ROOT.glob("*.php"), *(ROOT / name for name in RUNTIME_FILES)]
    for dirname in RUNTIME_DIRS:
        files.extend((ROOT / dirname).rglob("*"))
    with ZipFile(archive, "w") as zipped:
        for path in sorted(path for path in files if path.is_file() and not path.is_symlink()):
            relative = path.relative_to(ROOT)
            if any(part.startswith(".") for part in relative.parts):
                continue
            item = ZipInfo(f"moodlexhub/{relative.as_posix()}", (2026, 11, 20, 0, 0, 0))
            item.compress_type = ZIP_DEFLATED
            item.external_attr = 0o644 << 16
            zipped.writestr(item, path.read_bytes())

    with ZipFile(archive) as zipped:
        names = set(zipped.namelist())
        if not REQUIRED <= names or not all(name.startswith("moodlexhub/") for name in names) or zipped.testzip():
            raise RuntimeError("The ZIP structure or checksum is invalid.")
    return archive


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--output", type=Path, default=ROOT / "artifacts")
    result = package(parser.parse_args().output)
    print(result)
    print(hashlib.sha256(result.read_bytes()).hexdigest())
