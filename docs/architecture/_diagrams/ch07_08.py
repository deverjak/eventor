"""Diagrams of chapters 07 (Integrace) and 08 (Průřezové koncepty)."""

from __future__ import annotations

from dd import ACCENT, INK, MONO, MUTED, SANS, SOFT, Diagram, esc

from common import write


# --------------------------------------------------------------------------
# 07 · Integration pattern: port, decorators, adapter
# --------------------------------------------------------------------------
def integration_pattern():
    d = Diagram("integration-pattern", 1336, 432, "Společný vzor integrací",
                "Handler v aplikační vrstvě volá port PaymentGateway; v produkci za ním stojí řetěz dekorátorů "
                "(logování bez osobních údajů, circuit breaker, retry) a HTTP adaptér Pairrotu, v testech in-memory fake "
                "a lokálně pairrot-sandbox místo skutečného Pairrotu.")
    d.zone(40, 64, 352, 152, "PAYMENTS · APPLICATION")
    d.zone(416, 64, 680, 152, "PAYMENTS · INFRASTRUCTURE")
    d.zone(1120, 64, 176, 280, "EXTERNÍ")
    d.zone(40, 240, 352, 104, "TESTS")
    # chain arrows
    d.line(192, 144, 232, 144, "muted")
    d.line(368, 144, 440, 144, "muted")
    d.line(568, 144, 608, 144, "muted")
    d.line(736, 144, 776, 144, "muted")
    d.line(904, 144, 944, 144, "muted")
    d.line(1072, 144, 1144, 144, "link", "HTTPS")
    d.line(300, 184, 300, 264, "dashed")
    d.vlabel(300, 228, "IMPLEMENTUJE", "dashed")
    d.lpath(1008, 184, 1144, 304, "link-dashed", "LOKÁLNĚ", (1076, 304, "h"), first="v")
    # nodes
    d.node(64, 104, 128, 80, "Handler", "CreatePayment…", "CMD", "step")
    d.node(232, 104, 136, 80, "PaymentGateway", "port · interface", "PORT", "focal")
    d.node(440, 104, 128, 80, "Logování", "PiiRedacting…", "DEC", "step")
    d.node(608, 104, 128, 80, "Circuit breaker", "CircuitBreaker…", "DEC", "step")
    d.node(776, 104, 128, 80, "Retry", "Retrying…", "DEC", "step")
    d.node(944, 104, 128, 80, "HTTP adaptér", "HttpPairrot…", "ADAPTER", "step")
    d.node(1144, 104, 128, 80, "Pairrot", "REST API v1", "EXT", "external")
    d.node(232, 264, 136, 64, "In-memory fake", "testy + kontrakt", "FAKE", "optional")
    d.node(1144, 264, 128, 80, "pairrot-sandbox", "docker compose", "DEV", "optional")
    d.note(440, 252, "Dekorátory skládá kontejner přes #[AsDecorator]; adaptér ani handler o nich neví.")
    d.legend(368, [("focal", "Port (rozhraní)"), ("step", "Služba"), ("external", "Externí služba"),
                   ("optional", "Náhrada mimo produkci"), ("muted", "Volání v procesu"), ("link", "HTTP")])
    write(d, "07-integrace", "Integrace · porty a adaptéry", "Společný vzor integrací")


# --------------------------------------------------------------------------
# 07 · Pairrot flows: out (create / cancel / allocate), in (webhook + reconcile)
# --------------------------------------------------------------------------
def pairrot_flows():
    d = Diagram("pairrot-flows", 1120, 664, "Toky mezi Eventorem a Pairrotem",
                "Modul Payments vytváří, ruší a ručně přiřazuje platby v Pairrotu; změny se vracejí podepsaným webhookem "
                "a každých 15 minut dorovnávacím jobem přes event log, obojí přes inbox s unikátním ID; z rozdílu "
                "zaplacené částky vzniká zápis v ledgeru.")
    d.zone(24, 56, 784, 552, "EVENTOR · BACKEND")
    d.zone(832, 56, 264, 552, "PAIRROT")
    # arrows
    d.line(216, 136, 296, 136, "async", "ORDERPLACED")
    d.line(536, 136, 856, 136, "link", "CREATE · CANCEL")
    d.line(536, 216, 856, 216, "link", "ALLOCATE")
    d.lpath(336, 272, 216, 392, "muted", "DELTA", (336, 324, "v"), first="v", label_side="right")
    d.lpath(920, 272, 792, 392, "link-dashed", "WEBHOOK · HMAC", (920, 324, "v"), first="v", label_side="right")
    d.line(704, 432, 704, 496, "muted")
    d.vlabel(704, 464, "INSERT UNIQUE", "muted")
    d.lpath(616, 536, 496, 272, "accent", "ZPRACUJ", (496, 436, "v"), label_side="right")
    d.line(1000, 496, 1000, 272, "link")
    d.vlabel(1000, 456, "EVENT LOG", "link", side="right")
    d.line(856, 536, 792, 536, "muted")
    # nodes
    d.node(40, 96, 176, 80, "Messenger worker", "payments_events", "ASYNC", "step")
    d.node(296, 96, 240, 176, "Payments", "PaymentRequest · Ledger", "MODULE", "focal",
           sub2="FinancialState fold", name_size=16)
    d.node(856, 96, 200, 176, "Pairrot API", "/v1/subtenants/{id}/…", "EXT", "external",
           sub2="payment-requests · transactions")
    d.node(40, 352, 176, 80, "Ledger", "ledger_entry (append-only)", "STORE", "store")
    d.node(616, 352, 176, 80, "Webhook endpoint", "/api/webhooks/pairrot", "HTTP", "step")
    d.node(616, 496, 176, 80, "Inbox", "inbox_message", "STORE", "store")
    d.node(856, 496, 200, 80, "Reconcile job", "Scheduler · 15 min · 48 h", "CRON", "optional")
    d.note(40, 520, "Finance přiřazuje nespárované")
    d.note(40, 540, "platby z UI Eventoru (ALLOCATE).")
    d.legend(612, [("focal", "Vlastník peněz"), ("store", "Úložiště"), ("external", "Pairrot"),
                   ("link", "Volání HTTP"), ("link-dashed", "Webhook"), ("accent", "Zápis peněz")])
    write(d, "07-integrace", "Integrace · Pairrot", "Toky mezi Eventorem a Pairrotem")


# --------------------------------------------------------------------------
# 08 · Protection layers
# --------------------------------------------------------------------------
def protection_layers():
    d = Diagram("protection-layers", 1000, 560, "Vrstvy ochrany požadavku",
                "Požadavek prochází šesti vrstvami: rate limit, firewall s autentizací, value resolver tenanta, voter, "
                "doménový invariant a databázové constrainty; každá vrstva zastaví jinou třídu chyb.")
    rows = [
        ("L1", "Rate limit a antispam", "RateLimiter · honeypot · Turnstile port (volitelně)", "429 · zneužití", False),
        ("L2", "Firewall a autentizace", "webhooks · self_service · check_in · public · team", "401 · kdo jsi", False),
        ("L3", "Value resolver tenanta", "#[CurrentOrganizer] · Doctrine filtr organizer", "404 · cizí tenant", False),
        ("L4", "Voter", "Permission × role × událost · PII projekce", "403 · smíš to", True),
        ("L5", "Doménový invariant", "agregát · Workflow guard · CapacityAllocator", "409/422 · pravidla", False),
        ("L6", "Databáze", "CHECK · partial unique · FK RESTRICT · granty append-only", "poslední linie", False),
    ]
    x, w, h, y0 = 120, 840, 64, 56
    parts = []
    for i, (idx, name, path, note, focal) in enumerate(rows):
        y = y0 + i * h
        fill = "rgba(235,108,54,0.08)" if focal else ("#ffffff" if i % 2 == 0 else "#ececec")
        stroke = ACCENT if focal else "rgba(45,49,66,0.12)"
        parts.append(f'<rect x="{x}" y="{y}" width="{w}" height="{h}" fill="{fill}" stroke="{stroke}" stroke-width="1"/>')
        parts.append(f'<text x="{x + 20}" y="{y + 36}" fill="{ACCENT if focal else MUTED}" font-size="9" font-family="{MONO}" letter-spacing="0.14em">{idx}</text>')
        parts.append(f'<text x="{x + 64}" y="{y + 28}" fill="{INK}" font-size="15" font-weight="600" font-family="{SANS}">{esc(name)}</text>')
        parts.append(f'<text x="{x + 64}" y="{y + 46}" fill="{SOFT}" font-size="9" font-family="{MONO}">{esc(path)}</text>')
        parts.append(f'<text x="{x + w - 20}" y="{y + 36}" fill="{MUTED}" font-size="10" font-family="{MONO}" text-anchor="end">{esc(note)}</text>')
    parts.append(f'<rect x="{x}" y="{y0}" width="{w}" height="{h * len(rows)}" fill="none" stroke="{MUTED}" stroke-width="1"/>')
    parts.append(f'<line x1="72" y1="{y0 + 16}" x2="72" y2="{y0 + h * len(rows) - 16}" stroke="{MUTED}" stroke-width="1.2" marker-end="url(#arrow)"/>')
    parts.append(f'<text x="60" y="{y0 + 3 * h - 4}" fill="{MUTED}" font-size="8" font-family="{MONO}" text-anchor="end" letter-spacing="0.1em">POŽADAVEK</text>')
    parts.append(f'<text x="60" y="{y0 + 3 * h + 8}" fill="{MUTED}" font-size="8" font-family="{MONO}" text-anchor="end" letter-spacing="0.1em">JDE DOLŮ</text>')
    d.nodes.extend(parts)
    d.note(120, 488, "Žádná vrstva nespoléhá na tu nad sebou: i kdyby voter selhal, kapacitu a peníze hlídá L5 a L6.")
    write(d, "08-prurezove-koncepty", "Průřezové koncepty · ochrana", "Vrstvy ochrany požadavku")


DIAGRAMS = [integration_pattern, pairrot_flows, protection_layers]
