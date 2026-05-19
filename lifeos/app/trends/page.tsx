import { prisma } from "@/lib/prisma";
import { daysAgo } from "@/lib/utils";
import TrendChartWrapper from "./TrendChartWrapper";

export const dynamic = "force-dynamic";

export default async function TrendsPage() {
  const logs = await prisma.dailyLog.findMany({
    where: { date: { gte: daysAgo(90) } },
    orderBy: { date: "asc" },
  });

  const data = logs.map((l) => ({
    date: new Date(l.date).toLocaleDateString("en-US", { month: "short", day: "numeric" }),
    sleepScore: l.sleepScore,
    sleepHours: l.sleepHours,
    hrv: l.hrv,
    restingHR: l.restingHR,
    steps: l.steps ? l.steps / 1000 : null,
    stressScore: l.stressScore,
    caloriesIn: l.caloriesIn,
    mood: l.mood,
    energy: l.energy,
    weight: l.weight,
  }));

  return (
    <div className="space-y-8">
      <h1 className="text-2xl font-bold text-white">Trends</h1>
      <TrendChartWrapper data={data} />
    </div>
  );
}
