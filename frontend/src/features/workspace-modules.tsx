"use client";
import Link from "next/link";
import { useState } from "react";
import { PageHeader } from "@/components/ui";
import { EventPicker, orgRoot, useRole } from "./screens";
import {
  Box,
  Command,
  DataView,
  ErrorMessage,
  Resource,
  input,
  secondary,
  useData,
  type Row,
} from "./workspace-ui";
import {
  arr,
  campaignFields,
  choice,
  eventSpecs,
  f,
  formFieldFields,
  managers,
  organizerSpecs,
  reason,
  required,
} from "./workspace-config";

export function FormEditor({ root }: { root: string }) {
  const overview = useData<Row>(`${root}/form`);
  const event = useData<Row>(root);
  const activities = (event.data?.activities || []) as Row[];
  const variants = activities.flatMap((a) => (a.variants || []) as Row[]);
  const draft = overview.data?.draft as Row | null;
  const published = overview.data?.published as Row | null;
  const initial = draft ?? published ?? {};
  const [number, setNumber] = useState(1);
  return (
    <div className="space-y-6">
      <Box>
        <h2 className="mb-4 font-semibold">Registrační formulář</h2>
        <div className="mb-4">
          <Command
            action={{
              label: "Náhled formuláře pro vybranou účast",
              fields: [
                arr("items", "Výběr", [
                  f("itemRef", "Označení účastníka", "text-input", {
                    value: "preview",
                  }),
                  f("activityId", "Aktivita", "text-input", {
                    required: true,
                    options: activities.map((a) => String(a.id)),
                    optionLabels: Object.fromEntries(
                      activities.map((a) => [String(a.id), String(a.name)]),
                    ),
                  }),
                  f("variantId", "Varianta", "text-input", {
                    required: true,
                    options: variants.map((v) => String(v.id)),
                    optionLabels: Object.fromEntries(
                      variants.map((v) => [String(v.id), String(v.name)]),
                    ),
                  }),
                  f("minor", "Nezletilý účastník", "boolean"),
                ]),
              ],
            }}
            path={`${root}/form-preview`}
          />
        </div>
        <ErrorMessage error={overview.error} />
        <div className="mb-5 flex flex-wrap gap-3">
          <Command
            action={{
              label: "Upravit koncept",
              method: "PUT",
              fields: [arr("fields", "Pole formuláře", formFieldFields)],
            }}
            path={`${root}/form/draft`}
            initial={initial}
            onDone={overview.refresh}
          />
          <Command
            action={{ label: "Publikovat verzi" }}
            path={`${root}/form/publish`}
            onDone={overview.refresh}
          />
        </div>
        <DataView value={overview.data} compact />
      </Box>
      <Box>
        <h2 className="mb-3 font-semibold">Historie formuláře</h2>
        <label className="mb-2 block text-sm" htmlFor="form-version">
          Číslo verze
        </label>
        <input
          id="form-version"
          className={`${input} mb-3 max-w-32`}
          type="number"
          min="1"
          value={number}
          onChange={(e) => setNumber(Number(e.target.value))}
        />
        <Command
          action={{ label: "Zobrazit verzi", read: true }}
          path={`${root}/form/versions/${number}`}
        />
      </Box>
    </div>
  );
}
export function Registrations({
  root,
  org,
  role,
}: {
  root: string;
  org: string;
  role: string;
}) {
  const [orderId, setOrderId] = useState("");
  const [lookup, setLookup] = useState("");
  return (
    <div className="space-y-6">
      <Resource
        role={role}
        onSelect={(row) => setLookup(String(row.orderId))}
        spec={{
          title: "Účastníci",
          selectLabel: "Spravovat objednávku",
          path: `${root}/participations`,
          filters: [
            f("q", "Hledat účastníka"),
            f("state", "Stav"),
            f("activityId", "ID aktivity"),
            f("page", "Stránka", "integer", { value: 1 }),
            f("limit", "Na stránku (max. 100)", "integer", { value: 25 }),
          ],
          actions: [
            { label: "Detail účasti", read: true },
            {
              label: "Objednávka",
              path: `${root}/orders/{orderId}`,
              read: true,
            },
            {
              label: "Zrušit účast",
              path: "/cancel",
              fields: reason,
              roles: managers,
              danger: true,
            },
            {
              label: "Obnovit účast",
              path: "/restore",
              fields: reason,
              roles: ["OWNER", "FINANCE"],
            },
            {
              label: "Finance objednávky",
              path: `${org}/orders/{orderId}/finance`,
              read: true,
            },
          ],
        }}
      />
      <Box>
        <form
          className="flex flex-wrap gap-3"
          onSubmit={(e) => {
            e.preventDefault();
            setLookup(orderId);
          }}
        >
          <label className="grow text-sm">
            ID objednávky
            <input
              required
              value={orderId}
              onChange={(e) => setOrderId(e.target.value)}
              className={`${input} mt-2`}
            />
          </label>
          <button className={`${secondary} self-end`}>
            Otevřít objednávku
          </button>
        </form>
      </Box>
      {lookup && (
        <>
          <Resource
            spec={{
              title: "Objednávka",
              path: `${root}/orders/${encodeURIComponent(lookup)}`,
            }}
          />
          <OrderFinance root={org} orderId={lookup} role={role} />
        </>
      )}
    </div>
  );
}
export function OrderFinance({
  root,
  orderId,
  role,
}: {
  root: string;
  orderId: string;
  role: string;
}) {
  const path = `${root}/orders/${encodeURIComponent(orderId)}`;
  const amount = required("amountMinor", "Částka v haléřích", "integer");
  return (
    <div className="space-y-6">
      <Resource
        role={role}
        spec={{
          title: "Finance objednávky",
          path: `${path}/finance`,
          actions: [
            {
              label: "Zapsat hotovost",
              path: `${path}/cash`,
              fields: [amount, f("note", "Poznámka")],
              roles: ["OWNER", "EVENT_MANAGER", "FINANCE"],
            },
            {
              label: "Ruční úhrada",
              path: `${path}/manual-payments`,
              fields: [amount, ...reason],
              roles: ["OWNER", "FINANCE"],
            },
          ],
        }}
      />
      <Resource
        role={role}
        spec={{
          title: "Účetní kniha",
          path: `${path}/ledger`,
          actions: [
            {
              label: "Opravit hotovost",
              path: "/correction",
              roles: ["OWNER", "FINANCE"],
              fields: [
                ...reason,
                f(
                  "replacementAmountMinor",
                  "Náhradní částka v haléřích (prázdné = storno)",
                  "integer",
                ),
              ],
              danger: true,
            },
          ],
        }}
      />
    </div>
  );
}
export function LegalWorkspace({ root }: { root: string }) {
  const documents = useData<{ items: Row[] }>(`${root}/legal/documents`);
  const [documentId, setDocumentId] = useState("");
  return (
    <div className="space-y-6">
      {organizerSpecs(root, "documents", "").map((spec) => (
        <Resource key={spec.path} spec={spec} />
      ))}
      <Box>
        <Command
          action={{
            label: "Náhled právních požadavků",
            read: true,
            fields: [
              f("eventId", "Akce", "text-input", {
                source: `${root}/events`,
                required: true,
              }),
              f("activityId", "ID aktivity (volitelné)"),
              f("variantId", "ID varianty (volitelné)"),
              f("minor", "Nezletilý účastník", "boolean"),
            ],
          }}
          path={`${root}/legal/preview`}
        />
      </Box>
      <Box>
        <h2 className="mb-4 font-semibold">Publikace verze dokumentu</h2>
        <label className="block text-sm">
          Dokument
          <select
            className={`${input} mt-2`}
            value={documentId}
            onChange={(e) => setDocumentId(e.target.value)}
          >
            <option value="">Vyberte dokument…</option>
            {documents.data?.items.map((d) => (
              <option key={String(d.id)} value={String(d.id)}>
                {String(d.title)}
              </option>
            ))}
          </select>
        </label>
        {documentId && (
          <DocumentVersions
            key={documentId}
            root={root}
            documentId={documentId}
          />
        )}
      </Box>
    </div>
  );
}
function DocumentVersions({
  root,
  documentId,
}: {
  root: string;
  documentId: string;
}) {
  const path = `${root}/legal/documents/${encodeURIComponent(documentId)}`;
  const result = useData<Row>(path);
  const versions = (result.data?.versions || []) as Row[];
  return (
    <div className="mt-5 space-y-4">
      <ErrorMessage error={result.error} />
      {versions.map((v) => (
        <div
          key={String(v.id)}
          className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-stone-200 p-4"
        >
          <span className="text-sm">
            Verze {String(v.number)} ·{" "}
            {v.publishedAt ? "Publikována" : "Koncept"}
          </span>
          <div className="flex flex-wrap gap-2">
            <Command
              action={{ label: "Zobrazit verzi", read: true }}
              path={`${path}/versions/${encodeURIComponent(String(v.id))}`}
            />
            {!v.publishedAt && (
              <Command
                action={{
                  label: "Publikovat verzi",
                  fields: [
                    f(
                      "requiresReconfirmation",
                      "Vyžádat nové potvrzení",
                      "boolean",
                    ),
                  ],
                }}
                path={`${path}/versions/${encodeURIComponent(String(v.id))}/publish`}
                onDone={result.refresh}
              />
            )}
          </div>
        </div>
      ))}
    </div>
  );
}
export function Attention({ slug, role }: { slug: string; role: string }) {
  const root = `${orgRoot(slug)}/attention`;
  const result = useData<{ items: Row[] }>(root);
  const [selected, setSelected] = useState("");
  return (
    <Box>
      <h2 className="mb-4 font-semibold">Vyžaduje pozornost</h2>
      <ErrorMessage error={result.error} />
      {result.data?.items.length === 0 && (
        <p className="text-sm text-stone-500">Vše je vyřízené.</p>
      )}
      {result.data?.items.map((item) => (
        <button
          key={String(item.id)}
          className={`${secondary} mb-2 mr-2`}
          onClick={() => setSelected(String(item.id))}
        >
          {String(item.type)} · {String(item.state)}
        </button>
      ))}
      {selected && (
        <AttentionDetail
          path={`${root}/${encodeURIComponent(selected)}`}
          role={role}
          refresh={result.refresh}
        />
      )}
    </Box>
  );
}
function AttentionDetail({
  path,
  role,
  refresh,
}: {
  path: string;
  role: string;
  refresh: () => void;
}) {
  const result = useData<Row>(path);
  const actions = Array.isArray(result.data?.actions)
    ? (result.data.actions as Row[])
    : [];
  return (
    <div className="mt-4 space-y-4">
      <ErrorMessage error={result.error} />
      <DataView value={result.data} compact />
      {actions
        .filter((a) => a.kind === "link")
        .map((a, i) => {
          const target = a.target as Row | null;
          const resource = String(target?.resource || "");
          const targetModule =
            resource === "participation"
              ? "registrations"
              : ["delivery", "campaign"].includes(resource)
                ? "communication"
                : resource === "confirmation"
                  ? "content"
                  : "payments";
          const slug = path.split("/")[4];
          return (
            <div key={i} className="rounded-xl border border-stone-200 p-3">
              <Link
                className="text-sm text-lime-900 underline"
                href={`/org/${slug}/${targetModule}`}
              >
                Otevřít související záznam: {String(a.key)}
              </Link>
              <p className="mt-1 break-all text-xs text-stone-500">
                {String(target?.id || "")}
              </p>
            </div>
          );
        })}

      {actions
        .filter(
          (a) =>
            a.kind === "command" &&
            (!["resolve", "dismiss"].includes(String(a.key)) ||
              ["OWNER", "FINANCE"].includes(role)),
        )
        .map((a, i) => (
          <Command
            key={i}
            path={`${path}/actions/${encodeURIComponent(String(a.key))}`}
            action={{
              label: String(a.label ?? a.key),
              fields: [f("note", "Poznámka")],
            }}
            onDone={() => {
              result.refresh();
              refresh();
            }}
          />
        ))}
    </div>
  );
}
const moduleTitles: Record<string, string> = {
  registrations: "Registrace",
  payments: "Platby",
  communication: "Komunikace",
  "check-in": "Odbavení",
  documents: "Dokumenty a souhlasy",
  team: "Tým a oprávnění",
  settings: "Nastavení organizátora",
  content: "Obsah po akci",
  audit: "Historie změn",
};
export function ModuleScreen({
  orgSlug,
  module,
}: {
  orgSlug: string;
  module: string;
}) {
  const role = useRole(orgSlug);
  const [eventId, setEventId] = useState("");
  const root = orgRoot(orgSlug);
  const [order, setOrder] = useState("");
  const [paymentRequest, setPaymentRequest] = useState("");
  const restricted =
    (["team", "settings"].includes(module) && role !== "OWNER") ||
    (["communication", "content"].includes(module) && !managers.includes(role));
  if (!role) return <p role="status">Načítání účtu…</p>;
  return (
    <>
      <PageHeader
        eyebrow="PRACOVNÍ PROSTOR"
        title={moduleTitles[module] || module}
        description="Spravujte data svého organizátora."
      />
      {restricted ? (
        <Box>Tato sekce není pro vaši roli dostupná.</Box>
      ) : (
        <div className="space-y-6">
          {["registrations", "check-in", "content"].includes(module) && (
            <EventPicker slug={orgSlug} value={eventId} onChange={setEventId} />
          )}{" "}
          {module === "registrations" && eventId && (
            <Registrations
              root={`${root}/events/${encodeURIComponent(eventId)}`}
              org={root}
              role={role}
            />
          )}{" "}
          {module === "check-in" && eventId && (
            <CheckIn slug={orgSlug} eventId={eventId} role={role} />
          )}{" "}
          {module === "content" && eventId && (
            <>
              <Link
                className={secondary}
                href={`/org/${encodeURIComponent(orgSlug)}/events/${encodeURIComponent(eventId)}`}
              >
                Otevřít celou správu akce
              </Link>
              {eventSpecs(
                `${root}/events/${encodeURIComponent(eventId)}`,
                "Obsah",
              ).map((spec) => (
                <Resource key={spec.path} spec={spec} />
              ))}
              <Command
                action={{ label: "Připravit závěrečnou zprávu" }}
                path={`${root}/events/${encodeURIComponent(eventId)}/closing-message`}
              />
            </>
          )}
          {module === "documents" ? (
            <LegalWorkspace root={root} />
          ) : (
            organizerSpecs(root, module, eventId).map((spec) => (
              <Resource key={spec.path} spec={spec} role={role} />
            ))
          )}
          {module === "payments" && (
            <>
              <Box>
                <form
                  onSubmit={(e) => {
                    e.preventDefault();
                    setOrder(
                      String(new FormData(e.currentTarget).get("order")),
                    );
                  }}
                  className="flex flex-wrap items-end gap-3"
                >
                  <label className="grow text-sm">
                    ID objednávky
                    <input required name="order" className={`${input} mt-2`} />
                  </label>
                  <button className={secondary}>Otevřít finance</button>
                </form>
              </Box>
              {order && (
                <OrderFinance
                  key={order}
                  root={root}
                  orderId={order}
                  role={role}
                />
              )}
              <Box>
                <label className="mb-3 block text-sm">
                  ID platebního požadavku
                  <input
                    className={`${input} mt-2`}
                    value={paymentRequest}
                    onChange={(e) => setPaymentRequest(e.target.value)}
                  />
                </label>
                {paymentRequest && (
                  <Command
                    action={{ label: "Znovu vytvořit platební pokyny" }}
                    path={`${root}/payment-requests/${encodeURIComponent(paymentRequest)}/retry`}
                  />
                )}
              </Box>
            </>
          )}
          {module === "communication" && (
            <Box>
              <div className="flex flex-wrap gap-3">
                <Command
                  action={{
                    label: "Náhled kampaně a počtu příjemců",
                    fields: [
                      f("eventId", "Akce", "text-input", {
                        source: `${root}/events`,
                        required: true,
                      }),
                      ...campaignFields,
                    ],
                  }}
                  path={`${root}/campaigns/preview`}
                />
                <Command
                  action={{
                    label: "Náhled zprávy",
                    fields: [
                      choice("locale", "Jazyk", ["cs", "en", "de"], "cs"),
                      required("subject", "Předmět"),
                      required("body", "Text", "text"),
                    ],
                  }}
                  path={`${root}/message-templates/preview`}
                />
                <Command
                  action={{ label: "Dostupné proměnné", read: true }}
                  path={`${root}/message-templates/variables`}
                />
              </div>
            </Box>
          )}
          {module === "audit" && (
            <Box>
              <Command
                action={{
                  label: "Historie konkrétního záznamu",
                  read: true,
                  fields: [
                    required("entityType", "Typ záznamu"),
                    required("entityId", "ID záznamu"),
                    f("page", "Stránka", "integer", { value: 1 }),
                  ],
                }}
                path={`${root}/audit`}
              />
            </Box>
          )}
        </div>
      )}
    </>
  );
}
function CheckIn({
  slug,
  eventId,
  role,
}: {
  slug: string;
  eventId: string;
  role: string;
}) {
  const root = `/api/check-in/org/${encodeURIComponent(slug)}/events/${encodeURIComponent(eventId)}`;
  const lists = useData<{ items: Row[] }>(`${root}/lists`);
  const [list, setList] = useState("");
  const [checkInId, setCheckInId] = useState("");
  const participant = [
    f("qr", "QR obsah"),
    f("code", "Registrační kód"),
    f("participationId", "ID účasti"),
  ];
  return (
    <div className="space-y-6">
      <Resource
        spec={{ title: "Přehled odbavení", path: `${root}/overview` }}
      />
      <Box>
        <label className="text-sm" htmlFor="check-in-list">
          Odbavovací seznam
        </label>
        <select
          id="check-in-list"
          className={`${input} mt-2`}
          value={list}
          onChange={(e) => setList(e.target.value)}
        >
          <option value="">Vyberte seznam…</option>
          {lists.data?.items.map((l) => (
            <option key={String(l.id)} value={String(l.id)}>
              {String(l.name)} · {String(l.checkedIn)}/{String(l.expected)}
            </option>
          ))}
        </select>
        <ErrorMessage error={lists.error} />
      </Box>
      {list && (
        <>
          <Box>
            <div className="flex flex-wrap gap-3">
              <Command
                action={{
                  label: "Vyhledat podle QR nebo kódu",
                  fields: participant,
                }}
                path={`${root}/lists/${encodeURIComponent(list)}/lookup`}
              />
              {["OWNER", "EVENT_MANAGER", "CHECK_IN"].includes(role) && (
                <Command
                  action={{
                    label: "Odbavit účastníka",
                    fields: [
                      ...participant,
                      f(
                        "acknowledgeWarning",
                        "Potvrzuji zobrazené upozornění",
                        "boolean",
                      ),
                      f("stationId", "ID stanoviště"),
                    ],
                  }}
                  path={`${root}/lists/${encodeURIComponent(list)}/check-ins`}
                />
              )}{" "}
              {managers.includes(role) && (
                <Command
                  action={{
                    label: "Hotovost a odbavení",
                    fields: [
                      ...participant,
                      required("amountMinor", "Hotovost v haléřích", "integer"),
                      f("note", "Poznámka"),
                      f("stationId", "ID stanoviště"),
                    ],
                  }}
                  path={`${root}/lists/${encodeURIComponent(list)}/cash-check-ins`}
                />
              )}
              <Command
                action={{
                  label: "Předat k řešení",
                  fields: [...participant, ...reason],
                }}
                path={`${root}/lists/${encodeURIComponent(list)}/escalations`}
              />
            </div>
            <p className="mt-4 text-xs text-stone-500">
              Vyplňte jeden identifikátor: QR obsah, registrační kód nebo ID
              účasti. Upozornění potvrďte až po jeho přečtení ve výsledku
              vyhledání.
            </p>
          </Box>
          <Resource
            spec={{
              title: "Účastníci seznamu",
              path: `${root}/lists/${encodeURIComponent(list)}/participants`,
              filters: [f("q", "Jméno nebo registrační kód")],
            }}
          />
        </>
      )}
      {managers.includes(role) && (
        <Box>
          <label className="mb-3 block text-sm">
            ID záznamu odbavení
            <input
              className={`${input} mt-2`}
              value={checkInId}
              onChange={(e) => setCheckInId(e.target.value)}
            />
          </label>
          {checkInId && (
            <div className="flex gap-3">
              <Command
                action={{
                  label: "Zneplatnit odbavení",
                  fields: reason,
                  danger: true,
                }}
                path={`${root}/check-ins/${encodeURIComponent(checkInId)}/void`}
              />
              <Command
                action={{
                  label: "Opravit odbavení",
                  fields: [...reason, f("stationId", "ID stanoviště")],
                }}
                path={`${root}/check-ins/${encodeURIComponent(checkInId)}/correct`}
              />
            </div>
          )}
        </Box>
      )}
    </div>
  );
}
