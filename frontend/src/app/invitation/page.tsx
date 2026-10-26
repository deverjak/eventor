"use client";
import Link from "next/link";
import { useEffect, useState, type FormEvent } from "react";
import { useRouter } from "next/navigation";
import { request } from "@/api/client";
import {
  Box,
  DataView,
  ErrorMessage,
  button,
  input,
  type Row,
} from "@/features/workspace-ui";

export default function InvitationPage() {
  const router = useRouter();
  const [preview, setPreview] = useState<Row>();
  const [error, setError] = useState<unknown>();
  const [busy, setBusy] = useState(false);
  const [token, setToken] = useState("");
  useEffect(() => {
    const url = new URL(window.location.href);
    const secret =
      url.searchParams.get("token") || decodeURIComponent(url.hash.slice(1));
    if (secret) {
      window.history.replaceState(null, "", "/invitation");
      // Hydrate the incoming browser credential once, then keep it only in memory.
      // eslint-disable-next-line react-hooks/set-state-in-effect
      setToken(secret);
    }
  }, []);
  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setBusy(true);
    setError(undefined);
    const data = new FormData(event.currentTarget);
    try {
      if (!preview)
        setPreview(
          await request<Row>("/api/auth/invitations/preview", {
            method: "POST",
            body: { token },
          }),
        );
      else {
        await request("/api/auth/invitations/accept", {
          method: "POST",
          body: {
            token,
            displayName: data.get("displayName") || null,
            password: data.get("password") || null,
          },
        });
        router.replace("/organizers");
      }
    } catch (err) {
      setError(err);
    } finally {
      setBusy(false);
    }
  }
  return (
    <main className="mx-auto max-w-xl px-6 py-14">
      <Link href="/login" className="text-2xl font-bold">
        eventor.
      </Link>
      <h1 className="my-8 text-3xl font-semibold">Připojte se ke svému týmu</h1>
      <Box>
        <form className="space-y-5" onSubmit={submit}>
          <label className="block text-sm">
            Klíč z pozvánky
            <input
              required
              className={`${input} mt-2`}
              type="password"
              value={token}
              readOnly={!!preview}
              onChange={(e) => setToken(e.target.value)}
            />
          </label>
          {preview && (
            <>
              <DataView value={preview} compact />
              <p className="text-sm text-stone-500">
                Pokud ještě nemáte účet, vyplňte jméno a nové heslo. S
                existujícím účtem se nejprve přihlaste.
              </p>
              <label className="block text-sm">
                Vaše jméno
                <input
                  name="displayName"
                  autoComplete="name"
                  className={`${input} mt-2`}
                />
              </label>
              <label className="block text-sm">
                Heslo pro nový účet
                <input
                  type="password"
                  name="password"
                  minLength={10}
                  autoComplete="new-password"
                  className={`${input} mt-2`}
                />
              </label>
            </>
          )}
          <ErrorMessage error={error} />
          <button disabled={busy} className={button}>
            {busy
              ? "Zpracováváme…"
              : preview
                ? "Přijmout pozvánku"
                : "Ověřit pozvánku"}
          </button>
        </form>
      </Box>
      <Link className="mt-5 block text-sm underline" href="/login">
        Přihlásit se
      </Link>
    </main>
  );
}
