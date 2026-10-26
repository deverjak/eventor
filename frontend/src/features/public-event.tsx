"use client";
import Link from "next/link";
import { useRef, useState, type FormEvent } from "react";
import { request } from "@/api/client";
import { PublicContent } from "./public-content";
import {
  Box,
  DataView,
  ErrorMessage,
  Fields,
  button,
  input,
  secondary,
  useData,
  type Field,
  type Row,
} from "./workspace-ui";

type PreviewField = {
  key: string;
  label: string;
  type: string;
  required: boolean;
};
type Requirement = {
  requirementId: string;
  documentId: string;
  documentVersionId: string;
  title: string;
  purpose: string;
  mandatory: boolean;
};
type PreviewItem = {
  itemRef: string;
  fields: PreviewField[];
  requirements: Requirement[];
};
type Preview = {
  orderFields: PreviewField[];
  orderRequirements: Requirement[];
  items: PreviewItem[];
  formToken: string;
};
type Selection = {
  itemRef: string;
  activityId: string;
  variantId: string;
  minor: boolean;
};

function answerFields(
  fields: PreviewField[],
  definitions: Row[] = [],
  answers: Row = {},
): Field[] {
  return fields
    .filter((f) => !["info_text", "legal_requirement"].includes(f.type))
    .filter((f) => {
      const definition = definitions.find((d) => d.key === f.key);
      const condition = definition?.condition as Row | null;
      if (!condition || condition.type !== "answer") return true;
      const value = answers[String(condition.field)];
      const filled =
        value !== undefined &&
        value !== null &&
        value !== "" &&
        (!Array.isArray(value) || value.length > 0);
      if (condition.operator === "empty") return !filled;
      if (condition.operator === "filled") return filled;
      const equals = Array.isArray(value)
        ? value.includes(condition.value)
        : value === condition.value;
      return condition.operator === "not_equals" ? !equals : equals;
    })
    .map((f) => {
      const definition = definitions.find((d) => d.key === f.key);
      const constraints = definition?.constraints as Row | undefined;
      const choices = (constraints?.choices || []) as Row[];
      return {
        ...f,
        options: choices.length
          ? choices.map((c) => String(c.value))
          : undefined,
        optionLabels: Object.fromEntries(
          choices.map((c) => [String(c.value), String(c.label)]),
        ),
        type:
          (
            {
              short_text: "text-input",
              long_text: "text",
              number: "number",
              multi_choice: "multiselect",
              yes_no: "boolean",
              phone: "tel",
            } as Record<string, string>
          )[f.type] || f.type,
      };
    });
}
export function PublicEvent({
  orgSlug,
  eventSlug,
}: {
  orgSlug: string;
  eventSlug: string;
}) {
  const root = `/api/public/orgs/${encodeURIComponent(orgSlug)}/events/${encodeURIComponent(eventSlug)}`;
  const result = useData<Row>(root);
  const [items, setItems] = useState<Selection[]>([]);
  const [preview, setPreview] = useState<Preview>();
  const [answers, setAnswers] = useState<Record<string, Row>>({ order: {} });
  const [decisions, setDecisions] = useState<Record<string, boolean>>({});
  const [method, setMethod] = useState("");
  const [error, setError] = useState<unknown>();
  const [busy, setBusy] = useState(false);
  const [order, setOrder] = useState<Row>();
  const intent = useRef<{ body: string; key: string } | null>(null);
  const event = result.data;
  const activities = (event?.activities || []) as Row[];
  const definitions = ((event?.form as Row)?.fields || []) as Row[];
  async function prepare() {
    setBusy(true);
    setError(undefined);
    try {
      setPreview(
        await request<Preview>(`${root}/registration-form`, {
          method: "POST",
          body: { items },
        }),
      );
    } catch (err) {
      setError(err);
    } finally {
      setBusy(false);
    }
  }
  const decisionKey = (req: Requirement, person: string) =>
    `${person}:${req.requirementId}`;
  const requirementElements = (requirements: Requirement[], person: string) =>
    requirements.map((r) => (
      <label
        key={r.requirementId}
        className="block rounded-xl border border-stone-200 p-4 text-sm"
      >
        <input
          type="checkbox"
          className="mr-3 accent-lime-800"
          required={r.mandatory}
          checked={decisions[decisionKey(r, person)] || false}
          onChange={(e) =>
            setDecisions({
              ...decisions,
              [decisionKey(r, person)]: e.target.checked,
            })
          }
        />
        {r.title}
        {r.mandatory ? " *" : " (dobrovolné)"}
        <span className="mt-2 block text-xs text-stone-500">{r.purpose}</span>
        <a
          className="mt-2 inline-block text-lime-800 underline"
          href={`/api/public/orgs/${encodeURIComponent(orgSlug)}/legal/versions/${r.documentVersionId}/content`}
          target="_blank"
          rel="noreferrer"
        >
          Přečíst dokument
        </a>
      </label>
    ));
  async function submit(e: FormEvent) {
    e.preventDefault();
    if (!preview) return;
    setBusy(true);
    setError(undefined);
    const visibleAnswers = (fields: PreviewField[], value: Row) =>
      Object.fromEntries(
        answerFields(fields, definitions, value)
          .filter((field) => value[field.key] !== undefined)
          .map((field) => [field.key, value[field.key]]),
      );
    const body = {
      items: items.map((i) => ({ ...i, personRef: i.itemRef })),
      persons: items.map((i) => ({
        personRef: i.itemRef,
        answers: visibleAnswers(
          preview.items.find((item) => item.itemRef === i.itemRef)?.fields ||
            [],
          answers[i.itemRef] || {},
        ),
      })),
      orderAnswers: visibleAnswers(preview.orderFields, answers.order || {}),
      decisions: [
        ...preview.orderRequirements.map((r) => ({
          requirementId: r.requirementId,
          documentVersionId: r.documentVersionId,
          decision: decisions[decisionKey(r, "order")] ? "granted" : "declined",
        })),
        ...preview.items.flatMap((i) =>
          i.requirements.map((r) => ({
            requirementId: r.requirementId,
            documentVersionId: r.documentVersionId,
            personRef: i.itemRef,
            itemRef: i.itemRef,
            decision: decisions[decisionKey(r, i.itemRef)]
              ? "granted"
              : "declined",
          })),
        ),
      ],
      paymentMethod: method || null,
      locale: "cs",
      website: "",
      formToken: preview.formToken,
    };
    const serialized = JSON.stringify(body);
    if (!intent.current || intent.current.body !== serialized)
      intent.current = { body: serialized, key: crypto.randomUUID() };
    try {
      setOrder(
        await request<Row>(`${root}/orders`, {
          method: "POST",
          body,
          idempotencyKey: intent.current.key,
        }),
      );
      intent.current = null;
    } catch (err) {
      setError(err);
    } finally {
      setBusy(false);
    }
  }
  return (
    <main className="mx-auto max-w-4xl space-y-6 px-5 py-10">
      <div className="flex justify-between">
        <Link href="/" className="text-2xl font-bold">
          eventor.
        </Link>
        <Link href="/login" className="text-sm underline">
          Pro pořadatele
        </Link>
      </div>
      <ErrorMessage error={result.error} />
      {!event && !result.error && <p>Načítání akce…</p>}
      {event && (
        <>
          <Box>
            <p className="mb-3 text-xs tracking-widest text-stone-500">
              {orgSlug}
            </p>
            <h1 className="text-3xl font-semibold">{String(event.name)}</h1>
            <p className="my-5 whitespace-pre-wrap text-stone-600">
              {String(event.description || event.shortDescription || "")}
            </p>
            <DataView
              value={{
                startsAt: event.startsAt,
                endsAt: event.endsAt,
                venues: event.venues,
                contact: event.contact,
              }}
              compact
            />
          </Box>
          {Array.isArray(event.program) && event.program.length > 0 && (
            <Box>
              <h2 className="mb-4 text-xl font-semibold">Program</h2>
              <DataView value={event.program} />
            </Box>
          )}
          {Array.isArray(event.news) && event.news.length > 0 && (
            <Box>
              <h2 className="mb-4 text-xl font-semibold">Aktuality</h2>
              <DataView value={event.news} />
            </Box>
          )}
          {order ? (
            <Box>
              <h2 className="mb-4 text-xl font-semibold">
                Registrace byla přijata
              </h2>
              <DataView
                value={{
                  orderNumber: order.orderNumber,
                  payment: order.payment,
                }}
              />
              {typeof order.selfServiceUrl === "string" && (
                <Link
                  className={`${button} mt-5`}
                  href={selfServiceLink(order.selfServiceUrl)}
                >
                  Spravovat registraci a platbu
                </Link>
              )}
            </Box>
          ) : (
            <Box>
              <h2 className="mb-5 text-xl font-semibold">
                Přihlásit se na akci
              </h2>
              {event.registrationState !== "open" && (
                <p className="mb-4 text-sm text-amber-800">
                  Registrace nyní nejsou otevřené.
                </p>
              )}
              {!preview ? (
                <div className="space-y-5">
                  {items.map((item, index) => {
                    const activity = activities.find(
                      (a) => a.id === item.activityId,
                    );
                    const variants = (activity?.variants || []) as Row[];
                    const update = (values: Partial<Selection>) =>
                      setItems(
                        items.map((old, i) =>
                          index === i ? { ...old, ...values } : old,
                        ),
                      );
                    return (
                      <div
                        key={item.itemRef}
                        className="space-y-3 rounded-xl border border-stone-200 p-4"
                      >
                        <p className="font-medium">Účastník {index + 1}</p>
                        <label className="block text-sm">
                          Aktivita
                          <select
                            className={`${input} mt-2`}
                            value={item.activityId}
                            onChange={(e) =>
                              update({
                                activityId: e.target.value,
                                variantId: "",
                              })
                            }
                          >
                            <option value="">Vyberte…</option>
                            {activities.map((a) => (
                              <option key={String(a.id)} value={String(a.id)}>
                                {String(a.name)}
                              </option>
                            ))}
                          </select>
                        </label>
                        <label className="block text-sm">
                          Varianta
                          <select
                            className={`${input} mt-2`}
                            value={item.variantId}
                            onChange={(e) =>
                              update({ variantId: e.target.value })
                            }
                          >
                            <option value="">Vyberte…</option>
                            {variants.map((v) => (
                              <option key={String(v.id)} value={String(v.id)}>
                                {String(v.name)} ·{" "}
                                {String(
                                  (v.price as Row)?.formatted ||
                                    `${Number((v.price as Row)?.minor) / 100} Kč`,
                                )}
                              </option>
                            ))}
                          </select>
                        </label>
                        <label className="block text-sm">
                          <input
                            type="checkbox"
                            className="mr-2"
                            checked={item.minor}
                            onChange={(e) =>
                              update({ minor: e.target.checked })
                            }
                          />
                          Nezletilý účastník
                        </label>
                        <button
                          className={secondary}
                          onClick={() =>
                            setItems(items.filter((_, i) => i !== index))
                          }
                        >
                          Odebrat
                        </button>
                      </div>
                    );
                  })}
                  <div className="flex flex-wrap gap-3">
                    <button
                      className={secondary}
                      onClick={() =>
                        setItems([
                          ...items,
                          {
                            itemRef: crypto.randomUUID(),
                            activityId: "",
                            variantId: "",
                            minor: false,
                          },
                        ])
                      }
                    >
                      Přidat účastníka
                    </button>
                    <button
                      className={button}
                      disabled={
                        busy ||
                        !items.length ||
                        items.some((i) => !i.activityId || !i.variantId) ||
                        event.registrationState !== "open"
                      }
                      onClick={prepare}
                    >
                      {busy ? "Načítání…" : "Pokračovat k údajům"}
                    </button>
                  </div>
                </div>
              ) : (
                <form onSubmit={submit} className="space-y-6">
                  <fieldset disabled={busy} className="space-y-6">
                    <h3 className="font-semibold">Objednávající</h3>
                    <Fields
                      fields={answerFields(
                        preview.orderFields,
                        definitions,
                        answers.order || {},
                      )}
                      value={answers.order || {}}
                      onChange={(v) => setAnswers({ ...answers, order: v })}
                    />
                    {requirementElements(preview.orderRequirements, "order")}
                    {preview.items.map((item, index) => (
                      <div
                        key={item.itemRef}
                        className="space-y-4 border-t border-stone-200 pt-5"
                      >
                        <h3 className="font-semibold">Účastník {index + 1}</h3>
                        <Fields
                          fields={answerFields(
                            item.fields,
                            definitions,
                            answers[item.itemRef] || {},
                          )}
                          value={answers[item.itemRef] || {}}
                          onChange={(v) =>
                            setAnswers({ ...answers, [item.itemRef]: v })
                          }
                        />
                        {requirementElements(item.requirements, item.itemRef)}
                      </div>
                    ))}
                    <label className="block text-sm font-medium">
                      Způsob platby
                      <select
                        className={`${input} mt-2`}
                        value={method}
                        onChange={(e) => setMethod(e.target.value)}
                      >
                        <option value="">Dle akce / zdarma</option>
                        {((event.paymentMethods || []) as string[]).map((m) => (
                          <option key={m} value={m}>
                            {m === "cash"
                              ? "Hotovost na místě"
                              : "Bankovní převod"}
                          </option>
                        ))}
                      </select>
                    </label>
                  </fieldset>
                  <div className="flex gap-3">
                    <button
                      type="button"
                      disabled={busy}
                      className={secondary}
                      onClick={() => setPreview(undefined)}
                    >
                      Změnit výběr
                    </button>
                    <button className={button} disabled={busy}>
                      {busy ? "Odesílání…" : "Dokončit registraci"}
                    </button>
                  </div>
                </form>
              )}
              <div className="mt-5">
                <ErrorMessage error={error} />
              </div>
            </Box>
          )}
          <PublicContent root={root} />
          {["completed", "archived"].includes(
            String(event.publicationState),
          ) && <PublicContent root={root} archive />}
        </>
      )}
    </main>
  );
}
export function selfServiceLink(url: string) {
  const parsed = new URL(url, "http://localhost");
  const token =
    parsed.searchParams.get("token") ||
    parsed.hash.slice(1) ||
    parsed.pathname.split("/").find((part) => part.startsWith("ss1_"));
  return token ? `/self-service#${encodeURIComponent(token)}` : "/self-service";
}
