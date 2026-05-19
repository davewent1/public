import { NextRequest } from "next/server";
import { prisma } from "@/lib/prisma";
import { startOfWeek, daysAgo } from "@/lib/utils";
import Anthropic from "@anthropic-ai/sdk";

export async function POST(_req: NextRequest) {
  const weekStart = startOfWeek(new Date());

  try {
    const logs = await prisma.dailyLog.findMany({
      where: {
        date: {
          gte: daysAgo(7),
        },
      },
      orderBy: { date: "asc" },
    });

    if (logs.length === 0) {
      return Response.json({ ok: false, error: "No data for the past 7 days" }, { status: 400 });
    }

    const weekData = logs.map((l) => ({
      date: l.date.toISOString().split("T")[0],
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
    }));

    const client = new Anthropic({ apiKey: process.env.ANTHROPIC_API_KEY });

    const message = await client.messages.create({
      model: "claude-sonnet-4-6",
      max_tokens: 500,
      messages: [
        {
          role: "user",
          content: `Here is my health and habit data for the past 7 days. Write a 150-word weekly review that:
1. Calls out my best and worst days with specific metrics
2. Identifies one pattern or correlation worth paying attention to
3. Gives one concrete, actionable suggestion for next week

Data: ${JSON.stringify(weekData)}`,
        },
      ],
    });

    const content = message.content[0].type === "text" ? message.content[0].text : "";

    const review = await prisma.weeklyReview.upsert({
      where: { weekStart },
      update: { content, rawData: JSON.stringify(weekData) },
      create: {
        weekStart,
        content,
        rawData: JSON.stringify(weekData),
      },
    });

    return Response.json({ ok: true, review });
  } catch (err) {
    return Response.json({ ok: false, error: String(err) }, { status: 500 });
  }
}

export async function GET() {
  const reviews = await prisma.weeklyReview.findMany({
    orderBy: { weekStart: "desc" },
    take: 4,
  });
  return Response.json(reviews);
}
