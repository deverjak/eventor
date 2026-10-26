import { Suspense } from "react";
import { ModuleScreen } from "@/features/screens";

export default async function Page({
  params,
}: {
  params: Promise<{ orgSlug: string }>;
}) {
  const { orgSlug } = await params;
  return (
    <Suspense
      fallback={
        <div role="status" className="p-8 text-sm text-stone-500">
          Načítání…
        </div>
      }
    >
      <ModuleScreen orgSlug={orgSlug} module="check-in" />
    </Suspense>
  );
}
