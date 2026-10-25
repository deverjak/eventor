"""Diagrams of chapters 01 (context, deployment), 02 (modules, layers) and 03 (data)."""

from __future__ import annotations

from common import write
from dd import ACCENT, INK, MONO, MUTED, NODE_STYLES, PAPER, SANS, SOFT, Diagram, esc, mono_width


# --------------------------------------------------------------------------
# helpers
# --------------------------------------------------------------------------
def chip(d: Diagram, x, y, w, name, ver=""):
    d.nodes.append(
        f'<rect x="{x}" y="{y}" width="{w}" height="24" rx="4" fill="rgba(45,49,66,0.05)" stroke="{MUTED}" stroke-width="0.8"/>'
        f'<text x="{x + 8}" y="{y + 16}" fill="{INK}" font-size="11" font-family="{SANS}">{esc(name)}</text>'
        f'<text x="{x + w - 8}" y="{y + 16}" fill="{MUTED}" font-size="9" font-family="{MONO}" text-anchor="end">{esc(ver)}</text>'
    )


def infra(d: Diagram, x, y, w, h, name, tag, kind="step"):
    fill, stroke, dash, tag_stroke, tag_text = NODE_STYLES[kind]
    ds = f' stroke-dasharray="{dash}"' if dash else ""
    tw = max(28, mono_width(tag, 7))
    d.nodes.append(
        f'<rect x="{x}" y="{y}" width="{w}" height="{h}" rx="6" fill="{PAPER}"/>'
        f'<rect x="{x}" y="{y}" width="{w}" height="{h}" rx="6" fill="{fill}" stroke="{stroke}" stroke-width="1"{ds}/>'
        f'<rect x="{x + 8}" y="{y + 8}" width="{tw}" height="12" rx="2" fill="none" stroke="{tag_stroke}" stroke-width="0.8"/>'
        f'<text x="{x + 8 + tw / 2}" y="{y + 17}" fill="{tag_text}" font-size="7" font-family="{MONO}" text-anchor="middle" letter-spacing="0.08em">{esc(tag)}</text>'
        f'<text x="{x + 16 + tw}" y="{y + 18}" fill="{INK}" font-size="12" font-weight="600" font-family="{SANS}">{esc(name)}</text>'
    )


HEAD, ROW = 32, 24


class Schema:
    """Hand-placed db-schema tables; columns are (name, type) with PK/FK chips."""

    def __init__(self, d: Diagram):
        self.d = d
        self.pos: dict = {}

    def table(self, name, x, y, w, cols, pk=("id",), fk=(), focal=False, more=0):
        d = self.d
        h = HEAD + ROW * len(cols) + (ROW if more else 0)
        head_fill = "rgba(235,108,54,0.08)" if focal else "#ffffff"
        head_stroke = ACCENT if focal else INK
        s = [f'<rect x="{x}" y="{y}" width="{w}" height="{h}" rx="6" fill="#ffffff" stroke="{INK}" stroke-width="1"/>',
             f'<path d="M {x},{y + HEAD} V {y + 6} Q {x},{y} {x + 6},{y} H {x + w - 6} Q {x + w},{y} {x + w},{y + 6} V {y + HEAD} Z" fill="{head_fill}" stroke="{head_stroke}" stroke-width="1"/>',
             f'<rect x="{x + 8}" y="{y + 10}" width="36" height="12" rx="2" fill="none" stroke="rgba(45,49,66,0.40)" stroke-width="0.8"/>',
             f'<text x="{x + 26}" y="{y + 19}" fill="{INK}" font-size="7" font-family="{MONO}" text-anchor="middle" letter-spacing="0.08em">TABLE</text>',
             f'<text x="{x + 52}" y="{y + 21}" fill="{INK}" font-size="12" font-weight="600" font-family="{SANS}">{esc(name)}</text>']
        for i, (cname, typ) in enumerate(cols):
            ry = y + HEAD + ROW * i
            if i % 2 == 1:
                s.append(f'<rect x="{x + 1}" y="{ry}" width="{w - 2}" height="{ROW}" fill="rgba(45,49,66,0.02)"/>')
            s.append(f'<line x1="{x}" y1="{ry}" x2="{x + w}" y2="{ry}" stroke="rgba(45,49,66,0.10)" stroke-width="0.8"/>')
            s.append(f'<text x="{x + 12}" y="{ry + 16}" fill="{INK}" font-size="11" font-family="{SANS}">{esc(cname)}</text>')
            chips = (["PK"] if cname in pk else []) + (["FK"] if cname in fk else [])
            cx = x + 12 + len(cname) * 6.2 + 8
            for ch in chips:
                s.append(f'<rect x="{cx:.0f}" y="{ry + 6}" width="20" height="12" rx="2" fill="none" stroke="rgba(45,49,66,0.30)" stroke-width="0.8"/>'
                         f'<text x="{cx + 10:.0f}" y="{ry + 15}" fill="{MUTED}" font-size="8" font-family="{MONO}" text-anchor="middle">{ch}</text>')
                cx += 24
            s.append(f'<text x="{x + w - 10}" y="{ry + 16}" fill="{MUTED}" font-size="9" font-family="{MONO}" text-anchor="end">{esc(typ)}</text>')
        if more:
            ry = y + HEAD + ROW * len(cols)
            s.append(f'<line x1="{x}" y1="{ry}" x2="{x + w}" y2="{ry}" stroke="rgba(45,49,66,0.10)" stroke-width="0.8"/>')
            s.append(f'<text x="{x + 12}" y="{ry + 16}" fill="{MUTED}" font-size="9" font-family="{MONO}">+ {more} dalších sloupců</text>')
        d.nodes.append("".join(s))
        self.pos[name] = (x, y, w, [c for c, _ in cols])

    def cy(self, table, column, off=0):
        x, y, w, names = self.pos[table]
        return y + HEAD + ROW * names.index(column) + ROW // 2 + off


# --------------------------------------------------------------------------
# 01 · System context
# --------------------------------------------------------------------------
def system_context():
    d = Diagram("system-context", 1160, 600, "Eventor: systémový kontext",
                "Pořadatel a tým, účastník a obsluha odbavení používají Next.js frontend, který volá Symfony API; API a worker sdílejí Postgres, worker vytváří platební požadavky v Pairrotu, odesílá e-maily a Pairrot vrací platby podepsaným webhookem.")
    d.zone(296, 56, 512, 472, "EVENTOR · MONOREPO · DOCKER")
    # actors -> frontend
    d.line(240, 124, 320, 124, "link", "HTTPS")
    d.line(240, 252, 320, 252, "link", "HTTPS")
    d.line(240, 380, 320, 380, "link", "HTTPS")
    # frontend -> backend
    d.line(496, 164, 576, 164, "link", "/API/*")
    # backend / worker -> db
    d.line(680, 240, 680, 288, "muted")
    d.vlabel(680, 264, "SQL · TX", "muted")
    d.line(680, 408, 680, 360, "muted")
    d.vlabel(680, 384, "OUTBOX", "muted")
    # storage
    d.line(784, 120, 920, 120, "link", "S3 API")
    # pairrot: webhook in, requests out (money)
    d.elbow(920, 260, 784, 208, "accent", "WEBHOOK", mid=852)
    d.elbow(784, 424, 920, 316, "accent", "POŽADAVKY", mid=880)
    # mail
    d.line(784, 464, 920, 464, "link", "SMTP")
    # nodes
    d.node(40, 88, 200, 72, "Pořadatel a tým", "vlastník · správce · finance", "USER", "input")
    d.node(40, 216, 200, 72, "Účastník", "registrace · samoobsluha", "USER", "input")
    d.node(40, 344, 200, 72, "Obsluha odbavení", "mobil · tablet · QR", "USER", "input")
    d.node(320, 88, 176, 336, "Next.js", "veřejné stránky", "WEB", "step", sub2="admin · odbavení")
    d.node(576, 88, 208, 152, "Symfony API", "FrankenPHP · PHP 8.4", "BACKEND", "step", sub2="12 modulů · Symfony 8", name_size=14)
    d.node(576, 288, 208, 72, "PostgreSQL 17", "stav · ledger · fronty", "DB", "store")
    d.node(576, 408, 208, 96, "Worker", "messenger:consume", "WORKER", "step", sub2="scheduler · transporty modulů")
    d.node(920, 88, 200, 64, "Úložiště S3", "MinIO lokálně", "EXT", "external")
    d.node(920, 232, 200, 112, "Pairrot", "platební požadavky · QR", "EXT", "external", sub2="lokálně pairrot-sandbox")
    d.node(920, 432, 200, 64, "E-mail SMTP", "smtp4dev lokálně", "EXT", "external")
    d.legend(548, [("input", "Uživatel"), ("step", "Část Eventoru"), ("store", "Databáze"), ("external", "Externí služba"),
                   ("link", "HTTP / API"), ("accent", "Tok peněz")])
    write(d, "01-kontext-a-nasazeni", "Architektura · systémový kontext", "Kdo Eventor používá a s kým mluví")


# --------------------------------------------------------------------------
# 01 · Deployment
# --------------------------------------------------------------------------
def deployment():
    d = Diagram("deployment", 1160, 584, "Kde Eventor běží",
                "Lokálně spouští Docker Compose sedm služeb: Next.js, FrankenPHP s API, worker, Postgres, smtp4dev, MinIO a samostatný pairrot-sandbox; produkce na VPS používá stejné image za FrankenPHP/Caddy, který směruje / na Next.js a /api na Symfony.")
    d.zone(40, 56, 760, 448, "LOKÁLNĚ · DOCKER COMPOSE")
    d.zone(840, 56, 280, 448, "PRODUKCE · VPS (PŘIPRAVENO)", accent=True)
    # arrows
    d.line(264, 144, 312, 144, "link", "/API")
    d.line(520, 136, 568, 136, "muted", "SQL")
    d.line(520, 288, 568, 288, "muted", "SMTP")
    d.line(520, 372, 568, 372, "muted", "S3")
    d.line(312, 344, 264, 344, "accent", "API")
    d.lpath(176, 400, 312, 208, "accent", "WEBHOOK", (232, 208, "h"), first="v", label_side="below")
    # local nodes
    infra(d, 64, 96, 200, 96, "frontend", "WEB")
    chip(d, 76, 128, 176, "Next.js", "dev :3000")
    chip(d, 76, 156, 176, "rewrite", "/api → php")
    infra(d, 312, 96, 208, 128, "php", "API")
    chip(d, 324, 128, 184, "FrankenPHP runtime", "PHP 8.4")
    chip(d, 324, 156, 184, "Symfony", "8.x")
    chip(d, 324, 184, 184, "HTTP", ":8080")
    infra(d, 312, 264, 208, 128, "worker", "ASYNC")
    chip(d, 324, 296, 184, "worker runner", "messenger:consume")
    chip(d, 324, 324, 184, "scheduler", "expirace · reconcile")
    chip(d, 324, 352, 184, "LISTEN", "messenger_messages")
    infra(d, 568, 96, 208, 96, "postgres", "DB", "store")
    chip(d, 580, 128, 184, "PostgreSQL", "17 :5432")
    chip(d, 580, 156, 184, "role", "eventor_app")
    infra(d, 568, 256, 208, 72, "smtp4dev", "MAIL", "external")
    chip(d, 580, 288, 184, "UI + API", ":5000")
    infra(d, 568, 340, 208, 72, "minio", "S3", "external")
    chip(d, 580, 372, 184, "S3 API", ":9000")
    infra(d, 64, 288, 200, 112, "pairrot-sandbox", "EXT", "optional")
    chip(d, 76, 320, 176, "Pairrot API", "simulace")
    chip(d, 76, 348, 176, "webhooky", "HMAC v1")
    d.caption(64, 488, "WORKER = STEJNÝ IMAGE JAKO PHP, JINÝ PŘÍKAZ · PAIRROT-SANDBOX JE SAMOSTATNÝ PROJEKT")
    # prod nodes
    infra(d, 864, 96, 232, 96, "edge", "TLS")
    chip(d, 876, 128, 208, "Caddy (FrankenPHP)", ":443")
    chip(d, 876, 156, 208, "/ → web · /api → php", "")
    infra(d, 864, 208, 232, 96, "aplikace", "IMAGE")
    chip(d, 876, 240, 208, "php + worker", "1 image")
    chip(d, 876, 268, 208, "Next.js", "standalone")
    infra(d, 864, 320, 232, 68, "data", "DB", "store")
    chip(d, 876, 352, 208, "PostgreSQL 17", "volume")
    infra(d, 864, 404, 232, 84, "externí", "EXT", "external")
    chip(d, 876, 436, 208, "Pairrot · SMTP · S3", "")
    d.legend(532, [("step", "Kontejner"), ("store", "Data"), ("external", "Služba třetí strany"), ("optional", "Samostatný projekt"),
                   ("link", "HTTP"), ("accent", "Tok peněz")])
    write(d, "01-kontext-a-nasazeni", "Architektura · nasazení", "Kde Eventor běží")


# --------------------------------------------------------------------------
# 02 · Context map
# --------------------------------------------------------------------------
def contexts_map():
    d = Diagram("contexts-map", 1160, 648, "Mapa modulů: sdílená transakce a outbox",
                "Registration a Legal sdílejí jednu transakci a čtou snapshot katalogu přes jeho Contract; Payments a CheckIn spolupracují s Registration synchronně přes Contract nebo asynchronně přes integrační události, do kterého zapisují všechny moduly a ze kterého čtou Communication, Attention, Content, Privacy a Reporting.")
    d.zone(288, 96, 320, 296, "SDÍLENÁ TRANSAKCE", accent=True)
    # outbox bus
    d.under.append(f'<line x1="140" y1="440" x2="1100" y2="440" stroke="{MUTED}" stroke-width="2" stroke-dasharray="6,4"/>')
    d.caption(1100, 428, "OUTBOX", anchor="end", color=MUTED)
    # sync
    d.line(240, 184, 312, 184, "muted", "API")
    d.line(428, 232, 428, 272, "muted")
    d.vlabel(428, 252, "API", "muted")
    d.lpath(768, 320, 568, 232, "muted", "API", (680, 320, "h"))
    # async between registration and payments
    d.line(584, 160, 768, 160, "async", "ORDERPLACED")
    d.line(768, 208, 584, 208, "async", "PLATBA ZMĚNĚNA", side="below")
    # into the bus
    d.line(140, 256, 140, 436, "async")
    d.line(428, 360, 428, 436, "async")
    d.line(856, 376, 856, 436, "async")
    d.line(960, 232, 960, 436, "async")
    # out of the bus
    d.line(388, 444, 388, 488, "async")
    d.line(628, 444, 628, 488, "async")
    d.line(872, 444, 872, 488, "async")
    # nodes
    d.caption(40, 64, "IDENTITY (OPRÁVNĚNÍ) A AUDIT POUŽÍVAJÍ VŠECHNY MODULY · AUDIT SE ZAPISUJE V TRANSAKCI ZMĚNY")
    d.node(40, 168, 200, 88, "Catalog", "událost · aktivita · formulář", "MODUL", "step", sub2="snapshot pro registraci")
    d.node(312, 136, 272, 96, "Registration", "objednávka · účast · kapacita", "MODUL", "focal", sub2="rezervace · samoobsluha", name_size=14)
    d.node(312, 272, 232, 88, "Legal", "dokumenty · požadavky", "MODUL", "step", sub2="potvrzení")
    d.node(768, 136, 208, 96, "Payments", "ledger · Pairrot · hotovost", "MODUL", "step", sub2="refund úkoly")
    d.node(768, 296, 176, 80, "CheckIn", "QR · odbavení", "MODUL", "step")
    d.node(288, 488, 200, 72, "Communication", "šablony · doručení", "ODBĚR", "step")
    d.node(528, 488, 200, 72, "Attention", "fronta výjimek", "ODBĚR", "step")
    d.node(768, 488, 208, 72, "Content · Privacy", "Reporting", "ODBĚR", "optional")
    d.legend(592, [("focal", "Jádro registrace"), ("boundary", "Jedna DB transakce"), ("step", "Modul"),
                   ("muted", "Synchronně přes Contract/"), ("async", "Integrační událost přes Messenger")])
    write(d, "02-moduly-a-hranice", "Architektura · bounded contexty", "Kdo sdílí transakci a kdo reaguje přes outbox")


# --------------------------------------------------------------------------
# 02 · Layers inside one module
# --------------------------------------------------------------------------
def layers():
    d = Diagram("layers", 1040, 488, "Vrstvy uvnitř modulu",
                "Každý modul má vrstvy UserInterface, Contract, Application, Domain a pod nimi sdílený kernel; závislosti vedou jen dolů, Infrastructure implementuje porty domény a aplikace a cizí modul smí volat jen vrstvu Contract.")
    rows = [
        ("UserInterface", "controllery · value resolvery · CLI · handlery událostí", "→ Application, Shared"),
        ("Contract", "fasáda · DTO · integrační události · výjimky", "→ Application, Shared"),
        ("Application", "commandy · queries · handlery · porty", "→ Domain, Shared"),
        ("Domain", "entity · value objects · události · repozitáře (rozhraní)", "→ Shared\\Domain"),
        ("Shared (kernel)", "Money · Clock · Uuid · TenantId · Messenger", "→ nic"),
    ]
    x, w, h, y0 = 240, 560, 64, 56
    parts = []
    for i, (name, what, deps) in enumerate(rows):
        y = y0 + i * h
        fill = "#ffffff" if i % 2 == 0 else "#ececec"
        parts.append(f'<rect x="{x}" y="{y}" width="{w}" height="{h}" fill="{fill}" stroke="rgba(45,49,66,0.12)" stroke-width="1"/>')
        parts.append(f'<text x="{x + 20}" y="{y + 28}" fill="{INK}" font-size="15" font-weight="600" font-family="{SANS}">{esc(name)}</text>')
        parts.append(f'<text x="{x + 20}" y="{y + 46}" fill="{SOFT}" font-size="9" font-family="{MONO}">{esc(what)}</text>')
        parts.append(f'<text x="{x + w - 20}" y="{y + 28}" fill="{MUTED}" font-size="9" font-family="{MONO}" text-anchor="end">{esc(deps)}</text>')
    parts.append(f'<rect x="{x}" y="{y0}" width="{w}" height="{h * len(rows)}" fill="none" stroke="{MUTED}" stroke-width="1"/>')
    # infrastructure side box (rows Application + Domain)
    ix, iy, iw, ih = 848, y0 + 2 * h, 160, 2 * h
    parts.append(f'<rect x="{ix}" y="{iy}" width="{iw}" height="{ih}" rx="6" fill="rgba(235,108,54,0.08)" stroke="{ACCENT}" stroke-width="1" stroke-dasharray="4,4"/>')
    parts.append(f'<text x="{ix + 16}" y="{iy + 28}" fill="{INK}" font-size="15" font-weight="600" font-family="{SANS}">Infrastructure</text>')
    for j, t in enumerate(["Doctrine mapping", "repozitáře · adaptéry", "PairrotHttpGateway…", "implementuje porty"]):
        parts.append(f'<text x="{ix + 16}" y="{iy + 52 + 16 * j}" fill="{MUTED}" font-size="9" font-family="{MONO}">{esc(t)}</text>')
    d.nodes.extend(parts)
    d.line(848, y0 + 2 * h + 32, 800, y0 + 2 * h + 32, "dashed")
    d.line(848, y0 + 3 * h + 32, 800, y0 + 3 * h + 32, "dashed")
    # foreign module
    d.node(40, y0 + h + 8, 160, 48, "Cizí modul", "smí jen do Contract/", None, "input")
    d.line(200, y0 + h + 32, 240, y0 + h + 32, "link")
    d.note(240, 404, "Hranici hlídá deptrac (importy) a ModuleBoundaryPass (služby v kontejneru).")
    d.legend(432, [("input", "Jiný modul"), ("boundary", "Adaptéry a mapping"), ("link", "Jediný povolený vstup zvenku"),
                   ("dashed", "Implementuje rozhraní")])
    write(d, "02-moduly-a-hranice", "Architektura · vrstvy", "Vrstvy uvnitř jednoho modulu")


# --------------------------------------------------------------------------
# 03 · ER: registration
# --------------------------------------------------------------------------
def er_registration():
    d = Diagram("er-registration", 1200, 656, "Registrace: objednávka, osoba, účast, kapacita",
                "Objednávka drží osoby, účasti a sady odpovědí; účast je jediný nositel kapacity a přes capacity_claim obsazuje řádky capacity_bucket pro událost, aktivitu, variantu a vlnu.")
    s = Schema(d)
    s.table("orders", 40, 72, 280, [("id", "uuid"), ("organizer_id", "uuid"), ("event_id", "uuid"), ("number", "text"),
                                   ("orderer_email", "text"), ("amount_due_minor", "bigint"), ("placed_at", "timestamptz")],
            fk=("organizer_id", "event_id"), more=6)
    s.table("answer_set", 40, 392, 280, [("id", "uuid"), ("order_id", "uuid"), ("scope", "text"), ("subject_id", "uuid"),
                                         ("form_version_id", "uuid"), ("answers", "jsonb")],
            fk=("order_id", "form_version_id"))
    s.table("participation", 480, 72, 300, [("id", "uuid"), ("order_id", "uuid"), ("person_id", "uuid"), ("activity_id", "uuid"),
                                            ("variant_id", "uuid"), ("state", "text"), ("hold_expires_at", "timestamptz"),
                                            ("registration_code", "char(8)"), ("qr_version", "int")],
            fk=("order_id", "person_id", "activity_id", "variant_id"), focal=True, more=4)
    s.table("person", 480, 400, 300, [("id", "uuid"), ("order_id", "uuid"), ("first_name", "text"), ("last_name", "text"),
                                      ("birth_date", "date"), ("guardian_person_id", "uuid")],
            fk=("order_id", "guardian_person_id"))
    s.table("capacity_claim", 900, 72, 260, [("participation_id", "uuid"), ("bucket_id", "uuid"), ("seats", "int"), ("status", "text")],
            pk=("participation_id", "bucket_id"), fk=("participation_id", "bucket_id"))
    s.table("capacity_bucket", 900, 280, 260, [("id", "uuid"), ("organizer_id", "uuid"), ("event_id", "uuid"), ("scope_type", "text"),
                                               ("scope_id", "uuid"), ("unit", "text"), ("limit", "int"), ("held", "int"), ("confirmed", "int")], more=2)
    # participation.order_id -> order.id
    y1 = s.cy("participation", "order_id")
    s_id = s.cy("orders", "id", -4)
    d.path(f"M 480,{y1} H 428 Q 420,{y1} 420,{y1 - 8} V {s_id + 8} Q 420,{s_id} 412,{s_id} H 320")
    # participation.person_id -> person.id (left loop)
    y1, y2 = s.cy("participation", "person_id"), s.cy("person", "id")
    d.path(f"M 480,{y1} H 464 Q 456,{y1} 456,{y1 + 8} V {y2 - 8} Q 456,{y2} 464,{y2} H 480")
    # person.order_id -> order.id
    y1, y2 = s.cy("person", "order_id"), s.cy("orders", "id", 8)
    d.path(f"M 480,{y1} H 408 Q 400,{y1} 400,{y1 - 8} V {y2 + 8} Q 400,{y2} 392,{y2} H 320")
    # answer_set.order_id -> order.id (left side)
    y1, y2 = s.cy("answer_set", "order_id"), s.cy("orders", "id")
    d.path(f"M 40,{y1} H 28 Q 20,{y1} 20,{y1 - 8} V {y2 + 8} Q 20,{y2} 28,{y2} H 40")
    # capacity_claim.participation_id -> participation.id
    y = s.cy("capacity_claim", "participation_id")
    d.path(f"M 900,{y} H 780")
    # capacity_claim.bucket_id -> capacity_bucket.id (right side)
    y1, y2 = s.cy("capacity_claim", "bucket_id"), s.cy("capacity_bucket", "id")
    d.path(f"M 1160,{y1} H 1172 Q 1180,{y1} 1180,{y1 + 8} V {y2 - 8} Q 1180,{y2} 1172,{y2} H 1160")
    d.caption(900, 512, "PODMÍNĚNÝ UPDATE + CHECK … OR OVERBOOKED_ALLOWED")
    d.caption(900, 528, "SNÍŽENÍ LIMITU POD OBSAZENOST = VAROVÁNÍ (CAP-06)")
    d.legend(608, [("muted", "FK sloupec → sloupec, vše ON DELETE RESTRICT"), ("focal", "Nositel kapacity a odbavení")])
    write(d, "03-data", "Databázové schéma · registrace", "Registrace: objednávka, osoba, účast, kapacita")


# --------------------------------------------------------------------------
# 03 · ER: payments
# --------------------------------------------------------------------------
def er_payments():
    d = Diagram("er-payments", 1200, 664, "Platby: ledger a co z něj plyne",
                "Ledger je append-only a jeho fold dává finanční stav v order_finance; platební požadavky zrcadlí Pairrot, refund úkoly a nespárované transakce se k objednávce vážou jen odkazem na order_id.")
    s = Schema(d)
    s.table("payment_request", 40, 72, 280, [("id", "uuid"), ("order_id", "uuid"), ("attempt", "int"), ("pairrot_id", "text"),
                                             ("creation_state", "text"), ("variable_symbol", "text"), ("amount_minor", "bigint"), ("status", "text"),
                                             ("last_sequence", "bigint"), ("known_paid_amount", "bigint")],
            fk=("order_id",), more=5)
    s.table("refund_task", 40, 424, 280, [("id", "uuid"), ("order_id", "uuid"), ("amount_minor", "bigint"), ("reason", "text"),
                                          ("status", "text"), ("closed_by", "uuid")],
            fk=("order_id",))
    s.table("order_finance", 440, 240, 280, [("order_id", "uuid"), ("organizer_id", "uuid"), ("state", "text"),
                                             ("paid_minor", "bigint"), ("due_minor", "bigint"), ("ledger_version", "int")],
            pk=("order_id",), fk=("order_id",))
    s.table("ledger_entry", 840, 72, 300, [("id", "uuid"), ("order_id", "uuid"), ("kind", "text"), ("amount_minor", "bigint"),
                                           ("source_ref", "text"), ("compensates_id", "uuid"), ("actor", "jsonb"),
                                           ("occurred_at", "timestamptz")],
            fk=("order_id", "compensates_id"), focal=True, more=3)
    s.table("unmatched_transaction", 840, 404, 300, [("id", "uuid"), ("organizer_id", "uuid"), ("pairrot_tx_id", "text"),
                                                     ("amount_minor", "bigint"), ("variable_symbol", "text"),
                                                     ("matching_result", "text"), ("resolved_order_id", "uuid")],
            fk=("resolved_order_id",))
    t = s.cy("order_finance", "order_id")
    # payment_request -> order_finance (left, upper attach)
    y1 = s.cy("payment_request", "order_id")
    d.path(f"M 320,{y1} H 392 Q 400,{y1} 400,{y1 + 8} V {t - 16} Q 400,{t - 8} 408,{t - 8} H 440")
    # refund_task -> order_finance (left, lower attach)
    y1 = s.cy("refund_task", "order_id")
    d.path(f"M 320,{y1} H 376 Q 384,{y1} 384,{y1 - 8} V {t + 16} Q 384,{t + 8} 392,{t + 8} H 440")
    # ledger_entry -> order_finance (right, upper attach)
    y1 = s.cy("ledger_entry", "order_id")
    d.path(f"M 840,{y1} H 768 Q 760,{y1} 760,{y1 + 8} V {t - 16} Q 760,{t - 8} 752,{t - 8} H 720", "accent")
    # unmatched -> order_finance (right, lower attach)
    y1 = s.cy("unmatched_transaction", "resolved_order_id")
    d.path(f"M 840,{y1} H 784 Q 776,{y1} 776,{y1 - 8} V {t + 16} Q 776,{t + 8} 768,{t + 8} H 720", "dashed")
    # ledger self-reference
    y1, y2 = s.cy("ledger_entry", "compensates_id"), s.cy("ledger_entry", "id")
    d.path(f"M 1140,{y1} H 1152 Q 1160,{y1} 1160,{y1 - 8} V {y2 + 8} Q 1160,{y2} 1152,{y2} H 1140")
    d.caption(440, 448, "FOLD(LEDGER_ENTRY) = ORDER_FINANCE, VE STEJNÉ TRANSAKCI")
    d.caption(440, 464, "LEDGER_ENTRY: JEN INSERT A SELECT PRO EVENTOR_APP")
    d.legend(616, [("muted", "FK → order_finance.order_id, ON DELETE RESTRICT"), ("accent", "Fold ledgeru"),
                   ("dashed", "Volitelná vazba po ručním přiřazení")])
    write(d, "03-data", "Databázové schéma · platby", "Platby: ledger a co z něj plyne")


# --------------------------------------------------------------------------
# 03 · ER: legal
# --------------------------------------------------------------------------
def er_legal():
    d = Diagram("er-legal", 1200, 624, "Právní dokumenty, požadavky a potvrzení",
                "Dokument má neměnné verze s hashem obsahu, požadavek se váže na dokument a potvrzení zaznamenává rozhodnutí konkrétní osoby ke konkrétní verzi; nové rozhodnutí odkazuje na předchozí přes supersedes_id.")
    s = Schema(d)
    s.table("legal_document", 40, 72, 280, [("id", "uuid"), ("organizer_id", "uuid"), ("level", "text"), ("scope_id", "uuid"),
                                            ("title", "text"), ("current_version_id", "uuid")])
    s.table("document_version", 440, 72, 300, [("id", "uuid"), ("document_id", "uuid"), ("version", "int"), ("content_sha256", "char(64)"),
                                               ("storage_key", "text"), ("requires_reconfirm", "bool"), ("published_at", "timestamptz")],
            fk=("document_id",))
    s.table("legal_requirement", 40, 336, 280, [("id", "uuid"), ("document_id", "uuid"), ("type", "text"), ("confirmer", "text"),
                                                ("revocable", "bool"), ("activity_id", "uuid"), ("mandatory", "bool")],
            fk=("document_id", "activity_id"))
    s.table("confirmation", 840, 72, 300, [("id", "uuid"), ("document_version_id", "uuid"), ("requirement_id", "uuid"),
                                           ("person_id", "uuid"), ("participation_id", "uuid"), ("order_id", "uuid"),
                                           ("decision", "text"), ("channel", "text"), ("decided_at", "timestamptz"),
                                           ("supersedes_id", "uuid")],
            fk=("document_version_id", "requirement_id", "person_id", "participation_id", "order_id", "supersedes_id"), focal=True)
    y1, y2 = s.cy("document_version", "document_id"), s.cy("legal_document", "id")
    d.path(f"M 440,{y1} H 408 Q 400,{y1} 400,{y1 - 8} V {y2 + 8} Q 400,{y2} 392,{y2} H 320")
    y1, y2 = s.cy("legal_requirement", "document_id"), s.cy("legal_document", "id")
    d.path(f"M 40,{y1} H 28 Q 20,{y1} 20,{y1 - 8} V {y2 + 8} Q 20,{y2} 28,{y2} H 40")
    y1, y2 = s.cy("confirmation", "document_version_id"), s.cy("document_version", "id")
    d.path(f"M 840,{y1} H 808 Q 800,{y1} 800,{y1 - 8} V {y2 + 8} Q 800,{y2} 792,{y2} H 740", "accent")
    y1, y2 = s.cy("confirmation", "requirement_id"), s.cy("legal_requirement", "id")
    d.path(f"M 840,{y1} H 788 Q 780,{y1} 780,{y1 + 8} V {y2 - 8} Q 780,{y2} 772,{y2} H 320")
    y1, y2 = s.cy("confirmation", "supersedes_id"), s.cy("confirmation", "id")
    d.path(f"M 1140,{y1} H 1152 Q 1160,{y1} 1160,{y1 - 8} V {y2 + 8} Q 1160,{y2} 1152,{y2} H 1140")
    d.caption(440, 336, "DRAFT UŽ MÁ HASH I KLÍČ V S3 POD legal/sha256/<HASH>")
    d.caption(440, 352, "CONFIRMATION: JEN INSERT, NIKDY UPDATE")
    d.legend(576, [("muted", "FK sloupec → sloupec, ON DELETE RESTRICT"), ("accent", "Vazba na konkrétní verzi")])
    write(d, "03-data", "Databázové schéma · právní potvrzení", "Dokumenty, požadavky a potvrzení")


DIAGRAMS = [system_context, deployment, contexts_map, layers, er_registration, er_payments, er_legal]
