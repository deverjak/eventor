"""Shared helpers for the diagram build scripts: output location."""

from __future__ import annotations

from pathlib import Path

from dd import Diagram

ROOT = Path(__file__).resolve().parent.parent


def write(d: Diagram, folder: str, eyebrow: str, h1: str, subtitle: str | None = None):
    out = ROOT / folder / "img" / f"{d.slug}.html"
    out.parent.mkdir(parents=True, exist_ok=True)
    out.write_text(d.html(eyebrow, h1, subtitle), encoding="utf-8")
    print("html", out.relative_to(ROOT).as_posix())
