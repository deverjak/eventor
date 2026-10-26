import { PublicEvent } from "@/features/public-event";
export default async function Page({ params }: { params: Promise<{orgSlug: string; eventSlug: string}> }) { return <PublicEvent {...await params}/>; }
