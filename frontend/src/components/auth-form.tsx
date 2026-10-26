"use client";
import Link from "next/link";
import { useState, type FormEvent } from "react";
import { useRouter } from "next/navigation";
import { api, signUpWithGeneratedSlug } from "@/api/client";
import { ErrorMessage, input, button } from "@/features/workspace-ui";

export function AuthForm({ signUp = false }: { signUp?: boolean }) {
  const router = useRouter();
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<unknown>();
  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setBusy(true);
    setError(undefined);
    const data = new FormData(event.currentTarget);
    const get = (key: string) => String(data.get(key) || "");
    try {
      const account = signUp
        ? await signUpWithGeneratedSlug({
            email: get("email"),
            password: get("password"),
            displayName: get("displayName"),
            organizerName: get("organizerName"),
          })
        : await api.auth.login(get("email"), get("password"));
      router.replace(
        account.memberships.length === 1
          ? `/org/${encodeURIComponent(account.memberships[0].slug)}/overview`
          : "/organizers",
      );
      router.refresh();
    } catch (err) {
      setError(err);
    } finally {
      setBusy(false);
    }
  }
  return (
    <main className="grid min-h-dvh bg-[#f5f6f2] lg:grid-cols-2">
      <section className="hidden flex-col justify-between bg-[#1e2923] p-14 text-white lg:flex">
        <Link href="/" className="text-3xl font-semibold">
          eventor<span className="text-[#c5e493]">.</span>
        </Link>
        <div>
          <p className="mb-5 text-xs tracking-widest text-[#c5e493]">
            PROSTOR PRO VAŠE AKCE
          </p>
          <h2 className="max-w-lg text-5xl leading-tight font-semibold">
            Méně organizování.
            <br />
            Více společných zážitků.
          </h2>
          <p className="mt-7 max-w-md text-lg leading-relaxed text-white/60">
            Akce, účastníci, platby i váš tým. Všechno potřebné na jednom místě.
          </p>
        </div>
        <p className="text-sm text-white/45">
          Eventor · Pracovní prostor pořadatele
        </p>
      </section>
      <section className="flex items-center justify-center px-6 py-12">
        <div className="w-full max-w-md">
          <Link href="/" className="mb-10 block text-2xl font-bold lg:hidden">
            eventor.
          </Link>
          <p className="text-xs font-semibold tracking-widest text-stone-500">
            {signUp ? "ZAČNĚTE S VLASTNÍ AKCÍ" : "VÍTEJTE ZPÁTKY"}
          </p>
          <h1 className="mt-3 text-3xl font-semibold">
            {signUp ? "Vytvořte si účet" : "Přihlaste se do aplikace"}
          </h1>
          <p className="mt-3 text-sm leading-6 text-stone-500">
            {signUp
              ? "Založíme vám účet a vlastní prostor organizátora."
              : "Pokračujte ke svým akcím a svému týmu."}
          </p>
          <form onSubmit={submit} className="mt-8 space-y-5">
            <fieldset disabled={busy} className="space-y-5">
              {signUp && (
                <label className="block text-sm font-medium">
                  Vaše jméno
                  <input
                    required
                    name="displayName"
                    autoComplete="name"
                    maxLength={200}
                    className={`${input} mt-2`}
                  />
                </label>
              )}
              <label className="block text-sm font-medium">
                E-mail
                <input
                  required
                  type="email"
                  name="email"
                  autoComplete="username"
                  maxLength={255}
                  className={`${input} mt-2`}
                />
              </label>
              <label className="block text-sm font-medium">
                Heslo
                <input
                  required
                  type="password"
                  name="password"
                  autoComplete={signUp ? "new-password" : "current-password"}
                  minLength={signUp ? 10 : undefined}
                  maxLength={200}
                  className={`${input} mt-2`}
                />
                {signUp && (
                  <span className="mt-1 block text-xs text-stone-500">
                    Alespoň 10 znaků.
                  </span>
                )}
              </label>
              {signUp && (
                <>
                  <label className="block text-sm font-medium">
                    Název organizátora
                    <input
                      required
                      name="organizerName"
                      autoComplete="organization"
                      maxLength={200}
                      className={`${input} mt-2`}
                    />
                  </label>
                  <p className="text-xs text-stone-500">Adresu prostoru vytvoříme automaticky podle názvu.</p>
                </>
              )}
            </fieldset>
            <ErrorMessage error={error} />
            <button
              disabled={busy}
              aria-busy={busy}
              className={`${button} w-full`}
            >
              {busy
                ? "Chvilku strpení…"
                : signUp
                  ? "Vytvořit účet a organizátora"
                  : "Přihlásit se"}
            </button>
          </form>
          <p className="mt-7 text-center text-sm text-stone-500">
            {signUp ? "Už máte účet?" : "Ještě nemáte účet?"}{" "}
            <Link
              className="font-semibold text-lime-900 underline"
              href={signUp ? "/login" : "/register"}
            >
              {signUp ? "Přihlásit se" : "Zaregistrovat se"}
            </Link>
          </p>
          <p className="mt-4 text-center text-sm">
            <Link href="/invitation" className="text-stone-500 underline">
              Přijmout týmovou pozvánku
            </Link>
          </p>
        </div>
      </section>
    </main>
  );
}
