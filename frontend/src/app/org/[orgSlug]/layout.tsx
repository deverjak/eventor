import { AppShell } from "@/components/app-shell";
import { SessionGate } from "@/components/session-gate";
export default async function OrganizerLayout({
  children,
  params,
}: {
  children: React.ReactNode;
  params: Promise<{ orgSlug: string }>;
}) {
  const { orgSlug } = await params;
  return (
    <SessionGate key={orgSlug} orgSlug={orgSlug}>
      <AppShell orgSlug={orgSlug}>{children}</AppShell>
    </SessionGate>
  );
}
