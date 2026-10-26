"use client";

import {
  useEffect,
  useRef,
  useState,
  type FormEvent,
  type ReactNode,
} from "react";
import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import {
  ArrowDownLeft,
  ArrowRight,
  CalendarDays,
  Check,
  ChevronRight,
  CircleAlert,
  CreditCard,
  FileText,
  LoaderCircle,
  MapPin,
  Plus,
  Search,
  Settings2,
  Sprout,
  Ticket,
  Users,
  X,
} from "lucide-react";
import { api, type CapacityOverview } from "@/api/client";
import {
  Badge,
  EmptyState,
  PageHeader,
  Panel,
  StatCard,
  buttonStyles,
} from "@/components/ui";
import { demoEvents, demoOrders } from "./demo-data";

type EventItem = {
  id: string;
  slug: string;
  name: string;
  shortDescription: string | null;
  startsAt: string;
  endsAt: string;
  publicationState: string;
  registrationState: string;
  version: number;
};
type EventDetail = EventItem & {
  description?: string | null;
  timezone?: string;
  capacityLimit?: number | null;
  activities?: {
    id: string;
    name: string;
    description?: string | null;
    variants?: { id: string; name: string }[];
  }[];
  venues?: { name: string; address?: string | null }[];
};
type Collection = { items: Record<string, unknown>[] };
const inputStyle =
  "w-full rounded-xl border border-stone-200 bg-white px-3.5 py-2.5 text-sm outline-none focus:border-lime-600 focus:ring-2 focus:ring-lime-100";
const secondaryButton =
  "inline-flex items-center justify-center gap-2 rounded-xl border border-stone-200 bg-white px-4 py-2.5 text-sm font-semibold text-stone-700 hover:bg-stone-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-lime-600";

function useRemote<T>(key: string, load: () => Promise<T>, demo?: T) {
  const [state, setState] = useState<{ key: string; data?: T; error?: string }>(
    { key },
  );
  const [revision, setRevision] = useState(0);
  useEffect(() => {
    if (demo !== undefined) return;
    let cancelled = false;
    load()
      .then((data) => {
        if (!cancelled) setState({ key, data });
      })
      .catch((error) => {
        if (!cancelled)
          setState({
            key,
            error:
              error instanceof Error
                ? error.message
                : "Data se nepodařilo načíst.",
          });
      });
    return () => {
      cancelled = true;
    };
    // The key identifies the entire query and its organizer scope.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [key, revision]);
  return {
    data: demo ?? (state.key === key ? state.data : undefined),
    error: state.key === key ? state.error : undefined,
    retry: () => setRevision((value) => value + 1),
  };
}

export function DemoNotice() {
  return (
    <div className="mb-6 flex flex-wrap items-center gap-x-2 gap-y-1 rounded-xl border border-[#dce3c6] bg-[#f0f3e7] px-4 py-3 text-xs text-[#53613b]">
      <Sprout size={15} />
      <strong>Ukázkový pracovní prostor</strong>
      <span>
        Data jsou ilustrační. Pro práci s vlastními akcemi se přihlaste.
      </span>
      <Link
        href="/login"
        className="ml-auto font-semibold underline underline-offset-4"
      >
        Přihlásit se
      </Link>
    </div>
  );
}

function RequestState({
  error,
  retry,
  loading = false,
}: {
  error?: string;
  retry: () => void;
  loading?: boolean;
}) {
  return (
    <Panel>
      <div className="flex min-h-48 flex-col items-center justify-center gap-3 p-8 text-center">
        {loading ? (
          <>
            <LoaderCircle className="animate-spin text-lime-700" />
            <p className="text-sm text-stone-500" role="status">
              Načítáme váš pracovní prostor…
            </p>
          </>
        ) : (
          <>
            <CircleAlert className="text-amber-600" />
            <h2 className="font-semibold">Data nejsou dostupná</h2>
            <p className="max-w-lg text-sm text-stone-500" role="alert">
              {error}
            </p>
            <div className="flex gap-3">
              <button onClick={retry} className={secondaryButton}>
                Zkusit znovu
              </button>
              <Link href="/login" className={secondaryButton}>
                Přihlásit se
              </Link>
            </div>
          </>
        )}
      </div>
    </Panel>
  );
}

function dateLabel(date: string) {
  const parsed = new Date(date);
  return Number.isNaN(parsed.valueOf())
    ? "Termín není určen"
    : new Intl.DateTimeFormat("cs-CZ", {
        day: "numeric",
        month: "short",
        year: "numeric",
        timeZone: "Europe/Prague",
      }).format(parsed);
}
function EventBadge({ state }: { state: string }) {
  return (
    <Badge
      tone={
        state === "published"
          ? "green"
          : state === "draft"
            ? "amber"
            : "neutral"
      }
    >
      {(
        {
          published: "Publikováno",
          draft: "Koncept",
          archived: "Archivováno",
          cancelled: "Zrušeno",
        } as Record<string, string>
      )[state] ?? state}
    </Badge>
  );
}

function EventCard({ event, orgSlug }: { event: EventItem; orgSlug: string }) {
  const demo =
    orgSlug === "demo"
      ? demoEvents.find((item) => item.id === event.id)
      : undefined;
  return (
    <Link
      href={`/org/${orgSlug}/events/${event.id}`}
      className="group overflow-hidden rounded-2xl border border-stone-200 bg-white transition hover:border-stone-300 hover:shadow-sm focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-lime-700"
    >
      <div
        className={`relative flex h-36 items-center justify-center overflow-hidden ${demo?.color ?? "bg-[#e9edda]"}`}
      >
        <div className="absolute -right-8 -top-14 size-48 rounded-full border-[24px] border-white/30" />
        <div className="absolute -bottom-16 -left-8 size-40 rounded-full border-[22px] border-white/30" />
        <span className="relative font-serif text-7xl text-stone-700/70">
          {demo?.letter ?? event.name.charAt(0)}
        </span>
        <span className="absolute bottom-3 left-4 rounded-lg bg-white/85 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-widest text-stone-700">
          {demo?.category ?? "Událost"}
        </span>
      </div>
      <div className="p-5">
        <div className="mb-3 flex items-center justify-between gap-2">
          <span className="flex items-center gap-1.5 text-xs text-stone-500">
            <CalendarDays size={13} />
            {dateLabel(event.startsAt)}
          </span>
          <EventBadge state={event.publicationState} />
        </div>
        <h3 className="text-base font-semibold tracking-tight group-hover:text-lime-800">
          {event.name}
        </h3>
        <p className="mt-1 flex items-center gap-1.5 text-xs text-stone-500">
          <MapPin size={12} />
          {demo?.location ?? "Otevřít detail akce"}
        </p>
        {demo ? (
          <div className="mt-5">
            <div className="mb-2 flex justify-between text-xs">
              <span className="text-stone-500">Obsazenost</span>
              <span className="font-medium">
                {demo.participants}{" "}
                <span className="text-stone-400">/ {demo.capacity}</span>
              </span>
            </div>
            <div className="h-1.5 overflow-hidden rounded-full bg-stone-100">
              <div
                className="h-full rounded-full bg-[#83965a]"
                style={{
                  width: `${(demo.participants / demo.capacity) * 100}%`,
                }}
              />
            </div>
          </div>
        ) : (
          <p className="mt-4 line-clamp-2 text-sm text-stone-500">
            {event.shortDescription ??
              "Doplňte popis a připravte akci pro účastníky."}
          </p>
        )}
        <div className="mt-4 flex items-center justify-between border-t border-stone-100 pt-3 text-xs">
          <span className="text-stone-500">
            {event.registrationState === "open"
              ? "Registrace otevřena"
              : "Registrace uzavřena"}
          </span>
          <ArrowRight
            size={14}
            className="text-stone-400 transition group-hover:translate-x-1"
          />
        </div>
      </div>
    </Link>
  );
}

export function OverviewScreen({ orgSlug }: { orgSlug: string }) {
  const isDemo = orgSlug === "demo";
  const result = useRemote<{ items: EventItem[] }>(
    `${orgSlug}/events`,
    () => api.organizer(orgSlug).events.list(),
    isDemo ? { items: demoEvents } : undefined,
  );
  return (
    <>
      {isDemo && <DemoNotice />}
      <PageHeader
        eyebrow="VÁŠ PRACOVNÍ PROSTOR"
        title="Dobrý den, pořadateli 👋"
        description="Všechno důležité pro vaše akce. Přehledně na jednom místě."
        action={
          <Link href={`/org/${orgSlug}/events?new=1`} className={buttonStyles}>
            <Plus size={16} />
            Vytvořit akci
          </Link>
        }
      />
      {result.error ? (
        <RequestState error={result.error} retry={result.retry} />
      ) : !result.data ? (
        <RequestState loading retry={result.retry} />
      ) : (
        <>
          <div className="mb-8 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <StatCard
              label="Aktivní akce"
              value={String(
                result.data.items.filter(
                  (e) => e.publicationState === "published",
                ).length,
              )}
              detail={
                isDemo
                  ? "Jedna nová sezóna před vámi"
                  : "Publikované akce organizátora"
              }
              icon={<CalendarDays size={19} />}
            />
            <StatCard
              label="Registrovaní účastníci"
              value={isDemo ? "222" : "—"}
              detail={
                isDemo
                  ? "128 na nejbližší akci"
                  : "Souhrn registrací připravujeme"
              }
              icon={<Users size={19} />}
            />
            <StatCard
              label="Přijaté platby"
              value={isDemo ? "57 200 Kč" : "—"}
              detail={
                isDemo
                  ? "Z ilustračních objednávek"
                  : "Souhrn plateb připravujeme"
              }
              icon={<CreditCard size={19} />}
            />
            {isDemo ? (
              <StatCard
                label="Vyžaduje pozornost"
                value="3"
                detail="2 platby · 1 neúplná registrace"
                icon={<CircleAlert size={19} />}
              />
            ) : (
              <AttentionStat orgSlug={orgSlug} />
            )}
          </div>
          <div className="mb-5 flex items-center justify-between">
            <div>
              <h2 className="text-lg font-semibold tracking-tight">
                Vaše akce
              </h2>
              <p className="mt-1 text-xs text-stone-500">
                Od prvního nápadu až po posledního účastníka.
              </p>
            </div>
            <Link
              href={`/org/${orgSlug}/events`}
              className="flex items-center gap-1 text-xs font-semibold text-stone-600"
            >
              Všechny akce
              <ArrowRight size={14} />
            </Link>
          </div>
          {result.data.items.length ? (
            <div className="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
              {result.data.items.slice(0, 3).map((event) => (
                <EventCard key={event.id} event={event} orgSlug={orgSlug} />
              ))}
            </div>
          ) : (
            <EmptyState
              title="První akce začíná tady"
              description="Založte koncept. Program, registrace i platby doplníte postupně."
              action={
                <Link
                  href={`/org/${orgSlug}/events?new=1`}
                  className={buttonStyles}
                >
                  <Plus size={16} />
                  Vytvořit akci
                </Link>
              }
            />
          )}
          <div className="mt-8 grid gap-6 xl:grid-cols-[1.6fr_1fr]">
            <Panel>
              <div className="flex items-center justify-between border-b border-stone-100 px-5 py-4">
                <h2 className="font-semibold">Poslední registrace</h2>
                <Link
                  href={`/org/${orgSlug}/registrations`}
                  className="text-xs font-medium text-stone-500"
                >
                  Zobrazit přehled →
                </Link>
              </div>
              {isDemo ? (
                <OrderRows compact />
              ) : (
                <div className="p-6 text-sm text-stone-500">
                  Přehled registrací bude dostupný po napojení registračního
                  modulu.
                </div>
              )}
            </Panel>
            <Panel>
              <div className="flex items-center gap-2 border-b border-stone-100 px-5 py-4">
                <span className="size-2 rounded-full bg-amber-500" />
                <h2 className="font-semibold">Malé kroky, velký přehled</h2>
              </div>
              <div className="space-y-4 p-5">
                {[
                  {
                    title: "Připravte další akci",
                    text: "Začněte konceptem, publikujte až budete připraveni.",
                    href: "events?new=1",
                  },
                  {
                    title: "Mějte tým na jedné vlně",
                    text: "Role a přístupy spravujte na jednom místě.",
                    href: "team",
                  },
                  {
                    title: "Dokumenty vždy po ruce",
                    text: "Podmínky a souhlasy patří k vaší akci.",
                    href: "documents",
                  },
                ].map((item) => (
                  <Link
                    key={item.title}
                    href={`/org/${orgSlug}/${item.href}`}
                    className="flex items-start gap-3 rounded-lg py-1"
                  >
                    <span className="mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-lg bg-stone-100 text-stone-500">
                      <Check size={14} />
                    </span>
                    <div>
                      <p className="text-sm font-medium">{item.title}</p>
                      <p className="mt-1 text-xs leading-relaxed text-stone-500">
                        {item.text}
                      </p>
                    </div>
                    <ChevronRight
                      size={14}
                      className="ml-auto mt-1 shrink-0 text-stone-400"
                    />
                  </Link>
                ))}
              </div>
            </Panel>
          </div>
        </>
      )}
    </>
  );
}

function AttentionStat({ orgSlug }: { orgSlug: string }) {
  const result = useRemote(`${orgSlug}/attention/summary`, () =>
    api.organizer(orgSlug).attention.summary(),
  );
  return (
    <StatCard
      label="Vyžaduje pozornost"
      value={result.data?.unresolved ?? "—"}
      detail={
        result.error
          ? "Přehled není dostupný pro váš účet"
          : result.data
            ? "Nevyřešené provozní výjimky"
            : "Načítání provozních výjimek…"
      }
      icon={<CircleAlert size={19} />}
    />
  );
}

export function EventsScreen({ orgSlug }: { orgSlug: string }) {
  const [search, setSearch] = useState("");
  const [filter, setFilter] = useState("all");
  const [showCreate, setShowCreate] = useState(false);
  const searchParams = useSearchParams();
  const result = useRemote<{ items: EventItem[] }>(
    `${orgSlug}/events`,
    () => api.organizer(orgSlug).events.list(),
    orgSlug === "demo" ? { items: demoEvents } : undefined,
  );
  const items =
    result.data?.items.filter(
      (event) =>
        event.name
          .toLocaleLowerCase("cs")
          .includes(search.toLocaleLowerCase("cs")) &&
        (filter === "all" || event.publicationState === filter),
    ) ?? [];
  return (
    <>
      {orgSlug === "demo" && <DemoNotice />}
      <PageHeader
        eyebrow="KATALOG"
        title="Vaše akce"
        description="Plánujte, publikujte a tvořte zážitky, na které se nezapomíná."
        action={
          <button className={buttonStyles} onClick={() => setShowCreate(true)}>
            <Plus size={16} />
            Vytvořit akci
          </button>
        }
      />
      <div className="mb-6 flex flex-col justify-between gap-4 sm:flex-row">
        <div className="flex gap-1 overflow-x-auto rounded-xl bg-stone-100 p-1">
          {[
            ["all", "Všechny"],
            ["published", "Publikované"],
            ["draft", "Koncepty"],
            ["archived", "Archiv"],
          ].map(([value, label]) => (
            <button
              key={value}
              onClick={() => setFilter(value)}
              className={`whitespace-nowrap rounded-lg px-3 py-2 text-xs font-medium ${filter === value ? "bg-white shadow-sm" : "text-stone-500 hover:text-stone-900"}`}
            >
              {label}
            </button>
          ))}
        </div>
        <label className="relative sm:w-64">
          <Search size={15} className="absolute left-3 top-3 text-stone-400" />
          <span className="sr-only">Hledat akci</span>
          <input
            className={`${inputStyle} pl-9`}
            placeholder="Hledat akci…"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
          />
        </label>
      </div>
      {result.error ? (
        <RequestState error={result.error} retry={result.retry} />
      ) : !result.data ? (
        <RequestState loading retry={result.retry} />
      ) : items.length ? (
        <div className="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
          {items.map((event) => (
            <EventCard key={event.id} event={event} orgSlug={orgSlug} />
          ))}
        </div>
      ) : (
        <EmptyState
          icon={<CalendarDays size={25} />}
          title={
            search || filter !== "all"
              ? "Žádná odpovídající akce"
              : "Prostor pro vaši první akci"
          }
          description={
            search || filter !== "all"
              ? "Zkuste upravit hledání nebo filtr."
              : "Vytvořte koncept a postupně doplňte všechny podrobnosti."
          }
        />
      )}
      <CreateEventDialog
        orgSlug={orgSlug}
        open={showCreate || searchParams.get("new") === "1"}
        close={() => {
          setShowCreate(false);
          if (searchParams.has("new"))
            window.history.replaceState(null, "", `/org/${orgSlug}/events`);
        }}
      />
    </>
  );
}

function CreateEventDialog({
  orgSlug,
  open,
  close,
}: {
  orgSlug: string;
  open: boolean;
  close: () => void;
}) {
  const dialogRef = useRef<HTMLDialogElement>(null);
  const router = useRouter();
  const [error, setError] = useState<string>();
  const [pending, setPending] = useState(false);
  useEffect(() => {
    if (!open) return;
    const dialog = dialogRef.current;
    dialog?.showModal();
    return () => dialog?.close();
  }, [open]);
  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (orgSlug === "demo") return;
    setError(undefined);
    setPending(true);
    const data = new FormData(event.currentTarget);
    try {
      const created = await api
        .organizer(orgSlug)
        .events.create({
          mode: "simple",
          details: {
            name: String(data.get("name")),
            startsAt: new Date(String(data.get("startsAt"))).toISOString(),
            endsAt: new Date(String(data.get("endsAt"))).toISOString(),
            timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
          },
        });
      router.push(`/org/${orgSlug}/events/${created.eventId}`);
      close();
    } catch (error) {
      setError(error instanceof Error ? error.message : "Akci nelze vytvořit.");
    } finally {
      setPending(false);
    }
  }
  if (!open) return null;
  return (
    <dialog
      ref={dialogRef}
      aria-labelledby="create-title"
      onCancel={(event) => {
        event.preventDefault();
        if (!pending) close();
      }}
      className="fixed inset-0 m-auto max-h-[90dvh] w-[calc(100%-2rem)] max-w-lg overflow-y-auto rounded-2xl border-0 bg-white p-0 text-stone-900 shadow-xl backdrop:bg-stone-950/40 backdrop:backdrop-blur-sm"
    >
      <section className="p-6">
        <div className="mb-6 flex items-center justify-between">
          <h2 id="create-title" className="text-xl font-semibold">
            Nová akce
          </h2>
          <button
            onClick={close}
            disabled={pending}
            aria-label="Zavřít"
            className="rounded-lg p-2 hover:bg-stone-100"
          >
            <X size={18} />
          </button>
        </div>
        {orgSlug === "demo" ? (
          <>
            <p className="mb-6 text-sm leading-relaxed text-stone-500">
              V ukázkovém prostoru si můžete prohlédnout podobu aplikace.
              Přihlaste se a vytvořte skutečnou akci ve vlastním prostoru.
            </p>
            <Link className={buttonStyles} href="/login">
              Přihlásit se
              <ArrowRight size={16} />
            </Link>
          </>
        ) : (
          <form onSubmit={submit} className="space-y-4">
            <label className="block text-sm font-medium">
              Název akce
              <input
                autoFocus
                name="name"
                required
                maxLength={200}
                className={`${inputStyle} mt-1.5`}
                placeholder="Například Borský podzimní víkend"
              />
            </label>
            <div className="grid gap-4 sm:grid-cols-2">
              <label className="block text-sm font-medium">
                Začátek
                <input
                  name="startsAt"
                  type="datetime-local"
                  required
                  className={`${inputStyle} mt-1.5`}
                />
              </label>
              <label className="block text-sm font-medium">
                Konec
                <input
                  name="endsAt"
                  type="datetime-local"
                  required
                  className={`${inputStyle} mt-1.5`}
                />
              </label>
            </div>
            <p className="text-xs leading-relaxed text-stone-500">
              Časy v pásmu {Intl.DateTimeFormat().resolvedOptions().timeZone}.
              Vytvoříme neveřejný koncept jednoduché akce. Program i pravidla
              doplníte následně.
            </p>
            {error && (
              <p
                role="alert"
                className="rounded-xl bg-red-50 p-3 text-sm text-red-700"
              >
                {error}
              </p>
            )}
            <div className="flex justify-end gap-3 pt-3">
              <button
                type="button"
                onClick={close}
                disabled={pending}
                className={secondaryButton}
              >
                Zrušit
              </button>
              <button type="submit" disabled={pending} className={buttonStyles}>
                {pending ? (
                  <LoaderCircle size={16} className="animate-spin" />
                ) : (
                  <Plus size={16} />
                )}
                Vytvořit koncept
              </button>
            </div>
          </form>
        )}
      </section>
    </dialog>
  );
}

export function EventDetailScreen({
  orgSlug,
  eventId,
}: {
  orgSlug: string;
  eventId: string;
}) {
  const demo =
    orgSlug === "demo"
      ? demoEvents.find((event) => event.id === eventId)
      : undefined;
  const [tab, setTab] = useState("overview");
  const result = useRemote<EventDetail>(
    `${orgSlug}/events/${eventId}`,
    () =>
      orgSlug === "demo"
        ? Promise.reject(
            new Error(
              "Tato ukázková akce neexistuje. Vraťte se na seznam akcí.",
            ),
          )
        : api.organizer(orgSlug).events.get(eventId),
    demo
      ? {
          ...demo,
          description: demo.shortDescription,
          activities: [
            {
              id: "activity-demo",
              name: "Rodinná sobota",
              variants: [{ id: "variant-demo", name: "Základní vstup" }],
            },
            {
              id: "activity-demo-2",
              name: "Nedělní výprava",
              variants: [{ id: "variant-demo-2", name: "Celodenní program" }],
            },
          ],
        }
      : undefined,
  );
  return (
    <>
      {orgSlug === "demo" && <DemoNotice />}
      <Link
        href={`/org/${orgSlug}/events`}
        className="mb-4 inline-flex items-center gap-1 text-xs font-medium text-stone-500"
      >
        ← Zpět na akce
      </Link>
      <PageHeader
        eyebrow="DETAIL AKCE"
        title={result.data?.name ?? "Detail akce"}
        description={
          result.data
            ? `${dateLabel(result.data.startsAt)} · ${result.data.timezone ?? "Europe/Prague"}`
            : "Program, registrace a vše ostatní na jednom místě."
        }
        action={
          result.data && <EventBadge state={result.data.publicationState} />
        }
      />
      <div
        role="group"
        aria-label="Sekce akce"
        className="mb-6 flex gap-1 overflow-x-auto border-b border-stone-200"
      >
        {[
          ["overview", "Přehled"],
          ["program", "Program a varianty"],
          ["form", "Registrační formulář"],
          ["capacity", "Kapacity"],
          ["payments", "Platby"],
          ["documents", "Dokumenty"],
        ].map(([value, label]) => (
          <button
            key={value}
            aria-pressed={tab === value}
            onClick={() => setTab(value)}
            className={`whitespace-nowrap border-b-2 px-4 py-3 text-sm font-medium ${tab === value ? "border-lime-700 text-lime-800" : "border-transparent text-stone-500 hover:text-stone-900"}`}
          >
            {label}
          </button>
        ))}
      </div>
      {result.error ? (
        <RequestState error={result.error} retry={result.retry} />
      ) : !result.data ? (
        <RequestState loading retry={result.retry} />
      ) : (
        <div aria-label="Obsah sekce akce">
          {tab === "overview" ? (
            <div className="grid gap-6 lg:grid-cols-[1.5fr_1fr]">
              <Panel>
                <div className="p-6">
                  <h2 className="mb-4 font-semibold">O akci</h2>
                  <p className="whitespace-pre-wrap text-sm leading-7 text-stone-500">
                    {result.data.description ??
                      result.data.shortDescription ??
                      "Popis zatím není vyplněn."}
                  </p>
                  <div className="mt-6 grid gap-4 border-t border-stone-100 pt-5 sm:grid-cols-2">
                    <div>
                      <p className="text-xs text-stone-500">Začátek</p>
                      <p className="mt-1 text-sm font-medium">
                        {dateLabel(result.data.startsAt)}
                      </p>
                    </div>
                    <div>
                      <p className="text-xs text-stone-500">Konec</p>
                      <p className="mt-1 text-sm font-medium">
                        {dateLabel(result.data.endsAt)}
                      </p>
                    </div>
                  </div>
                </div>
              </Panel>
              <Panel>
                <div className="space-y-4 p-6">
                  <h2 className="font-semibold">Příprava akce</h2>
                  {[
                    "Základní údaje",
                    "Program a varianty",
                    "Formulář a souhlasy",
                    "Pravidla plateb",
                  ].map((label, index) => (
                    <button
                      key={label}
                      onClick={() =>
                        setTab(
                          ["overview", "program", "form", "payments"][index],
                        )
                      }
                      className="flex w-full items-center gap-3 text-left text-sm"
                    >
                      <span className="flex size-7 items-center justify-center rounded-full bg-stone-100 text-xs text-stone-500">
                        {index + 1}
                      </span>
                      {label}
                      <ChevronRight
                        size={14}
                        className="ml-auto text-stone-400"
                      />
                    </button>
                  ))}
                </div>
              </Panel>
            </div>
          ) : tab === "program" ? (
            <div className="space-y-4">
              {result.data.activities?.length ? (
                result.data.activities.map((activity) => (
                  <Panel key={activity.id}>
                    <div className="p-5">
                      <h2 className="font-semibold">{activity.name}</h2>
                      {activity.description && (
                        <p className="mt-2 text-sm text-stone-500">
                          {activity.description}
                        </p>
                      )}
                      <div className="mt-4 flex flex-wrap gap-2">
                        {activity.variants?.map((variant) => (
                          <Badge key={variant.id}>{variant.name}</Badge>
                        ))}
                      </div>
                    </div>
                  </Panel>
                ))
              ) : (
                <EmptyState
                  title="Program zatím není sestaven"
                  description="Aktivity a varianty budou tvořit nabídku vaší akce."
                />
              )}
            </div>
          ) : tab === "capacity" ? (
            <CapacitySection orgSlug={orgSlug} eventId={eventId} demo={demo} />
          ) : (
            <EmptyState
              icon={
                tab === "form" ? (
                  <Ticket size={24} />
                ) : tab === "payments" ? (
                  <CreditCard size={24} />
                ) : (
                  <FileText size={24} />
                )
              }
              title={
                {
                  form: "Registrační formulář",
                  payments: "Platební pravidla a přehled",
                  documents: "Dokumenty a souhlasy",
                }[tab] ?? "Nastavení"
              }
              description={
                tab === "form"
                  ? "Prostor pro editor verzovaného formuláře, podmíněná pole a náhled registrace. Editor doplníme v další etapě."
                  : tab === "payments"
                    ? "Přehled plateb a nastavení organizátora jsou dostupné v samostatné sekci."
                    : "Dokumenty organizátora spravujte v knihovně dokumentů."
              }
              action={
                tab !== "form" ? (
                  <Link
                    href={`/org/${orgSlug}/${tab}`}
                    className={secondaryButton}
                  >
                    Otevřít sekci
                    <ArrowRight size={15} />
                  </Link>
                ) : undefined
              }
            />
          )}
        </div>
      )}
    </>
  );
}

function CapacitySection({
  orgSlug,
  eventId,
  demo,
}: {
  orgSlug: string;
  eventId: string;
  demo?: (typeof demoEvents)[number];
}) {
  const result = useRemote<CapacityOverview>(
    `${orgSlug}/capacity/${eventId}`,
    () => api.organizer(orgSlug).capacity(eventId),
    demo
      ? {
          eventId,
          buckets: [
            {
              bucketId: "demo",
              scope: "event",
              scopeId: eventId,
              unit: "seat",
              limit: demo.capacity,
              held: 0,
              confirmed: demo.participants,
              available: demo.capacity - demo.participants,
              overbooked: false,
            },
          ],
        }
      : undefined,
  );
  if (result.error)
    return <RequestState error={result.error} retry={result.retry} />;
  if (!result.data) return <RequestState loading retry={result.retry} />;
  return (
    <div className="space-y-4">
      {result.data.buckets.length ? (
        result.data.buckets.map((bucket) => (
          <Panel key={bucket.bucketId}>
            <div className="p-6">
              <div className="mb-5 flex flex-wrap items-center justify-between gap-2">
                <h2 className="font-semibold">
                  {
                    {
                      event: "Celá akce",
                      activity: "Aktivita",
                      variant: "Varianta",
                      wave: "Registrační vlna",
                    }[bucket.scope]
                  }
                </h2>
                <Badge tone={bucket.overbooked ? "red" : "green"}>
                  {bucket.overbooked
                    ? "Překročená kapacita"
                    : bucket.available === null
                      ? "Bez omezení"
                      : `${bucket.available} volných míst`}
                </Badge>
              </div>
              <div className="mb-3 flex justify-between text-sm">
                <span>
                  Potvrzeno {bucket.confirmed} · rezervováno {bucket.held}
                </span>
                <strong>
                  {bucket.confirmed + bucket.held} / {bucket.limit ?? "∞"}
                </strong>
              </div>
              {bucket.limit !== null && bucket.limit > 0 && (
                <div className="h-2 overflow-hidden rounded-full bg-stone-100">
                  <div
                    className={`h-full rounded-full ${bucket.overbooked ? "bg-red-600" : "bg-lime-700"}`}
                    style={{
                      width: `${Math.min(100, ((bucket.confirmed + bucket.held) / bucket.limit) * 100)}%`,
                    }}
                  />
                </div>
              )}
              <p className="mt-3 text-xs text-stone-500">
                Jednotka: {bucket.unit === "team" ? "tým" : "místo"}
                {demo
                  ? " · ilustrační data"
                  : bucket.scope !== "event"
                    ? ` · ${bucket.scopeId}`
                    : ""}
              </p>
            </div>
          </Panel>
        ))
      ) : (
        <EmptyState
          title="Žádná kapacitní skupina"
          description="Kapacity se zobrazí po vytvoření programu akce."
        />
      )}
    </div>
  );
}

function OrderRows({ compact = false }: { compact?: boolean }) {
  return (
    <div className="overflow-x-auto">
      <table className="w-full whitespace-nowrap text-left text-sm">
        <thead className="border-b border-stone-100 text-[10px] uppercase tracking-wider text-stone-400">
          <tr>
            <th className="px-5 py-3 font-medium">Účastník</th>
            {!compact && <th className="px-5 py-3 font-medium">Aktivita</th>}
            <th className="px-5 py-3 font-medium">Částka</th>
            <th className="px-5 py-3 font-medium">Stav</th>
          </tr>
        </thead>
        <tbody>
          {demoOrders.map((order) => (
            <tr
              key={order.name}
              className="border-b border-stone-100 last:border-0"
            >
              <td className="px-5 py-4">
                <div className="flex items-center gap-3">
                  <span className="flex size-8 items-center justify-center rounded-full bg-stone-100 text-[10px] font-semibold text-stone-500">
                    {order.initials}
                  </span>
                  <div>
                    <p className="text-xs font-medium">{order.name}</p>
                    <p className="mt-1 text-[10px] text-stone-400">
                      {compact ? order.date : order.email}
                    </p>
                  </div>
                </div>
              </td>
              {!compact && (
                <td className="px-5 py-4 text-xs text-stone-500">
                  {order.activity}
                  <span className="ml-2 text-stone-400">× {order.count}</span>
                </td>
              )}
              <td className="px-5 py-4 text-xs font-medium">{order.amount}</td>
              <td className="px-5 py-4">
                <Badge tone={order.state === "Zaplaceno" ? "green" : "amber"}>
                  {order.state}
                </Badge>
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

const moduleInfo: Record<
  string,
  { eyebrow: string; title: string; description: string; icon: ReactNode }
> = {
  registrations: {
    eyebrow: "ÚČASTNÍCI",
    title: "Registrace",
    description: "Objednávky, účastníci a jejich cesta na vaši akci.",
    icon: <Users size={24} />,
  },
  payments: {
    eyebrow: "FINANCE",
    title: "Platby",
    description: "Přehled plateb, párování transakcí a vratek.",
    icon: <CreditCard size={24} />,
  },
  communication: {
    eyebrow: "V KONTAKTU",
    title: "Komunikace",
    description: "Správná zpráva správným lidem ve správný čas.",
    icon: <ArrowDownLeft size={24} />,
  },
  "check-in": {
    eyebrow: "V DEN AKCE",
    title: "Odbavení",
    description: "Přivítejte účastníky. Rychle, jednoduše a bez zmatků.",
    icon: <Ticket size={24} />,
  },
  documents: {
    eyebrow: "PRÁVNÍ DOKUMENTY",
    title: "Dokumenty a souhlasy",
    description: "Podmínky, souhlasy a jejich neměnné verze na jednom místě.",
    icon: <FileText size={24} />,
  },
  team: {
    eyebrow: "SPOLEČNĚ",
    title: "Váš tým",
    description:
      "Lidé, se kterými vaše akce vznikají. Role a přístupy pod kontrolou.",
    icon: <Users size={24} />,
  },
  settings: {
    eyebrow: "PRACOVNÍ PROSTOR",
    title: "Nastavení organizátora",
    description: "Profil organizátora, platební nastavení a propojené služby.",
    icon: <Settings2 size={24} />,
  },
};

export function ModuleScreen({
  orgSlug,
  module,
}: {
  orgSlug: string;
  module: string;
}) {
  const info = moduleInfo[module];
  const isDemo = orgSlug === "demo";
  const [query, setQuery] = useState("");
  if (!info) return null;
  return (
    <>
      {isDemo && <DemoNotice />}
      <PageHeader
        eyebrow={info.eyebrow}
        title={info.title}
        description={info.description}
      />
      {module === "registrations" && isDemo ? (
        <>
          <div className="mb-6 grid gap-3 sm:grid-cols-3">
            <StatCard
              label="Registrovaní"
              value="222"
              detail="Ilustrační přehled"
              icon={<Users size={18} />}
            />
            <StatCard
              label="Potvrzené účasti"
              value="208"
              detail="Připraveno na zážitek"
              icon={<Check size={18} />}
            />
            <StatCard
              label="Čeká na platbu"
              value="14"
              detail="Rezervovaná místa"
              icon={<CreditCard size={18} />}
            />
          </div>
          <Panel>
            <div className="border-b border-stone-100 px-5 py-4">
              <h2 className="font-semibold">Poslední objednávky</h2>
              <p className="mt-1 text-xs text-stone-500">
                Ukázka přehledu · filtrování doplníme s registračním API.
              </p>
            </div>
            <OrderRows />
          </Panel>
        </>
      ) : module === "payments" && isDemo ? (
        <>
          <div className="mb-6 grid gap-3 sm:grid-cols-3">
            <StatCard
              label="Přijato"
              value="57 200 Kč"
              detail="Ilustrační přehled"
              icon={<CreditCard size={18} />}
            />
            <StatCard
              label="Čeká na úhradu"
              value="4 200 Kč"
              detail="14 objednávek"
              icon={<CalendarDays size={18} />}
            />
            <StatCard
              label="Nespárované platby"
              value="2"
              detail="Vyžadují vaši pozornost"
              icon={<CircleAlert size={18} />}
            />
          </div>
          <Panel>
            <div className="p-6">
              <h2 className="font-semibold">
                Automatické párování přes Pairrot
              </h2>
              <p className="mt-2 max-w-xl text-sm leading-relaxed text-stone-500">
                Příchozí platby se přiřadí k objednávkám. Výjimky, ruční úhrady
                a vratky najdete ve finančním přehledu.
              </p>
              <div className="mt-4">
                <Badge tone="neutral">Ukázka propojení</Badge>
              </div>
            </div>
          </Panel>
        </>
      ) : ["documents", "team", "settings", "payments"].includes(module) ? (
        <>
          <div className="mb-5 flex justify-end">
            <label className="relative w-full sm:w-72">
              <Search
                size={15}
                className="absolute left-3 top-3 text-stone-400"
              />
              <span className="sr-only">Hledat v seznamu</span>
              <input
                className={`${inputStyle} pl-9`}
                placeholder="Hledat v seznamu…"
                value={query}
                onChange={(event) => setQuery(event.target.value)}
              />
            </label>
          </div>
          <CollectionSection orgSlug={orgSlug} module={module} query={query} />
        </>
      ) : (
        <EmptyState
          icon={info.icon}
          title={
            module === "check-in"
              ? "Připraveno pro den akce"
              : module === "communication"
                ? "Každá zpráva má své místo"
                : "Registrace na jednom místě"
          }
          description={
            module === "check-in"
              ? "Prostor pro mobilní čtečku QR kódů, vyhledávání účastníků a přehled odbavení. Aktivujeme po dokončení odbavovacího API."
              : module === "communication"
                ? "Prostor pro transakční šablony, kampaně, připomínky a historii doručení. Napojení komunikačního API připravujeme."
                : "Správa objednávek a účastí bude dostupná po dokončení registračního API."
          }
          action={
            <Link className={secondaryButton} href={`/org/${orgSlug}/events`}>
              Přejít na akce
              <ArrowRight size={15} />
            </Link>
          }
        />
      )}
    </>
  );
}

const demoCollections: Record<string, Collection> = {
  documents: {
    items: [
      {
        id: "terms",
        name: "Obchodní podmínky",
        description: "Organizátor · aktuální verze 2",
        state: "Publikováno",
      },
      {
        id: "privacy",
        name: "Ochrana osobních údajů",
        description: "Organizátor · aktuální verze 1",
        state: "Publikováno",
      },
      {
        id: "photo",
        name: "Souhlas s fotografováním",
        description: "Borský podzimní víkend · verze 1",
        state: "Koncept",
      },
    ],
  },
  team: {
    items: [
      {
        id: "owner",
        name: "Jana Novotná",
        email: "jana@example.cz",
        role: "OWNER",
      },
      {
        id: "manager",
        name: "Martin Dvořák",
        email: "martin@example.cz",
        role: "EVENT_MANAGER",
      },
      {
        id: "finance",
        name: "Eva Malá",
        email: "eva@example.cz",
        role: "FINANCE",
      },
    ],
  },
  settings: {
    items: [
      {
        id: "profile",
        name: "Borský spolek",
        description: "Ukázkový organizátor · Europe/Prague",
      },
      {
        id: "payment",
        name: "Platební metody",
        description: "Bankovní převod, hotovost, zdarma",
      },
    ],
  },
};

function displayText(value: unknown): string {
  return typeof value === "string" || typeof value === "number"
    ? String(value)
    : "";
}
function CollectionSection({
  orgSlug,
  module,
  query,
}: {
  orgSlug: string;
  module: string;
  query: string;
}) {
  const result = useRemote<Collection>(
    `${orgSlug}/${module}`,
    async () => {
      const client = api.organizer(orgSlug);
      if (module === "documents") {
        const data = await client.legal.documents();
        return {
          items: data.items.map((item) => ({
            id: item.id,
            name: item.title,
            description: `${item.level} · ${item.currentVersion ? `Verze ${item.currentVersion.number}` : "Bez publikované verze"}`,
            state: item.currentVersion?.publishedAt ? "Publikováno" : "Koncept",
          })),
        };
      }
      if (module === "team") {
        const data = await client.team.members();
        return {
          items: data.items.map((item) => ({
            id: item.membershipId,
            name: item.displayName,
            email: item.email,
            role: item.role,
            description: item.email,
          })),
        };
      }
      if (module === "payments") {
        const data = await client.payments.unmatched();
        return {
          items: data.items.map((item) => ({
            id: item.id,
            name: new Intl.NumberFormat("cs-CZ", {
              style: "currency",
              currency: item.amount.currency,
            }).format(item.amount.minor / 100),
            description: `VS: ${item.variableSymbol ?? "neuveden"} · ${dateLabel(item.bookedOn)}`,
            state: item.handlingState,
          })),
        };
      }
      const profile = await client.profile();
      return {
        items: [
          {
            id: profile.id,
            name: profile.name,
            description: [
              profile.contactEmail,
              profile.contactPhone,
              profile.legalName,
            ]
              .filter(Boolean)
              .join(" · "),
          },
        ],
      };
    },
    orgSlug === "demo" ? demoCollections[module] : undefined,
  );
  if (result.error)
    return <RequestState error={result.error} retry={result.retry} />;
  if (!result.data) return <RequestState loading retry={result.retry} />;
  const rows = result.data.items.filter((item) =>
    Object.values(item).some((value) =>
      displayText(value)
        .toLocaleLowerCase("cs")
        .includes(query.toLocaleLowerCase("cs")),
    ),
  );
  if (!rows.length)
    return (
      <EmptyState
        icon={moduleInfo[module].icon}
        title={query ? "Žádný odpovídající záznam" : "Zatím tu nic není"}
        description={
          query
            ? "Zkuste jiné hledání."
            : "Záznamy z vašeho pracovního prostoru se zobrazí na tomto místě."
        }
      />
    );
  return (
    <Panel>
      <div className="divide-y divide-stone-100">
        {rows.map((item, index) => (
          <div
            key={
              displayText(item.id ?? item.membershipId ?? item.documentId) ||
              index
            }
            className="flex flex-wrap items-center gap-4 p-5"
          >
            <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-stone-100 text-stone-500">
              {moduleInfo[module].icon}
            </span>
            <div className="min-w-0 flex-1">
              <h2 className="text-sm font-semibold">
                {displayText(
                  item.name ?? item.title ?? item.email ?? item.reference,
                ) || "Záznam"}
              </h2>
              <p className="mt-1 break-words text-xs text-stone-500">
                {displayText(
                  item.description ?? item.email ?? item.slug ?? item.currency,
                )}
              </p>
            </div>
            {item.role || item.state || item.level ? (
              <Badge>
                {displayText(item.role ?? item.state ?? item.level)}
              </Badge>
            ) : null}
          </div>
        ))}
      </div>
    </Panel>
  );
}
