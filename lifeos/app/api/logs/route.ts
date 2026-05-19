import { NextRequest } from "next/server";
import { prisma } from "@/lib/prisma";
import { daysAgo } from "@/lib/utils";

export async function GET(req: NextRequest) {
  const { searchParams } = new URL(req.url);
  const days = parseInt(searchParams.get("days") ?? "30", 10);
  const logs = await prisma.dailyLog.findMany({
    where: { date: { gte: daysAgo(days) } },
    orderBy: { date: "asc" },
  });
  return Response.json(logs);
}
