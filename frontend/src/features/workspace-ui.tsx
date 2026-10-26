"use client";

import {
  useEffect,
  useId,
  useRef,
  useState,
  type FormEvent,
  type ReactNode,
} from "react";
import { useRouter } from "next/navigation";
import { ApiError, request } from "@/api/client";
import { commandPayload, resolveActionPath } from "@/api/payload";

export const input =
  "w-full rounded-xl border border-stone-300 bg-white px-3 py-2.5 text-sm focus:outline-lime-700";
export const button =
  "inline-flex min-h-10 items-center justify-center gap-2 rounded-xl bg-[#233c2b] px-4 py-2 text-sm font-semibold text-white disabled:opacity-50";
export const secondary =
  "inline-flex min-h-10 items-center justify-center rounded-xl border border-stone-300 bg-white px-4 py-2 text-sm hover:bg-stone-50 disabled:opacity-50";
export type Row = Record<string, unknown>;
export type Field = {
  key: string;
  label: string;
  type?: string;
  required?: boolean;
  options?: string[];
  fields?: Field[];
  value?: unknown;
  help?: string;
  source?: string;
  optionLabels?: Record<string, string>;
};
export type Action = {
  label: string;
  path?: string;
  method?: string;
  fields?: Field[];
  danger?: boolean;
  version?: boolean;
  roles?: string[];
  multipart?: boolean;
  load?: boolean;
  initial?: Row;
  read?: boolean;
};
export type ResourceSpec = {
  title: string;
  path: string;
  create?: Action;
  actions?: Action[];
  fields?: Field[];
  edit?: boolean;
  loadDetail?: boolean;
  remove?: boolean;
  idKey?: string;
  rowsKey?: string;
  roles?: string[];
  filters?: Field[];
  selectLabel?: string;
};

export function ErrorMessage({ error }: { error: unknown }) {
  if (!error) return null;
  const problem = error instanceof ApiError ? error.problem : undefined;
  return (
    <div
      role="alert"
      className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800"
    >
      <p>{error instanceof Error ? error.message : String(error)}</p>
      {problem?.violations?.map((v, i) => (
        <p key={i} className="mt-1">
          {v.path}: {v.message}
        </p>
      ))}
      {problem?.code === "version_conflict" && (
        <p>Data se mezitím změnila. Obnovte přehled a otevřete úpravu znovu.</p>
      )}
      {problem?.correlationId && (
        <p className="mt-2 text-xs">ID požadavku: {problem.correlationId}</p>
      )}
    </div>
  );
}

export function useData<T>(path: string) {
  const router = useRouter();
  const [state, setState] = useState<{
    path: string;
    data?: T;
    error?: unknown;
  }>({ path });
  const [revision, setRevision] = useState(0);
  useEffect(() => {
    const abort = new AbortController();
    request<T>(path, { signal: abort.signal })
      .then((data) => setState({ path, data }))
      .catch((error) => {
        if (abort.signal.aborted) return;
        if (error instanceof ApiError && error.status === 401)
          router.replace("/login");
        setState({ path, error });
      });
    return () => abort.abort();
  }, [path, revision, router]);
  return {
    data: state.path === path ? state.data : undefined,
    error: state.path === path ? state.error : undefined,
    refresh: () => setRevision((v) => v + 1),
  };
}

const labels: Record<string, string> = {
  name: "Název",
  title: "Název",
  displayName: "Jméno",
  email: "E-mail",
  state: "Stav",
  status: "Stav",
  publicationState: "Publikace",
  registrationState: "Registrace",
  startsAt: "Začátek",
  endsAt: "Konec",
  createdAt: "Vytvořeno",
  updatedAt: "Upraveno",
  description: "Popis",
  role: "Role",
  amount: "Částka",
  amountMinor: "Částka v haléřích",
  total: "Celkem",
  checkedIn: "Odbaveno",
  expected: "Očekáváno",
  missing: "Chybí",
  unpaid: "Nezaplaceno",
  body: "Text",
  subject: "Předmět",
  published: "Publikováno",
  inArchive: "V archivu",
  visibility: "Viditelnost",
  contact: "Kontakt",
  reason: "Důvod",
  note: "Poznámka",
  version: "Verze",
  minor: "Haléře",
  currency: "Měna",
  paymentInstructionsPending: "Připravujeme platební pokyny",
  items: "Položky",
  persons: "Osoby",
  participations: "Účasti",
  financialState: "Stav platby",
  shortCode: "Kód",
  firstName: "Jméno",
  lastName: "Příjmení",
  phone: "Telefon",
  available: "Volno",
  confirmed: "Potvrzeno",
  held: "Rezervováno",
  limit: "Limit",
  eventId: "Akce",
  activityId: "Aktivita",
  variantId: "Varianta",
  dueAt: "Splatnost",
  payment: "Platba",
  methods: "Způsoby platby",
  fields: "Pole formuláře",
  required: "Povinné",
  label: "Popisek",
  url: "Odkaz",
  linkToken: "Klíč sdílené kolekce",
  token: "Přístupový klíč",
  spaydPayload: "QR platební řetězec",
  accountNumber: "Číslo účtu",
  iban: "IBAN",
  variableSymbol: "Variabilní symbol",
};
export const label = (key: string) =>
  labels[key] || key.replace(/([a-z])([A-Z])/g, "$1 $2");
export function DataView({
  value,
  compact = false,
}: {
  value: unknown;
  compact?: boolean;
}) {
  if (value === null || value === undefined)
    return <span className="text-stone-400">—</span>;
  if (typeof value === "boolean") return <span>{value ? "Ano" : "Ne"}</span>;
  if (Array.isArray(value))
    return value.length ? (
      <div className="space-y-3">
        {value.map((item, i) => (
          <div key={i} className="rounded-lg border border-stone-100 p-3">
            <DataView value={item} compact={compact} />
          </div>
        ))}
      </div>
    ) : (
      <span className="text-stone-400">Žádné položky</span>
    );
  if (typeof value === "object") {
    const row = value as Row;
    if (typeof row.minor === "number" && typeof row.currency === "string")
      return (
        <span>
          {new Intl.NumberFormat("cs-CZ", {
            style: "currency",
            currency: row.currency,
          }).format(row.minor / 100)}
        </span>
      );
    return (
      <dl className={`grid gap-3 ${compact ? "sm:grid-cols-2" : ""}`}>
        {Object.entries(row)
          .filter(([key]) => !["actions", "payload"].includes(key))
          .map(([key, item]) => (
            <div key={key} className="min-w-0">
              <dt className="mb-1 text-xs text-stone-500">{label(key)}</dt>
              <dd className="break-words text-sm">
                <DataView value={item} compact={compact} />
              </dd>
            </div>
          ))}
      </dl>
    );
  }
  const text = String(value);
  if (/^https?:\/\//.test(text))
    return (
      <a
        className="break-all text-lime-800 underline"
        href={text}
        target="_blank"
        rel="noreferrer"
      >
        Otevřít odkaz
      </a>
    );
  if (/^\d{4}-\d{2}-\d{2}T/.test(text) && !Number.isNaN(Date.parse(text)))
    return (
      <span>
        {new Intl.DateTimeFormat("cs-CZ", {
          dateStyle: "medium",
          timeStyle: "short",
          timeZone: "Europe/Prague",
        }).format(new Date(text))}
      </span>
    );
  return <span className="whitespace-pre-wrap">{text}</span>;
}

function ReferenceField({
  field,
  value,
  onChange,
}: {
  field: Field;
  value: unknown;
  onChange: (v: unknown) => void;
}) {
  const result = useData<Row>(field.source!);
  const rows = Array.isArray(result.data?.items)
    ? (result.data.items as Row[])
    : [];
  return (
    <>
      <select
        className={input}
        required={field.required}
        multiple={field.type === "strings"}
        value={
          field.type === "strings"
            ? Array.isArray(value)
              ? value.map(String)
              : []
            : String(value ?? "")
        }
        onChange={(e) =>
          onChange(
            field.type === "strings"
              ? Array.from(e.target.selectedOptions, (o) => o.value)
              : e.target.value || null,
          )
        }
      >
        {field.type !== "strings" && <option value="">Vyberte…</option>}
        {rows.map((r) => (
          <option key={String(r.id)} value={String(r.id)}>
            {String(r.name ?? r.title ?? r.id)}
          </option>
        ))}
      </select>
      <ErrorMessage error={result.error} />
    </>
  );
}

function localDateTime(value: unknown): string {
  if (!value) return "";
  const date = new Date(String(value));
  if (Number.isNaN(date.valueOf())) return "";
  const local = new Date(date.valueOf() - date.getTimezoneOffset() * 60000);
  return local.toISOString().slice(0, 16);
}

export function Fields({
  fields,
  value,
  onChange,
}: {
  fields: Field[];
  value: Row;
  onChange: (v: Row) => void;
}) {
  const prefix = useId();
  return (
    <div className="grid gap-4 sm:grid-cols-2">
      {fields.map((f) => {
        const val = value[f.key] ?? f.value;
        const update = (v: unknown) => onChange({ ...value, [f.key]: v });
        const control = f.source ? (
          <ReferenceField field={f} value={val} onChange={update} />
        ) : f.type === "object" ? (
          <Fields
            fields={f.fields || []}
            value={(val as Row) || {}}
            onChange={update}
          />
        ) : f.type === "array" ? (
          <div className="space-y-3">
            {((val as Row[]) || []).map((r, i, rows) => (
              <div
                key={i}
                className="space-y-3 rounded-xl border border-stone-200 p-3"
              >
                <Fields
                  fields={f.fields || []}
                  value={r}
                  onChange={(v) =>
                    update(rows.map((old, n) => (n === i ? v : old)))
                  }
                />
                <button
                  type="button"
                  className={secondary}
                  onClick={() => update(rows.filter((_, n) => n !== i))}
                >
                  Odebrat položku
                </button>
              </div>
            ))}
            <button
              type="button"
              className={secondary}
              onClick={() =>
                update([...((val as Row[]) || []), defaults(f.fields || [])])
              }
            >
              Přidat položku
            </button>
          </div>
        ) : f.type === "boolean" ? (
          <input
            type="checkbox"
            className="size-5 accent-lime-800"
            checked={Boolean(val)}
            onChange={(e) => update(e.target.checked)}
          />
        ) : f.type === "multiselect" ? (
          <select
            multiple
            className={input}
            required={f.required}
            value={Array.isArray(val) ? val.map(String) : []}
            onChange={(e) =>
              update(Array.from(e.target.selectedOptions, (o) => o.value))
            }
          >
            {f.options?.map((o) => (
              <option key={o} value={o}>
                {f.optionLabels?.[o] || o}
              </option>
            ))}
          </select>
        ) : f.options ? (
          <select
            className={input}
            required={f.required}
            value={String(val ?? "")}
            onChange={(e) => update(e.target.value || null)}
          >
            <option value="">Vyberte…</option>
            {f.options.map((o) => (
              <option key={o} value={o}>
                {f.optionLabels?.[o] || label(o)}
              </option>
            ))}
          </select>
        ) : f.type === "text" ? (
          <textarea
            className={`${input} min-h-32`}
            required={f.required}
            value={String(val ?? "")}
            onChange={(e) => update(e.target.value)}
          />
        ) : f.type === "pdf-file" ? (
          <input
            type="file"
            accept="application/pdf"
            required={f.required}
            className={input}
            onChange={async (e) => {
              const file = e.target.files?.[0];
              if (!file) {
                update(null);
                return;
              }
              const bytes = new Uint8Array(await file.arrayBuffer());
              let binary = "";
              for (const byte of bytes) binary += String.fromCharCode(byte);
              update(btoa(binary));
            }}
          />
        ) : f.type === "strings" || f.type === "numbers" ? (
          <input
            className={input}
            placeholder="Hodnoty oddělené čárkou"
            value={Array.isArray(val) ? val.join(", ") : ""}
            onChange={(e) =>
              update(
                e.target.value
                  .split(",")
                  .map((v) => v.trim())
                  .filter(Boolean)
                  .map((v) => (f.type === "numbers" ? Number(v) : v)),
              )
            }
          />
        ) : (
          <input
            className={input}
            type={
              f.type === "integer"
                ? "number"
                : f.type === "file"
                  ? "file"
                  : f.type || "text"
            }
            accept={
              f.type === "file" ? "image/jpeg,image/png,image/webp" : undefined
            }
            step={
              f.type === "integer" ? 1 : f.type === "number" ? "any" : undefined
            }
            required={f.required}
            value={
              f.type === "file"
                ? undefined
                : f.type === "datetime-local"
                  ? localDateTime(val)
                  : String(val ?? "")
            }
            onChange={(e) =>
              update(
                f.type === "file"
                  ? e.target.files?.[0]
                  : f.type === "integer" || f.type === "number"
                    ? e.target.value === ""
                      ? null
                      : Number(e.target.value)
                    : f.type === "datetime-local"
                      ? e.target.value
                        ? new Date(e.target.value).toISOString()
                        : null
                      : e.target.value || null,
              )
            }
          />
        );
        return (
          <div
            key={f.key}
            className={
              ["array", "object", "text"].includes(f.type || "")
                ? "sm:col-span-2"
                : ""
            }
          >
            <div
              id={`${prefix}-${f.key}`}
              className="mb-2 block text-sm font-medium"
            >
              {f.label}
              {f.required ? " *" : ""}
            </div>
            <div
              ref={(el) => {
                if (!["object", "array"].includes(f.type || ""))
                  el?.querySelector("input,textarea,select")?.setAttribute(
                    "aria-labelledby",
                    `${prefix}-${f.key}`,
                  );
              }}
            >
              {control}
            </div>
            {f.help && <p className="mt-1 text-xs text-stone-500">{f.help}</p>}
          </div>
        );
      })}
    </div>
  );
}
export function defaults(fields: Field[]): Row {
  return Object.fromEntries(
    fields.map((f) => [
      f.key,
      f.value ??
        (f.type === "boolean"
          ? false
          : f.type === "array" || f.type === "strings" || f.type === "numbers"
            ? []
            : f.type === "object"
              ? defaults(f.fields || [])
              : null),
    ]),
  );
}
function pick(fields: Field[], initial: Row): Row {
  return Object.fromEntries(
    fields.map((f) => [f.key, initial[f.key] ?? defaults([f])[f.key]]),
  );
}

export function Command({
  action,
  path,
  initial = {},
  onDone,
}: {
  action: Action;
  path: string;
  initial?: Row;
  onDone?: (result: unknown) => void;
}) {
  const [opened, setOpened] = useState(false);
  const [value, setValue] = useState<Row>({});
  const [error, setError] = useState<unknown>();
  const [busy, setBusy] = useState(false);
  const [result, setResult] = useState<unknown>();
  const intent = useRef<{ body: string; key: string } | null>(null);
  const trigger = useRef<HTMLButtonElement>(null);
  const dialog = useRef<HTMLDialogElement>(null);
  useEffect(() => {
    if (opened) dialog.current?.showModal();
    else if (dialog.current?.open) dialog.current.close();
  }, [opened]);
  async function submit(e: FormEvent) {
    e.preventDefault();
    setBusy(true);
    setError(undefined);
    try {
      const normalized = action.multipart ? value : commandPayload(path, value);
      const serialized = JSON.stringify(normalized);
      if (!intent.current || intent.current.body !== serialized)
        intent.current = { body: serialized, key: crypto.randomUUID() };
      let body: unknown = normalized;
      if (action.multipart) {
        const form = new FormData();
        for (const [key, item] of Object.entries(value))
          if (item != null)
            form.append(key, item instanceof Blob ? item : String(item));
        body = form;
      }
      const params = new URLSearchParams();
      if (action.read)
        for (const [key, item] of Object.entries(value))
          if (item !== null && item !== undefined && item !== "")
            params.set(key, String(item));
      const requestPath =
        action.read && params.size
          ? `${path}${path.includes("?") ? "&" : "?"}${params}`
          : path;
      const response = await request(requestPath, {
        method: action.read ? "GET" : action.method || "POST",
        body: action.read || action.method === "DELETE" ? undefined : body,
        idempotencyKey: action.read ? undefined : intent.current.key,
        version:
          action.version && typeof initial.version === "number"
            ? initial.version
            : undefined,
      });
      setResult(response ?? { status: "Hotovo" });
      intent.current = null;
      onDone?.(response);
    } catch (err) {
      setError(err);
    } finally {
      setBusy(false);
    }
  }
  return (
    <>
      <button
        ref={trigger}
        className={action.danger ? `${secondary} text-red-700` : secondary}
        onClick={async () => {
          setValue(
            pick(action.fields || [], { ...initial, ...action.initial }),
          );
          setResult(undefined);
          setError(undefined);
          setOpened(true);
          if (action.load) {
            setBusy(true);
            try {
              const detail = await request<Row>(path);
              setValue(pick(action.fields || [], detail));
            } catch (err) {
              setError(err);
            } finally {
              setBusy(false);
            }
          }
        }}
      >
        {action.label}
      </button>
      {opened && (
        <dialog
          ref={dialog}
          onCancel={(e) => {
            e.preventDefault();
            if (!busy) setOpened(false);
          }}
          onClose={() => {
            setOpened(false);
            trigger.current?.focus();
          }}
          className="fixed inset-0 m-auto max-h-[90dvh] w-[min(680px,94vw)] overflow-y-auto rounded-2xl border border-stone-200 bg-white p-6 shadow-2xl backdrop:bg-black/40"
        >
          <div className="mb-6 flex items-center justify-between gap-4">
            <h2 className="text-xl font-semibold">{action.label}</h2>
            <button
              disabled={busy}
              className={secondary}
              onClick={() => setOpened(false)}
            >
              Zavřít
            </button>
          </div>
          {result !== undefined ? (
            <div role="status" className="space-y-4">
              <p className="font-medium text-green-800">
                Požadavek byl dokončen.
              </p>
              <DataView value={result} compact />
            </div>
          ) : (
            <form onSubmit={submit} className="space-y-5">
              <fieldset disabled={busy}>
                <Fields
                  fields={action.fields || []}
                  value={value}
                  onChange={setValue}
                />
              </fieldset>
              {action.danger && (
                <p className="rounded-xl bg-amber-50 p-3 text-sm">
                  Zkontrolujte vybraný záznam. Touto akcí měníte jeho stav.
                </p>
              )}
              <ErrorMessage error={error} />
              <button disabled={busy} className={button}>
                {busy ? "Zpracováváme…" : action.read ? "Načíst" : "Potvrdit"}
              </button>
            </form>
          )}
        </dialog>
      )}
    </>
  );
}

export function Resource({
  spec,
  role = "",
  onChanged,
  onSelect,
}: {
  spec: ResourceSpec;
  role?: string;
  onChanged?: () => void;
  onSelect?: (row: Row) => void;
}) {
  const [filters, setFilters] = useState<Row>({});
  const [applied, setApplied] = useState("");
  const result = useData<unknown>(spec.path + applied);
  const [selected, setSelected] = useState<string>();
  const allowed = (action: Action) =>
    !action.roles || action.roles.includes(role);
  const refresh = () => {
    result.refresh();
    onChanged?.();
  };
  const data = result.data;
  const object =
    data && typeof data === "object" && !Array.isArray(data)
      ? (data as Row)
      : {};
  const rawRows = spec.rowsKey
    ? object[spec.rowsKey]
    : Array.isArray(data)
      ? data
      : object.items;
  const rows = Array.isArray(rawRows) ? (rawRows as Row[]) : undefined;
  const rowId = (row: Row) => String(row[spec.idKey || "id"] ?? "");
  const actionPath = (a: Action, row: Row) =>
    resolveActionPath(spec.path, rowId(row), a.path, row);
  const actions: Action[] = [
    ...(spec.edit
      ? [
          {
            label: "Upravit",
            method: "PUT",
            fields: spec.fields,
            version: true,
            load: spec.loadDetail,
          },
        ]
      : []),
    ...(spec.actions || []),
    ...(spec.remove
      ? [{ label: "Odebrat", method: "DELETE", danger: true }]
      : []),
  ];
  return (
    <section className="overflow-hidden rounded-2xl border border-stone-200 bg-white">
      <div className="flex flex-wrap items-center justify-between gap-3 border-b border-stone-100 p-5">
        <h2 className="font-semibold">{spec.title}</h2>
        <div className="flex flex-wrap gap-2">
          <button className={secondary} onClick={result.refresh}>
            Obnovit
          </button>
          {spec.create && allowed(spec.create) && (
            <Command
              action={spec.create}
              path={spec.path + (spec.create.path || "")}
              onDone={refresh}
            />
          )}
        </div>
      </div>
      {spec.filters && (
        <form
          className="space-y-3 border-b border-stone-100 p-5"
          onSubmit={(e) => {
            e.preventDefault();
            const params = new URLSearchParams();
            for (const [k, v] of Object.entries(filters))
              if (v !== null && v !== "") params.set(k, String(v));
            setApplied(params.size ? `?${params}` : "");
          }}
        >
          <Fields fields={spec.filters} value={filters} onChange={setFilters} />
          <button className={secondary}>Použít filtry</button>
        </form>
      )}
      <div className="p-5">
        <ErrorMessage error={result.error} />
        {data === undefined && !result.error && (
          <p role="status" className="py-8 text-center text-stone-500">
            Načítání…
          </p>
        )}
        {rows ? (
          rows.length ? (
            <div className="divide-y divide-stone-100">
              {rows.map((row, index) => {
                const id = rowId(row) || String(index);
                return (
                  <article key={id} className="py-4 first:pt-0">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                      <button
                        className="min-w-0 text-left"
                        onClick={() =>
                          setSelected(selected === id ? undefined : id)
                        }
                        aria-expanded={selected === id}
                      >
                        <strong className="block break-words text-sm">
                          {String(
                            row.name ??
                              row.title ??
                              row.personName ??
                              row.displayName ??
                              row.email ??
                              row.subject ??
                              row.shortCode ??
                              row.type ??
                              `Záznam ${index + 1}`,
                          )}
                        </strong>
                        <span className="mt-1 block text-xs text-stone-500">
                          {String(
                            row.state ??
                              row.status ??
                              row.role ??
                              row.visibility ??
                              "Zobrazit podrobnosti",
                          )}
                        </span>
                      </button>
                      <div className="flex flex-wrap gap-2">
                        {onSelect && (
                          <button
                            className={secondary}
                            onClick={() => onSelect(row)}
                          >
                            {spec.selectLabel || "Otevřít"}
                          </button>
                        )}
                        {actions.filter(allowed).map((a, n) => (
                          <Command
                            key={n}
                            action={a}
                            initial={row}
                            path={actionPath(a, row)}
                            onDone={refresh}
                          />
                        ))}
                      </div>
                    </div>
                    {selected === id && (
                      <div className="mt-4 rounded-xl bg-stone-50 p-4">
                        <DataView value={row} compact />
                      </div>
                    )}
                  </article>
                );
              })}
            </div>
          ) : (
            <p className="py-8 text-center text-sm text-stone-500">
              Zatím žádné záznamy.
            </p>
          )
        ) : (
          data !== undefined && (
            <>
              <DataView value={data} compact />
              {spec.edit && (
                <div className="mt-5">
                  <Command
                    action={{
                      label: "Upravit",
                      method: "PUT",
                      fields: spec.fields,
                      version: true,
                    }}
                    path={spec.path}
                    initial={object}
                    onDone={refresh}
                  />
                </div>
              )}
              {spec.actions?.filter(allowed).map((a, i) => (
                <div className="mt-3 inline-block pr-2" key={i}>
                  <Command
                    action={a}
                    path={
                      a.path?.startsWith("/api/")
                        ? a.path
                        : spec.path + (a.path || "")
                    }
                    initial={object}
                    onDone={refresh}
                  />
                </div>
              ))}
            </>
          )
        )}
        {typeof object.total === "number" && (
          <p className="mt-4 text-xs text-stone-500">
            Celkem {object.total} záznamů · stránka {String(object.page ?? 1)}
          </p>
        )}
      </div>
    </section>
  );
}

export function SectionTabs({
  tabs,
  value,
  onChange,
}: {
  tabs: string[];
  value: string;
  onChange: (s: string) => void;
}) {
  return (
    <div className="mb-6 flex gap-2 overflow-x-auto border-b border-stone-200 pb-3">
      {tabs.map((t) => (
        <button
          key={t}
          className={`shrink-0 rounded-xl px-4 py-2 text-sm ${value === t ? "bg-[#233c2b] text-white" : "bg-white text-stone-600"}`}
          onClick={() => onChange(t)}
        >
          {t}
        </button>
      ))}
    </div>
  );
}
export function Box({ children }: { children: ReactNode }) {
  return (
    <div className="rounded-2xl border border-stone-200 bg-white p-6">
      {children}
    </div>
  );
}
