import { Suspense } from "react";
import { OverviewScreen } from "@/features/screens";

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
      <OverviewScreen orgSlug={orgSlug} />
    </Suspense>
  );
}
