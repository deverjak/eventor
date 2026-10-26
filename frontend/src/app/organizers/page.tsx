"use client";
import { useEffect, useState } from "react";
import Link from "next/link";
import { ArrowRight, Building2, LogOut } from "lucide-react";
import { api, ApiError } from "@/api/client";
import { useRouter } from "next/navigation";

type Membership = {
  organizerId: string;
  slug: string;
  name: string;
  role: string;
};
export default function OrganizersPage() {
  const [memberships, setMemberships] = useState<Membership[] | null>(null);
  const [error, setError] = useState("");
  const router = useRouter();
  useEffect(() => {
    let active = true;
    api.auth
      .me()
      .then((p) => {
        if (active) setMemberships(p.memberships);
      })
      .catch((err) => {
        if (err instanceof ApiError && err.status === 401)
          router.replace("/login");
        if (active)
          setError(
            "Nepodařilo se načíst účet. Přihlaste se nebo zkuste stránku obnovit.",
          );
      });
    return () => {
      active = false;
    };
  }, [router]);
  async function logout() {
    try {
      await api.auth.logout();
      router.push("/login");
    } catch {
      setError("Odhlášení se nepodařilo. Zkuste to prosím znovu.");
    }
  }
  return (
    <main className="mx-auto min-h-screen max-w-3xl px-6 py-14">
      <div className="flex items-center justify-between">
        <Link href="/" className="text-2xl font-bold">
          eventor.
        </Link>
        {memberships && (
          <button
            onClick={logout}
            className="flex items-center gap-2 text-sm text-stone-500"
          >
            <LogOut size={16} />
            Odhlásit se
          </button>
        )}
      </div>
      <p className="mt-20 text-xs font-semibold tracking-widest text-stone-500">
        PRACOVNÍ PROSTORY
      </p>
      <h1 className="mt-3 text-3xl font-semibold">Kam se dnes podíváme?</h1>
      <p className="mt-3 text-stone-500">
        Vyberte organizátora a pusťte se do přípravy další akce.
      </p>
      {error && (
        <div
          role="alert"
          className="mt-8 rounded-xl bg-red-50 p-5 text-sm text-red-700"
        >
          {error}
          <Link href="/login" className="ml-2 underline">
            Přihlásit se
          </Link>
        </div>
      )}
      <div className="mt-8 space-y-3">
        {memberships === null && !error && (
          <p aria-live="polite">Načítání organizátorů…</p>
        )}
        {memberships?.map((m) => (
          <Link
            key={m.organizerId}
            href={`/org/${encodeURIComponent(m.slug)}/overview`}
            className="flex items-center gap-4 rounded-2xl border border-stone-200 bg-white p-6 transition hover:border-stone-400"
          >
            <span className="rounded-xl bg-stone-100 p-3">
              <Building2 size={22} />
            </span>
            <span className="flex-1">
              <strong className="block">{m.name}</strong>
              <span className="text-sm text-stone-500">{m.role}</span>
            </span>
            <ArrowRight size={19} />
          </Link>
        ))}
        {memberships?.length === 0 && (
          <p className="rounded-xl border border-stone-200 p-6 text-stone-500">
            Zatím nemáte pracovní prostor. Přijměte týmovou pozvánku nebo se
            zaregistrujte s novým organizátorem.
          </p>
        )}
        <Link href="/invitation" className="mt-6 block text-sm underline">
          Přijmout týmovou pozvánku
        </Link>
      </div>
    </main>
  );
}
