import type { Metadata } from "next";
import "./globals.css";

export const metadata: Metadata = {
  title: "LifeOS Dashboard",
  description: "Personal health, fitness, and productivity aggregator",
};

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="en" className="h-full">
      <body className="min-h-full bg-[#0f1117] text-slate-200 antialiased">
        <nav className="border-b border-slate-800 px-6 py-3 flex items-center gap-6">
          <span className="font-semibold text-white text-lg">LifeOS</span>
          <a href="/" className="text-sm text-slate-400 hover:text-white transition-colors">Dashboard</a>
          <a href="/trends" className="text-sm text-slate-400 hover:text-white transition-colors">Trends</a>
          <a href="/correlations" className="text-sm text-slate-400 hover:text-white transition-colors">Correlations</a>
          <a href="/goals" className="text-sm text-slate-400 hover:text-white transition-colors">Goals</a>
          <a href="/review" className="text-sm text-slate-400 hover:text-white transition-colors">Weekly Review</a>
          <a href="/sync" className="ml-auto text-sm text-slate-400 hover:text-white transition-colors">Sync</a>
        </nav>
        <main className="max-w-6xl mx-auto px-6 py-8">{children}</main>
      </body>
    </html>
  );
}
