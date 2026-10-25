"""Tiny generator for diagram-design editorial diagrams (default skin).

Every diagram in docs/architecture is described in build.py with these
primitives and rendered to a self-contained HTML file next to the chapter
that embeds it. The PNG next to it is exported from that HTML by export.mjs.
Edit the description in build.py, never the generated HTML.
"""

from __future__ import annotations

import html
import math

PAPER = "#f5f5f5"
INK = "#2d3142"
MUTED = "#4f5d75"
SOFT = "#7a8399"
ACCENT = "#eb6c36"
LINK = "#2e5aa8"
MONO = "'Geist Mono', monospace"
SANS = "'Geist', sans-serif"

FONTS = (
    "https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1"
    "&family=Geist:wght@400;500;600&family=Geist+Mono:wght@400;500;600&display=swap"
)

NODE_STYLES = {
    # kind: (fill, stroke, dash, tag stroke, tag text)
    "focal": ("rgba(235,108,54,0.08)", ACCENT, None, "rgba(235,108,54,0.50)", ACCENT),
    "step": ("#ffffff", INK, None, "rgba(45,49,66,0.40)", INK),
    "store": ("rgba(45,49,66,0.05)", MUTED, None, "rgba(79,93,117,0.50)", MUTED),
    "external": ("rgba(45,49,66,0.03)", "rgba(45,49,66,0.30)", None, "rgba(45,49,66,0.22)", SOFT),
    "input": ("rgba(79,93,117,0.10)", SOFT, None, "rgba(122,131,153,0.40)", SOFT),
    "optional": ("rgba(45,49,66,0.02)", "rgba(45,49,66,0.22)", "4,3", "rgba(45,49,66,0.22)", SOFT),
}

ARROWS = {
    # style: (stroke, marker, dash, width)
    "muted": (MUTED, "arrow", None, 1.2),
    "accent": (ACCENT, "arrow-accent", None, 1.4),
    "link": (LINK, "arrow-link", None, 1.2),
    "dashed": (MUTED, "arrow", "5,4", 1.2),
    "return": (MUTED, "arrow", "5,4", 1.2),
    "async": (MUTED, "arrow-open", "5,4", 1.2),
    "link-dashed": (LINK, "arrow-link", "5,4", 1.2),
}

LABEL_COLORS = {"muted": MUTED, "accent": ACCENT, "link": LINK, "dashed": MUTED,
                "return": MUTED, "async": MUTED, "link-dashed": LINK}


def esc(s: str) -> str:
    return html.escape(s, quote=True)


def r4(v: float) -> int:
    return int(math.ceil(v / 4.0) * 4)


def mono_width(text: str, size: int = 8) -> int:
    # Geist Mono advance is ~0.6em plus 0.08em tracking.
    return r4(len(text) * size * 0.68 + 10)


class Diagram:
    def __init__(self, slug: str, width: int, height: int, title: str, desc: str):
        self.slug = slug
        self.w = width
        self.h = height
        self.title = title
        self.desc = desc
        self.zones: list[str] = []
        self.under: list[str] = []   # lifelines, frames, activation bars
        self.arrows: list[str] = []
        self.labels: list[str] = []
        self.nodes: list[str] = []
        self.legend_items: list[str] = []

    # ---- zones and frames -------------------------------------------------
    def zone(self, x, y, w, h, label, accent=False):
        stroke = "rgba(235,108,54,0.50)" if accent else "rgba(45,49,66,0.10)"
        fill = "rgba(235,108,54,0.03)" if accent else "rgba(45,49,66,0.02)"
        dash = ' stroke-dasharray="4,4"' if accent else ""
        lw = mono_width(label, 7) + 8
        color = ACCENT if accent else "rgba(45,49,66,0.55)"
        self.zones.append(
            f'<rect x="{x}" y="{y}" width="{w}" height="{h}" rx="8" fill="{fill}" stroke="{stroke}" stroke-width="0.8"{dash}/>'
            f'<rect x="{x + 12}" y="{y + 4}" width="{lw}" height="12" rx="2" fill="{PAPER}"/>'
            f'<text x="{x + 12 + lw / 2}" y="{y + 13}" fill="{color}" font-size="7" font-family="{MONO}" '
            f'text-anchor="middle" letter-spacing="0.14em">{esc(label)}</text>'
        )

    def frame(self, x, y, w, h, op, guard=None, divider_y=None, else_guard=None):
        tw = max(40, mono_width(op, 8))
        s = (
            f'<rect x="{x}" y="{y}" width="{w}" height="{h}" rx="4" fill="rgba(45,49,66,0.02)" stroke="rgba(45,49,66,0.22)" stroke-width="1"/>'
            f'<rect x="{x}" y="{y}" width="{tw}" height="16" rx="2" fill="{PAPER}" stroke="rgba(45,49,66,0.22)" stroke-width="1"/>'
            f'<text x="{x + tw / 2}" y="{y + 12}" fill="{MUTED}" font-size="8" font-family="{MONO}" text-anchor="middle" letter-spacing="0.12em">{esc(op)}</text>'
        )
        if guard:
            s += f'<text x="{x + tw + 12}" y="{y + 12}" fill="{MUTED}" font-size="8" font-family="{MONO}" letter-spacing="0.04em">{esc(guard)}</text>'
        if divider_y is not None:
            s += f'<line x1="{x + 8}" y1="{divider_y}" x2="{x + w - 8}" y2="{divider_y}" stroke="rgba(45,49,66,0.20)" stroke-width="1" stroke-dasharray="4,3"/>'
            if else_guard:
                s += f'<text x="{x + 12}" y="{divider_y + 14}" fill="{MUTED}" font-size="8" font-family="{MONO}" letter-spacing="0.04em">{esc(else_guard)}</text>'
        self.under.append(s)

    # ---- nodes ------------------------------------------------------------
    def node(self, x, y, w, h, name, sub=None, tag=None, kind="step", sub2=None, name_size=12):
        fill, stroke, dash, tag_stroke, tag_text = NODE_STYLES[kind]
        d = f' stroke-dasharray="{dash}"' if dash else ""
        parts = [
            f'<rect x="{x}" y="{y}" width="{w}" height="{h}" rx="6" fill="{PAPER}"/>',
            f'<rect x="{x}" y="{y}" width="{w}" height="{h}" rx="6" fill="{fill}" stroke="{stroke}" stroke-width="1"{d}/>',
        ]
        lines = [s for s in (sub, sub2) if s]
        top_pad = 12 if tag else 0
        block = 14 + 13 * len(lines)
        cy = y + top_pad + (h - top_pad - block) / 2 + 11
        if tag:
            tw = max(28, mono_width(tag, 7))
            parts.append(
                f'<rect x="{x + 8}" y="{y + 6}" width="{tw}" height="12" rx="2" fill="none" stroke="{tag_stroke}" stroke-width="0.8"/>'
                f'<text x="{x + 8 + tw / 2}" y="{y + 15}" fill="{tag_text}" font-size="7" font-family="{MONO}" text-anchor="middle" letter-spacing="0.08em">{esc(tag)}</text>'
            )
        cx = x + w / 2
        parts.append(
            f'<text x="{cx}" y="{cy:.0f}" fill="{INK}" font-size="{name_size}" font-weight="600" font-family="{SANS}" text-anchor="middle">{esc(name)}</text>'
        )
        for i, s in enumerate(lines):
            parts.append(
                f'<text x="{cx}" y="{cy + 15 + 13 * i:.0f}" fill="{MUTED}" font-size="9" font-family="{MONO}" text-anchor="middle">{esc(s)}</text>'
            )
        self.nodes.append("".join(parts))

    def state(self, x, y, w, h, name, sub=None, focal=False, terminal=False, sub2=None):
        kind = "focal" if focal else ("store" if terminal else "step")
        self.node(x, y, w, h, name, sub, None, kind, sub2=sub2, name_size=13)

    def diamond(self, cx, cy, hw, hh, text, text2=None, focal=False):
        stroke = ACCENT if focal else INK
        fill = "rgba(235,108,54,0.08)" if focal else "#ffffff"
        pts = f"{cx},{cy - hh} {cx + hw},{cy} {cx},{cy + hh} {cx - hw},{cy}"
        s = f'<polygon points="{pts}" fill="{PAPER}"/><polygon points="{pts}" fill="{fill}" stroke="{stroke}" stroke-width="1"/>'
        if text2:
            s += f'<text x="{cx}" y="{cy - 2}" fill="{INK}" font-size="11" font-weight="600" font-family="{SANS}" text-anchor="middle">{esc(text)}</text>'
            s += f'<text x="{cx}" y="{cy + 12}" fill="{MUTED}" font-size="9" font-family="{MONO}" text-anchor="middle">{esc(text2)}</text>'
        else:
            s += f'<text x="{cx}" y="{cy + 4}" fill="{INK}" font-size="11" font-weight="600" font-family="{SANS}" text-anchor="middle">{esc(text)}</text>'
        self.nodes.append(s)

    def pill(self, x, y, w, h, text, sub=None, focal=False):
        """Oval start/end of a flowchart."""
        stroke = ACCENT if focal else INK
        fill = "rgba(235,108,54,0.08)" if focal else "#ffffff"
        s = (f'<rect x="{x}" y="{y}" width="{w}" height="{h}" rx="{h / 2}" fill="{PAPER}"/>'
             f'<rect x="{x}" y="{y}" width="{w}" height="{h}" rx="{h / 2}" fill="{fill}" stroke="{stroke}" stroke-width="1"/>')
        cy = y + h / 2 + (4 if not sub else -2)
        s += f'<text x="{x + w / 2}" y="{cy:.0f}" fill="{INK}" font-size="12" font-weight="600" font-family="{SANS}" text-anchor="middle">{esc(text)}</text>'
        if sub:
            s += f'<text x="{x + w / 2}" y="{cy + 14:.0f}" fill="{MUTED}" font-size="9" font-family="{MONO}" text-anchor="middle">{esc(sub)}</text>'
        self.nodes.append(s)

    def start_dot(self, cx, cy):
        self.nodes.append(f'<circle cx="{cx}" cy="{cy}" r="6" fill="{INK}"/>')

    def end_dot(self, cx, cy):
        self.nodes.append(
            f'<circle cx="{cx}" cy="{cy}" r="8" fill="{PAPER}" stroke="{INK}" stroke-width="1.2"/>'
            f'<circle cx="{cx}" cy="{cy}" r="5" fill="{INK}"/>'
        )

    def note(self, x, y, text, anchor="start", size=13, color=MUTED):
        """Editorial aside in italic serif."""
        self.labels.append(
            f'<text x="{x}" y="{y}" fill="{color}" font-size="{size}" font-style="italic" font-family="\'Instrument Serif\', serif" text-anchor="{anchor}">{esc(text)}</text>'
        )

    def caption(self, x, y, text, anchor="start", color=SOFT, size=8):
        self.labels.append(
            f'<text x="{x}" y="{y}" fill="{color}" font-size="{size}" font-family="{MONO}" text-anchor="{anchor}" letter-spacing="0.08em">{esc(text)}</text>'
        )

    # ---- connectors -------------------------------------------------------
    def _stroke(self, style):
        stroke, marker, dash, width = ARROWS[style]
        d = f' stroke-dasharray="{dash}"' if dash else ""
        return f'fill="none" stroke="{stroke}" stroke-width="{width}"{d} marker-end="url(#{marker})"'

    def line(self, x1, y1, x2, y2, style="muted", label=None, label_at=None, side="above"):
        self.arrows.append(f'<path d="M {x1},{y1} L {x2},{y2}" {self._stroke(style)}/>')
        if label:
            if y1 == y2:
                cx = label_at if label_at is not None else (x1 + x2) / 2
                self.hlabel(cx, y1, label, style, side)
            else:
                cy = label_at if label_at is not None else (y1 + y2) / 2
                self.vlabel(x1, cy, label, style, side="right" if side == "above" else side)

    def path(self, d, style="muted"):
        self.arrows.append(f'<path d="{d}" {self._stroke(style)}/>')

    def elbow(self, x1, y1, x2, y2, style="muted", label=None, mid=None, label_side="right"):
        """Horizontal -> vertical -> horizontal with r=8 rounded corners."""
        m = mid if mid is not None else r4((x1 + x2) / 2)
        sx = 1 if x2 > x1 else -1
        sy = 1 if y2 > y1 else -1
        d = (f"M {x1},{y1} H {m - 8 * sx} Q {m},{y1} {m},{y1 + 8 * sy} "
             f"V {y2 - 8 * sy} Q {m},{y2} {m + 8 * sx},{y2} H {x2}")
        self.path(d, style)
        if label:
            self.vlabel(m, (y1 + y2) / 2, label, style, side=label_side)

    def lpath(self, x1, y1, x2, y2, style="muted", label=None, label_pos=None, first="h", label_side="above"):
        """Single bend. first='h': horizontal then vertical into (x2,y2); 'v': vertical then horizontal."""
        if first == "h":
            sx = 1 if x2 > x1 else -1
            sy = 1 if y2 > y1 else -1
            d = f"M {x1},{y1} H {x2 - 8 * sx} Q {x2},{y1} {x2},{y1 + 8 * sy} V {y2}"
        else:
            sx = 1 if x2 > x1 else -1
            sy = 1 if y2 > y1 else -1
            d = f"M {x1},{y1} V {y2 - 8 * sy} Q {x1},{y2} {x1 + 8 * sx},{y2} H {x2}"
        self.path(d, style)
        if label:
            lx, ly, orient = label_pos
            if orient == "h":
                self.hlabel(lx, ly, label, style, label_side)
            else:
                self.vlabel(lx, ly, label, style, side=label_side)

    def hlabel(self, cx, line_y, text, style="muted", side="above"):
        w = mono_width(text)
        y = line_y - 20 if side == "above" else line_y + 8
        color = LABEL_COLORS.get(style, MUTED)
        self.labels.append(
            f'<rect x="{cx - w / 2:.0f}" y="{y}" width="{w}" height="12" rx="2" fill="{PAPER}"/>'
            f'<text x="{cx:.0f}" y="{y + 9}" fill="{color}" font-size="8" font-family="{MONO}" text-anchor="middle" letter-spacing="0.06em">{esc(text)}</text>'
        )

    def vlabel(self, line_x, cy, text, style="muted", side="right"):
        w = mono_width(text)
        x = line_x + 8 if side == "right" else line_x - 8 - w
        y = r4(cy - 6)
        color = LABEL_COLORS.get(style, MUTED)
        self.labels.append(
            f'<rect x="{x:.0f}" y="{y}" width="{w}" height="12" rx="2" fill="{PAPER}"/>'
            f'<text x="{x + w / 2:.0f}" y="{y + 9}" fill="{color}" font-size="8" font-family="{MONO}" text-anchor="middle" letter-spacing="0.06em">{esc(text)}</text>'
        )

    # ---- sequence primitives ---------------------------------------------
    def actor(self, cx, y, w, name, sub, tag, kind="step", bottom=None):
        self.under.append(
            f'<line x1="{cx}" y1="{y + 56}" x2="{cx}" y2="{bottom}" stroke="rgba(45,49,66,0.22)" stroke-width="1" stroke-dasharray="3,3"/>'
        )
        self.node(cx - w / 2, y, w, 56, name, sub, tag, kind)

    def activation(self, cx, y1, y2):
        self.under.append(
            f'<rect x="{cx - 4}" y="{y1}" width="8" height="{y2 - y1}" fill="#efefef" stroke="{MUTED}" stroke-width="0.8"/>'
        )

    def msg(self, x1, x2, y, text, style="muted", label_at=None):
        """Horizontal message between lifelines; arrow stops at activation edge."""
        self.line(x1, y, x2, y, style, label=text, label_at=label_at)

    def self_msg(self, cx, y, text, style="muted", h=24):
        d = f"M {cx + 4},{y} H {cx + 36} V {y + h} H {cx + 6}"
        self.path(d, style)
        w = mono_width(text)
        self.labels.append(
            f'<rect x="{cx + 44}" y="{y + h / 2 - 6:.0f}" width="{w}" height="12" rx="2" fill="{PAPER}"/>'
            f'<text x="{cx + 44 + w / 2:.0f}" y="{y + h / 2 + 3:.0f}" fill="{LABEL_COLORS[style]}" font-size="8" font-family="{MONO}" text-anchor="middle" letter-spacing="0.06em">{esc(text)}</text>'
        )

    # ---- legend -----------------------------------------------------------
    def legend(self, y, items):
        """items: list of (swatch, text). swatch kinds: node kinds or arrow styles."""
        parts = [
            f'<line x1="40" y1="{y}" x2="{self.w - 40}" y2="{y}" stroke="rgba(45,49,66,0.10)" stroke-width="0.8"/>',
            f'<text x="40" y="{y + 20}" fill="{MUTED}" font-size="8" font-family="{MONO}" letter-spacing="0.18em">LEGENDA</text>',
        ]
        x = 128
        for sw, text in items:
            if sw in NODE_STYLES:
                fill, stroke, dash, _, _ = NODE_STYLES[sw]
                d = f' stroke-dasharray="{dash}"' if dash else ""
                parts.append(f'<rect x="{x}" y="{y + 12}" width="16" height="12" rx="2" fill="{fill}" stroke="{stroke}" stroke-width="1"{d}/>')
            elif sw == "zone":
                parts.append(f'<rect x="{x}" y="{y + 12}" width="16" height="12" rx="2" fill="rgba(45,49,66,0.02)" stroke="rgba(45,49,66,0.30)" stroke-width="0.8"/>')
            elif sw == "boundary":
                parts.append(f'<rect x="{x}" y="{y + 12}" width="16" height="12" rx="2" fill="rgba(235,108,54,0.03)" stroke="rgba(235,108,54,0.50)" stroke-width="0.8" stroke-dasharray="4,4"/>')
            else:
                stroke, marker, dash, width = ARROWS[sw]
                d = f' stroke-dasharray="{dash}"' if dash else ""
                parts.append(f'<line x1="{x}" y1="{y + 18}" x2="{x + 24}" y2="{y + 18}" stroke="{stroke}" stroke-width="{width}"{d} marker-end="url(#{marker})"/>')
            parts.append(f'<text x="{x + 32}" y="{y + 21}" fill="{MUTED}" font-size="9" font-family="{SANS}">{esc(text)}</text>')
            x += 32 + int(len(text) * 5.2) + 28
        self.legend_items.append("".join(parts))

    # ---- output -----------------------------------------------------------
    def svg(self) -> str:
        defs = (
            '<defs>'
            f'<marker id="arrow" markerWidth="8" markerHeight="6" refX="7" refY="3" orient="auto"><polygon points="0 0, 8 3, 0 6" fill="{MUTED}"/></marker>'
            f'<marker id="arrow-accent" markerWidth="8" markerHeight="6" refX="7" refY="3" orient="auto"><polygon points="0 0, 8 3, 0 6" fill="{ACCENT}"/></marker>'
            f'<marker id="arrow-link" markerWidth="8" markerHeight="6" refX="7" refY="3" orient="auto"><polygon points="0 0, 8 3, 0 6" fill="{LINK}"/></marker>'
            f'<marker id="arrow-open" markerWidth="8" markerHeight="6" refX="7" refY="3" orient="auto"><polyline points="0 0, 8 3, 0 6" fill="none" stroke="{MUTED}" stroke-width="1.2"/></marker>'
            '</defs>'
        )
        body = "\n".join(
            [f'<rect width="100%" height="100%" fill="{PAPER}"/>']
            + self.zones + self.under + self.arrows + self.labels + self.nodes + self.legend_items
        )
        return (
            f'<svg viewBox="0 0 {self.w} {self.h}" xmlns="http://www.w3.org/2000/svg" role="img" '
            f'aria-labelledby="{self.slug}-title {self.slug}-desc">\n'
            f'<title id="{self.slug}-title">{esc(self.title)}</title>\n'
            f'<desc id="{self.slug}-desc">{esc(self.desc)}</desc>\n'
            f"{defs}\n{body}\n</svg>"
        )

    def html(self, eyebrow: str, h1: str, subtitle: str | None = None) -> str:
        sub = f'<p class="sub">{esc(subtitle)}</p>' if subtitle else ""
        return f"""<!DOCTYPE html>
<html lang="cs">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{esc(h1)}</title>
<link href="{FONTS}" rel="stylesheet">
<style>
*, *::before, *::after {{ box-sizing: border-box; margin: 0; padding: 0; }}
body {{ font-family: 'Geist', system-ui, sans-serif; background: {PAPER}; color: {INK}; padding: 2.5rem 2rem; }}
.frame {{ max-width: {self.w + 40}px; margin: 0 auto; }}
.eyebrow {{ font-family: 'Geist Mono', monospace; font-size: 0.66rem; font-weight: 500; letter-spacing: 0.18em; text-transform: uppercase; color: {MUTED}; margin-bottom: 0.5rem; }}
h1 {{ font-family: 'Instrument Serif', serif; font-size: 2rem; font-weight: 400; letter-spacing: -0.02em; line-height: 1.15; margin-bottom: 0.4rem; }}
.sub {{ color: {MUTED}; font-size: 0.9rem; margin-bottom: 1.25rem; }}
svg {{ width: 100%; display: block; }}
</style>
</head>
<body>
<div class="frame">
<p class="eyebrow">{esc(eyebrow)}</p>
<h1>{esc(h1)}</h1>
{sub}
{self.svg()}
</div>
</body>
</html>
"""
