import type { ReactNode } from "react";

export const buttonStyles = "inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-[var(--accent)] px-4 py-2.5 text-sm font-semibold text-[var(--ink)] transition hover:bg-[var(--accent-hover)] focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[var(--ink)] disabled:cursor-not-allowed disabled:opacity-50";
export const secondaryButtonStyles = "inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-[var(--border)] bg-white px-4 py-2.5 text-sm font-semibold text-[var(--ink)] transition hover:bg-[var(--surface-muted)] focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[var(--ink)] disabled:cursor-not-allowed disabled:opacity-50";
export const inputStyles = "min-h-11 w-full rounded-xl border border-[var(--border)] bg-white px-3.5 py-2.5 text-sm text-[var(--ink)] outline-none placeholder:text-[var(--muted)] focus:border-[var(--olive)] focus:ring-2 focus:ring-[var(--accent-soft)] disabled:bg-[var(--surface-muted)]";

export function PageHeader({ eyebrow, title, description, action }: { eyebrow?: string; title: string; description?: string; action?: ReactNode }) {
  return <div className="mb-7 flex flex-col justify-between gap-4 sm:mb-8 sm:flex-row sm:items-end">
    <div className="max-w-2xl">{eyebrow && <p className="mb-2 text-[11px] font-bold tracking-[0.16em] text-[var(--olive)] uppercase">{eyebrow}</p>}<h1 className="text-[28px] leading-tight font-semibold tracking-[-0.04em] text-[var(--ink)] sm:text-[34px]">{title}</h1>{description && <p className="mt-2 text-sm leading-6 text-[var(--muted)]">{description}</p>}</div>
    {action && <div className="flex shrink-0 flex-wrap items-center gap-2">{action}</div>}
  </div>;
}

const tones = {
  green: "bg-[#edf4e4] text-[#4b692f]",
  amber: "bg-[#fff3dc] text-[#956719]",
  blue: "bg-[#eaf1fc] text-[#476994]",
  neutral: "bg-[#f0f1ef] text-[#686e65]",
  red: "bg-[#fcece8] text-[#a64e3e]",
};
export function Badge({ children, tone = "neutral" }: { children: ReactNode; tone?: keyof typeof tones }) {
  return <span className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] leading-4 font-semibold whitespace-nowrap ${tones[tone]}`}><span className="size-1.5 shrink-0 rounded-full bg-current opacity-70" />{children}</span>;
}

export function Panel({ children, className = "" }: { children: ReactNode; className?: string }) {
  return <section className={`min-w-0 rounded-2xl border border-[var(--border)] bg-white ${className}`}>{children}</section>;
}

export function StatCard({ label, value, detail, icon }: { label: string; value: ReactNode; detail?: string; icon?: ReactNode }) {
  return <Panel className="p-5 sm:p-6"><div className="flex items-center justify-between gap-3"><p className="text-sm text-[var(--muted)]">{label}</p>{icon && <span className="flex size-9 items-center justify-center rounded-xl bg-[var(--surface-muted)] text-[var(--olive)] [&_svg]:size-[18px]">{icon}</span>}</div><p className="mt-4 text-[32px] leading-none font-semibold tracking-[-0.04em] text-[var(--ink)]">{value}</p>{detail && <p className="mt-3 text-xs leading-5 text-[var(--muted)]">{detail}</p>}</Panel>;
}

export function EmptyState({ icon, title, description, action }: { icon?: ReactNode; title: string; description: string; action?: ReactNode }) {
  return <div className="flex min-h-64 flex-col items-center justify-center px-6 py-12 text-center">{icon && <span className="mb-5 flex size-14 items-center justify-center rounded-2xl bg-[var(--accent-soft)] text-[var(--olive)] [&_svg]:size-6">{icon}</span>}<h2 className="text-base font-semibold tracking-tight text-[var(--ink)]">{title}</h2><p className="mt-2 max-w-sm text-sm leading-6 text-[var(--muted)]">{description}</p>{action && <div className="mt-6">{action}</div>}</div>;
}
