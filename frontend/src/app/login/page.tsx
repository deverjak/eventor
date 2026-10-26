"use client";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useState } from "react";
import { ArrowRight, CalendarDays, LoaderCircle } from "lucide-react";
import { api } from "@/api/client";

export default function LoginPage() {
  const router=useRouter();
  const [busy,setBusy]=useState(false);
  const [error,setError]=useState("");
  async function submit(event:React.FormEvent<HTMLFormElement>) {
    event.preventDefault();setBusy(true);setError("");
    const data=new FormData(event.currentTarget);
    try {
      await api.auth.login(String(data.get("email")),String(data.get("password")));
      router.push("/organizers");
    } catch {setError("Přihlášení se nepodařilo. Ověřte e-mail, heslo a dostupnost serveru.");}
    finally {setBusy(false);}
  }
  return <main className="grid min-h-screen bg-[#f5f6f2] lg:grid-cols-2">
    <section className="hidden flex-col justify-between bg-[#1e2923] p-14 text-white lg:flex"><Link href="/" className="flex items-center gap-3 text-2xl font-bold"><CalendarDays className="text-[#c5e493]"/>eventor<span className="text-[#c5e493]">.</span></Link><div><p className="mb-5 text-sm tracking-widest text-[#c5e493]">VÁŠ PROSTOR PRO DOBRÉ AKCE</p><h2 className="max-w-lg text-5xl leading-tight font-semibold">Méně organizování.<br/>Více společných zážitků.</h2><p className="mt-7 max-w-md text-lg leading-relaxed text-white/60">Akce, účastníci i váš tým. Všechno potřebné v jednom pracovním prostoru.</p></div><p className="text-sm text-white/45">Eventor · Pracovní prostor pořadatele</p></section>
    <section className="flex items-center justify-center px-6 py-16"><div className="w-full max-w-sm"><Link href="/" className="mb-12 block text-2xl font-bold lg:hidden">eventor.</Link><span className="text-xs font-semibold tracking-widest text-stone-500">VÍTEJTE ZPÁTKY</span><h1 className="mt-3 text-3xl font-semibold tracking-tight">Přihlaste se do aplikace</h1><p className="mt-3 text-sm leading-6 text-stone-500">Použijte svůj týmový účet a pokračujte ke svým akcím.</p><form onSubmit={submit} className="mt-9 space-y-5"><label className="block text-sm font-medium">E-mail<input required type="email" name="email" autoComplete="username" className="mt-2 w-full rounded-xl border border-stone-300 bg-white px-4 py-3 outline-offset-4" placeholder="vy@organizace.cz"/></label><label className="block text-sm font-medium">Heslo<input required type="password" name="password" autoComplete="current-password" className="mt-2 w-full rounded-xl border border-stone-300 bg-white px-4 py-3 outline-offset-4"/></label>{error&&<p role="alert" className="rounded-xl bg-red-50 p-3 text-sm text-red-700">{error}</p>}<button disabled={busy} aria-busy={busy} aria-label={busy ? "Přihlašování…" : "Přihlásit se"} className="flex min-h-12 w-full items-center justify-center gap-3 rounded-xl bg-[#233c2b] px-5 py-3 font-medium text-white disabled:opacity-60">{busy?<LoaderCircle className="animate-spin" size={18}/>:<>Přihlásit se<ArrowRight size={17}/></>}</button></form><div className="mt-8 border-t border-stone-200 pt-6 text-center"><Link href="/org/demo/overview" className="text-sm text-stone-500 underline underline-offset-4">Prohlédnout ukázkový pracovní prostor</Link></div></div></section>
  </main>;
}

