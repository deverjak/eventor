"""Builds every diagram of docs/architecture.

    python docs/architecture/_diagrams/build.py      # HTML sources
    node   docs/architecture/_diagrams/export.mjs    # PNG next to each HTML

One module per chapter (chNN_*.py); each exposes DIAGRAMS, a list of functions.
Every function is one diagram; its HTML lands in the chapter's img/ folder.
Edit the description here, never the generated HTML.
"""

from __future__ import annotations

import importlib
import sys
from pathlib import Path

HERE = Path(__file__).parent
sys.path.insert(0, str(HERE))

only = sys.argv[1:]
for mod_path in sorted(HERE.glob("ch*.py")):
    mod = importlib.import_module(mod_path.stem)
    for fn in getattr(mod, "DIAGRAMS", []):
        if only and not any(o in fn.__name__ or o in mod_path.stem for o in only):
            continue
        fn()
