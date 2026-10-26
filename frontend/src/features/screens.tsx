"use client";

import Link from "next/link";
import { useState } from "react";
import type { AccountProfile, EventDetail, EventListItem } from "@/api/client";
import { PageHeader } from "@/components/ui";
import {
  Box,
  Command,
  DataView,
  ErrorMessage,
  Resource,
  SectionTabs,
  input,
  secondary,
  useData,
  type Action,
  type Row,
} from "./workspace-ui";
import {
  activityFields,
  createEventFields,
  eventFields,
  eventSpecs,
  f,
  managers,
  paymentRules,
  reason,
  registrationActions,
  variantFields,
} from "./workspace-config";
import {
  Attention,
  FormEditor,
  LegalWorkspace,
  Registrations,
} from "./workspace-modules";
export { ModuleScreen } from "./workspace-modules";
export const orgRoot = (slug: string) =>
  `/api/admin/org/${encodeURIComponent(slug)}`;
export function useRole(slug: string) {
  const me = useData<AccountProfile>("/api/auth/me");
  return me.data?.memberships.find((m) => m.slug === slug)?.role || "";
}
export function EventPicker({
  slug,
  value,
  onChange,
}: {
  slug: string;
  value: string;
  onChange: (s: string) => void;
}) {
  const data = useData<{ items: EventListItem[] }>(`${orgRoot(slug)}/events`);
  return (
    <div className="mb-6 max-w-md">
      <label className="mb-2 block text-sm font-medium" htmlFor="event-picker">
        Vyberte akci
      </label>
      <select
        id="event-picker"
        value={value}
        className={input}
        onChange={(e) => onChange(e.target.value)}
      >
        <option value="">Vyberte akci…</option>
        {data.data?.items.map((e) => (
          <option key={e.id} value={e.id}>
            {e.name}
          </option>
        ))}
      </select>
      <ErrorMessage error={data.error} />
      {data.data?.items.length === 0 && (
        <p className="mt-2 text-sm text-stone-500">
          Nejprve{" "}
          <Link
            className="underline"
            href={`/org/${encodeURIComponent(slug)}/events`}
          >
            vytvořte akci
          </Link>
          .
        </p>
      )}
    </div>
  );
}
export function OverviewScreen({ orgSlug }: { orgSlug: string }) {
  const role = useRole(orgSlug);
  return (
    <>
      <PageHeader
        eyebrow="VÁŠ PRACOVNÍ PROSTOR"
        title="Vítejte v Eventoru"
        description="Vaše akce a vše, co právě potřebuje pozornost."
      />
      <EventsScreen orgSlug={orgSlug} compact />
      {role && role !== "CHECK_IN" && (
        <div className="mt-6">
          <Attention slug={orgSlug} role={role} />
        </div>
      )}
    </>
  );
}
export function EventsScreen({
  orgSlug,
  compact = false,
}: {
  orgSlug: string;
  compact?: boolean;
}) {
  const role = useRole(orgSlug);
  const result = useData<{ items: EventListItem[] }>(
    `${orgRoot(orgSlug)}/events`,
  );
  const [q, setQ] = useState("");
  const [state, setState] = useState("");
  const rows = result.data?.items.filter(
    (e) =>
      e.name.toLocaleLowerCase("cs").includes(q.toLocaleLowerCase("cs")) &&
      (!state || e.publicationState === state),
  );
  return (
    <>
      {!compact && (
        <PageHeader
          eyebrow="UDÁLOSTI"
          title="Vaše akce"
          description="Od prvního nápadu přes registrace až po společné vzpomínky."
        />
      )}
      <div className="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div className="flex flex-wrap gap-3">
          <input
            className={`${input} sm:w-72`}
            aria-label="Hledat akci"
            placeholder="Hledat akci…"
            value={q}
            onChange={(e) => setQ(e.target.value)}
          />
          <select
            aria-label="Stav akce"
            className={`${input} sm:w-48`}
            value={state}
            onChange={(e) => setState(e.target.value)}
          >
            <option value="">Všechny stavy</option>
            {["draft", "published", "completed", "cancelled", "archived"].map(
              (s) => (
                <option key={s}>{s}</option>
              ),
            )}
          </select>
        </div>
        {managers.includes(role) && (
          <Command
            action={{ label: "Vytvořit akci", fields: createEventFields }}
            path={`${orgRoot(orgSlug)}/events`}
            onDone={result.refresh}
          />
        )}
      </div>
      <ErrorMessage error={result.error} />
      {!result.data && !result.error && <p role="status">Načítání akcí…</p>}
      {rows?.length === 0 && (
        <Box>
          <h2 className="font-semibold">
            {q || state
              ? "Žádná odpovídající akce"
              : "Tady začíná vaše první akce"}
          </h2>
          <p className="mt-2 text-sm text-stone-500">
            {q || state
              ? "Změňte hledání nebo filtr."
              : "Vytvořte akci a nastavte program, registrace a kapacity."}
          </p>
        </Box>
      )}
      <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        {rows?.map((e) => (
          <Link
            key={e.id}
            className="rounded-2xl border border-stone-200 bg-white p-6 transition hover:border-lime-700"
            href={`/org/${encodeURIComponent(orgSlug)}/events/${e.id}`}
          >
            <div className="mb-5 flex flex-wrap gap-2 text-xs text-stone-500">
              <span className="rounded-full bg-lime-50 px-3 py-1">
                {e.publicationState}
              </span>
              <span className="rounded-full bg-stone-100 px-3 py-1">
                {e.registrationState}
              </span>
            </div>
            <h2 className="text-lg font-semibold">{e.name}</h2>
            <p className="mt-2 text-sm text-stone-500">{e.shortDescription}</p>
            <p className="mt-6 text-xs text-stone-500">
              <DataView value={e.startsAt} />
            </p>
          </Link>
        ))}
      </div>
    </>
  );
}
export function EventDetailScreen({
  orgSlug,
  eventId,
}: {
  orgSlug: string;
  eventId: string;
}) {
  const role = useRole(orgSlug);
  const root = `${orgRoot(orgSlug)}/events/${encodeURIComponent(eventId)}`;
  const result = useData<EventDetail>(root);
  const [tab, setTab] = useState("Přehled");
  const event = result.data;
  const editable = managers.includes(role);
  if (result.error) return <ErrorMessage error={result.error} />;
  if (!event) return <p role="status">Načítání akce…</p>;
  const tabs = editable
    ? [
        "Přehled",
        "Aktivity",
        "Program",
        "Formulář",
        "Kapacity",
        "Platby",
        "Registrace",
        "Obsah",
        "Dokumenty",
      ]
    : ["Přehled", "Kapacity", ...(role !== "CHECK_IN" ? ["Registrace"] : [])];
  const validTransitions: Record<string, string[]> = {
    not_open: ["open"],
    open: ["pause", "close"],
    paused: ["resume", "close"],
    closed: [],
  };
  const allowedLifecycle = (action: Action) => {
    if (!action.roles?.includes(role)) return false;
    if (action.path?.startsWith("/registration/"))
      return (validTransitions[event.registrationState] || []).includes(
        action.path.split("/").at(-1)!,
      );
    if (action.path === "/publish") return event.publicationState === "draft";
    if (action.path === "/complete")
      return event.publicationState === "published";
    if (action.path === "/archive")
      return ["completed", "cancelled"].includes(event.publicationState);
    if (action.path === "/cancel")
      return ["draft", "published"].includes(event.publicationState);
    return true;
  };
  const lifecycle: Action[] = [
    { label: "Publikovat akci", path: "/publish", roles: managers },
    ...registrationActions,
    { label: "Dokončit akci", path: "/complete", roles: managers },
    { label: "Archivovat akci", path: "/archive", roles: managers },
    {
      label: "Zrušit akci",
      path: "/cancel",
      fields: [
        ...reason,
        f("confirmed", "Potvrzuji zrušení celé akce", "boolean"),
      ],
      danger: true,
      roles: ["OWNER"],
    },
  ];
  return (
    <>
      <Link
        className="mb-5 inline-block text-sm text-stone-500 underline"
        href={`/org/${encodeURIComponent(orgSlug)}/events`}
      >
        ← Všechny akce
      </Link>
      <PageHeader
        eyebrow={`${event.publicationState} · ${event.registrationState}`}
        title={event.name}
        description={event.shortDescription || "Správa akce"}
      />
      <div className="mb-6 flex flex-wrap gap-2">
        {lifecycle.filter(allowedLifecycle).map((a) => (
          <Command
            key={a.path}
            action={a}
            path={root + a.path}
            initial={event as unknown as Row}
            onDone={result.refresh}
          />
        ))}
        <Link
          className={secondary}
          href={`/events/${encodeURIComponent(orgSlug)}/${encodeURIComponent(event.slug)}`}
        >
          Veřejná stránka akce
        </Link>
      </div>
      <SectionTabs tabs={tabs} value={tab} onChange={setTab} />
      <div className="space-y-6">
        {tab === "Přehled" && (
          <Box>
            <div className="mb-5 flex justify-end">
              {editable && (
                <Command
                  action={{
                    label: "Upravit akci",
                    method: "PUT",
                    fields: eventFields,
                    version: true,
                  }}
                  path={root}
                  initial={{
                    ...event,
                    contactName: event.contact.name,
                    contactEmail: event.contact.email,
                    contactPhone: event.contact.phone,
                  }}
                  onDone={result.refresh}
                />
              )}
            </div>
            <DataView
              value={{
                description: event.description,
                startsAt: event.startsAt,
                endsAt: event.endsAt,
                timezone: event.timezone,
                contact: event.contact,
                venues: event.venues,
              }}
              compact
            />
          </Box>
        )}
        {tab === "Aktivity" && (
          <>
            <Command
              action={{ label: "Přidat aktivitu", fields: activityFields }}
              path={`${root}/activities`}
              onDone={result.refresh}
            />
            {event.activities.map((a) => (
              <Box key={a.id}>
                <div className="mb-5 flex flex-wrap items-center justify-between gap-3">
                  <h2 className="font-semibold">{a.name}</h2>
                  <div className="flex flex-wrap gap-2">
                    <Command
                      action={{
                        label: "Upravit aktivitu",
                        method: "PUT",
                        fields: activityFields,
                        version: true,
                      }}
                      initial={a as unknown as Row}
                      path={`${root}/activities/${a.id}`}
                      onDone={result.refresh}
                    />
                    {!a.implicit && (
                      <Command
                        action={{
                          label: "Odebrat aktivitu",
                          method: "DELETE",
                          danger: true,
                        }}
                        path={`${root}/activities/${a.id}`}
                        onDone={result.refresh}
                      />
                    )}
                  </div>
                </div>
                <DataView
                  value={{
                    description: a.description,
                    startsAt: a.startsAt,
                    endsAt: a.endsAt,
                    registrationState: a.registrationState,
                  }}
                  compact
                />
                <div className="my-4 flex flex-wrap gap-2">
                  {registrationActions.map((action) => (
                    <Command
                      key={action.path}
                      action={action}
                      initial={a as unknown as Row}
                      path={`${root}/activities/${a.id}${action.path}`}
                      onDone={result.refresh}
                    />
                  ))}
                </div>
                <Command
                  action={{ label: "Přidat variantu", fields: variantFields }}
                  path={`${root}/activities/${a.id}/variants`}
                  onDone={result.refresh}
                />
                <div className="mt-4 space-y-3">
                  {a.variants.map((v) => (
                    <div
                      key={v.id}
                      className="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-stone-50 p-4"
                    >
                      <div>
                        <strong className="text-sm">{v.name}</strong>
                        <p className="text-sm">
                          <DataView value={v.price} />
                        </p>
                      </div>
                      <div className="flex gap-2">
                        <Command
                          action={{
                            label: "Upravit variantu",
                            method: "PUT",
                            fields: variantFields,
                            version: true,
                          }}
                          path={`${root}/variants/${v.id}`}
                          initial={v as unknown as Row}
                          onDone={result.refresh}
                        />
                        <Command
                          action={{
                            label: "Odebrat variantu",
                            method: "DELETE",
                            danger: true,
                          }}
                          path={`${root}/variants/${v.id}`}
                          onDone={result.refresh}
                        />
                      </div>
                    </div>
                  ))}
                </div>
              </Box>
            ))}
          </>
        )}
        {["Program", "Kapacity", "Obsah"].includes(tab) &&
          eventSpecs(root, tab).map((spec) => (
            <Resource
              key={spec.path}
              spec={
                tab === "Kapacity" && !editable
                  ? { ...spec, create: undefined }
                  : spec
              }
              role={role}
              onChanged={result.refresh}
            />
          ))}
        {tab === "Formulář" && <FormEditor root={root} />}{" "}
        {tab === "Platby" && (
          <Box>
            <div className="mb-5">
              <Command
                action={{
                  label: "Nastavit platební pravidla",
                  method: "PUT",
                  fields: paymentRules,
                  version: true,
                }}
                path={`${root}/payment-rules`}
                initial={{ ...event.paymentRules, version: event.version }}
                onDone={result.refresh}
              />
            </div>
            <DataView value={event.paymentRules} compact />
          </Box>
        )}
        {tab === "Registrace" && (
          <Registrations root={root} org={orgRoot(orgSlug)} role={role} />
        )}{" "}
        {tab === "Obsah" && (
          <Box>
            <h2 className="mb-3 font-semibold">Poděkování účastníkům</h2>
            <p className="mb-4 text-sm text-stone-500">
              Vytvořte koncept závěrečné zprávy. Před odesláním jej upravte a
              zkontrolujte v Komunikaci.
            </p>
            <Command
              action={{ label: "Připravit závěrečnou zprávu" }}
              path={`${root}/closing-message`}
            />
          </Box>
        )}
        {tab === "Dokumenty" && <LegalWorkspace root={orgRoot(orgSlug)} />}
      </div>
    </>
  );
}
