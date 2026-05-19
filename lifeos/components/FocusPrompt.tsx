import type { DailyLog } from "@/app/generated/prisma/client";

function generateFocus(log: Partial<DailyLog>): string {
  const issues: string[] = [];
  const positives: string[] = [];

  if (log.sleepScore != null) {
    if (log.sleepScore < 60) issues.push("sleep was poor");
    else if (log.sleepScore >= 80) positives.push("sleep was excellent");
  }
  if (log.stressScore != null) {
    if (log.stressScore > 70) issues.push("stress is elevated");
    else if (log.stressScore < 30) positives.push("stress is low");
  }
  if (log.hrv != null) {
    if (log.hrv < 30) issues.push("HRV is low");
  }
  if (log.steps != null) {
    if (log.steps < 5000) issues.push("steps are low");
  }

  if (issues.length === 0 && positives.length === 0) {
    return "Data is looking balanced today. Stay consistent.";
  }
  if (issues.length === 0) {
    return `${positives.join(" and ").replace(/^\w/, (c) => c.toUpperCase())}. Great day for a hard workout or deep work session.`;
  }

  const rec =
    issues.some((i) => i.includes("sleep") || i.includes("stress") || i.includes("HRV"))
      ? "Light movement and recovery today."
      : "Moderate activity recommended.";

  return `${issues.join(" and ").replace(/^\w/, (c) => c.toUpperCase())}. ${rec}`;
}

export default function FocusPrompt({ log }: { log: Partial<DailyLog> }) {
  const text = generateFocus(log);
  return (
    <div className="bg-blue-950/40 border border-blue-800/50 rounded-xl px-5 py-4">
      <p className="text-xs text-blue-400 uppercase tracking-wider mb-1">Focus</p>
      <p className="text-white text-sm leading-relaxed">{text}</p>
    </div>
  );
}
