import { prisma } from "@/lib/prisma";
import GoalsClient from "./GoalsClient";

export const dynamic = "force-dynamic";

export default async function GoalsPage() {
  const goals = await prisma.goal.findMany({
    where: { active: true },
    orderBy: { createdAt: "asc" },
  });

  return (
    <div className="space-y-8">
      <h1 className="text-2xl font-bold text-white">Goals</h1>
      <GoalsClient initialGoals={goals} />
    </div>
  );
}
