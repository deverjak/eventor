import { ModuleScreen } from "@/features/screens";
export default async function Page({params}: {params: Promise<{orgSlug: string}>}) { const {orgSlug} = await params; return <ModuleScreen orgSlug={orgSlug} module="audit"/>; }
