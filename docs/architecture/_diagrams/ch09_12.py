"""Diagrams for chapters 09 (Symfony), 10 (quality), 11 (risks) and 12 (plan)."""

from __future__ import annotations

from dd import ACCENT, INK, MONO, MUTED, PAPER, SANS, SOFT, Diagram, esc

from common import write


# --------------------------------------------------------------------------
# 09 · Container build: compile time vs. one request
# --------------------------------------------------------------------------
def container_build():
    d = Diagram("container-build", 1120, 616, "Kontejner při kompilaci a při požadavku",
                "Atributy a tagy posbírají compiler passy, které ověří registry a hranice modulů už při cache:warmup; za běhu projde požadavek firewallem a value resolvery do tenkého controlleru, command bus s middleware spustí handler a ten zapíše stav, audit i integrační události ve stejné transakci.")
    d.zone(24, 64, 1072, 152, "KOMPILACE · CACHE:WARMUP V CI I PŘI DEPLOYI")
    d.zone(24, 288, 1072, 248, "BĚH · JEDEN HTTP POŽADAVEK")
    # compile-time arrows
    d.line(280, 140, 360, 140, "muted", "SBĚR")
    d.line(640, 140, 720, 140, "muted", "OVĚŘENÍ")
    # injection from compiled container into runtime
    d.line(780, 184, 780, 320, "dashed")
    d.vlabel(780, 252, "INJEKCE", "dashed")
    d.line(1000, 184, 1000, 320, "dashed")
    d.vlabel(1000, 252, "LOCATORY", "dashed")
    # runtime arrows
    d.line(200, 364, 240, 364, "muted")
    d.line(440, 364, 480, 364, "muted")
    d.line(640, 364, 680, 364, "muted")
    d.line(880, 364, 920, 364, "muted")
    d.line(1000, 408, 1000, 448, "muted")
    d.vlabel(1000, 428, "STEJNÁ TX", "muted", side="left")
    # nodes
    d.node(40, 96, 240, 88, "Atributy a tagy", "#[AsMessageHandler] · #[AsFieldType]", "INPUT", "input",
           sub2="#[AsDecorator] · #[AsTaggedItem]")
    d.node(360, 96, 280, 88, "Compiler passy", "FieldTypeRegistry · EventRouting", "PASS", "focal",
           sub2="ModuleBoundary · PermissionMatrix")
    d.node(720, 96, 360, 88, "Zkompilovaný kontejner", "registry jako service locatory", "CACHE", "store",
           sub2="chyba konfigurace = pád buildu, ne produkce")
    d.node(40, 320, 160, 88, "Požadavek", "FrankenPhpRuntime", "HTTP", "input", sub2="firewall · authenticator")
    d.node(240, 320, 200, 88, "Value resolvery", "organizátor · token", "RESOLVE", "focal", sub2="QR · idempotenční klíč")
    d.node(480, 320, 160, 88, "Controller", "#[MapRequestPayload]", "UI", "step", sub2="voter · command")
    d.node(680, 320, 200, 88, "command.bus", "tenant · audit", "BUS", "step", sub2="idempotence · transakce")
    d.node(920, 320, 160, 88, "Handler", "doména modulu", "APP", "step")
    d.node(920, 448, 160, 64, "messenger_messages", "+ audit_entry", "DB", "store")
    d.legend(560, [("focal", "Místa, kde Eventor rozšiřuje framework"), ("input", "Vstup"), ("store", "Stav"),
                   ("muted", "Tok požadavku"), ("dashed", "Kontejner dodá služby")])
    write(d, "09-symfony-v-architekture", "Symfony · DI kontejner", "Co se rozhodne při kompilaci a co za běhu")


# --------------------------------------------------------------------------
# 10 · Test pyramid
# --------------------------------------------------------------------------
def test_pyramid():
    d = Diagram("test-pyramid", 1120, 584, "Testovací pyramida Eventoru",
                "Pět vrstev testů od stovek rychlých unit testů domény přes integrační testy nad skutečným Postgresem a scénáře S01 až S18 po kontraktní testy proti pairrot-sandboxu a několik end-to-end průchodů storyboardem v Playwrightu.")
    levels = [
        ("E2E", "Playwright · storyboard kap. 13", "~6 průchodů · noční + před demem", False),
        ("Kontrakt", "pairrot-sandbox · smtp4dev", "~20 testů · při změně adaptéru", False),
        ("Scénáře S01–S18", "jedna třída na scénář · souběh", "18 tříd · každý PR", True),
        ("Integrační", "Postgres · WebTestCase · Messenger", "~300 testů · každý PR", False),
        ("Unit", "doména · fold ledgeru · pravidla", "~800 testů · pod 10 s", False),
    ]
    cx, top, lh, step = 400, 64, 80, 104
    parts = []
    for i, (name, tech, meta, focal) in enumerate(levels):
        y = top + i * lh
        tw, bw = 96 + i * step, 96 + (i + 1) * step
        pts = f"{cx - tw / 2},{y} {cx + tw / 2},{y} {cx + bw / 2},{y + lh} {cx - bw / 2},{y + lh}"
        fill = "rgba(235,108,54,0.08)" if focal else ("#ffffff" if i % 2 == 0 else "#ececec")
        stroke = ACCENT if focal else "rgba(45,49,66,0.30)"
        parts.append(f'<polygon points="{pts}" fill="{PAPER}"/><polygon points="{pts}" fill="{fill}" stroke="{stroke}" stroke-width="1"/>')
        size = 11 if i == 0 else 13
        parts.append(f'<text x="{cx}" y="{y + 46}" fill="{ACCENT if focal else INK}" font-size="{size}" font-weight="600" font-family="{SANS}" text-anchor="middle">{esc(name)}</text>')
        # right annotation with guide line
        gy = y + lh / 2
        parts.append(f'<line x1="{cx + bw / 2 + 12}" y1="{gy}" x2="732" y2="{gy}" stroke="rgba(45,49,66,0.20)" stroke-width="0.8" stroke-dasharray="3,3"/>')
        parts.append(f'<text x="744" y="{gy - 4}" fill="{INK}" font-size="12" font-weight="600" font-family="{SANS}">{esc(tech)}</text>')
        parts.append(f'<text x="744" y="{gy + 12}" fill="{MUTED}" font-size="9" font-family="{MONO}">{esc(meta)}</text>')
    d.nodes.extend(parts)
    d.caption(40, 88, "POMALEJŠÍ, DRAŽŠÍ")
    d.caption(40, 104, "BLÍŽ UŽIVATELI")
    d.caption(40, 488, "RYCHLEJŠÍ, LEVNĚJŠÍ")
    d.caption(40, 504, "BLÍŽ DOMÉNĚ")
    d.under.append(f'<line x1="48" y1="468" x2="48" y2="120" stroke="{MUTED}" stroke-width="1.2" marker-end="url(#arrow)"/>')
    d.legend(524, [("focal", "Důkaz chování mimo CRUD (zadání kap. 11)"), ("step", "Vrstva testů")])
    write(d, "10-kvalita-a-testovani", "Kvalita · testy", "Testovací pyramida")


# --------------------------------------------------------------------------
# 11 · Risk heatmap (likelihood x impact)
# --------------------------------------------------------------------------
def risk_heatmap():
    d = Diagram("risk-heatmap", 1120, 640, "Rizika podle pravděpodobnosti a dopadu",
                "Devět rizik v mřížce pravděpodobnost krát dopad; nejvýš vpravo leží rozsah P0 plus P1 vůči času a odchylka skutečného Pairrotu od pairrot-sandboxu, vlevo dole provozní rizika workeru a FrankenPHP.")
    X0, Y0, W, H = 160, 64, 880, 448
    parts = [
        f'<rect x="{X0}" y="{Y0}" width="{W}" height="{H}" fill="#ffffff" stroke="{MUTED}" stroke-width="1"/>',
        f'<rect x="{X0 + W / 2}" y="{Y0}" width="{W / 2}" height="{H / 2}" fill="rgba(235,108,54,0.04)"/>',
        f'<line x1="{X0 + W / 2}" y1="{Y0}" x2="{X0 + W / 2}" y2="{Y0 + H}" stroke="rgba(45,49,66,0.20)" stroke-width="1" stroke-dasharray="4,3"/>',
        f'<line x1="{X0}" y1="{Y0 + H / 2}" x2="{X0 + W}" y2="{Y0 + H / 2}" stroke="rgba(45,49,66,0.20)" stroke-width="1" stroke-dasharray="4,3"/>',
        f'<text x="{X0 + W / 2}" y="{Y0 + H + 28}" fill="{MUTED}" font-size="8" font-family="{MONO}" text-anchor="middle" letter-spacing="0.14em">PRAVDĚPODOBNOST →</text>',
        f'<text x="{X0 - 16}" y="{Y0 + H / 2}" fill="{MUTED}" font-size="8" font-family="{MONO}" text-anchor="end" letter-spacing="0.14em">DOPAD ↑</text>',
        f'<text x="{X0 + W - 12}" y="{Y0 + 20}" fill="{ACCENT}" font-size="8" font-family="{MONO}" text-anchor="end" letter-spacing="0.14em">ŘEŠIT HNED</text>',
        f'<text x="{X0 + 12}" y="{Y0 + H - 12}" fill="{SOFT}" font-size="8" font-family="{MONO}" letter-spacing="0.14em">SLEDOVAT</text>',
    ]
    # (id, label, likelihood 0..1, impact 0..1, focal)
    risks = [
        ("R1", "Rozsah P0 + P1 vs. čas", 0.82, 0.86, True),
        ("R2", "Sandbox ≠ skutečný Pairrot", 0.60, 0.72, True),
        ("R3", "Editor formuláře je pracnější", 0.76, 0.48, False),
        ("R4", "Výpadek webhooků přes 1 h", 0.40, 0.58, False),
        ("R5", "Drift kapacitních counterů", 0.20, 0.82, False),
        ("R6", "Neúplná anonymizace", 0.28, 0.68, False),
        ("R7", "Jedovatá zpráva ve frontě", 0.32, 0.36, False),
        ("R8", "Stav ve worker módu FrankenPHP", 0.48, 0.24, False),
        ("R9", "Pairrot jen Česká spořitelna", 0.88, 0.16, False),
    ]
    for rid, label, lx, iy, focal in risks:
        x = round((X0 + 24 + lx * (W - 48)) / 4) * 4
        y = round((Y0 + H - 24 - iy * (H - 48)) / 4) * 4
        color = ACCENT if focal else INK
        r = 8 if focal else 6
        parts.append(f'<circle cx="{x}" cy="{y}" r="{r}" fill="{color}"/>')
        anchor = "end" if lx > 0.7 else "start"
        tx = x - 16 if anchor == "end" else x + 16
        weight = "600"
        parts.append(f'<text x="{tx}" y="{y - 2}" fill="{color}" font-size="12" font-weight="{weight}" font-family="{SANS}" text-anchor="{anchor}">{esc(label)}</text>')
        parts.append(f'<text x="{tx}" y="{y + 12}" fill="{SOFT}" font-size="8" font-family="{MONO}" text-anchor="{anchor}" letter-spacing="0.08em">{rid}</text>')
    d.nodes.extend(parts)
    d.legend(576, [("focal", "Riziko s plánem hned od F0"), ("step", "Riziko se zmírněním v příslušné fázi")])
    write(d, "11-rizika-a-kompromisy", "Rizika · heatmapa", "Kde hrozí, že se něco nepovede")


# --------------------------------------------------------------------------
# 12 · Roadmap Gantt
# --------------------------------------------------------------------------
def roadmap_gantt():
    d = Diagram("roadmap-gantt", 1120, 600, "Plán implementace po týdnech",
                "Devět fází od F0 skeletu po F8 zpevnění během deseti týdnů; kritickou cestu tvoří registrace s kapacitami a platby s Pairrotem, milníky jsou průchod registrace a platby, kompletní P0 a demo P0 plus P1.")
    LX, CX, WK, RY, RH = 40, 280, 80, 88, 40
    tasks = [
        ("F0", "Skelet a kernel", 0, 1.5, False),
        ("F1", "Katalog, právo, formulář", 1.5, 2.5, False),
        ("F2", "Registrace a kapacity", 2.5, 4, True),
        ("F3", "Platby a Pairrot", 3.5, 5, True),
        ("F4", "Komunikace", 4.5, 5.5, False),
        ("F5", "Odbavení", 5.5, 6.5, False),
        ("F6", "Obsah, reporting, výjimky", 6, 7, False),
        ("F7", "P1 funkce", 7, 9, False),
        ("F8", "Zpevnění a demo", 9, 10, False),
    ]
    parts = []
    for w in range(10):
        x = CX + w * WK
        fill = "#ffffff" if w % 2 == 0 else "#f0f0f0"
        parts.append(f'<rect x="{x}" y="{RY - 24}" width="{WK}" height="{RH * len(tasks) + 24}" fill="{fill}"/>')
        parts.append(f'<text x="{x + WK / 2}" y="{RY - 8}" fill="{MUTED}" font-size="8" font-family="{MONO}" text-anchor="middle" letter-spacing="0.12em">T{w + 1}</text>')
    d.under.extend(parts)
    parts = []
    for i, (fid, name, s, e, focal) in enumerate(tasks):
        y = RY + i * RH
        parts.append(f'<text x="{LX}" y="{y + 25}" fill="{SOFT}" font-size="9" font-family="{MONO}" letter-spacing="0.08em">{fid}</text>')
        parts.append(f'<text x="{LX + 36}" y="{y + 25}" fill="{INK}" font-size="12" font-weight="600" font-family="{SANS}">{esc(name)}</text>')
        x1, x2 = CX + s * WK + 4, CX + e * WK - 4
        fill = "rgba(235,108,54,0.20)" if focal else "rgba(45,49,66,0.10)"
        stroke = ACCENT if focal else MUTED
        parts.append(f'<rect x="{x1:.0f}" y="{y + 10}" width="{x2 - x1:.0f}" height="20" rx="4" fill="{fill}" stroke="{stroke}" stroke-width="1"/>')
    my = RY + RH * len(tasks) + 32
    parts.append(f'<line x1="{CX}" y1="{my}" x2="{CX + 10 * WK}" y2="{my}" stroke="{INK}" stroke-width="1"/>')
    for wk, label in [(5, "M1 · registrace → platba → potvrzení"), (7, "M2 · P0 kompletní"), (10, "M3 · demo P0 + P1")]:
        x = CX + wk * WK
        pts = f"{x},{my - 8} {x + 8},{my} {x},{my + 8} {x - 8},{my}"
        parts.append(f'<polygon points="{pts}" fill="{INK}"/>')
        anchor = "end" if wk == 10 else "middle"
        parts.append(f'<text x="{x}" y="{my + 28}" fill="{INK}" font-size="11" font-family="{SANS}" text-anchor="{anchor}">{esc(label)}</text>')
    parts.append(f'<text x="{LX}" y="{my + 4}" fill="{MUTED}" font-size="8" font-family="{MONO}" letter-spacing="0.14em">MILNÍKY</text>')
    d.nodes.extend(parts)
    d.legend(536, [("focal", "Kritická cesta"), ("store", "Fáze (týdny T1–T10, jeden vývojář)")])
    write(d, "12-plan-implementace", "Plán · Gantt", "Deset týdnů po fázích")


DIAGRAMS = [container_build, test_pyramid, risk_heatmap, roadmap_gantt]
