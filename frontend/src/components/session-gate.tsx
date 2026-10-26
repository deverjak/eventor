"use client";

import { useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import Link from "next/link";
import { api, ApiError } from "@/api/client";

export function SessionGate({orgSlug, children}: {orgSlug:string;children:React.ReactNode}) {
  const router = useRouter();
  const [state,setState] = useState<"loading"|"ready"|"forbidden"|"error">("loading");
  const [attempt,setAttempt] = useState(0);
  useEffect(() => {
    if(orgSlug === "demo") return;
    let active = true;
    api.auth.me().then(profile => {
      if(active) setState(profile.memberships.some(m=>m.slug===orgSlug) ? "ready" : "forbidden");
    }).catch(error=> {
      if(!active) return;
      if(error instanceof ApiError && error.status===401) router.replace("/login");
      else setState("error");
    });
    return ()=>{active=false;};
  },[orgSlug,router,attempt]);
  if(orgSlug === "demo" || state === "ready") return children;
  return <main className="grid min-h-screen place-content-center gap-5 p-6 text-center" aria-live="polite">
    <div className="text-sm font-semibold tracking-widest text-stone-500">EVENTOR</div>
    <h1 className="text-2xl font-semibold">{state === "loading" ? "Načítáme váš pracovní prostor…" : state === "forbidden" ? "K tomuto organizátorovi nemáte přístup." : "Spojení se nepodařilo navázat."}</h1>
    {state !== "loading" && <><p className="text-stone-500">{state === "error" ? "Zkontrolujte připojení a dostupnost backendu." : "Vyberte si pracovní prostor, jehož jste členem."}</p><div className="flex justify-center gap-4">{state === "error" && <button className="underline" onClick={()=>{setState("loading");setAttempt(v=>v+1);}}>Zkusit znovu</button>}<Link href="/organizers" className="underline">Moji organizátoři</Link></div></>}
  </main>;
}
