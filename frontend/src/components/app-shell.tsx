"use client";

import { useEffect, useRef, useState, type ReactNode } from "react";
import Link from "next/link";
import { api } from "@/api/client";
import { usePathname } from "next/navigation";
import {
  ArrowUpRight,
  CalendarDays,
  ChevronDown,
  ChevronRight,
  CreditCard,
  FileText,
  LayoutDashboard,
  LifeBuoy,
  Menu,
  MessageSquare,
  ScanLine,
  Settings2,
  Users,
  UsersRound,
  X,
} from "lucide-react";

const navigation = [
  { path: "overview", label: "Přehled", icon: LayoutDashboard },
  { path: "events", label: "Události", icon: CalendarDays },
  { path: "registrations", label: "Registrace", icon: Users },
  { path: "payments", label: "Platby", icon: CreditCard },
  { path: "communication", label: "Komunikace", icon: MessageSquare },
  { path: "check-in", label: "Odbavení", icon: ScanLine },
  { path: "content", label: "Obsah po akci", icon: FileText },
  { path: "audit", label: "Historie změn", icon: FileText },
  { path: "documents", label: "Dokumenty", icon: FileText },
];
const administration = [
  { path: "team", label: "Tým a oprávnění", icon: UsersRound },
  { path: "settings", label: "Nastavení", icon: Settings2 },
];

export function AppShell({
  children,
  orgSlug,
  eventName,
}: {
  children: ReactNode;
  orgSlug: string;
  eventName?: string;
}) {
  const pathname = usePathname();
  const [openAt, setOpenAt] = useState<string | null>(null);
  const open = openAt === pathname;
  const trigger = useRef<HTMLButtonElement>(null);
  const drawer = useRef<HTMLDivElement>(null);
  const base = `/org/${encodeURIComponent(orgSlug)}`;
  const current = [...navigation, ...administration].find((item) =>
    pathname.startsWith(`${base}/${item.path}`),
  );
  const [account, setAccount] = useState<{ name: string; role: string }>();
  useEffect(() => {
    let active = true;
    api.auth
      .me()
      .then((profile) => {
        const membership = profile.memberships.find((m) => m.slug === orgSlug);
        if (active && membership) setAccount(membership);
      })
      .catch(() => {});
    return () => {
      active = false;
    };
  }, [orgSlug]);
  const organizerName = account?.name || orgSlug;
  const canNavigate = (path: string) => {
    if (["team", "settings"].includes(path)) return account?.role === "OWNER";
    if (["communication", "content"].includes(path))
      return ["OWNER", "EVENT_MANAGER"].includes(account?.role || "");
    if (["payments", "audit", "documents", "registrations"].includes(path))
      return !!account && account.role !== "CHECK_IN";
    return true;
  };

  useEffect(() => {
    if (!open) return;
    const previous = document.body.style.overflow;
    document.body.style.overflow = "hidden";
    drawer.current?.querySelector<HTMLButtonElement>("button")?.focus();
    const onKey = (event: KeyboardEvent) => {
      if (event.key === "Escape") {
        setOpenAt(null);
        trigger.current?.focus();
      }
      if (event.key !== "Tab") return;
      const elements = drawer.current?.querySelectorAll<HTMLElement>(
        "a[href], button:not([disabled])",
      );
      if (!elements?.length) return;
      const first = elements[0];
      const last = elements[elements.length - 1];
      if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
      } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
      }
    };
    const desktop = window.matchMedia("(min-width: 1024px)");
    const onResize = () => {
      if (desktop.matches) setOpenAt(null);
    };
    desktop.addEventListener("change", onResize);
    document.addEventListener("keydown", onKey);
    return () => {
      document.body.style.overflow = previous;
      document.removeEventListener("keydown", onKey);
      desktop.removeEventListener("change", onResize);
    };
  }, [open]);

  const sidebar = (mobile = false) => (
    <>
      <div className="flex h-[86px] items-center justify-between px-7">
        <Link
          href={`${base}/overview`}
          onClick={() => setOpenAt(null)}
          className="flex items-center gap-2.5 text-[25px] font-semibold tracking-[-0.055em] text-white"
          aria-label="Eventor – přehled"
        >
          <span
            className="grid size-7 grid-cols-2 gap-[3px] p-0.5"
            aria-hidden="true"
          >
            <span className="rounded-[4px] bg-[var(--accent)]" />
            <span className="rounded-[4px] bg-[var(--accent)] opacity-45" />
            <span className="rounded-[4px] bg-[var(--accent)] opacity-70" />
            <span className="rounded-[4px] bg-[var(--accent)]" />
          </span>
          eventor<span className="-ml-1 text-[var(--accent)]">.</span>
        </Link>
        {mobile && (
          <button
            onClick={() => {
              setOpenAt(null);
              trigger.current?.focus();
            }}
            aria-label="Zavřít navigaci"
            className="flex size-11 items-center justify-center rounded-xl text-white hover:bg-white/10"
          >
            <X size={20} />
          </button>
        )}
      </div>
      <div className="px-4">
        <Link
          href="/organizers"
          onClick={() => setOpenAt(null)}
          className="flex min-h-[65px] items-center gap-3 rounded-xl border border-white/10 bg-white/5 px-3.5 py-3 text-white transition hover:bg-white/10"
        >
          <span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-[#d6dfc2] text-xs font-bold text-[#36432e]">
            {organizerName.slice(0, 2).toUpperCase()}
          </span>
          <span className="min-w-0 flex-1">
            <span className="block text-[10px] leading-4 text-[#a2ada2]">
              PRACOVNÍ PROSTOR
            </span>
            <span className="block truncate text-xs font-semibold">
              {organizerName}
            </span>
          </span>
          <ChevronDown size={14} className="text-[#a2ada2]" />
        </Link>
      </div>
      <nav aria-label="Hlavní navigace" className="mt-7 flex-1 px-3.5">
        <p className="mb-2 px-3.5 text-[10px] font-semibold tracking-[0.12em] text-[#98a493] uppercase">
          Organizace událostí
        </p>
        <div className="space-y-1">
          {navigation
            .filter((item) => canNavigate(item.path))
            .map((item) => {
              const active = pathname.startsWith(`${base}/${item.path}`);
              return (
                <Link
                  key={item.path}
                  href={`${base}/${item.path}`}
                  onClick={() => setOpenAt(null)}
                  aria-current={active ? "page" : undefined}
                  className={`group flex min-h-11 items-center gap-3 rounded-xl px-3.5 text-[13px] font-medium transition ${active ? "bg-[var(--accent)] text-[#263121]" : "text-[#bdc6ba] hover:bg-white/6 hover:text-white"}`}
                >
                  <item.icon size={17} strokeWidth={1.7} />
                  <span>{item.label}</span>
                  {active && (
                    <span className="ml-auto size-1.5 rounded-full bg-[#445330]" />
                  )}
                </Link>
              );
            })}
        </div>
        <div className="mt-7 border-t border-white/8 pt-5">
          <p className="mb-2 px-3.5 text-[10px] font-semibold tracking-[0.12em] text-[#98a493] uppercase">
            Pracovní prostor
          </p>
          {administration
            .filter((item) => canNavigate(item.path))
            .map((item) => {
              const active = pathname.startsWith(`${base}/${item.path}`);
              return (
                <Link
                  key={item.path}
                  href={`${base}/${item.path}`}
                  onClick={() => setOpenAt(null)}
                  aria-current={active ? "page" : undefined}
                  className={`flex min-h-11 items-center gap-3 rounded-xl px-3.5 text-[13px] font-medium transition ${active ? "bg-white/10 text-white" : "text-[#bdc6ba] hover:bg-white/6 hover:text-white"}`}
                >
                  <item.icon size={17} strokeWidth={1.7} />
                  {item.label}
                </Link>
              );
            })}
        </div>
      </nav>
      <div className="m-4 mt-8 rounded-xl border border-white/8 bg-white/4 p-4">
        <LifeBuoy size={19} className="mb-3 text-[#bacb9c]" />
        <p className="text-xs font-semibold text-white">
          Všechno pro vaši událost.
        </p>
        <p className="mt-1.5 text-[11px] leading-5 text-[#9eaa9b]">
          Od první registrace až po posledního návštěvníka.
        </p>
        <Link
          href={`${base}/events`}
          onClick={() => setOpenAt(null)}
          className="mt-3 inline-flex min-h-8 items-center gap-2 text-[11px] font-semibold text-[var(--accent)]"
        >
          Spravovat události
          <ArrowUpRight size={13} />
        </Link>
      </div>
      <div className="px-7 pb-5 text-[10px] text-[#98a493]">
        Eventor · prostor pro vaše nápady
      </div>
    </>
  );

  return (
    <div className="min-h-dvh bg-[var(--background)] text-[var(--ink)]">
      <a
        href="#main-content"
        className="sr-only fixed top-3 left-3 z-[70] rounded-lg bg-white p-3 text-sm focus:not-sr-only"
      >
        Přejít na obsah
      </a>
      <aside className="fixed inset-y-0 left-0 z-30 hidden w-60 flex-col overflow-y-auto bg-[var(--sidebar)] lg:flex">
        {sidebar()}
      </aside>
      {open && (
        <div className="fixed inset-0 z-50 lg:hidden">
          <div
            className="absolute inset-0 bg-[#172014]/55 backdrop-blur-[2px]"
            onClick={() => {
              setOpenAt(null);
              trigger.current?.focus();
            }}
            aria-hidden="true"
          />
          <div
            ref={drawer}
            role="dialog"
            aria-modal="true"
            aria-label="Navigace aplikace"
            className="relative flex h-full w-[min(300px,85vw)] flex-col overflow-y-auto bg-[var(--sidebar)]"
          >
            {sidebar(true)}
          </div>
        </div>
      )}
      <div className="lg:pl-60">
        <header className="flex h-[72px] items-center justify-between gap-4 border-b border-[var(--border)] bg-white/70 px-4 sm:px-8 xl:px-10">
          <div className="flex min-w-0 items-center gap-3">
            <button
              ref={trigger}
              aria-label="Otevřít navigaci"
              aria-expanded={open}
              onClick={() => setOpenAt(pathname)}
              className="-ml-2 flex size-11 shrink-0 items-center justify-center rounded-xl text-[var(--ink)] hover:bg-[var(--surface-muted)] lg:hidden"
            >
              <Menu size={21} />
            </button>
            <div className="flex min-w-0 items-center gap-2 text-xs">
              <span className="hidden text-[var(--muted)] sm:inline">
                Pracovní prostor
              </span>
              <ChevronRight
                size={13}
                className="hidden text-[#a5aca0] sm:block"
              />
              <span className="truncate font-medium">
                {eventName ?? current?.label ?? "Eventor"}
              </span>
            </div>
          </div>
          <div className="flex shrink-0 items-center gap-3 sm:gap-5">
            <Link
              href="/organizers"
              className="flex size-9 items-center justify-center rounded-full border border-[var(--border)] bg-[#eef0e8] text-xs font-semibold text-[var(--olive)]"
              aria-label="Vybrat organizátora"
            >
              {organizerName.slice(0, 2).toUpperCase()}
            </Link>
          </div>
        </header>
        <main
          id="main-content"
          tabIndex={-1}
          className="mx-auto max-w-[1536px] px-4 py-7 outline-none sm:px-8 sm:py-9 xl:px-10"
        >
          {children}
        </main>
        <footer className="mx-auto flex max-w-[1536px] flex-wrap items-center justify-between gap-2 px-4 pt-5 pb-7 text-[10px] text-[var(--muted)] sm:px-8 xl:px-10">
          <span>Eventor · organizace s přehledem</span>
          <span>Vytvořeno pro lidi, kteří propojují lidi.</span>
        </footer>
      </div>
    </div>
  );
}
