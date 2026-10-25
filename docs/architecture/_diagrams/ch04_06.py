"""Diagrams of chapters 04 (key paths), 05 (state machines) and 06 (async world)."""

from __future__ import annotations

from dd import ACCENT, LABEL_COLORS, MONO, PAPER, Diagram, esc, mono_width

from common import write

X5 = [124, 340, 556, 772, 988]


def seq(slug, title, desc, actors, steps, legend, folder, eyebrow, h1, frame=None, width=1120):
    """actors: [(x, name, sub, tag, kind)]; steps: ('m', i, j, text, style), ('s', i, text, style), ('p', text)."""
    y = 176
    rows = []
    for st in steps:
        rows.append((y, st))
        y += 28 if st[0] == "p" else (52 if st[0] == "s" else 44)
    bottom = y + (24 if frame is None else 96)
    d = Diagram(slug, width, bottom + 80, title, desc)
    for x, name, sub, tag, kind in actors:
        d.actor(x, 72, 176, name, sub, tag, kind, bottom=bottom)
    for yy, st in rows:
        if st[0] == "m":
            _, i, j, text, style = st
            d.msg(actors[i][0], actors[j][0], yy, text, style)
        elif st[0] == "s":
            _, i, text, style = st
            if i == len(actors) - 1:
                self_msg_left(d, actors[i][0], yy - 8, text, style)
            else:
                d.self_msg(actors[i][0], yy - 8, text, style)
        else:
            d.under.append(f'<line x1="40" y1="{yy}" x2="{width - 40}" y2="{yy}" stroke="rgba(45,49,66,0.14)" '
                           f'stroke-width="0.8" stroke-dasharray="2,3"/>')
            tw = len(st[1]) * 6 + 16
            d.labels.append(f'<rect x="40" y="{yy - 8}" width="{tw}" height="16" rx="2" fill="{PAPER}"/>'
                            f'<text x="48" y="{yy + 4}" fill="{ACCENT}" font-size="8" font-family="{MONO}" '
                            f'letter-spacing="0.14em">{esc(st[1])}</text>')
    if frame:
        i, j, guard, text, style = frame
        fx = actors[i][0] - 32
        fw = actors[j][0] - actors[i][0] + 64
        d.frame(fx, y + 8, fw, 80, "ALT", guard)
        d.msg(actors[i][0], actors[j][0], y + 56, text, style)
    d.legend(bottom + 16, legend)
    write(d, folder, eyebrow, h1)


def self_msg_left(d: Diagram, cx, y, text, style="muted", h=24):
    """Self message drawn to the left of the lifeline (for the rightmost actor)."""
    d.path(f"M {cx - 4},{y} H {cx - 36} V {y + h} H {cx - 6}", style)
    w = mono_width(text)
    x = cx - 44 - w
    d.labels.append(
        f'<rect x="{x}" y="{y + h / 2 - 6:.0f}" width="{w}" height="12" rx="2" fill="{PAPER}"/>'
        f'<text x="{x + w / 2:.0f}" y="{y + h / 2 + 3:.0f}" fill="{LABEL_COLORS[style]}" font-size="8" '
        f'font-family="{MONO}" text-anchor="middle" letter-spacing="0.06em">{esc(text)}</text>'
    )


# --------------------------------------------------------------------------
# 04 · Registration (PlaceOrder)
# --------------------------------------------------------------------------
def registration_sequence():
    seq("registration-sequence", "Registrace: PlaceOrder v jedné transakci",
        "Next.js pošle objednávku na veřejné API; value resolvery a rate limiter ji pustí k PlaceOrderHandleru, "
        "který v jedné transakci ověří formulář, spočítá cenu, obsadí všechny kapacitní buckety podmíněným UPDATE "
        "a zapíše objednávku, audit a zprávy do messenger_messages; po commitu worker doručí OrderPlaced odebírajícím modulům.",
        [(X5[0], "Účastník", "Next.js · prohlížeč", "USER", "input"),
         (X5[1], "Veřejné API", "PlaceOrderController", "HTTP", "step"),
         (X5[2], "PlaceOrderHandler", "Registration · command.bus", "APP", "focal"),
         (X5[3], "Postgres", "capacity_bucket · order", "DB", "store"),
         (X5[4], "Messenger worker", "messenger:consume", "ASYNC", "optional")],
        [("p", "1 · POŽADAVEK"),
         ("m", 0, 1, "POST ORDERS", "link"),
         ("s", 1, "LIMIT · IDEMPOTENCY-KEY", "muted"),
         ("m", 1, 2, "PLACEORDER", "muted"),
         ("p", "2 · JEDNA TRANSAKCE"),
         ("s", 2, "FORMULÁŘ · CENA · POŽADAVKY", "muted"),
         ("m", 2, 3, "UPDATE BUCKET × N", "accent"),
         ("m", 3, 2, "1 ROW KAŽDÝ", "return"),
         ("m", 2, 3, "ORDER · AUDIT · OUTBOX", "muted"),
         ("m", 3, 2, "COMMIT", "return"),
         ("m", 2, 1, "ORDERID", "return"),
         ("m", 1, 0, "201 · ODKAZ", "return"),
         ("p", "3 · PO COMMITU"),
         ("m", 3, 4, "NOTIFY OUTBOX", "async"),
         ("s", 4, "ORDER_PLACED → ODBĚRATELÉ", "muted")],
        [("input", "Uživatel"), ("focal", "Rozhodující krok"), ("link", "HTTP"), ("return", "Odpověď"),
         ("async", "Asynchronně"), ("accent", "Obsazení kapacity")],
        "04-klicove-cesty", "Klíčová cesta · sekvence", "Registrace jako jedna transakce",
        frame=(2, 3, "[0 ROWS] MÍSTO OBSAZENO", "ROLLBACK → 409", "return"))


# --------------------------------------------------------------------------
# 04 · Incoming Pairrot payment
# --------------------------------------------------------------------------
def payment_webhook_sequence():
    seq("payment-webhook-sequence", "Příchozí platba z Pairrotu",
        "Pairrot pošle podepsaný webhook; endpoint ověří HMAC a uloží zprávu do inboxu s unikátním ID, takže duplicita "
        "skončí na unique constraintu; worker zkontroluje sequence, zapíše deltu paid_amount do ledgeru, přepočte "
        "finanční stav a přes outbox nechá Registration potvrdit účasti.",
        [(X5[0], "Pairrot", "payment_request.updated", "EXT", "external"),
         (X5[1], "Webhook API", "/api/webhooks/pairrot", "HTTP", "step"),
         (X5[2], "Postgres", "inbox · ledger · outbox", "DB", "store"),
         (X5[3], "Payments", "ApplyPairrotPaymentUpdate", "APP", "focal"),
         (X5[4], "Registration", "odběratel finančního stavu", "APP", "step")],
        [("p", "1 · PŘÍJEM"),
         ("m", 0, 1, "POST WEBHOOK", "link-dashed"),
         ("s", 1, "HMAC · ±5 MIN", "muted"),
         ("m", 1, 2, "INBOX ON CONFLICT", "muted"),
         ("m", 1, 0, "200 OK", "return"),
         ("p", "2 · ZPRACOVÁNÍ"),
         ("m", 2, 3, "NOTIFY INBOX", "async"),
         ("s", 3, "SEQUENCE > POSLEDNÍ?", "muted"),
         ("m", 3, 2, "LEDGER DELTA · FOLD", "accent"),
         ("m", 3, 2, "OUTBOX FINANCE", "muted"),
         ("p", "3 · REAKCE"),
         ("m", 2, 4, "ORDER_PAYMENT_CHANGED", "async"),
         ("s", 4, "ÚČASTI → CONFIRMED", "muted")],
        [("external", "Externí služba"), ("focal", "Rozhodující krok"), ("link-dashed", "Webhook"),
         ("return", "Odpověď"), ("async", "Asynchronně"), ("accent", "Zápis do ledgeru")],
        "04-klicove-cesty", "Klíčová cesta · sekvence", "Platba z Pairrotu až k potvrzení účasti",
        frame=(3, 4, "[ÚČAST EXPIROVANÁ]", "VÝJIMKA / AUTO-OBNOVA", "dashed"))


# --------------------------------------------------------------------------
# 05 · Participation
# --------------------------------------------------------------------------
def participation_states():
    d = Diagram("participation-states", 1120, 616, "Stavy účasti (participation.state)",
                "Účast vzniká jako čekající s rezervací při platbě převodem, nebo rovnou potvrzená při hotovosti a zdarma; "
                "čekající se potvrdí platbou, nebo po TTL expiruje; platba po expiraci vede na automatickou obnovu nebo do "
                "stavu vyžaduje řešení; storno a převod jsou koncové stavy.")
    d.start_dot(72, 280)
    d.line(78, 280, 128, 280, "muted")
    d.start_dot(424, 304)
    d.line(430, 304, 480, 304, "muted")
    d.caption(424, 332, "HOTOVOST · ZDARMA", anchor="middle")
    # pending -> confirmed
    d.line(320, 272, 480, 272, "accent", "ZAPLACENO · RUČNĚ")
    # pending -> expired
    d.line(224, 320, 224, 432, "muted")
    d.vlabel(224, 380, "TTL VYPRŠEL", side="left")
    # expired -> confirmed (auto restore)
    d.path("M 288,432 V 368 Q 288,360 296,360 H 504 Q 512,360 512,352 V 320", "dashed")
    d.caption(300, 352, "AUTO-OBNOVA (S06)")
    # expired -> requires_attention
    d.line(320, 472, 480, 472, "muted", "PLATBA PO TTL")
    # requires_attention -> confirmed
    d.line(608, 432, 608, 320, "muted")
    d.vlabel(608, 380, "OBNOVA", side="right")
    # requires_attention -> cancelled
    d.path("M 672,472 H 968 Q 976,472 976,464 V 320", "muted")
    d.hlabel(824, 472, "REFUND ÚKOL (S05)")
    # confirmed -> cancelled
    d.line(672, 272, 880, 272, "muted", "STORNO")
    # pending -> cancelled
    d.path("M 224,240 V 56 Q 224,48 232,48 H 1000 Q 1008,48 1008,56 V 240", "muted")
    d.hlabel(760, 48, "STORNO · ZRUŠENÍ AKCE")
    # confirmed -> transferred
    d.line(576, 240, 576, 160, "muted")
    d.vlabel(576, 200, "PŘEVOD · P1", side="right")
    d.state(128, 240, 192, 80, "pending", "čeká · drží hold", sub2="hold_expires_at")
    d.state(480, 240, 192, 80, "confirmed", "kapacita confirmed", focal=True)
    d.state(480, 80, 192, 80, "transferred", "vzniká nová účast", terminal=True)
    d.state(880, 240, 192, 80, "cancelled", "kapacita uvolněna", terminal=True)
    d.state(128, 432, 192, 80, "expired", "kapacita uvolněna")
    d.state(480, 432, 192, 80, "requires_attention", "AttentionItem otevřen")
    d.legend(556, [("focal", "Cílový stav"), ("step", "Živý stav"), ("store", "Koncový stav"),
                   ("accent", "Hlavní cesta"), ("dashed", "Podle nastavení akce")])
    write(d, "05-stavove-automaty", "Stavový automat · Participation", "Stavy účasti")


# --------------------------------------------------------------------------
# 05 · Financial state (derived)
# --------------------------------------------------------------------------
def financial_state():
    d = Diagram("financial-state", 1120, 576, "Finanční stav objednávky (odvozený)",
                "Finanční stav není uložený přechod, ale výsledek foldu ledgeru proti aktuální dlužné částce; "
                "platby posouvají objednávku od neuhrazené přes částečně uhrazenou k uhrazené, přeplatek otevře refund "
                "úkol a jeho vypořádání vede ke stavu vráceno; změna dlužné částky může stav vrátit zpět.")
    d.start_dot(40, 240)
    d.line(46, 240, 64, 240, "muted")
    d.line(240, 240, 344, 240, "muted", "PLATBA < DLUH")
    d.line(520, 228, 624, 228, "accent", "DOPLATEK")
    d.line(800, 240, 904, 240, "muted", "PLATBA > DLUH")
    d.path("M 152,200 V 160 Q 152,152 160,152 H 704 Q 712,152 712,160 V 200", "accent")
    d.hlabel(432, 152, "PŘESNÁ ČÁSTKA (AUTO-MATCH)", "accent")
    d.path("M 680,280 V 324 Q 680,332 672,332 H 440 Q 432,332 432,324 V 280", "dashed")
    d.hlabel(556, 332, "DLUH ↑ ZMĚNA (S18)", "dashed", side="below")
    d.path("M 760,280 V 352 Q 760,360 768,360 H 952 Q 960,360 960,352 V 280", "dashed")
    d.hlabel(860, 360, "STORNO ↓ DLUH", "dashed", side="below")
    d.line(1032, 280, 1032, 400, "muted")
    d.vlabel(1032, 340, "REFUND ÚKOL", side="right")
    d.line(904, 440, 800, 440, "muted", "VYPOŘÁDÁNO")
    d.line(152, 280, 152, 400, "dashed")
    d.vlabel(152, 340, "REVIEW CASE / SPOR", "dashed", side="right")
    d.state(64, 200, 176, 80, "unpaid", "neuhrazená · Σ = 0")
    d.state(344, 200, 176, 80, "partially_paid", "0 < Σ < dluh")
    d.state(624, 200, 176, 80, "paid", "Σ = dluh", focal=True)
    d.state(904, 200, 176, 80, "overpaid", "Σ > dluh")
    d.state(904, 400, 176, 80, "refund_pending", "otevřený RefundTask")
    d.state(624, 400, 176, 80, "refunded", "refund_settled", terminal=True)
    d.state(64, 400, 176, 80, "disputed", "sporná · Attention")
    d.note(64, 104, "stav = f(Σ ledger_entry, amount_due, otevřené refund úkoly, spor)")
    d.legend(516, [("focal", "Cílový stav"), ("step", "Odvozený stav"), ("store", "Koncový stav"),
                   ("accent", "Hlavní cesta"), ("dashed", "Změna dluhu / spor")])
    write(d, "05-stavove-automaty", "Odvozený stav · FinancialState", "Finanční stav objednávky")


# --------------------------------------------------------------------------
# 06 · Outbox flow
# --------------------------------------------------------------------------
def outbox_flow():
    d = Diagram("outbox-flow", 1120, 496, "Transakční outbox a doručení odběratelům",
                "HTTP požadavek i plánovač končí příkazem, který v jedné transakci změní stav a zapíše integrační událost "
                "do messenger_messages, jeden řádek na transport každého odebírajícího modulu; Postgres NOTIFY probudí worker, "
                "ten si zprávu vezme přes SKIP LOCKED a předá handleru modulu, s retry strategií a failure transportem.")
    d.zone(40, 128, 576, 120, "JEDNA DB TRANSAKCE")
    d.line(164, 96, 164, 152, "link")
    d.vlabel(164, 120, "PŘÍKAZ", "link")
    d.line(164, 328, 164, 224, "dashed")
    d.vlabel(164, 276, "PLÁNOVANĚ", "dashed")
    d.line(264, 188, 360, 188, "accent", "COMMIT")
    d.line(584, 172, 680, 172, "async", "NOTIFY")
    d.line(680, 204, 584, 204, "dashed", "SKIP LOCKED", side="below")
    d.path("M 720,224 V 260 Q 720,268 712,268 H 648 Q 640,268 640,276 V 328", "muted")
    d.path("M 780,224 V 268 Q 780,276 788,276 H 816 Q 824,276 824,284 V 328", "muted")
    d.path("M 840,224 V 284 Q 840,292 848,292 H 1000 Q 1008,292 1008,300 V 328", "muted")
    d.hlabel(924, 292, "FAILED · VÝJIMKY", side="above")
    d.node(64, 40, 200, 56, "HTTP · webhook", "controller → command.bus", "IN", "input")
    d.node(64, 152, 200, 72, "Command handler", "stav + audit + event.bus", "APP", "step")
    d.node(360, 152, 224, 72, "Postgres", "messenger_messages", "DB", "store")
    d.node(680, 152, 200, 72, "Messenger worker", "transport na modul", "ASYNC", "focal")
    d.caption(896, 180, "BACKOFF 1M · 5M · 30M · 2H · 12H")
    d.caption(896, 196, "6. SELHÁNÍ → FAILED")
    d.node(64, 328, 200, 72, "Scheduler", "expirace · upomínky · reconcile", "CRON", "optional")
    d.node(560, 328, 160, 72, "payments_events", "Pairrot · ledger", "SUB", "step")
    d.node(744, 328, 160, 72, "communication_events", "Delivery · Mailer", "SUB", "step")
    d.node(928, 328, 160, 72, "attention_events", "AttentionItem", "SUB", "step")
    d.legend(440, [("input", "Vstup"), ("focal", "Worker"), ("store", "Databáze"), ("accent", "Commit"),
                   ("async", "Probuzení"), ("dashed", "Plánovaně / claim")])
    write(d, "06-asynchronni-svet", "Asynchronní svět · outbox", "Od commitu k odběratelům")


DIAGRAMS = [registration_sequence, payment_webhook_sequence, participation_states, financial_state, outbox_flow]
