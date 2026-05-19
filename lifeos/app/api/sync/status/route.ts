import { prisma } from "@/lib/prisma";

export async function GET() {
  const logs = await prisma.syncLog.findMany({
    orderBy: { syncedAt: "desc" },
    take: 10,
  });
  return Response.json(logs);
}
