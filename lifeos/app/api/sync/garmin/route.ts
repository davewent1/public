import { NextRequest } from "next/server";
import { prisma } from "@/lib/prisma";
import { toDateOnly } from "@/lib/utils";

export async function POST(req: NextRequest) {
  const email = process.env.GARMIN_EMAIL;
  const password = process.env.GARMIN_PASSWORD;

  if (!email || !password) {
    return Response.json(
      { ok: false, error: "GARMIN_EMAIL or GARMIN_PASSWORD not set" },
      { status: 400 }
    );
  }

  try {
    // Dynamic import to avoid build issues with ESM-only package
    const { GarminConnect } = await import("garmin-connect");
    const gc = new GarminConnect({ username: email, password });
    await gc.login();

    const synced: string[] = [];
    const today = new Date();

    for (let i = 0; i < 7; i++) {
      const d = new Date(today);
      d.setDate(d.getDate() - i);
      const dateStr = d.toISOString().split("T")[0];
      const date = toDateOnly(d);

      try {
        const [steps, sleep, heartRate] = await Promise.allSettled([
          gc.getSteps(d),
          gc.getSleepData(d),
          gc.getHeartRate(d),
        ]);

        const data: Record<string, unknown> = {};

        if (steps.status === "fulfilled" && steps.value) {
          const s = steps.value as any;
          data.steps = s.totalSteps ?? s.steps ?? null;
          data.activeCalories = s.activeKilocalories ?? null;
          if (s.vo2MaxPreciseValue) data.vo2Max = s.vo2MaxPreciseValue;
        }

        if (sleep.status === "fulfilled" && sleep.value) {
          const s = sleep.value as any;
          const daily = s.dailySleepDTO ?? s;
          data.sleepScore = daily.sleepScores?.overall?.value ?? daily.averageSpO2 ?? null;
          data.sleepHours = daily.sleepTimeSeconds ? daily.sleepTimeSeconds / 3600 : null;
          data.sleepDeep = daily.deepSleepSeconds ? daily.deepSleepSeconds / 3600 : null;
          data.sleepRem = daily.remSleepSeconds ? daily.remSleepSeconds / 3600 : null;
          data.sleepLight = daily.lightSleepSeconds ? daily.lightSleepSeconds / 3600 : null;
        }

        if (heartRate.status === "fulfilled" && heartRate.value) {
          const h = heartRate.value as any;
          data.restingHR = h.restingHeartRate ?? null;
          data.hrv = h.hrvStatus ?? null;
        }

        if (Object.keys(data).length > 0) {
          await prisma.dailyLog.upsert({
            where: { date },
            update: data,
            create: { date, ...data },
          });
          synced.push(dateStr);
        }
      } catch {
        // Skip days that fail, don't break the loop
      }
    }

    await prisma.syncLog.create({
      data: { source: "garmin", status: "success", message: `Synced ${synced.length} days` },
    });

    return Response.json({ ok: true, synced });
  } catch (err) {
    await prisma.syncLog.create({
      data: { source: "garmin", status: "error", message: String(err) },
    });
    return Response.json({ ok: false, error: String(err) }, { status: 500 });
  }
}
