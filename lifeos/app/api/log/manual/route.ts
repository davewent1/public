import { NextRequest } from "next/server";
import { prisma } from "@/lib/prisma";
import { todayUTC } from "@/lib/utils";

export async function POST(req: NextRequest) {
  try {
    const body = await req.json();
    const date = body.date ? new Date(body.date) : todayUTC();

    const data: Record<string, unknown> = {};
    if (body.mood != null) data.mood = Number(body.mood);
    if (body.energy != null) data.energy = Number(body.energy);
    if (body.fastingHours != null) data.fastingHours = Number(body.fastingHours);
    if (body.weight != null) data.weight = Number(body.weight);
    if (body.notes != null) data.notes = String(body.notes);
    if (body.caloriesIn != null) data.caloriesIn = Number(body.caloriesIn);

    const log = await prisma.dailyLog.upsert({
      where: { date },
      update: data,
      create: { date, ...data },
    });

    return Response.json({ ok: true, log });
  } catch (err) {
    return Response.json({ ok: false, error: String(err) }, { status: 500 });
  }
}
