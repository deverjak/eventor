import type { Metadata } from "next";
import "./globals.css";

export const metadata: Metadata = {
  title: { default: "Eventor · Správa akcí", template: "%s · Eventor" },
  description: "Pracovní prostor pro vaše akce, registrace a tým.",
};

export default function RootLayout({ children }: Readonly<{ children: React.ReactNode }>) {
  return <html lang="cs"><body>{children}</body></html>;
}
