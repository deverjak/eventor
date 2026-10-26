import { EventDetailScreen } from "@/features/screens";

export default async function Page({
  params,
}: {
  params: Promise<{ orgSlug: string; eventId: string }>;
}) {
  const { orgSlug, eventId } = await params;
  return <EventDetailScreen orgSlug={orgSlug} eventId={eventId} />;
}
