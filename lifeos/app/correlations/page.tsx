import { prisma } from "@/lib/prisma";
import { daysAgo } from "@/lib/utils";
import CorrelationExplorer from "./CorrelationExplorer";

export const dynamic = "force-dynamic";

export default async function CorrelationsPage() {
  const logs = await prisma.dailyLog.findMany({
    where: { date: { gte: daysAgo(90) } },
    orderBy: { date: "asc" },
  });

  const data = logs.map((l) => ({
    date: new Date(l.date).toISOString().split("T")[0],
    sleepScore: l.sleepScore,
    sleepHours: l.sleepHours,
    hrv: l.hrv,
    restingHR: l.restingHR,
    steps: l.steps,
    stressScore: l.stressScore,
    caloriesIn: l.caloriesIn,
    proteinG: l.proteinG,
    mood: l.mood,
    energy: l.energy,
    weight: l.weight,
    fastingHours: l.fastingHours,
  }));

  return (
    <div className="space-y-8">
      <h1 className="text-2xl font-bold text-white">Correlation Explorer</h1>
      <CorrelationExplorer data={data} />
    </div>
  );
}
