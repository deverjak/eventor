import Link from "next/link";
export default function NotFound() {
  return <main className="grid min-h-screen place-content-center gap-5 p-6 text-center"><p className="text-sm text-stone-500">404 · Eventor</p><h1 className="text-3xl font-semibold">Tuhle stránku jsme nenašli.</h1><Link className="underline" href="/">Zpět do aplikace</Link></main>;
}
