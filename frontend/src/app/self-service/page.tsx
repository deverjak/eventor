"use client";
import Link from "next/link";
import { useEffect, useState, type FormEvent } from "react";
import { request } from "@/api/client";
import {
  Box,
  DataView,
  ErrorMessage,
  Fields,
  button,
  input,
  secondary,
  type Row,
} from "@/features/workspace-ui";
import { required } from "@/features/workspace-config";

export default function SelfServicePage() {
  const [token, setToken] = useState("");
  const [order, setOrder] = useState<Row>();
  const [error, setError] = useState<unknown>();
  const [busy, setBusy] = useState(false);
  const [done, setDone] = useState(false);
  const [contact, setContact] = useState<Row>({});
  const [personAnswers, setPersonAnswers] = useState<Record<string, Row>>({});
  useEffect(() => {
    const url = new URL(window.location.href);
    const secret =
      url.searchParams.get("token") ||
      decodeURIComponent(url.hash.slice(1)) ||
      (url.pathname.startsWith("/s/")
        ? decodeURIComponent(url.pathname.slice(3))
        : "");
    if (secret) {
      window.history.replaceState(null, "", "/self-service");
      // Hydrate the incoming browser credential once, then keep it only in memory.
      // eslint-disable-next-line react-hooks/set-state-in-effect
      setToken(secret);
    }
  }, []);
  useEffect(() => {
    if (!token) return;
    const abort = new AbortController();
    request<Row>("/api/self-service/order", {
      signal: abort.signal,
      headers: { Authorization: `SelfService ${token}` },
    })
      .then(setOrder)
      .catch((err) => {
        if (!abort.signal.aborted) setError(err);
      });
    return () => abort.abort();
  }, [token]);
  async function command(path: string, method = "POST", body: Row = {}) {
    setBusy(true);
    setError(undefined);
    try {
      setOrder(
        await request<Row>(`/api/self-service/${path}`, {
          method,
          body,
          headers: { Authorization: `SelfService ${token}` },
        }),
      );
    } catch (err) {
      setError(err);
    } finally {
      setBusy(false);
    }
  }
  async function recover(e: FormEvent<HTMLFormElement>) {
    e.preventDefault();
    setBusy(true);
    setError(undefined);
    const data = new FormData(e.currentTarget);
    try {
      await request("/api/public/self-service/recover", {
        method: "POST",
        body: Object.fromEntries(data),
      });
      setDone(true);
    } catch (err) {
      setError(err);
    } finally {
      setBusy(false);
    }
  }
  const participations = (order?.participations || []) as Row[];
  return (
    <main className="mx-auto max-w-3xl space-y-6 px-5 py-12">
      <Link href="/" className="text-2xl font-bold">
        eventor.
      </Link>
      <h1 className="text-3xl font-semibold">Moje registrace</h1>
      <ErrorMessage error={error} />
      {order ? (
        <>
          <Box>
            <h2 className="mb-4 text-xl font-semibold">
              {String(order.eventName)}
            </h2>
            <DataView value={order} compact />
          </Box>
          {Boolean(order.cancellationAllowed) &&
            participations
              .filter((p) => p.canCancel)
              .map((p) => (
                <Box key={String(p.id)}>
                  <h2 className="mb-3 font-semibold">
                    {String(p.personName ?? p.activityName ?? "Účastník")}
                  </h2>
                  <form
                    onSubmit={(e) => {
                      e.preventDefault();
                      void command(
                        `participations/${encodeURIComponent(String(p.id))}/cancel`,
                        "POST",
                        {
                          reason: String(
                            new FormData(e.currentTarget).get("reason") || "",
                          ),
                        },
                      );
                    }}
                    className="space-y-3"
                  >
                    <label className="block text-sm">
                      Důvod zrušení
                      <input
                        required
                        name="reason"
                        className={`${input} mt-2`}
                      />
                    </label>
                    <button
                      disabled={busy}
                      className={`${secondary} text-red-800`}
                    >
                      Zrušit tuto účast
                    </button>
                  </form>
                </Box>
              ))}
          {participations.map((p) =>
            ((p.missingConfirmations || []) as Row[]).map((requirement) => (
              <Box key={`${p.id}-${requirement.requirementId}`}>
                <h2 className="mb-2 font-semibold">
                  {String(requirement.title)}
                </h2>
                <p className="mb-4 text-sm text-stone-500">
                  {String(p.personName)} ·{" "}
                  {requirement.mandatory
                    ? "Povinné potvrzení"
                    : "Dobrovolný souhlas"}
                </p>
                <div className="flex flex-wrap gap-3">
                  <button
                    disabled={busy}
                    className={button}
                    onClick={() =>
                      void command("confirmations", "POST", {
                        decisions: [
                          {
                            requirementId: requirement.requirementId,
                            participationId: p.id,
                            decision: "granted",
                          },
                        ],
                      })
                    }
                  >
                    Potvrdit
                  </button>
                  {!requirement.mandatory && (
                    <button
                      disabled={busy}
                      className={secondary}
                      onClick={() =>
                        void command("confirmations", "POST", {
                          decisions: [
                            {
                              requirementId: requirement.requirementId,
                              participationId: p.id,
                              decision: "declined",
                            },
                          ],
                        })
                      }
                    >
                      Nesouhlasím
                    </button>
                  )}
                </div>
              </Box>
            )),
          )}
          <Box>
            <h2 className="mb-4 font-semibold">
              Opravit kontaktní údaje objednávajícího
            </h2>
            <form
              className="space-y-4"
              onSubmit={(e) => {
                e.preventDefault();
                void command("contact", "PUT", { answers: contact });
              }}
            >
              <Fields
                fields={[
                  required("orderer_first_name", "Jméno"),
                  required("orderer_last_name", "Příjmení"),
                  required("orderer_email", "E-mail", "email"),
                  { key: "orderer_phone", label: "Telefon", type: "tel" },
                ]}
                value={contact}
                onChange={setContact}
              />
              <button disabled={busy} className={button}>
                Uložit kontakt
              </button>
            </form>
          </Box>
          {participations.map((p) => {
            const id = String(p.personId);
            return (
              <Box key={id}>
                <h2 className="mb-4 font-semibold">Opravit údaje účastníka</h2>
                <form
                  className="space-y-4"
                  onSubmit={(e) => {
                    e.preventDefault();
                    void command(`persons/${encodeURIComponent(id)}`, "PUT", {
                      answers: personAnswers[id] || {},
                    });
                  }}
                >
                  <Fields
                    fields={[
                      required("first_name", "Jméno"),
                      required("last_name", "Příjmení"),
                      required("birth_date", "Datum narození", "date"),
                    ]}
                    value={personAnswers[id] || {}}
                    onChange={(v) =>
                      setPersonAnswers({ ...personAnswers, [id]: v })
                    }
                  />
                  <button disabled={busy} className={button}>
                    Uložit osobu
                  </button>
                </form>
              </Box>
            );
          })}
        </>
      ) : token && !error ? (
        <p role="status">Načítání vaší registrace…</p>
      ) : (
        <Box>
          <h2 className="mb-3 font-semibold">
            Získejte odkaz ke své registraci
          </h2>
          <p className="mb-5 text-sm text-stone-500">
            Zadejte e-mail použitý při registraci a adresu organizátora.
          </p>
          {done ? (
            <p role="status">
              Pokud registrace existuje, pošleme vám přístupový odkaz e-mailem.
            </p>
          ) : (
            <form className="space-y-4" onSubmit={recover}>
              <label className="block text-sm">
                E-mail
                <input
                  required
                  type="email"
                  name="email"
                  className={`${input} mt-2`}
                />
              </label>
              <label className="block text-sm">
                Adresa organizátora
                <input
                  required
                  name="orgSlug"
                  className={`${input} mt-2`}
                  placeholder="vas-spolek"
                />
              </label>
              <label className="block text-sm">
                Adresa akce (volitelné)
                <input name="eventSlug" className={`${input} mt-2`} />
              </label>
              <button disabled={busy} className={button}>
                Poslat přístupový odkaz
              </button>
            </form>
          )}
        </Box>
      )}
    </main>
  );
}
