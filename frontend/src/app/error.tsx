"use client";
export default function ErrorPage({ reset }: {reset: () => void}) {
  return <main className="grid min-h-screen place-content-center gap-5 p-6 text-center"><h1 className="text-2xl font-semibold">Stránku se nepodařilo načíst.</h1><p>Zkuste to prosím znovu.</p><button onClick={reset} className="rounded-xl bg-stone-900 px-5 py-3 text-white">Zkusit znovu</button></main>;
}
